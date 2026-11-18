<?php
// Included by index.php after authentication. Do not browse this file directly.
if (!defined('ARTCARE_MODULE')) { http_response_code(403); exit; }
function artcare_money($amount) { return '₱' . number_format((float)$amount, 2); }
function artcare_billing_action($action) {
    global $db;
    if ($action === 'add_clinical_record') {
        // Clinical entry is restricted to admins until a dentist role is implemented.
        if (!admin()) throw new Exception('Only an authorized administrator can enter clinical records.');
        $patient=(int)($_POST['patient_id']??0);
        $appointment=(int)($_POST['appointment_id']??0);
        $service=trim($_POST['service_name']??'');
        $dentist=trim($_POST['dentist_name']??'');
        $date=$_POST['treatment_date']??'';
        $tooth=trim($_POST['tooth_number']??'');
        $diagnosis=trim($_POST['diagnosis']??'');
        $notes=trim($_POST['treatment_notes']??'');
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$patient || !$service || !$dentist || !$diagnosis || !$notes || !$parsed || $parsed->format('Y-m-d')!==$date || $date>date('Y-m-d')) throw new Exception('Complete all required treatment fields with a valid date.');
        if (!one('SELECT id FROM patients WHERE id=?','i',[$patient])) throw new Exception('Patient does not exist.');
        if ($appointment) {
            $appt=one('SELECT patient_id,status FROM appointments WHERE id=?','i',[$appointment]);
            if (!$appt || (int)$appt['patient_id']!==$patient || $appt['status']!=='Completed') throw new Exception('Select a completed appointment belonging to this patient.');
        }
        stmt('INSERT INTO artcare_clinical_records (patient_id,appointment_id,service_name,dentist_name,treatment_date,tooth_number,diagnosis,treatment_notes,created_by) VALUES (?,NULLIF(?,0),?,?,?,?,?,?,?)',
             'iissssssi',[$patient,$appointment,$service,$dentist,$date,$tooth,$diagnosis,$notes,(int)$_SESSION['user']['id']]);
        notice('Dental record saved.'); go('?page=dental_records');
    }
    if ($action === 'create_invoice') {
        $record=(int)($_POST['record_id']??0);
        $amount=trim($_POST['total']??'');
        if (!$record || !preg_match('/^\d{1,10}(\.\d{1,2})?$/',$amount) || (float)$amount<=0) throw new Exception('Choose a treatment and enter a valid positive amount.');
        $r=one('SELECT patient_id FROM artcare_clinical_records WHERE id=?','i',[$record]);
        if (!$r) throw new Exception('Treatment not found.');
        stmt('INSERT INTO artcare_invoices (record_id,patient_id,total,created_by) VALUES (?,?,?,?)','iidi',[$record,(int)$r['patient_id'],(float)$amount,(int)$_SESSION['user']['id']]);
        notice('Invoice created.'); go('?page=billing');
    }
    if ($action === 'record_payment') {
        $invoice=(int)($_POST['invoice_id']??0);
        $amount=trim($_POST['amount']??'');
        $method=$_POST['method']??'';
        $reference=trim($_POST['reference_number']??'');
        if (!$invoice || !preg_match('/^\d{1,10}(\.\d{1,2})?$/',$amount) || (float)$amount<=0 || !in_array($method,['Cash','GCash','Bank Transfer'],true)) throw new Exception('Enter valid payment details.');
        if ($method!=='Cash' && !$reference) throw new Exception('Enter a transaction reference for electronic payments.');
        $db->begin_transaction();
        try {
            $inv=one('SELECT total FROM artcare_invoices WHERE id=? FOR UPDATE','i',[$invoice]);
            if (!$inv) throw new Exception('Invoice not found.');
            $paid=one('SELECT COALESCE(SUM(amount),0) AS paid FROM artcare_payments WHERE invoice_id=?','i',[$invoice]);
            $remaining=round((float)$inv['total']-(float)$paid['paid'],2);
            if (round((float)$amount,2)>$remaining || $remaining<=0) throw new Exception('Payment exceeds the outstanding balance.');
            stmt('INSERT INTO artcare_payments (invoice_id,amount,method,reference_number,received_by) VALUES (?,?,?,?,?)',
                 'idssi',[$invoice,(float)$amount,$method,$reference,(int)$_SESSION['user']['id']]);
            $db->commit();
        } catch (Throwable $e) { $db->rollback(); throw $e; }
        notice('Payment recorded.'); go('?page=billing');
    }
}
function artcare_render($page,$patients,$appointments) {
    $records=rows('SELECT r.*,p.full_name AS patient_name FROM artcare_clinical_records r JOIN patients p ON p.id=r.patient_id ORDER BY r.treatment_date DESC,r.id DESC');
    if ($page==='dental_records') { ?>
        <h2>Dental Records</h2><p>Record treatment findings after care has been provided.</p>
        <?php if(admin()): ?><div class="card"><h3>Add dental record</h3>
        <form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?=esc(csrf())?>"><input type="hidden" name="action" value="add_clinical_record">
        <label>Patient<select name="patient_id" required><option value="">Select patient</option><?php foreach($patients as $p):?><option value="<?=(int)$p['id']?>"><?=esc($p['full_name'])?></option><?php endforeach;?></select></label>
        <label>Completed appointment (optional)<select name="appointment_id"><option value="0">No linked appointment</option><?php foreach($appointments as $a): if($a['status']!=='Completed')continue;?><option value="<?=(int)$a['id']?>"><?=esc($a['patient_name'].' — '.$a['appointment_date'].' — '.$a['service'])?></option><?php endforeach;?></select></label>
        <label>Dental service<input name="service_name" maxlength="150" required placeholder="e.g. Tooth Extraction"></label>
        <label>Treating dentist<input name="dentist_name" maxlength="150" required></label>
        <label>Treatment date<input type="date" name="treatment_date" max="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required></label>
        <label>Tooth number (optional)<input name="tooth_number" maxlength="30" placeholder="e.g. 36"></label>
        <label class="wide">Diagnosis<textarea name="diagnosis" required></textarea></label>
        <label class="wide">Treatment performed<textarea name="treatment_notes" required></textarea></label>
        <button type="submit">Save dental record</button></form></div><?php endif; ?>
    <?php }
    if($page==='dental_records'||$page==='treatments') { ?>
        <div class="card"><h3><?= $page==='treatments'?'Treatment History':'Saved Dental Records' ?></h3><div class="table-wrap"><table><tr><th>Date</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Tooth</th><th>Diagnosis</th><th>Treatment</th></tr>
        <?php foreach($records as $r):?><tr><td><?=esc($r['treatment_date'])?></td><td><?=esc($r['patient_name'])?></td><td><?=esc($r['service_name'])?></td><td><?=esc($r['dentist_name'])?></td><td><?=esc($r['tooth_number'])?></td><td><?=esc($r['diagnosis'])?></td><td><?=esc($r['treatment_notes'])?></td></tr><?php endforeach; ?></table></div>
        <?php if(!$records):?><p>No dental records yet.</p><?php endif;?></div>
    <?php }
    if($page==='billing') {
        $invoices=rows('SELECT i.*,p.full_name AS patient_name,r.service_name,COALESCE(SUM(pay.amount),0) AS paid FROM artcare_invoices i JOIN patients p ON p.id=i.patient_id JOIN artcare_clinical_records r ON r.id=i.record_id LEFT JOIN artcare_payments pay ON pay.invoice_id=i.id GROUP BY i.id ORDER BY i.id DESC');
        $payments=rows('SELECT pay.*,i.patient_id,p.full_name AS patient_name FROM artcare_payments pay JOIN artcare_invoices i ON i.id=pay.invoice_id JOIN patients p ON p.id=i.patient_id ORDER BY pay.id DESC');
        $billedIds=array_column($invoices,'record_id'); ?>
        <h2>Billing & Payments</h2><p>Create invoices and record full or partial payments.</p>
        <div class="card"><h3>Generate invoice</h3><form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?=esc(csrf())?>"><input type="hidden" name="action" value="create_invoice">
        <label class="wide">Completed treatment<select name="record_id" required><option value="">Select treatment</option><?php foreach($records as $r): if(in_array($r['id'],$billedIds))continue;?><option value="<?=(int)$r['id']?>"><?=esc($r['patient_name'].' — '.$r['service_name'].' — '.$r['treatment_date'])?></option><?php endforeach;?></select></label>
        <label>Final treatment cost (PHP)<input type="number" name="total" min="0.01" step="0.01" required></label>
        <div class="wide"><button type="submit">Generate invoice</button></div></form></div>
        <div class="card"><h3>Record payment</h3><form method="post" class="form-grid">
        <input type="hidden" name="csrf" value="<?=esc(csrf())?>"><input type="hidden" name="action" value="record_payment">
        <label class="wide">Unpaid invoice<select name="invoice_id" required><option value="">Select invoice</option><?php foreach($invoices as $i):$balance=round((float)$i['total']-(float)$i['paid'],2);if($balance<=0)continue;?><option value="<?=(int)$i['id']?>"><?=esc('#'.$i['id'].' — '.$i['patient_name'].' — balance PHP '.number_format($balance,2))?></option><?php endforeach;?></select></label>
        <label>Amount paid (PHP)<input type="number" name="amount" min="0.01" step="0.01" required></label>
        <label>Method<select name="method"><option>Cash</option><option>GCash</option><option>Bank Transfer</option></select></label>
        <label class="wide">Transaction reference (required for electronic payments)<input name="reference_number" maxlength="120"></label>
        <button type="submit">Save payment</button></form></div>
        <div class="card"><h3>Invoices</h3><div class="table-wrap"><table><tr><th>Invoice</th><th>Patient</th><th>Service</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
        <?php foreach($invoices as $i):$balance=max(0,round((float)$i['total']-(float)$i['paid'],2));?><tr><td>#<?=(int)$i['id']?></td><td><?=esc($i['patient_name'])?></td><td><?=esc($i['service_name'])?></td><td><?=artcare_money($i['total'])?></td><td><?=artcare_money($i['paid'])?></td><td><?=artcare_money($balance)?></td><td><?= $balance<=0?'Paid':((float)$i['paid']>0?'Partially Paid':'Unpaid') ?></td></tr><?php endforeach;?></table></div></div>
        <div class="card"><h3>Payment History</h3><div class="table-wrap"><table><tr><th>Date</th><th>Invoice</th><th>Patient</th><th>Amount</th><th>Method</th><th>Reference</th></tr>
        <?php foreach($payments as $p):?><tr><td><?=esc($p['paid_at'])?></td><td>#<?=(int)$p['invoice_id']?></td><td><?=esc($p['patient_name'])?></td><td><?=artcare_money($p['amount'])?></td><td><?=esc($p['method'])?></td><td><?=esc($p['reference_number'])?></td></tr><?php endforeach;?></table></div></div>
    <?php }
}
