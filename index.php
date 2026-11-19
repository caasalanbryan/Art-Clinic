
<?php
require 'config.php';
require_login();

define('ARTCARE_PAYMENT_MODULE', true);

require_once __DIR__ . '/artcare_payment_module.php';
require_once __DIR__ . '/artcare_remaining_payments.php';

ar_handle_receipt();
ar_handle_post();
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', [
    'service_save',
    'service_toggle',
    'deposit_submit',
    'deposit_verify',
    'remaining_payment_submit',
    'remaining_payment_verify'
], true)) {
    payment_handle_post();
}

$page = $_GET['page'] ?? 'dashboard';
// COUNT UNREAD NOTIFICATIONS
$userId = (int)($_SESSION['user']['id'] ?? 0);

$unreadNotifications = 0;

if ($userId > 0) {
    $result = one(
        "SELECT COUNT(*) AS total
         FROM notifications
         WHERE user_id = ? AND is_read = 0",
        "i",
        [$userId]
    );

    $unreadNotifications = (int)($result['total'] ?? 0);
}


$labels = [
    'dashboard' => 'Dashboard',
    'patients' => 'Patient Management',
    'appointments' => 'Appointment Management',
    'calendar' => 'Calendar & Schedule',
    'history' => 'Appointment History',
    'notifications' => 'Notifications',
    'reports' => 'Reports',
    'settings' => 'Clinic Settings',
    'accounts' => 'Receptionist Account',
    'service_management' => 'Dental Service Management',
    'payment_management' => 'Payment Management',
];



if (!isset($labels[$page])) {
    $page = 'dashboard';
}

// ADMIN ONLY - DENTAL SERVICE MANAGEMENT
if ($page === 'service_management' && !admin()) {
    $page = 'dashboard';
}


if ($page === 'accounts' && !can_create_accounts()) {
    $page = 'dashboard';
}


$error = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_error']);
$statuses = [
    'Pending', 'Confirmed', 'Rescheduled',
    'Completed', 'Cancelled', 'No-Show'
];

// HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_post();
        $action = $_POST['action'] ?? '';

        // MARK SINGLE NOTIFICATION AS READ
if ($action === 'read_notification') {

    $id = (int)($_POST['id'] ?? 0);
    $userId = (int)$_SESSION['user']['id'];

    stmt(
        "UPDATE notifications
         SET is_read = 1
         WHERE id = ? AND user_id = ?",
        "ii",
        [$id, $userId]
    );

    go('?page=notifications');
}

