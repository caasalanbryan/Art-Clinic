<?php
// Included by index.php after config.php, require_login(), and page validation.
if (!defined('ARTCARE_PAYMENT_MODULE')) { http_response_code(403); exit('Forbidden'); }

function payment_services($activeOnly = false) {
    return rows('SELECT * FROM artcare_services ' . ($activeOnly ? 'WHERE is_active=1 ' : '') . 'ORDER BY name');
}
function payment_deposit_for($price) {
    // Work in integer centavos to avoid floating-point rounding errors.
    $cents = (int)round(((float)$price) * 100);
    return number_format((int)round($cents / 2) / 100, 2, '.', '');
}
function payment_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['service_save','service_toggle','deposit_submit','deposit_verify' , 'remaining_payment_submit', 'remaining_payment_verify'], true)) return;
    verify_post();
    $adminOnly = in_array($action, ['service_save','service_toggle','deposit_verify' , 'remaining_payment_verify'], true);
    if ($adminOnly && !admin()) throw new Exception('Administrator only.');

    if ($action === 'service_save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = trim($_POST['price'] ?? '');
        if ($name === '' || mb_strlen($name) > 150 || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price) || (float)$price <= 0)
            throw new Exception('Enter a valid service name and positive price (up to two decimals).');
        if ($id) {
            $s = stmt('UPDATE artcare_services SET name=?, price=? WHERE id=?', 'sdi', [$name,(float)$price,$id]);
            if ($s->affected_rows < 0) throw new Exception('Could not update service.');
        } else {
            stmt('INSERT INTO artcare_services(name,price) VALUES (?,?)', 'sd', [$name,(float)$price]);
        }
        notice('Dental service saved.'); go('?page=service_management');
    }
    if ($action === 'service_toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $service = one('SELECT id,is_active FROM artcare_services WHERE id=?', 'i', [$id]);
        if (!$service) throw new Exception('Service not found.');
        $active = $service['is_active'] ? 0 : 1;
        stmt('UPDATE artcare_services SET is_active=? WHERE id=?','ii',[$active,$id]);
        notice($active ? 'Service restored.' : 'Service removed from new bookings; history preserved.');
        go('?page=service_management');
    }
    if ($action === 'deposit_submit') {
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        $method = $_POST['method'] ?? '';
        $ref = trim($_POST['reference_number'] ?? '');
        if (!in_array($method, ['GCash','Maya'], true) || $ref === '' || strlen($ref) > 120)
            throw new Exception('Select a payment method and enter a valid transaction reference.');
        $appointment = one('SELECT id,service,status FROM appointments WHERE id=?','i',[$appointmentId]);
        if (!$appointment || !in_array($appointment['status'], ['Pending','Rescheduled'], true))
            throw new Exception('Only pending or rescheduled appointments accept new downpayment submissions.');
        $service = one('SELECT service_id AS id, service_name AS name, service_price AS price FROM artcare_appointment_prices WHERE appointment_id=?','i',[$appointmentId]);
        if (!$service) $service = one('SELECT id,name,price FROM artcare_services WHERE name=?','s',[$appointment['service']]);
        if (!$service) throw new Exception('Service pricing is missing. Ask admin to add this service.');
        $existing = one('SELECT verification_status FROM artcare_appointment_deposits WHERE appointment_id=?','i',[$appointmentId]);
        if ($existing && $existing['verification_status'] !== 'Rejected')
            throw new Exception('This appointment already has a submitted or verified downpayment.');
        $deposit = payment_deposit_for($service['price']);
        // This form records a payment CLAIM; admin must verify actual receipt.
        if ($existing) {
            stmt("UPDATE artcare_appointment_deposits SET service_id=?,service_name=?,service_price=?,required_deposit=?,amount=?,method=?,reference_number=?,verification_status='Pending',submitted_by=?,verified_by=NULL,verified_at=NULL,submitted_at=NOW() WHERE appointment_id=?",
                'isdddssii',[(int)$service['id'],$service['name'],(float)$service['price'],(float)$deposit,(float)$deposit,$method,$ref,(int)$_SESSION['user']['id'],$appointmentId]);
        } else {
            stmt('INSERT INTO artcare_appointment_deposits(appointment_id,service_id,service_name,service_price,required_deposit,amount,method,reference_number,submitted_by) VALUES(?,?,?,?,?,?,?,?,?)',
                'iisdddssi',[$appointmentId,(int)$service['id'],$service['name'],(float)$service['price'],(float)$deposit,(float)$deposit,$method,$ref,(int)$_SESSION['user']['id']]);
        }
        create_notification('Downpayment submitted','Appointment #'.$appointmentId.' payment requires admin verification.','info');
        notice('Downpayment submitted for verification. Do not confirm until funds are received.');
        go('?page=payment_management');
    }
    if ($action === 'deposit_verify') {
        $id = (int)($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision,['Verified','Rejected'],true)) throw new Exception('Invalid decision.');
        $deposit = one('SELECT id,appointment_id,verification_status FROM artcare_appointment_deposits WHERE id=?','i',[$id]);
        if (!$deposit || $deposit['verification_status'] !== 'Pending') throw new Exception('Payment is no longer pending.');
        stmt('UPDATE artcare_appointment_deposits SET verification_status=?,verified_by=?,verified_at=NOW() WHERE id=? AND verification_status=\'Pending\'',
            'sii',[$decision,(int)$_SESSION['user']['id'],$id]);
        create_notification('Downpayment '.$decision,'Appointment #'.$deposit['appointment_id'].' payment marked '.$decision.'.','info');
        notice('Payment marked '.$decision.'.'); go('?page=payment_management');
    }
}

