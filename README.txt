ART CARE Dental Clinic Management System — XAMPP

1. Start Apache and MySQL in XAMPP Control Panel.
2. Copy the artcare_xampp folder into C:\xampp\htdocs\artcare
3. Open http://localhost/phpmyadmin
4. Click Import and choose database.sql from this folder, then click Go.
5. Open http://localhost/artcare/setup.php
6. Create the first administrator (at least 8 character password).
7. Log in at http://localhost/artcare/login.php
8. As administrator, open Accounts to create separate Staff or additional Admin accounts.

The application uses PHP + MySQL, NOT Node.js. Do not open via VS Code Live Server or port 3000.
If your MySQL root password is not blank, update config.php.

Included: dashboard, patient registration/list/deletion (admin), appointments with overlap protection, status updates, attendance, calendar, appointment status history, date-range reports, clinic settings, role-controlled account creation, session login/logout, CSRF protection and password hashing.

Important limitations: This is a starter system, not a production-ready medical records application. Add access logs, HTTPS, backup/recovery, stronger privacy controls, input validation, account recovery, and secure deployment before handling real patient data. Patient editing, appointment rescheduling and report new-patient counts are not implemented in this starter.