// MARK ALL NOTIFICATIONS AS READ
if ($action === 'read_all_notifications') {

    $userId = (int)$_SESSION['user']['id'];

    stmt(
        "UPDATE notifications
         SET is_read = 1
         WHERE user_id = ?",
        "i",
        [$userId]
    );

    go('?page=notifications');
}

        // ADD PATIENT
        if ($action === 'add_patient') {
            $name = trim($_POST['name'] ?? '');

            if ($name === '') {
                throw new Exception('Patient name is required.');
            }

            stmt(
                "INSERT INTO patients
                (full_name, contact, email, address, birth_date, notes)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), ?)",
                'ssssss',
                [
                    $name,
                    trim($_POST['contact'] ?? ''),
                    trim($_POST['email'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    $_POST['birth_date'] ?? '',
                    trim($_POST['notes'] ?? '')
                ]
            );

            notice('Patient added successfully.');
            go('?page=patients');
        }

        // EDIT PATIENT
        if ($action === 'edit_patient') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $birthDate = trim($_POST['birth_date'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($id <= 0 || $name === '') {
                throw new Exception('Valid patient name is required.');
            }

            if ($email !== '' &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }

            if ($birthDate !== '') {
                $birth = DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $birthDate
                );

                if (!$birth ||
                    $birth->format('Y-m-d') !== $birthDate ||
                    $birthDate > date('Y-m-d')) {
                    throw new Exception('Invalid birth date.');
                }
            }

            $existing = one(
                'SELECT id FROM patients WHERE id=?',
                'i',
                [$id]
            );

            if (!$existing) {
                throw new Exception('Patient not found.');
            }

            stmt(
                "UPDATE patients SET
                    full_name=?,
                    contact=?,
                    email=?,
                    address=?,
                    birth_date=NULLIF(?, ''),
                    notes=?
                 WHERE id=?",
                'ssssssi',
                [
                    $name,
                    $contact,
                    $email,
                    $address,
                    $birthDate,
                    $notes,
                    $id
                ]
            );

            notice('Patient information updated successfully.');
            go('?page=patients');
        }


        
        // DELETE PATIENT
        if ($action === 'delete_patient') {

            if (!admin()) {
                throw new Exception('Administrator only.');
            }

            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid patient ID.');
            }

            // Check whether the patient has appointments
            $linked = one(
                'SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE patient_id = ?',
                'i',
                [$id]
            );

            if ((int)$linked['total'] > 0) {
                throw new Exception(
                    'Cannot delete this patient because they have appointment records. Keep the patient to preserve appointment history.'
                );
            }

            // Delete patient without appointments
            stmt(
                'DELETE FROM patients WHERE id = ?',
                'i',
                [$id]
            );

            notice('Patient deleted successfully.');
            go('?page=patients');
        }

        // ADD APPOINTMENT
        if ($action === 'add_appointment') {
            $patient = (int)($_POST['patient_id'] ?? 0);
            $date = $_POST['date'] ?? '';
            $start = $_POST['start'] ?? '';
            $end = $_POST['end'] ?? '';
            $service = trim($_POST['service'] ?? '');

            if (
                !$patient ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
                !preg_match('/^\d{2}:\d{2}$/', $start) ||
                !preg_match('/^\d{2}:\d{2}$/', $end) ||
                $end <= $start ||
                $service === ''
            ) {
                throw new Exception('Enter valid appointment details.');
            }

            $parsedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

            if (
                !$parsedDate ||
                $parsedDate->format('Y-m-d') !== $date
            ) {
                throw new Exception('Invalid appointment date.');
            }

            if ($date < date('Y-m-d')) {
                throw new Exception('Cannot schedule past appointments.');
            }

            $settingsCheck = one(
                'SELECT * FROM clinic_settings WHERE id=1'
            );

            if (
                $start < substr($settingsCheck['opening_time'], 0, 5) ||
                $end > substr($settingsCheck['closing_time'], 0, 5)
            ) {
                throw new Exception('Appointment is outside clinic hours.');
            }

            $lockName = 'artcare_' . $date;

            $lock = one(
                'SELECT GET_LOCK(?, 5) AS acquired',
                's',
                [$lockName]
            );

            if (!$lock || (int)$lock['acquired'] !== 1) {
                throw new Exception('Schedule is busy. Try again.');
            }

            try {
                $conflict = one(
                    "SELECT id FROM appointments
                     WHERE appointment_date=?
                     AND status IN ('Pending','Confirmed','Rescheduled')
                     AND start_time < ?
                     AND end_time > ?
                     LIMIT 1",
                    'sss',
                    [$date, $end, $start]
                );

                if ($conflict) {
                    throw new Exception(
                       'DOUBLE BOOKING WARNING! This date and time is already reserved. Please select another available schedule.'
                    );
                }

                stmt(
                    "INSERT INTO appointments
                    (patient_id, appointment_date, start_time,
                     end_time, service, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)",
                    'isssssi',
                    [
                        $patient,
                        $date,
                        $start,
                        $end,
                        $service,
                        trim($_POST['notes'] ?? ''),
                        $_SESSION['user']['id']
                    ]
                );
            } finally {
                stmt(
                    'SELECT RELEASE_LOCK(?)',
                    's',
                    [$lockName]
                );
            }

        create_notification(
    'New Appointment',
    'A new dental appointment has been scheduled.',
    'appointment'
);    
            notice('Appointment created successfully.');
            go('?page=appointments');
        }


if ($action === 'account') {
    if (!admin()) {
        throw new Exception('Administrator access required.');
    }

    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';

    if (
        $name === '' ||
        $username === '' ||
        strlen($password) < 8 ||
        !in_array($role, ['admin', 'staff'], true)
    ) {
        throw new Exception(
            'Invalid account details. Password must have at least 8 characters.'
        );
    }

    $existing = one(
        "SELECT id FROM users WHERE username = ? LIMIT 1",
        's',
        [$username]
    );

    if ($existing) {
        throw new Exception('Username already exists.');
    }

    stmt(
        "INSERT INTO users
         (full_name, username, password_hash, role)
         VALUES (?, ?, ?, ?)",
        'ssss',
        [
            $name,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        ]
    );

    notice('Account created successfully.');
    go('?page=accounts');
}


if ($action === 'update_appointment_action') {
    $selected = $_POST['appointment_action'] ?? '';

    if ($selected === 'check_in') {
        $_POST['action'] = 'mark_arrived';
        $action = 'mark_arrived';
    } else {
        $_POST['status'] = $selected;
        $_POST['action'] = 'status';
        $action = 'status';
    }
}


        // CONFIRM PATIENT ARRIVAL
        if ($action === 'mark_arrived') {

            $id = (int)($_POST['id'] ?? 0);

            $appointment = one(
                "SELECT id, status, arrived_at
                 FROM appointments
                 WHERE id = ?",
                'i',
                [$id]
            );

            if (!$appointment) {
                throw new Exception('Appointment not found.');
            }

            if (!in_array(
                $appointment['status'],
                ['Pending', 'Confirmed', 'Rescheduled'],
                true
            )) {
                throw new Exception('Appointment is no longer active.');
            }

            if ($appointment['arrived_at'] !== null) {
                throw new Exception('Patient already marked as arrived.');
            }

            $arrivalTime = (new DateTimeImmutable(
                'now',
                new DateTimeZone('Asia/Manila')
            ))->format('Y-m-d H:i:s');

            $updated = stmt(
                "UPDATE appointments
                 SET arrived_at = ?
                 WHERE id = ?
                   AND arrived_at IS NULL
                   AND status IN
                   ('Pending', 'Confirmed', 'Rescheduled')",
                'si',
                [$arrivalTime, $id]
            );

            if ($updated->affected_rows !== 1) {
                throw new Exception(
                    'Appointment already updated. Please refresh.'
                );
            }

            notice('Patient arrival confirmed successfully.');
            go('?page=appointments');
        }

        // UPDATE APPOINTMENT STATUS
