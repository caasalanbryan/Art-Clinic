
<?php
require 'config.php';

// Redirect if already logged in
if (logged_in()) {
    go();
}

// Redirect to setup if no users exist
$userCount = one('SELECT COUNT(*) AS n FROM users');

if (!(int)$userCount['n']) {
    go('setup.php');
}

$error = '';

// HANDLE LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_post();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = one(
        'SELECT * FROM users WHERE username = ?',
        's',
        [$username]
    );

    if (
        $user &&
        password_verify($password, $user['password_hash'])
    ) {

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'   => $user['id'],
            'name' => $user['full_name'],
            'role' => $user['role']
        ];

        go();
    }

    $error = 'Invalid username or password.';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | ART CARE</title>

    <link rel="stylesheet" href="style.css">

    <style>
        /* ART CARE - PASTEL YELLOW LOGIN THEME */

        body.auth {
            background: #F5D76E !important;
        }

        /* LEFT LOGIN PANEL */
        body.auth .hero {
            background: #F5D76E !important;
            color: #3D3525 !important;
        }

        body.auth .hero h1,
        body.auth .hero p,
        body.auth .hero small,
        body.auth .hero .brand {
            color: #3D3525 !important;
        }

        /* RIGHT LOGIN PANEL */
        body.auth .auth-form {
            background: #FFFDF2 !important;
        }

        body.auth .auth-form h1 {
            color: #3D3525 !important;
        }

        /* LOGIN BUTTON */
        body.auth .auth-form button {
            background: #F5D76E !important;
            color: #3D3525 !important;
            border-color: #F5D76E !important;
        }

        body.auth .auth-form button:hover {
            background: #E8C65B !important;
        }

        /* INPUT FOCUS */
        body.auth input:focus {
            border-color: #F5D76E !important;
            outline-color: #F5D76E !important;
        }
    </style>
</head>

<body class="auth">

    <!-- LEFT SIDE: BRANDING -->
    <div class="hero">

        <div class="brand">
            ✦ ART CARE

            <small>
                DENTAL CARE CLINIC MANAGEMENT SYSTEM
            </small>
        </div>

        <small>WELCOME BACK</small>

        <h1>
            A healthier smile starts here.
        </h1>

        <p>
            Simple, organized, and reliable
            dental clinic scheduling.
        </p>

    </div>

    <!-- RIGHT SIDE: LOGIN FORM -->
    <main class="auth-form">

        <small>STAFF PORTAL</small>

        <h1>Log in to your account</h1>

        <p>
            Enter your username and password to continue.
        </p>

        <!-- ERROR MESSAGE -->
        <?php if ($error): ?>

            <p class="error">
                <?= esc($error) ?>
            </p>

        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form method="post">

            <input
                type="hidden"
                name="csrf"
                value="<?= csrf() ?>"
            >

            <label>
                Username

                <input
                    type="text"
                    name="username"
                    autocomplete="username"
                    required
                >
            </label>

            <label>
                Password

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <button type="submit">
                Log in
            </button>

        </form>

        <p>
            New accounts are created by the administrator.
        </p>

    </main>

</body>
</html>
