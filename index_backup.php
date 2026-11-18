
<?php
require 'config.php';
require_login();

$page = $_GET['page'] ?? 'dashboard';



$labels = [
    'dashboard' => 'Dashboard',
    'patients' => 'Patients',
    'appointments' => 'Appointments',
    'calendar' => 'Calendar & Schedule',
    'attendance' => 'Attendance',
    'history' => 'Appointment History',
    'services' => 'Dental Services',
    'reminders' => 'Appointment Reminders',
    'reports' => 'Reports',
    'settings' => 'Clinic Settings',
    'accounts' => 'Accounts'
];



if (!isset($labels[$page])) {
    $page = 'dashboard';
}

if ($page === 'accounts' && !admin()) {
    $page = 'dashboard';
}

$error = '';
$statuses = [
    'Pending', 'Confirmed', 'Rescheduled',
    'Completed', 'Cancelled', 'No-Show'
];

// HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_post();
        $action = $_POST['action'] ?? '';

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

        // DELETE PATIENT
        if ($action === 'delete_patient') {
            if (!admin()) {
                throw new Exception('Administrator only.');
            }

            stmt(
                'DELETE FROM patients WHERE id=?',
                'i',
                [(int)($_POST['id'] ?? 0)]
            );

            notice('Patient deleted.');
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
                        'Time conflict! Another appointment already exists.'
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

            notice('Appointment created successfully.');
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
                'SELECT status FROM appointments WHERE id=?',
                'i',
                [$id]
            );

            if (!$old) {
                throw new Exception('Appointment not found.');
            }

            if ($old['status'] !== $status) {
                stmt(
                    'UPDATE appointments SET status=? WHERE id=?',
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
                        $old['status'],
                        $status,
                        $_SESSION['user']['id']
                    ]
                );
            }

            notice('Appointment updated.');
            go('?page=appointments');
        }

        // CLINIC SETTINGS
        if ($action === 'settings') {
            if (!admin()) {
                throw new Exception('Administrator only.');
            }

            $open = $_POST['opening'] ?? '';
            $close = $_POST['closing'] ?? '';
            $duration = (int)($_POST['duration'] ?? 0);

            if (
                !preg_match('/^\d{2}:\d{2}$/', $open) ||
                !preg_match('/^\d{2}:\d{2}$/', $close) ||
                $close <= $open ||
                $duration < 5 ||
                $duration > 480
            ) {
                throw new Exception('Invalid clinic settings.');
            }

            stmt(
                "UPDATE clinic_settings SET
                clinic_name=?, contact=?, address=?,
                opening_time=?, closing_time=?, duration_minutes=?
                WHERE id=1",
                'sssssi',
                [
                    trim($_POST['clinic_name'] ?? ''),
                    trim($_POST['contact'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    $open,
                    $close,
                    $duration
                ]
            );

            notice('Clinic settings saved.');
            go('?page=settings');
        }

        // CREATE ADMIN OR STAFF ACCOUNT
        if ($action === 'account') {
            if (!admin()) {
                throw new Exception('Administrator only.');
            }

            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if (
                $name === '' ||
                $username === '' ||
                strlen($password) < 8 ||
                !in_array($role, ['admin', 'staff'], true)
            ) {
                throw new Exception(
                    'Invalid account details. Password needs 8+ characters.'
                );
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

    } catch (Throwable $e) {
        $error = $e instanceof mysqli_sql_exception
            ? 'Database operation failed. Check for duplicate or linked records.'
            : $e->getMessage();
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
    <div class="brand">
        ✦ &nbsp; ART CARE
        <small>DENTAL CLINIC SYSTEM</small>
    </div>

    <small class="navtitle">WORKSPACE</small>

   
<nav>
    <?php
    $sidebarMenu = [
        'dashboard' => 'Dashboard',
        'patients' => 'Patients',
        'appointments' => 'Appointments',
        'calendar' => 'Calendar & Schedule',
        'attendance' => 'Attendance',
        'history' => 'Appointment History',
        'services' => 'Dental Services',
        'reminders' => 'Appointment Reminders',
        'reports' => 'Reports',
        'settings' => 'Clinic Settings',
        'accounts' => 'Accounts'
    ];

    foreach ($sidebarMenu as $key => $label):
        if ($key === 'accounts' && !admin()) continue;
    ?>
        <a
            href="?page=<?= esc($key) ?>"
            class="<?= $page === $key ? 'active' : '' ?>"
        >
            <?= esc($label) ?>
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
    <?php endif; ?>

    <!-- DASHBOARD -->
    <?php if ($page === 'dashboard'): ?>

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
                    Email
                    <input type="email" name="email">
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
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <?php if (admin()): ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>

                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><?= esc($p['full_name']) ?></td>
                            <td><?= esc($p['contact']) ?></td>
                            <td><?= esc($p['email']) ?></td>

                            <?php if (admin()): ?>
                                <td>
                                    <form
                                        method="post"
                                        onsubmit="return confirm('Delete this patient?');"
                                    >
                                        <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                        <input type="hidden" name="action" value="delete_patient">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                                        <button class="muted">Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

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
                            <td><?= esc($a['status']) ?></td>

                            <td>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

                                    <select name="status">
                                        <?php foreach ($statuses as $s): ?>
                                            <option
                                                value="<?= esc($s) ?>"
                                                <?= $a['status'] === $s ? 'selected' : '' ?>
                                            >
                                                <?= esc($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
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
                        'text' => $a['patient_name'] . ' — ' .
                            substr($a['start_time'], 0, 5) . ' ' .
                            $a['status']
                    ],
                    $appointments
                ),
                JSON_HEX_TAG | JSON_HEX_AMP |
                JSON_HEX_APOS | JSON_HEX_QUOT
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

                    const count = events.filter(e => e.date === day).length;
                    const button = document.createElement('button');

                    button.type = 'button';
                    button.className = 'day' + (
                        day === selected ? ' chosen' : ''
                    );
                    button.textContent = d;

                    if (count > 0) {
                        const small = document.createElement('small');
                        small.textContent = count + ' appointment(s)';
                        button.appendChild(small);
                    }

                    button.addEventListener('click', () => chooseDay(day));
                    calendar.appendChild(button);
                }

                document.getElementById('selectedLabel').textContent =
                    'Appointments on ' + selected;

                const items = events.filter(e => e.date === selected);

                document.getElementById('dayEvents').textContent =
                    items.length
                        ? items.map(e => e.text).join(' | ')
                        : 'No appointments for this date.';
            }

            renderCal();
        </script>

    <!-- ATTENDANCE -->
    <?php elseif ($page === 'attendance'): ?>

        <h2>Attendance Monitoring</h2>
        <p>Mark appointments as completed or no-show.</p>

        <div class="card">
            <form method="get">
                <input type="hidden" name="page" value="attendance">

                <input
                    type="date"
                    name="date"
                    value="<?= esc($_GET['date'] ?? date('Y-m-d')) ?>"
                >

                <button type="submit">Filter</button>
            </form>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Patient</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                    <?php foreach ($appointments as $a): ?>
                        <?php
                        if (
                            $a['appointment_date'] !==
                            ($_GET['date'] ?? date('Y-m-d'))
                        ) {
                            continue;
                        }
                        ?>

                        <tr>
                            <td><?= esc($a['patient_name']) ?></td>
                            <td><?= esc(substr($a['start_time'], 0, 5)) ?></td>
                            <td><?= esc($a['status']) ?></td>

                            <td>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

                                    <button name="status" value="Completed">
                                        Completed
                                    </button>

                                    <button
                                        class="muted"
                                        name="status"
                                        value="No-Show"
                                    >
                                        No-show
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
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
    <?php elseif ($page === 'accounts' && admin()): ?>

        <h2>Admin & Staff Registration</h2>
        <p>Only administrators can create accounts.</p>

        <div class="card">
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="account">

                <label>
                    Full name
                    <input name="name" required>
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