if ($action === 'status') {

    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, $statuses, true)) {
        throw new Exception('Invalid appointment status.');
    }

    $old = one(
        "SELECT id, status, arrived_at
         FROM appointments
         WHERE id = ?",
        'i',
        [$id]
    );

    if (!$old) {
        throw new Exception('Appointment not found.');
    }

    $oldStatus = $old['status'];

    if ($oldStatus === $status) {
        notice('Appointment status is already ' . $status . '.');
        go('?page=appointments');
    }

    if (
        in_array($oldStatus, ['Completed', 'Cancelled', 'No-Show'], true)
    ) {
        throw new Exception('This appointment is already closed.');
    }

    if ($status === 'Completed' && empty($old['arrived_at'])) {
        throw new Exception('Confirm patient arrival before completing the appointment.');
    }

    stmt(
        "UPDATE appointments
         SET status = ?
         WHERE id = ?",
        'si',
        [$status, $id]
    );

    stmt(
        "INSERT INTO appointment_history
         (appointment_id, old_status, new_status, changed_by)
         VALUES (?, ?, ?, ?)",
        'issi',
        [
            $id,
            $oldStatus,
            $status,
            (int)$_SESSION['user']['id']
        ]
    );

    notice('Appointment updated successfully.');
    go('?page=appointments');
}

   } catch (Throwable $e) {

    $message = $e instanceof mysqli_sql_exception
        ? 'Database operation failed. Check for duplicate or linked records.'
        : $e->getMessage();

    $_SESSION['form_error'] = $message;

    $target = $_POST['action'] ?? '';
    
    if ($target === 'account') {
    go('?page=accounts');
}
    if ($target === 'add_appointment' || $target === 'status' || $target === 'mark_arrived') {
        go('?page=appointments');
    }

    if (in_array($target, ['add_patient', 'edit_patient', 'delete_patient'], true)) {
        go('?page=patients');
    }

    go('?page=dashboard');
}
}


// LOAD DATABASE RECORDS
$settings = one('SELECT * FROM clinic_settings WHERE id=1');

$patients = rows(
    'SELECT * FROM patients ORDER BY id DESC'
);

$appointments = rows(
    "SELECT a.*, p.full_name AS patient_name
     FROM appointments a
     JOIN patients p ON p.id=a.patient_id
     ORDER BY a.appointment_date DESC, a.start_time DESC"
);

$history = rows(
    "SELECT h.*, p.full_name AS patient_name
     FROM appointment_history h
     JOIN appointments a ON a.id=h.appointment_id
     JOIN patients p ON p.id=a.patient_id
     ORDER BY h.id DESC LIMIT 100"
);

$today = date('Y-m-d');
$activeStatuses = ['Pending', 'Confirmed', 'Rescheduled'];

$todayCount = count(array_filter(
    $appointments,
    fn($a) => $a['appointment_date'] === $today
));

$upcomingAppointments = array_values(array_filter(
    $appointments,
    fn($a) =>
        $a['appointment_date'] >= $today &&
        in_array($a['status'], $activeStatuses, true)
));

