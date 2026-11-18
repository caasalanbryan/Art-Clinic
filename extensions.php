
<?php
require_once 'config.php';
require_login();

$modules = [
    'dashboard' => 'Advanced Dashboard',
    'records' => 'Dental Records',
    'dentists' => 'Dentist Management',
    'services' => 'Dental Services',
    'billing' => 'Billing & Payments',
    'inventory' => 'Inventory Management',
    'reminders' => 'Appointment Reminders',
    'audit' => 'Audit Logs'
];

$page = $_GET['page'] ?? 'dashboard';

if (!isset($modules[$page])) {
    $page = 'dashboard';
}

if ($page === 'audit' && !admin()) {
    http_response_code(403);
    exit('Administrator access required.');
}

function extensionAudit($action, $type, $id = null) {
    stmt(
        "INSERT INTO audit_logs
        (user_id, action, entity_type, entity_id)
        VALUES (?, ?, ?, ?)",
        'issi',
        [
            (int)$_SESSION['user']['id'],
            $action,
            $type,
            $id
        ]
    );
}

function extensionMoney($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function extensionSelect($label, $name, $data, $textColumn) {
    echo '<label>' . esc($label);
    echo '<select name="' . esc($name) . '" required>';
    echo '<option value="">Select ' . esc($label) . '</option>';

    foreach ($data as $row) {
        echo '<option value="' . (int)$row['id'] . '">';
        echo esc($row[$textColumn]);
        echo '</option>';
    }

    echo '</select></label>';
}

function extensionInput($label, $name, $type = 'text') {
    echo '<label>' . esc($label);
    echo '<input type="' . esc($type) . '" name="' .
         esc($name) . '" required>';
    echo '</label>';
}

function extensionForm($action) {
    echo '<form method="POST" class="form-grid">';
    echo '<input type="hidden" name="csrf" value="' .
         esc(csrf()) . '">';
    echo '<input type="hidden" name="action" value="' .
         esc($action) . '">';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_post();

        $action = $_POST['action'] ?? '';

        if ($action === 'dentist') {
            if (!admin()) {
                throw new Exception('Administrator access required.');
            }

            $name = trim($_POST['name'] ?? '');

            if ($name === '') {
                throw new Exception('Dentist name is required.');
            }

            stmt(
                "INSERT INTO dentists
                (full_name, specialization, contact, email)
                VALUES (?, ?, ?, ?)",
                'ssss',
                [
                    $name,
                    trim($_POST['specialization'] ?? ''),
                    trim($_POST['contact'] ?? ''),
                    trim($_POST['email'] ?? '')
                ]
            );

            extensionAudit('Create', 'dentist');
        }

        elseif ($action === 'service') {
            if (!admin()) {
                throw new Exception('Administrator access required.');
            }

            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $duration = (int)($_POST['duration'] ?? 30);

            if ($name === '' || $price < 0 || $duration <= 0) {
                throw new Exception('Invalid dental service.');
            }

            stmt(
                "INSERT INTO dental_services
                (service_name, price, duration_minutes)
                VALUES (?, ?, ?)",
                'sdi',
                [$name, $price, $duration]
            );

            extensionAudit('Create', 'service');
        }

        elseif ($action === 'record') {
            $patient = (int)($_POST['patient_id'] ?? 0);
            $dentist = (int)($_POST['dentist_id'] ?? 0);
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment = trim($_POST['treatment'] ?? '');
            $date = $_POST['date'] ?? '';

            if (!$patient || !$dentist ||
                !$diagnosis || !$treatment || !$date) {
                throw new Exception('Complete all required fields.');
            }

            stmt(
                "INSERT INTO dental_records
                (patient_id, dentist_id, tooth_number,
                diagnosis, treatment, prescription,
                treatment_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                'iisssssi',
                [
                    $patient,
                    $dentist,
                    trim($_POST['tooth'] ?? ''),
                    $diagnosis,
                    $treatment,
                    trim($_POST['prescription'] ?? ''),
                    $date,
                    (int)$_SESSION['user']['id']
                ]
            );

            extensionAudit('Create', 'dental_record');
        }

        elseif ($action === 'invoice') {
            $patient = (int)($_POST['patient_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $total = (float)($_POST['total'] ?? 0);

            if (!$patient || !$description || $total <= 0) {
                throw new Exception('Invalid invoice details.');
            }

            stmt(
                "INSERT INTO invoices
                (patient_id, description, total, created_by)
                VALUES (?, ?, ?, ?)",
                'isdi',
                [
                    $patient,
                    $description,
                    $total,
                    (int)$_SESSION['user']['id']
                ]
            );

            extensionAudit('Create', 'invoice');
        }

        elseif ($action === 'payment') {
            $invoice = (int)($_POST['invoice_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $method = $_POST['method'] ?? '';

            if ($amount <= 0 || !in_array($method, [
                'Cash', 'GCash', 'Bank Transfer', 'Card'
            ], true)) {
                throw new Exception('Invalid payment details.');
            }

            $lockName = 'artcare_invoice_' . $invoice;
            $lock = one(
                "SELECT GET_LOCK(?, 5) AS locked",
                's',
                [$lockName]
            );

            if (!$lock || (int)$lock['locked'] !== 1) {
                throw new Exception('Invoice is currently busy.');
            }

            try {
                $balance = one(
                    "SELECT i.total -
                    COALESCE(SUM(p.amount), 0) AS balance
                    FROM invoices i
                    LEFT JOIN payments p ON p.invoice_id = i.id
                    WHERE i.id = ?
                    GROUP BY i.id, i.total",
                    'i',
                    [$invoice]
                );

                if (!$balance ||
                    $amount > (float)$balance['balance']) {
                    throw new Exception('Payment exceeds balance.');
                }

                stmt(
                    "INSERT INTO payments
                    (invoice_id, amount, payment_method,
                    reference_number, received_by)
                    VALUES (?, ?, ?, ?, ?)",
                    'idssi',
                    [
                        $invoice,
                        $amount,
                        $method,
                        trim($_POST['reference'] ?? ''),
                        (int)$_SESSION['user']['id']
                    ]
                );

                extensionAudit('Receive payment', 'invoice', $invoice);
            } finally {
                stmt(
                    "SELECT RELEASE_LOCK(?)",
                    's',
                    [$lockName]
                );
            }
        }

        elseif ($action === 'inventory') {
            if (!admin()) {
                throw new Exception('Administrator access required.');
            }

            $name = trim($_POST['name'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? -1);
            $minimum = (int)($_POST['minimum'] ?? -1);

            if (!$name || $quantity < 0 || $minimum < 0) {
                throw new Exception('Invalid inventory details.');
            }

            stmt(
                "INSERT INTO inventory
                (item_name, category, quantity,
                minimum_quantity, unit)
                VALUES (?, ?, ?, ?, ?)",
                'ssiis',
                [
                    $name,
                    trim($_POST['category'] ?? ''),
                    $quantity,
                    $minimum,
                    trim($_POST['unit'] ?? 'pcs')
                ]
            );

            extensionAudit('Create', 'inventory');
        }

        else {
            throw new Exception('Unknown action.');
        }

        notice('Record saved successfully.');
        go('extensions.php?page=' . urlencode($page));

    } catch (Throwable $e) {
        $error = $e instanceof mysqli_sql_exception
            ? 'Database error. Please check the information.'
            : $e->getMessage();
    }
}

$patients = rows(
    "SELECT id, full_name FROM patients ORDER BY full_name"
);

$dentists = rows(
    "SELECT * FROM dentists ORDER BY full_name"
);

$services = rows(
    "SELECT * FROM dental_services ORDER BY service_name"
);

$records = rows(
    "SELECT r.*, p.full_name AS patient_name,
    d.full_name AS dentist_name
    FROM dental_records r
    JOIN patients p ON p.id = r.patient_id
    LEFT JOIN dentists d ON d.id = r.dentist_id
    ORDER BY r.treatment_date DESC, r.id DESC"
);

$invoices = rows(
    "SELECT i.*, p.full_name AS patient_name,
    i.total - COALESCE(SUM(pay.amount), 0) AS balance
    FROM invoices i
    JOIN patients p ON p.id = i.patient_id
    LEFT JOIN payments pay ON pay.invoice_id = i.id
    GROUP BY i.id
    ORDER BY i.id DESC"
);

$items = rows(
    "SELECT * FROM inventory ORDER BY item_name"
);

$reminders = rows(
    "SELECT a.id, a.appointment_date, a.start_time,
    a.service, p.full_name AS patient_name,
    p.contact, p.email
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.status IN ('Pending','Confirmed','Rescheduled')
    AND TIMESTAMP(a.appointment_date, a.start_time)
    BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
    ORDER BY a.appointment_date, a.start_time"
);

$logs = $page === 'audit'
    ? rows(
        "SELECT l.*, u.full_name AS username
        FROM audit_logs l
        LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.id DESC LIMIT 100"
    )
    : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($modules[$page]) ?> | ART CARE</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<aside>
    <div class="brand">
        ✦ ART CARE
        <small>DENTAL CLINIC SYSTEM</small>
    </div>

    <small class="navtitle">MAIN SYSTEM</small>

    <nav>
        <a href="index.php?page=dashboard">Dashboard</a>
        <a href="index.php?page=patients">Patients</a>
        <a href="index.php?page=appointments">Appointments</a>
        <a href="index.php?page=calendar">Calendar</a>
        <a href="index.php?page=reports">Reports</a>
    </nav>

    <small class="navtitle" style="margin-top:25px">
        CLINIC MANAGEMENT
    </small>

    <nav>
        <?php foreach ($modules as $key => $title): ?>
            <?php if ($key === 'audit' && !admin()) continue; ?>

            <a
                href="extensions.php?page=<?= esc($key) ?>"
                class="<?= $page === $key ? 'active' : '' ?>"
            >
                <?= esc($title) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">
        <strong>
            <?= esc($_SESSION['user']['name']) ?>
        </strong>

        <small>
            <?= esc($_SESSION['user']['role']) ?>
        </small>

        <a
            href="logout.php"
            class="logout-btn"
            onclick="return confirm('Are you sure you want to log out?')"
        >
            Log out
        </a>
    </div>
</aside>

<main class="main">

<header>
    <small>DENTAL CLINIC MANAGEMENT</small>
    <h1><?= esc($modules[$page]) ?></h1>
</header>

<?php if ($error): ?>
    <div class="error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['notice'])): ?>
    <div class="success">
        <?= esc($_SESSION['notice']) ?>
    </div>
    <?php unset($_SESSION['notice']); ?>
<?php endif; ?>

<?php if ($page === 'dashboard'): ?>

    <?php
    $totalPaid = one(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments"
    );

    $completed = one(
        "SELECT COUNT(*) AS total FROM appointments
        WHERE status = 'Completed'"
    );

    $lowStock = one(
        "SELECT COUNT(*) AS total FROM inventory
        WHERE quantity <= minimum_quantity"
    );
    ?>

    <div class="stats">

        <div class="card">
            <small>Total Patients</small>
            <h1><?= count($patients) ?></h1>
        </div>

        <div class="card">
            <small>Completed Appointments</small>
            <h1><?= (int)$completed['total'] ?></h1>
        </div>

        <div class="card">
            <small>Payments Received</small>
            <h1><?= extensionMoney($totalPaid['total']) ?></h1>
        </div>

        <div class="card">
            <small>Low Stock Items</small>
            <h1><?= (int)$lowStock['total'] ?></h1>
        </div>

    </div>

    <div class="card">
        <h2>Popular Dental Services</h2>

        <?php
        $popular = rows(
            "SELECT service, COUNT(*) AS total
            FROM appointments
            GROUP BY service
            ORDER BY total DESC LIMIT 10"
        );
        ?>

        <?php foreach ($popular as $s): ?>
            <p>
                <?= esc($s['service']) ?> —
                <?= (int)$s['total'] ?> appointments
            </p>
        <?php endforeach; ?>
    </div>

<?php elseif ($page === 'dentists'): ?>

    <?php if (admin()): ?>
        <div class="card">
            <h2>Add Dentist</h2>

            <?php extensionForm('dentist'); ?>

            <?php extensionInput('Full Name', 'name'); ?>
            <?php extensionInput('Specialization', 'specialization'); ?>
            <?php extensionInput('Contact Number', 'contact'); ?>
            <?php extensionInput('Email', 'email', 'email'); ?>

            <button type="submit">Add Dentist</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Registered Dentists</h2>

        <?php foreach ($dentists as $d): ?>
            <p>
                <?= esc($d['full_name']) ?> —
                <?= esc($d['specialization']) ?>
            </p>
        <?php endforeach; ?>
    </div>

<?php elseif ($page === 'services'): ?>

    <?php if (admin()): ?>
        <div class="card">
            <h2>Add Dental Service</h2>

            <?php extensionForm('service'); ?>

            <?php extensionInput('Service Name', 'name'); ?>
            <?php extensionInput('Price (PHP)', 'price', 'number'); ?>
            <?php extensionInput('Duration (Minutes)', 'duration', 'number'); ?>

            <button type="submit">Add Service</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Available Dental Services</h2>

        <?php foreach ($services as $s): ?>
            <p>
                <?= esc($s['service_name']) ?> —
                <?= extensionMoney($s['price']) ?> —
                <?= (int)$s['duration_minutes'] ?> minutes
            </p>
        <?php endforeach; ?>
    </div>

<?php elseif ($page === 'records'): ?>

    <div class="card">
        <h2>Add Dental Treatment Record</h2>

        <?php extensionForm('record'); ?>

        <?php extensionSelect(
            'Patient', 'patient_id', $patients, 'full_name'
        ); ?>

        <?php extensionSelect(
            'Dentist', 'dentist_id', $dentists, 'full_name'
        ); ?>

        <?php extensionInput('Tooth Number', 'tooth'); ?>
        <?php extensionInput('Treatment Date', 'date', 'date'); ?>

        <label class="wide">
            Diagnosis
            <textarea name="diagnosis" required></textarea>
        </label>

        <label class="wide">
            Treatment Performed
            <textarea name="treatment" required></textarea>
        </label>

        <label class="wide">
            Prescription / Instructions
            <textarea name="prescription"></textarea>
        </label>

        <button type="submit">Save Dental Record</button>
        </form>
    </div>

    <div class="card">
        <h2>Treatment History</h2>

        <div class="table-wrap">
        <table>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Dentist</th>
                <th>Tooth</th>
                <th>Diagnosis</th>
                <th>Treatment</th>
            </tr>

            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= esc($r['treatment_date']) ?></td>
                <td><?= esc($r['patient_name']) ?></td>
                <td><?= esc($r['dentist_name'] ?? '') ?></td>
                <td><?= esc($r['tooth_number']) ?></td>
                <td><?= esc($r['diagnosis']) ?></td>
                <td><?= esc($r['treatment']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

<?php elseif ($page === 'billing'): ?>

    <div class="card">
        <h2>Create Invoice</h2>

        <?php extensionForm('invoice'); ?>

        <?php extensionSelect(
            'Patient', 'patient_id', $patients, 'full_name'
        ); ?>

        <?php extensionInput('Description', 'description'); ?>
        <?php extensionInput('Total Amount', 'total', 'number'); ?>

        <button type="submit">Create Invoice</button>
        </form>
    </div>

    <div class="card">
        <h2>Record Payment</h2>

        <?php extensionForm('payment'); ?>

        <label>
            Invoice
            <select name="invoice_id" required>
                <option value="">Select invoice</option>

                <?php foreach ($invoices as $i): ?>
                    <?php if ((float)$i['balance'] > 0): ?>
                        <option value="<?= (int)$i['id'] ?>">
                            #<?= (int)$i['id'] ?> —
                            <?= esc($i['patient_name']) ?> —
                            <?= extensionMoney($i['balance']) ?> due
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>

        <?php extensionInput('Amount', 'amount', 'number'); ?>

        <label>
            Payment Method
            <select name="method" required>
                <option>Cash</option>
                <option>GCash</option>
                <option>Bank Transfer</option>
                <option>Card</option>
            </select>
        </label>

        <?php extensionInput('Reference Number', 'reference'); ?>

        <button type="submit">Save Payment</button>
        </form>
    </div>

    <div class="card">
        <h2>Invoices & Balances</h2>

        <div class="table-wrap">
        <table>
            <tr>
                <th>Invoice</th>
                <th>Patient</th>
                <th>Total</th>
                <th>Balance</th>
            </tr>

            <?php foreach ($invoices as $i): ?>
            <tr>
                <td>#<?= (int)$i['id'] ?></td>
                <td><?= esc($i['patient_name']) ?></td>
                <td><?= extensionMoney($i['total']) ?></td>
                <td><?= extensionMoney($i['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

<?php elseif ($page === 'inventory'): ?>

    <?php if (admin()): ?>
        <div class="card">
            <h2>Add Inventory Item</h2>

            <?php extensionForm('inventory'); ?>

            <?php extensionInput('Item Name', 'name'); ?>
            <?php extensionInput('Category', 'category'); ?>
            <?php extensionInput('Quantity', 'quantity', 'number'); ?>
            <?php extensionInput('Minimum Stock', 'minimum', 'number'); ?>
            <?php extensionInput('Unit', 'unit'); ?>

            <button type="submit">Add Item</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Dental Supplies</h2>

        <div class="table-wrap">
        <table>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Status</th>
            </tr>

            <?php foreach ($items as $i): ?>
            <tr>
                <td><?= esc($i['item_name']) ?></td>
                <td>
                    <?= (int)$i['quantity'] ?>
                    <?= esc($i['unit']) ?>
                </td>
                <td>
                    <?= $i['quantity'] <= $i['minimum_quantity']
                        ? 'Low Stock'
                        : 'Available' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

<?php elseif ($page === 'reminders'): ?>

    <div class="card">
        <h2>Upcoming Appointment Reminders</h2>

        <p>Appointments scheduled within the next 48 hours.</p>

        <?php if (!$reminders): ?>
            <p>No upcoming reminders.</p>
        <?php endif; ?>

        <?php foreach ($reminders as $r): ?>
            <p>
                <?= esc($r['patient_name']) ?> —
                <?= esc($r['appointment_date']) ?> —
                <?= esc($r['start_time']) ?> —
                <?= esc($r['service']) ?>
            </p>
        <?php endforeach; ?>
    </div>

<?php elseif ($page === 'audit'): ?>

    <div class="card">
        <h2>Recent Audit Logs</h2>

        <div class="table-wrap">
        <table>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Module</th>
            </tr>

            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= esc($l['created_at']) ?></td>
                <td><?= esc($l['username'] ?? 'Unknown') ?></td>
                <td><?= esc($l['action']) ?></td>
                <td><?= esc($l['entity_type']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>

<?php endif; ?>

</main>
</body>
</html>