function payment_render_services() {
    if (!admin()) { http_response_code(403); echo '<p>Administrator only.</p>'; return; }
    $services = payment_services();
    ?>
    <h2>Dental Service Management</h2><p>Sample prices only. Admin can edit anytime. Deactivate instead of deleting used services.</p>
    <div class="card"><h3>Add service</h3>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="service_save">
      <label>Service name<input name="name" maxlength="150" required></label>
      <label>Price (PHP)<input type="number" name="price" min="0.01" max="9999999999.99" step="0.01" required></label>
      <button type="submit">Add Service</button>
    </form></div>
    <div class="card"><h3>Service list</h3><div class="table-wrap"><table>
      <tr><th>Service</th><th>Price</th><th>50% Deposit</th><th>Status</th><th>Admin actions</th></tr>
      <?php foreach ($services as $s): ?>
      <tr><td><?= esc($s['name']) ?></td><td>₱<?= number_format((float)$s['price'],2) ?></td>
      <td>₱<?= esc(payment_deposit_for($s['price'])) ?></td><td><?= $s['is_active'] ? 'Active' : 'Inactive' ?></td><td>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="service_save"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <input name="name" value="<?= esc($s['name']) ?>" maxlength="150" required aria-label="Service name">
        <input type="number" name="price" value="<?= esc($s['price']) ?>" min="0.01" step="0.01" required aria-label="Service price">
        <button type="submit">Save</button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm('Change availability of this service?')">
        <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="service_toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button type="submit" class="muted"><?= $s['is_active'] ? 'Deactivate' : 'Restore' ?></button>
      </form></td></tr>
      <?php endforeach; ?></table></div></div>
    <?php
}
function payment_render_payments() {
    $appointments = rows("SELECT a.id,a.appointment_date,a.service,a.status,p.full_name AS patient_name,d.id AS deposit_id,d.service_price,d.required_deposit,d.amount,d.method,d.reference_number,d.verification_status FROM appointments a JOIN patients p ON p.id=a.patient_id LEFT JOIN artcare_appointment_deposits d ON d.appointment_id=a.id ORDER BY a.appointment_date DESC,a.id DESC");
    ?>
    <h2>Payment Management</h2><p>GCash and PayMaya only. Record a payment reference, then admin verifies funds received.</p>
    <div class="card"><h3>Appointment downpayments</h3><div class="table-wrap"><table>
    <tr><th>Patient / Appointment</th><th>Service</th><th>Downpayment</th><th>Payment details</th><th>Verification / Actions</th></tr>
    <?php foreach ($appointments as $a): ?>
      <tr><td><?= esc($a['patient_name']) ?><br><small>#<?= (int)$a['id'] ?> · <?= esc($a['appointment_date']) ?> · <?= esc($a['status']) ?></small></td>
      <td><?= esc($a['service']) ?></td>
      <td><?php if ($a['deposit_id']): ?>Total: ₱<?= number_format((float)$a['service_price'],2) ?><br>Deposit: ₱<?= number_format((float)$a['required_deposit'],2) ?><br>Remaining: ₱<?= number_format(max(0,(float)$a['service_price']-(float)$a['amount']),2) ?><?php else: ?>Not submitted<?php endif; ?></td>
      <td><?= $a['deposit_id'] ? esc($a['method'].' / '.$a['reference_number']) : '—' ?></td><td>
      <?php if ($a['deposit_id']): ?><strong><?= esc($a['verification_status']) ?></strong><?php endif; ?>
      <?php if ((!$a['deposit_id'] || $a['verification_status']==='Rejected') && in_array($a['status'],['Pending','Rescheduled'],true)): ?>
      <form method="post" class="payment-form">
        <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="deposit_submit"><input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>">
       <select name="method" required>
    <option value="GCash">GCash</option>
    <option value="Maya">PayMaya</option>
</select>
        <input name="reference_number" placeholder="Transaction reference" maxlength="120" required>
        <button type="submit">Submit for verification</button>
      </form>
      <?php elseif ($a['verification_status']==='Pending' && admin()): ?>
      <form method="post" class="inline" onsubmit="return confirm('Have you verified the money was actually received?')">
        <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="deposit_verify"><input type="hidden" name="id" value="<?= (int)$a['deposit_id'] ?>"><input type="hidden" name="decision" value="Verified"><button type="submit">Verify</button>
      </form>
      <form method="post" class="inline"><input type="hidden" name="csrf" value="<?= esc(csrf()) ?>"><input type="hidden" name="action" value="deposit_verify"><input type="hidden" name="id" value="<?= (int)$a['deposit_id'] ?>"><input type="hidden" name="decision" value="Rejected"><button type="submit" class="muted">Reject</button></form>
      <?php endif; ?></td></tr>
    <?php endforeach; ?></table></div></div>
    <?php
}