$completedCount = count(array_filter(
    $appointments,
    fn($a) => $a['status'] === 'Completed'
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="artcare-logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= esc($labels[$page]) ?> | ART CARE</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .logout-btn {
            display: block;
            margin-top: 14px;
            padding: 11px 12px;
            color: #a94442;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background: #fff0ed;
            color: #8b2926;
        }

        .sidebar-bottom {
            padding-bottom: 20px;
        }

        .sidebar-bottom small {
            display: block;
            margin-top: 5px;
        }

        @media print {
            aside, .section-head button, form {
                display: none !important;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<aside>
   <div class="brand sidebar-brand">
    <img
        src="artcare-logo.png"
        alt="ART Dental Center Logo"
        class="sidebar-logo"
    >

    <div class="brand-text">
        <strong>ART CARE</strong>
        <small>DENTAL CLINIC SYSTEM</small>
    </div>
</div>

    <small class="navtitle">WORKSPACE</small>

    <nav>
        <?php foreach ($labels as $key => $label): ?>

            <?php
if ($key === 'accounts' && !can_create_accounts()) {
    continue;
}

if ($key === 'service_management' && !admin()) {
    continue;
}
?>

           <a
    href="?page=<?= esc($key) ?>"
    class="<?= $page === $key ? 'active' : '' ?>"
>
    <?= esc($label) ?>

    <?php if ($key === 'notifications' && $unreadNotifications > 0): ?>
        <span class="notification-dot"></span>
    <?php endif; ?>
</a>
        <?php endforeach; ?>
    </nav>

    <!-- ACCOUNT INFORMATION AND LOGOUT -->
    <div class="sidebar-bottom">
        <b><?= esc($_SESSION['user']['name']) ?></b>

        <small>
            <?= esc(ucfirst($_SESSION['user']['role'])) ?>
        </small>

        <a
            href="logout.php"
            class="logout-btn"
            onclick="return confirm('Are you sure you want to log out?');"
        >
            ↪ Log out
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">

    <header>
        <small>DENTAL CLINIC MANAGEMENT</small>

        <h1><?= esc($labels[$page]) ?></h1>

        <span><?= date('D, M j, Y') ?></span>
    </header>

    <?php if (isset($_SESSION['notice'])): ?>
    <div class="success">
        <?= esc($_SESSION['notice']) ?>
    </div>
    <?php unset($_SESSION['notice']); ?>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error">
        <?= esc($error) ?>
    </div>

    <?php if (strpos($error, 'DOUBLE BOOKING WARNING') !== false): ?>
        <script>
            alert(<?= json_encode(
                $error,
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) ?>);
        </script>
    <?php endif; ?>

<?php endif; ?>

    <!-- DASHBOARD -->
    <?php if ($page === 'service_management'): ?>

    <?php payment_render_services(); ?>

<?php elseif ($page === 'payment_management'): ?>

<?php ar_render_payment_management(); ?>

<?php elseif ($page === 'dashboard'): ?>

        <div class="section-head">
            <div>
                <h2>
                    Good day,
                    <?= esc(explode(' ', $_SESSION['user']['name'])[0]) ?>!
                </h2>

                <p>Here's what's happening at your clinic today.</p>
            </div>

            <a class="button" href="?page=appointments">
                + New appointment
            </a>
        </div>

        <div class="stats">
            <div class="card">
                <small>Total patients</small>
                <h1><?= count($patients) ?></h1>
                <p>Registered patients</p>
            </div>

            <div class="card">
                <small>Today's appointments</small>
                <h1><?= $todayCount ?></h1>
                <p>Scheduled for today</p>
            </div>

            <div class="card">
                <small>Upcoming</small>
                <h1><?= count($upcomingAppointments) ?></h1>
                <p>Active appointments</p>
            </div>

            <div class="card">
                <small>Completed</small>
                <h1><?= $completedCount ?></h1>
                <p>Appointments completed</p>
            </div>
        </div>

        <div class="card">
            <h2>Upcoming appointments</h2>

            <?php if (empty($upcomingAppointments)): ?>
                <p>No upcoming appointments recorded.</p>
            <?php else: ?>
                <?php foreach (array_slice($upcomingAppointments, 0, 8) as $a): ?>
                    <p>
                        <?= esc(
                            $a['appointment_date'] . ' ' .
                            substr($a['start_time'], 0, 5) . ' — ' .
                            $a['patient_name'] . ' (' . $a['status'] . ')'
                        ) ?>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <!-- PATIENTS -->
    <?php elseif ($page === 'patients'): ?>

        <div class="section-head">
            <div>
                <h2>Patient Records</h2>
                <p>Register and manage clinic patient information.</p>
            </div>
        </div>

        <div class="card">
            <h3>Add patient</h3>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="add_patient">

                <label>
                    Full name
                    <input name="name" required>
                </label>

                <label>
                    Contact number
                    <input name="contact">
                </label>

                <label>
                    Email Address (Optional)
                    <input 
                    type="email" 
                    name="email"
                    placeholder="Leave Blank if not applicable">
                </label>

                <label>
                    Birth date
                    <input type="date" name="birth_date">
                </label>

                <label class="wide">
                    Address
                    <input name="address">
                </label>

                <label class="wide">
                    Notes
                    <textarea name="notes"></textarea>
                </label>

                <button type="submit">Add patient</button>
            </form>
        </div>

        
<div class="card">
    <h3>Patients (<?= count($patients) ?>)</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Age</th>
                    <th>Birthday</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($patients as $p): ?>
                    <?php
                    $age = '—';

                    if (!empty($p['birth_date'])) {
                        $birthday = new DateTimeImmutable(
                            $p['birth_date']
                        );
                        $todayDate = new DateTimeImmutable('today');

                        if ($birthday <= $todayDate) {
                            $age = $birthday->diff($todayDate)->y;
                        }
                    }
                    ?>

                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><?= esc($p['full_name']) ?></td>
                        <td><?= esc($age) ?></td>
                        <td>
                            <?= esc($p['birth_date'] ?: '—') ?>
                        </td>
                        <td><?= esc($p['contact'] ?: '—') ?></td>
                        <td><?= esc($p['email'] ?: '—') ?></td>
                        <td><?= esc($p['address'] ?: '—') ?></td>

                        <td>
                            <div class="patient-actions">

                                <button
                                    type="button"
                                    onclick="openPatientModal(<?= (int)$p['id'] ?>, 'edit')"
                                >
                                    Edit
                                </button>

                                <?php if (admin()): ?>
                                    <form
                                        method="post"
                                        onsubmit="return confirm('Are you sure you want to delete this patient?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= esc(csrf()) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_patient"
                                        >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$p['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="muted"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="8">
                            No patients registered yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- PATIENT DETAILS / EDIT MODAL -->
<div id="patientModal" class="patient-modal" hidden>
    <div class="patient-modal-box">
        <div class="patient-modal-header">
            <h2 id="patientModalTitle">Patient Details</h2>

            <button
                type="button"
                class="muted"
                onclick="closePatientModal()"
            >
                ✕
            </button>
        </div>

        <form method="post" id="patientEditForm" class="form-grid">
            <input
                type="hidden"
                name="csrf"
                value="<?= esc(csrf()) ?>"
            >
            <input
                type="hidden"
                name="action"
                value="edit_patient"
            >
            <input
                type="hidden"
                name="id"
                id="editPatientId"
            >

            <label>
                Full Name
                <input id="editPatientName" name="name" required>
            </label>

            <label>
                Contact Number
                <input id="editPatientContact" name="contact">
            </label>

            <label>
                Email
                <input
                    type="email"
                    id="editPatientEmail"
                    name="email"
                >
            </label>

            <label>
                Birthday
                <input
                    type="date"
                    id="editPatientBirthday"
                    name="birth_date"
                >
            </label>

            <label>
                Age
                <input id="editPatientAge" readonly>
            </label>

            <label class="wide">
                Address
                <input id="editPatientAddress" name="address">
            </label>

            <label class="wide">
                Notes
                <textarea
                    id="editPatientNotes"
                    name="notes"
                ></textarea>
            </label>

            <div class="wide patient-modal-actions">
                <button
                    type="button"
                    class="muted"
                    onclick="closePatientModal()"
                >
                    Close
                </button>

                <button type="submit" id="savePatientButton">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const patientData = <?= json_encode(
    array_column($patients, null, 'id'),
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;

function calculatePatientAge(birthDate) {
    if (!birthDate) return '';

    const birthday = new Date(birthDate + 'T00:00:00');
    const today = new Date();

    let age = today.getFullYear() - birthday.getFullYear();

    const monthDifference =
        today.getMonth() - birthday.getMonth();

    if (
        monthDifference < 0 ||
        (
            monthDifference === 0 &&
            today.getDate() < birthday.getDate()
        )
    ) {
        age--;
    }

    return age >= 0 ? age : '';
}

function openPatientModal(id, mode) {
    const patient = patientData[id];

    if (!patient) return;

    const modal = document.getElementById('patientModal');
    const form = document.getElementById('patientEditForm');
    const viewOnly = mode === 'view';

    document.getElementById('patientModalTitle').textContent =
        viewOnly ? 'Patient Information' : 'Edit Patient';

    document.getElementById('editPatientId').value = patient.id;
    document.getElementById('editPatientName').value =
        patient.full_name || '';
    document.getElementById('editPatientContact').value =
        patient.contact || '';
    document.getElementById('editPatientEmail').value =
        patient.email || '';
    document.getElementById('editPatientBirthday').value =
        patient.birth_date || '';
    document.getElementById('editPatientAge').value =
        calculatePatientAge(patient.birth_date);
    document.getElementById('editPatientAddress').value =
        patient.address || '';
    document.getElementById('editPatientNotes').value =
        patient.notes || '';

    form.querySelectorAll(
        'input:not([type="hidden"]), textarea'
    ).forEach(field => {
        field.readOnly = viewOnly ||
            field.id === 'editPatientAge';
    });

    document.getElementById('savePatientButton').hidden =
        viewOnly;

    modal.hidden = false;
}

function closePatientModal() {
    document.getElementById('patientModal').hidden = true;
}

document.getElementById('editPatientBirthday')
    .addEventListener('change', function () {
        document.getElementById('editPatientAge').value =
            calculatePatientAge(this.value);
    });

document.getElementById('patientModal')
    .addEventListener('click', function (event) {
        if (event.target === this) {
            closePatientModal();
        }
    });

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePatientModal();
    }
});
</script>


    <!-- APPOINTMENTS -->
    <?php elseif ($page === 'appointments'): ?>

        <h2>Appointments</h2>
        <p>Record, confirm, and update appointments.</p>

        <div class="card">
            <h3>New appointment</h3>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="add_appointment">

                <label>
                    Patient
                    <select name="patient_id" required>
                        <option value="">Select patient</option>

                        <?php foreach ($patients as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= esc($p['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                
<label>
    Dental Service / Treatment

    <select name="service" id="dentalService" required>
        <option value="">Select dental service</option>
        <option value="Dental Checkup / Consultation">
            Dental Checkup / Consultation
        </option>
        <option value="Teeth Cleaning">
            Teeth Cleaning (Oral Prophylaxis)
        </option>
        <option value="Tooth Extraction">
            Tooth Extraction
        </option>
        <option value="Dental Filling">
            Dental Filling (Pasta)
        </option>
        <option value="Braces / Orthodontics">
            Braces / Orthodontics
        </option>
        <option value="Teeth Whitening">
            Teeth Whitening
        </option>
        <option value="Root Canal Treatment">
            Root Canal Treatment
        </option>
        <option value="Dentures">
            Dentures
        </option>
        <option value="Dental Crown / Bridge">
            Dental Crown / Bridge
        </option>
        <option value="Other Treatment">
            Other Treatment
        </option>
    </select>
</label>


                <label>
                    Date
                    <input
                        type="date"
                        name="date"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >
                </label>

                <label>
                    Start time
                    <input type="time" name="start" required>
                </label>

                <label>
                    End time
                    <input type="time" name="end" required>
                </label>

                <label class="wide">
                    Notes
                    <textarea name="notes"></textarea>
                </label>

                <button type="submit">Save appointment</button>
            </form>
        </div>

        <div class="card">
            <h3>All appointments</h3>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>

                    <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td><?= esc($a['patient_name']) ?></td>
                            <td><?= esc($a['appointment_date']) ?></td>

                            <td>
                                <?= esc(
                                    substr($a['start_time'], 0, 5) . '–' .
                                    substr($a['end_time'], 0, 5)
                                ) ?>
                            </td>

                            <td><?= esc($a['service']) ?></td>
                            <td>
    <?php if (
        !empty($a['arrived_at']) &&
        in_array($a['status'], ['Pending', 'Confirmed', 'Rescheduled'], true)
    ): ?>
        <strong style="color:#16803c;">Checked In</strong>
    <?php else: ?>
        <?= esc($a['status']) ?>
    <?php endif; ?>
</td>

                            <!-- UPDATE STATUS -->


<td>
    <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= esc(csrf()) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

        <select name="appointment_action" required>
            <?php if (
                in_array($a['status'], ['Pending', 'Confirmed', 'Rescheduled'], true)
                && empty($a['arrived_at'])
            ): ?>
                <option value="check_in">Check In</option>
            <?php endif; ?>

            <?php foreach ($statuses as $s): ?>
                <option value="<?= esc($s) ?>"
                    <?= $a['status'] === $s ? 'selected' : '' ?>>
                    <?= esc($s) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="action" value="update_appointment_action">
            Save
        </button>
    </form>
</td>               
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

    <!-- CALENDAR -->
    <?php elseif ($page === 'calendar'): ?>

        <h2>Calendar & Schedule</h2>
        <p>Review appointments by date.</p>

        <div class="card">
            <div class="section-head">
                <button type="button" class="muted" onclick="shiftMonth(-1)">
                    ←
                </button>

                <h2 id="monthLabel"></h2>

                <button type="button" class="muted" onclick="shiftMonth(1)">
                    →
                </button>
            </div>

            <div id="calendar" class="calendar"></div>

            <h3 id="selectedLabel"></h3>
            <div id="dayEvents"></div>
        </div>

        <script>
            const events = <?= json_encode(
    array_map(
        fn($a) => [
            'date' => $a['appointment_date'],
            'patient' => $a['patient_name'],
            'service' => $a['service'],
            'start' => substr($a['start_time'], 0, 5),
            'end' => substr($a['end_time'], 0, 5),
            'status' => $a['status'],
            'notes' => $a['notes'] ?? ''
        ],
        $appointments
    ),
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT |
    JSON_INVALID_UTF8_SUBSTITUTE
) ?>;
            let month = new Date();
            let selected = [
                month.getFullYear(),
                String(month.getMonth() + 1).padStart(2, '0'),
                String(month.getDate()).padStart(2, '0')
            ].join('-');

            function shiftMonth(n) {
                month.setDate(1);
                month.setMonth(month.getMonth() + n);
                renderCal();
            }

            function chooseDay(day) {
                selected = day;
                renderCal();
            }

            function renderCal() {
                const year = month.getFullYear();
                const monthIndex = month.getMonth();

                document.getElementById('monthLabel').textContent =
                    month.toLocaleString('en', {
                        month: 'long',
                        year: 'numeric'
                    });

                const calendar = document.getElementById('calendar');
                calendar.replaceChildren();

                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
                    .forEach(name => {
                        const label = document.createElement('b');
                        label.textContent = name;
                        calendar.appendChild(label);
                    });

                const first = new Date(year, monthIndex, 1).getDay();
                const last = new Date(year, monthIndex + 1, 0).getDate();

                for (let i = 0; i < first; i++) {
                    calendar.appendChild(document.createElement('div'));
                }

                for (let d = 1; d <= last; d++) {
                    const day = [
                        year,
                        String(monthIndex + 1).padStart(2, '0'),
                        String(d).padStart(2, '0')
                    ].join('-');

                    
                    const count = events.filter(e =>
                        e.date === day &&
                        ['Pending', 'Confirmed', 'Rescheduled'].includes(e.status)
                    ).length;

                    const button = document.createElement('button');

                    button.type = 'button';
                    button.className = 'day' + (
                        day === selected ? ' chosen' : ''
                    );
                    button.textContent = d;

                    if (count > 0) {
                        const dot = document.createElement('span');
                        dot.className = 'calendar-appointment-dot';
                        button.appendChild(dot);
                    }


                    

button.addEventListener('click', () => {
    chooseDay(day);
    openCalendarAppointment(day);
});

calendar.appendChild(button);
}

document.getElementById('selectedLabel').textContent =
    'Appointments on ' + selected;

const items = events.filter(e => e.date === selected);

document.getElementById('dayEvents').textContent =
    items.length
        ? items.map(e =>
            e.patient + ' — ' + e.start + ' (' + e.status + ')'
          ).join(' | ')
        : 'No appointments for this date.';
}


            
function openCalendarAppointment(date) {
    const appointments = events.filter(e => e.date === date);
    const modal = document.getElementById('calendarAppointmentModal');
    const title = document.getElementById('calendarModalTitle');
    const content = document.getElementById('calendarModalContent');

    title.textContent = 'Appointments on ' + date;
    content.replaceChildren();

    if (appointments.length === 0) {
        const empty = document.createElement('p');
        empty.textContent = 'No appointments for this date.';
        content.appendChild(empty);
    }

    appointments.forEach(a => {
        const card = document.createElement('div');
        card.className = 'calendar-appointment-card';

        const name = document.createElement('h3');
        name.textContent = a.patient;

        const service = document.createElement('p');
        service.textContent = 'Service: ' + a.service;

        const time = document.createElement('p');
        time.textContent = 'Time: ' + a.start + ' - ' + a.end;

        const status = document.createElement('p');
        status.textContent = 'Status: ' + a.status;

        const notes = document.createElement('p');
        notes.textContent = 'Notes: ' + (a.notes || 'None');

        card.append(name, service, time, status, notes);
        content.appendChild(card);
    });

    modal.hidden = false;
}

function closeCalendarAppointment() {
    document.getElementById('calendarAppointmentModal').hidden = true;
}

            renderCal();

        </script>

<!-- CALENDAR APPOINTMENT POPUP -->
<div id="calendarAppointmentModal" class="calendar-modal" hidden>
    <div class="calendar-modal-box"
         role="dialog"
         aria-modal="true"
         aria-labelledby="calendarModalTitle">

        <div class="calendar-modal-header">
            <h2 id="calendarModalTitle">Appointment Details</h2>

            <button type="button"
                    class="muted"
                    onclick="closeCalendarAppointment()">
                ✕
            </button>
        </div>

        <div id="calendarModalContent"></div>

        <div class="calendar-modal-footer">
            <button type="button"
                    class="muted"
                    onclick="closeCalendarAppointment()">
                Close
            </button>
        </div>
    </div>
</div>

    <!-- APPOINTMENT HISTORY -->
    <?php elseif ($page === 'history'): ?>

        <h2>Appointment History</h2>
        <p>Review previous status changes.</p>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Patient</th>
                        <th>Previous</th>
                        <th>New status</th>
                        <th>Changed at</th>
                    </tr>

                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= esc($h['patient_name']) ?></td>
                            <td><?= esc($h['old_status']) ?></td>
                            <td><?= esc($h['new_status']) ?></td>
                            <td><?= esc($h['changed_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    
    <!-- NOTIFICATIONS -->    
        <?php elseif ($page === 'notifications'): ?>

<?php
$userId = (int)$_SESSION['user']['id'];

$notifications = rows(
    "SELECT *
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 100",
    "i",
    [$userId]
);

$unread = one(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id = ? AND is_read = 0",
    "i",
    [$userId]
);

$unreadCount = (int)($unread['total'] ?? 0);
?>

<div class="section-head">
    <div>
        <h2>Notifications</h2>
        <p>Stay updated with clinic activities.</p>
    </div>
</div>

<div class="card">

    <div class="notification-header">
        <h3>
            Notifications
            (<?= $unreadCount ?> unread)
        </h3>

        <?php if ($unreadCount > 0): ?>
            <form method="post">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= esc(csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="read_all_notifications"
                >

                <button type="submit">
                    Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>

        <p>No notifications yet.</p>

    <?php else: ?>

        <?php foreach ($notifications as $n): ?>

            <div class="notification-item <?= $n['is_read'] ? 'read' : 'unread' ?>">

                <div>
                    <h4>
                        <?= esc($n['title']) ?>
                    </h4>

                    <p>
                        <?= esc($n['message']) ?>
                    </p>

                    <small>
                        <?= esc($n['created_at']) ?>
                    </small>
                </div>

                <?php if (!$n['is_read']): ?>

                    <form method="post">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= esc(csrf()) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="read_notification"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int)$n['id'] ?>"
                        >

                        <button type="submit">
                            Mark as Read
                        </button>
                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
    <!-- REPORTS -->
    <?php elseif ($page === 'reports'): ?>

        <?php
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        $report = array_filter(
            $appointments,
            fn($a) =>
                $a['appointment_date'] >= $from &&
                $a['appointment_date'] <= $to
        );

        $counts = array_count_values(array_column($report, 'status'));
        ?>

        <div class="section-head">
            <div>
                <h2>Reports</h2>
                <p>Appointment totals for a selected period.</p>
            </div>

            <button
                type="button"
                class="muted"
                onclick="window.print()"
            >
                Print report
            </button>
        </div>

        <div class="card">
            <form method="get" class="inline">
                <input type="hidden" name="page" value="reports">

                <label>
                    From
                    <input type="date" name="from" value="<?= esc($from) ?>">
                </label>

                <label>
                    To
                    <input type="date" name="to" value="<?= esc($to) ?>">
                </label>

                <button type="submit">Generate</button>
            </form>
        </div>

        <div class="stats">
            <div class="card">
                <small>Total appointments</small>
                <h1><?= count($report) ?></h1>
            </div>

            <div class="card">
                <small>Completed</small>
                <h1><?= $counts['Completed'] ?? 0 ?></h1>
            </div>
        </div>

        <div class="card">
            <h3>Appointment status</h3>

            <?php foreach ($statuses as $s): ?>
                <p>
                    <?= esc($s) ?>:
                    <?= $counts[$s] ?? 0 ?>
                </p>
            <?php endforeach; ?>
        </div>

    <!-- CLINIC SETTINGS -->
    <?php elseif ($page === 'settings'): ?>

        <h2>Clinic Settings</h2>
        <p>Update your clinic details and operating hours.</p>

        <div class="card">
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="settings">

                <?php $disabled = admin() ? '' : 'disabled'; ?>

                <label>
                    Clinic name
                    <input
                        name="clinic_name"
                        value="<?= esc($settings['clinic_name']) ?>"
                        <?= $disabled ?>
                        required
                    >
                </label>

                <label>
                    Contact number
                    <input
                        name="contact"
                        value="<?= esc($settings['contact']) ?>"
                        <?= $disabled ?>
                    >
                </label>

                <label class="wide">
                    Clinic address
                    <input
                        name="address"
                        value="<?= esc($settings['address']) ?>"
                        <?= $disabled ?>
                    >
                </label>

                <label>
                    Opening time
                    <input
                        type="time"
                        name="opening"
                        value="<?= esc(substr($settings['opening_time'], 0, 5)) ?>"
                        <?= $disabled ?>
                    >
                </label>

                <label>
                    Closing time
                    <input
                        type="time"
                        name="closing"
                        value="<?= esc(substr($settings['closing_time'], 0, 5)) ?>"
                        <?= $disabled ?>
                    >
                </label>

                <label>
                    Default appointment duration (minutes)
                    <input
                        type="number"
                        min="5"
                        max="480"
                        name="duration"
                        value="<?= (int)$settings['duration_minutes'] ?>"
                        <?= $disabled ?>
                    >
                </label>

                <?php if (admin()): ?>
                    <div class="wide">
                        <button type="submit">Save settings</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

    <!-- ADMIN AND STAFF ACCOUNTS -->
    <?php elseif ($page === 'accounts' && can_create_accounts()): ?>

        <h2>Admin & Staff Registration</h2>
        <p>Only administrators can create accounts.</p>

        <div class="card">
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="account">

                <label>
                    Full name
                    <input type="text" name="full_name" required>
                </label>

                <label>
                    Username
                    <input name="username" required>
                </label>

                <label>
                    Password
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        required
                    >
                </label>

                <label>
                    Account role
                    <select name="role">
                        <option value="staff">Staff</option>
                        <option value="admin">Administrator</option>
                    </select>
                </label>

                <button type="submit">Create account</button>
            </form>
        </div>

        <div class="card">
            <h3>Registered accounts</h3>

            <?php
            $users = rows(
                'SELECT id, full_name, username, role
                 FROM users ORDER BY id DESC'
            );
            ?>

            <?php foreach ($users as $u): ?>
                <p>
                    <?= esc(
                        $u['full_name'] . ' — @' .
                        $u['username'] . ' (' . $u['role'] . ')'
                    ) ?>
                </p>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</main>

</body>
</html>
