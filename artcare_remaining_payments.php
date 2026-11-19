<?php
// ART CARE appointment payments extension. Included only through authenticated index.php.
if (!defined('ARTCARE_PAYMENT_MODULE')) { http_response_code(403); exit('Forbidden'); }

function ar_money($value): int { return (int) round(((float)$value) * 100); }
function ar_fmt(int $cents): string { return number_format($cents / 100, 2); }
function ar_price(array $a): int {
    if ($a['service_price'] !== null) return ar_money($a['service_price']);
    if ($a['snapshot_price'] !== null) return ar_money($a['snapshot_price']);
    if ($a['catalog_price'] !== null) return ar_money($a['catalog_price']);
    return 0;
}
function ar_appointment(int $id, bool $lock = false): ?array {
    return one("SELECT a.id,a.patient_id,a.appointment_date,a.service,a.status,p.full_name AS patient_name,
       d.id AS deposit_id,d.amount AS deposit_amount,d.method AS deposit_method,
       d.reference_number AS deposit_reference,d.verification_status AS deposit_status,
       d.verified_at AS deposit_verified_at,d.service_price,
       ap.service_price AS snapshot_price,s.price AS catalog_price
       FROM appointments a JOIN patients p ON p.id=a.patient_id
       LEFT JOIN artcare_appointment_deposits d ON d.appointment_id=a.id
       LEFT JOIN artcare_appointment_prices ap ON ap.appointment_id=a.id
       LEFT JOIN artcare_services s ON s.name=a.service
       WHERE a.id=?" . ($lock ? ' FOR UPDATE' : ''), 'i', [$id]);
}
function ar_payments(int $id): array {
    return rows("SELECT id,appointment_id,amount,method,reference_number,payment_status,payment_type,
       recorded_by,verified_by,paid_at,verified_at
       FROM artcare_appointment_payments WHERE appointment_id=? ORDER BY paid_at,id", 'i', [$id]);
}
function ar_totals(array $a, array $payments): array {
    $price = ar_price($a);
    $deposit = ($a['deposit_status'] ?? '') === 'Verified' ? ar_money($a['deposit_amount']) : 0;
    $additional = 0;
    foreach ($payments as $p) if ($p['payment_status'] === 'Verified') $additional += ar_money($p['amount']);
    $paid = $deposit + $additional;
    $balance = max(0, $price - $paid);
    $status = $price <= 0 ? 'Price missing' : ($paid >= $price ? 'Fully Paid' : ($paid > 0 ? 'Partially Paid' : 'Unpaid'));
    return compact('price','deposit','additional','paid','balance','status');
}
function ar_handle_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['remaining_payment_submit','remaining_payment_verify'], true)) return;
    verify_post();
    global $db;
    try {
        if ($action === 'remaining_payment_submit') {
            $id = (int)($_POST['appointment_id'] ?? 0);
            $method = $_POST['method'] ?? '';
            $reference = trim($_POST['reference_number'] ?? '');
            $rawAmount = trim((string)($_POST['amount'] ?? ''));
            if ($id < 1 || !in_array($method, ['Cash','GCash','Maya'], true)) throw new Exception('Invalid appointment or payment method.');
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $rawAmount) || ar_money($rawAmount) <= 0)
                throw new Exception('Enter a positive amount with at most two decimals.');
            if (strlen($reference) > 120 || ($method !== 'Cash' && $reference === ''))
                throw new Exception('GCash and PayMaya require a transaction reference.');
            $db->begin_transaction();
            $a = ar_appointment($id, true);
            if (!$a) throw new Exception('Appointment not found.');
            if (in_array($a['status'], ['Cancelled','No-Show'], true)) throw new Exception('Cannot accept payment for cancelled or no-show appointments.');
            if (($a['deposit_status'] ?? '') !== 'Verified') throw new Exception('Verify the downpayment before recording remaining payments.');
            $payments = ar_payments($id);
            $t = ar_totals($a, $payments);
            $amount = ar_money($rawAmount);
            if ($t['price'] <= 0 || $amount > $t['balance']) throw new Exception('Amount exceeds remaining balance or service price is missing.');
            // Prevent multiple pending claims that together exceed the outstanding balance.
            $pending = 0;
            foreach ($payments as $p) if ($p['payment_status'] === 'Pending') $pending += ar_money($p['amount']);
            if ($amount + $pending > $t['balance']) throw new Exception('Amount exceeds balance after pending submissions.');
            stmt("INSERT INTO artcare_appointment_payments
                (appointment_id,amount,method,reference_number,payment_status,payment_type,recorded_by)
                VALUES (?,?,?,?,'Pending','Remaining',?)", 'idssi',
                [$id,$amount/100,$method,$reference !== '' ? $reference : null,(int)$_SESSION['user']['id']]);
            $db->commit();
            create_notification('Remaining payment submitted','Appointment #'.$id.' has a payment awaiting admin verification.','info');
            notice('Payment submitted for verification. It is not counted as paid yet.');
        } elseif ($action === 'remaining_payment_verify') {
            if (!admin()) throw new Exception('Administrator only.');
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            if ($paymentId < 1 || !in_array($decision, ['Verified','Rejected'], true)) throw new Exception('Invalid payment decision.');
            $db->begin_transaction();
            // Find appointment ID, then lock the appointment to serialize changes.
            $lookup = one('SELECT appointment_id FROM artcare_appointment_payments WHERE id=?', 'i', [$paymentId]);
            if (!$lookup) throw new Exception('Payment not found.');
            $a = ar_appointment((int)$lookup['appointment_id'], true);
            if (!$a) throw new Exception('Appointment not found.');
            $payment = one('SELECT * FROM artcare_appointment_payments WHERE id=? FOR UPDATE', 'i', [$paymentId]);
            if (!$payment || $payment['payment_status'] !== 'Pending') throw new Exception('Payment already reviewed.');
            if ($decision === 'Verified') {
                if (($a['deposit_status'] ?? '') !== 'Verified') throw new Exception('Downpayment must be verified first.');
                $t = ar_totals($a, ar_payments((int)$a['id']));
                if (ar_money($payment['amount']) > $t['balance']) throw new Exception('Payment exceeds the outstanding balance.');
            }
            $updated = stmt("UPDATE artcare_appointment_payments
                SET payment_status=?,verified_by=?,verified_at=NOW()
                WHERE id=? AND payment_status='Pending'", 'sii',
                [$decision,(int)$_SESSION['user']['id'],$paymentId]);
            if ($updated->affected_rows !== 1) throw new Exception('Payment already reviewed.');
            $db->commit();
            create_notification('Remaining payment '.$decision,'Appointment #'.$a['id'].' payment marked '.$decision.'.','info');
            notice('Payment marked '.$decision.'.');
        }
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        $_SESSION['form_error'] = $e instanceof mysqli_sql_exception
            ? 'Payment database error. Check the payment table and constraints.' : $e->getMessage();
    }
    go('?page=payment_management');
}
function ar_render_payment_management(): void {
    $appointments = rows("SELECT a.id FROM appointments a ORDER BY a.appointment_date DESC,a.id DESC");
    ?>
    <h2>Payment Management</h2>
    <p>GCash, PayMaya and Cash for remaining payments. Submitted payments are counted only after administrator verification.</p>
    <div class="card"><h3>Appointment balances and payment history</h3><div class="table-wrap"><table>
      <thead><tr><th>Patient / Appointment</th><th>Service</th><th>Billing</th><th>Payment status</th><th>Payment actions and history</th></tr></thead><tbody>
      <?php foreach ($appointments as $item):
        $a = ar_appointment((int)$item['id']);
        if (!$a) continue;
        $payments = ar_payments((int)$a['id']);
        $t = ar_totals($a, $payments);
      ?>
      <tr>
        <td><?= esc($a['patient_name']) ?><br><small>#<?= (int)$a['id'] ?> · <?= esc($a['appointment_date']) ?> · <?= esc($a['status']) ?></small></td>
        <td><?= esc($a['service']) ?></td>
        <td>Total: ₱<?= ar_fmt($t['price']) ?><br>Verified downpayment: ₱<?= ar_fmt($t['deposit']) ?><br>Verified additional: ₱<?= ar_fmt($t['additional']) ?><br><strong>Balance: ₱<?= ar_fmt($t['balance']) ?></strong></td>
        <td><strong><?= esc($t['status']) ?></strong><br><small>Downpayment: <?= esc($a['deposit_status'] ?? 'Not submitted') ?></small></td>
        <td>
        <?php if (!$a['deposit_id'] || $a['deposit_status'] === 'Rejected'): ?>
          <?php if (in_array($a['status'], ['Pending','Rescheduled'], true)): ?>
          <form method="post" class="payment-form">
            <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>">
            <input type="hidden" name="action" value="deposit_submit">
            <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
            <select name="method" required><option value="GCash">GCash</option><option value="Maya">PayMaya</option></select>
            <input name="reference_number" placeholder="Downpayment transaction reference" maxlength="120" required>
            <button type="submit">Submit 50% downpayment</button>
          </form>
          <?php endif; ?>
        <?php elseif ($a['deposit_status'] === 'Pending' && admin()): ?>
          <form method="post" class="inline" onsubmit="return confirm('Confirm actual funds received before verifying?')">
            <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="deposit_verify">
            <input type="hidden" name="id" value="<?= (int)$a['deposit_id'] ?>"><input type="hidden" name="decision" value="Verified">
            <button type="submit">Verify downpayment</button>
          </form>
          <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="deposit_verify"><input type="hidden" name="id" value="<?= (int)$a['deposit_id'] ?>"><input type="hidden" name="decision" value="Rejected"><button type="submit" class="muted">Reject</button></form>
        <?php endif; ?>
        <?php if ($a['deposit_status'] === 'Verified' && $t['balance'] > 0 && !in_array($a['status'], ['Cancelled','No-Show'], true)): ?>
          <details style="margin:8px 0"><summary style="cursor:pointer">Record remaining payment</summary>
            <form method="post" class="payment-form" style="padding:8px 0">
              <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="remaining_payment_submit">
              <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
              <label>Amount (PHP)<input type="number" name="amount" min="0.01" max="<?= esc(number_format($t['balance']/100,2,'.','')) ?>" step="0.01" required></label>
              <label>Method<select name="method" required><option value="GCash">GCash</option><option value="Maya">PayMaya</option><option value="Cash">Cash</option></select></label>
              <label>Reference (required for wallets)<input name="reference_number" maxlength="120" placeholder="Reference / optional for Cash"></label>
              <button type="submit">Submit for verification</button>
            </form>
          </details>
        <?php endif; ?>
        <details><summary style="cursor:pointer">Payment history (<?= count($payments) + ($a['deposit_id'] ? 1 : 0) ?>)</summary>
          <?php if ($a['deposit_id']): ?>
            <p><small>Downpayment · ₱<?= ar_fmt(ar_money($a['deposit_amount'])) ?> · <?= esc($a['deposit_method']) ?> · <?= esc($a['deposit_reference']) ?> · <?= esc($a['deposit_status']) ?></small></p>
          <?php endif; ?>
          <?php foreach ($payments as $p): ?>
            <p><small>#<?= (int)$p['id'] ?> · ₱<?= ar_fmt(ar_money($p['amount'])) ?> · <?= esc($p['method'] === 'Maya' ? 'PayMaya' : $p['method']) ?> · <?= esc($p['reference_number'] ?? '—') ?> · <?= esc($p['payment_status']) ?> · <?= esc($p['paid_at']) ?></small>
            <?php if ($p['payment_status'] === 'Verified'): ?>
              <a href="?page=payment_management&amp;payment_receipt=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Print acknowledgment</a>
            <?php elseif ($p['payment_status'] === 'Pending' && admin()): ?>
              <form method="post" class="inline" onsubmit="return confirm('Have you confirmed receipt of funds?')">
                <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="remaining_payment_verify"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decision" value="Verified"><button type="submit">Verify</button>
              </form>
              <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="remaining_payment_verify"><input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="decision" value="Rejected"><button type="submit" class="muted">Reject</button></form>
            <?php endif; ?></p>
          <?php endforeach; ?>
        </details>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div></div>
    <?php
}
function ar_handle_receipt(): void {
    if (($_GET['page'] ?? '') !== 'payment_management' || !isset($_GET['payment_receipt'])) return;
    $id = filter_var($_GET['payment_receipt'], FILTER_VALIDATE_INT);
    if (!$id || $id < 1) { http_response_code(400); exit('Invalid receipt ID'); }
    $p = one("SELECT p.*,a.service,a.appointment_date,pt.full_name AS patient_name
        FROM artcare_appointment_payments p
        JOIN appointments a ON a.id=p.appointment_id
        JOIN patients pt ON pt.id=a.patient_id
        WHERE p.id=? AND p.payment_status='Verified'", 'i', [$id]);
    if (!$p) { http_response_code(404); exit('Verified payment not found'); }
    $a = ar_appointment((int)$p['appointment_id']);
    $t = ar_totals($a, ar_payments((int)$p['appointment_id']));
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: private, no-store');
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><title>Payment Acknowledgment #<?= (int)$id ?></title>
    <style>body{font-family:Arial,sans-serif;max-width:700px;margin:45px auto;padding:20px;color:#222}h1{color:#a47500}table{width:100%;border-collapse:collapse}td{padding:10px;border-bottom:1px solid #ddd}td:last-child{text-align:right}small{color:#555}.actions{margin-top:30px}@media print{.actions{display:none}}</style></head><body>
    <h1>ART CARE Dental Clinic</h1><h2>Payment Acknowledgment</h2>
    <p><small>This document acknowledges a payment recorded and verified by the clinic. It is not a BIR-registered invoice or official tax receipt.</small></p>
    <table>
      <tr><td>Payment record</td><td>#<?= (int)$p['id'] ?></td></tr>
      <tr><td>Appointment</td><td>#<?= (int)$p['appointment_id'] ?></td></tr>
      <tr><td>Patient</td><td><?= esc($p['patient_name']) ?></td></tr>
      <tr><td>Service</td><td><?= esc($p['service']) ?></td></tr>
      <tr><td>Appointment date</td><td><?= esc($p['appointment_date']) ?></td></tr>
      <tr><td>Payment method</td><td><?= esc($p['method'] === 'Maya' ? 'PayMaya' : $p['method']) ?></td></tr>
      <tr><td>Transaction reference</td><td><?= esc($p['reference_number'] ?: 'N/A (Cash)') ?></td></tr>
      <tr><td>Recorded on</td><td><?= esc($p['paid_at']) ?></td></tr>
      <tr><td>Verified on</td><td><?= esc($p['verified_at']) ?></td></tr>
      <tr><td><strong>This payment</strong></td><td><strong>₱<?= ar_fmt(ar_money($p['amount'])) ?></strong></td></tr>
      <tr><td>Total service fee</td><td>₱<?= ar_fmt($t['price']) ?></td></tr>
      <tr><td>Total verified payments to date</td><td>₱<?= ar_fmt($t['paid']) ?></td></tr>
      <tr><td>Current remaining balance</td><td>₱<?= ar_fmt($t['balance']) ?></td></tr>
      <tr><td>Current status</td><td><?= esc($t['status']) ?></td></tr>
    </table>
    <p class="actions"><button onclick="window.print()">Print / Save as PDF</button></p>
    </body></html>
    <?php exit;
}
