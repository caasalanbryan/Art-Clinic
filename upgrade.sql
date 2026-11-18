
-- ART CARE DENTAL CLINIC MANAGEMENT SYSTEM
-- DATABASE UPGRADE
-- Compatible with MySQL / MariaDB in XAMPP
-- Preserves existing tables and records

USE artcare_db;

-- 1. DENTIST MANAGEMENT

CREATE TABLE IF NOT EXISTS dentists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    specialization VARCHAR(120),
    contact VARCHAR(40),
    email VARCHAR(120),
    status ENUM('Active','Inactive')
        NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- 2. DENTAL SERVICE MANAGEMENT

CREATE TABLE IF NOT EXISTS dental_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_minutes INT NOT NULL DEFAULT 30,
    status ENUM('Active','Inactive')
        NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- 3. DENTAL RECORDS AND TREATMENT HISTORY

CREATE TABLE IF NOT EXISTS dental_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    dentist_id INT NULL,
    service_id INT NULL,
    tooth_number VARCHAR(30),
    diagnosis TEXT NOT NULL,
    treatment TEXT NOT NULL,
    prescription TEXT,
    treatment_date DATE NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE RESTRICT,

    FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE SET NULL,

    FOREIGN KEY (dentist_id)
        REFERENCES dentists(id)
        ON DELETE SET NULL,

    FOREIGN KEY (service_id)
        REFERENCES dental_services(id)
        ON DELETE SET NULL,

    FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 4. BILLING AND INVOICES

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    description TEXT NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    status ENUM('Unpaid','Partial','Paid','Cancelled')
        NOT NULL DEFAULT 'Unpaid',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE RESTRICT,

    FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE SET NULL,

    FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 5. PAYMENT MANAGEMENT

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM(
        'Cash',
        'GCash',
        'Bank Transfer',
        'Card'
    ) NOT NULL,
    reference_number VARCHAR(120),
    received_by INT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (invoice_id)
        REFERENCES invoices(id)
        ON DELETE RESTRICT,

    FOREIGN KEY (received_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 6. INVENTORY MANAGEMENT

CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100),
    quantity INT NOT NULL DEFAULT 0,
    minimum_quantity INT NOT NULL DEFAULT 5,
    unit VARCHAR(40) DEFAULT 'pcs',
    expiration_date DATE NULL,
    supplier VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- 7. INVENTORY TRANSACTION HISTORY

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    transaction_type ENUM(
        'Stock In',
        'Stock Out',
        'Adjustment'
    ) NOT NULL,
    quantity INT NOT NULL,
    remarks TEXT,
    performed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (inventory_id)
        REFERENCES inventory(id)
        ON DELETE RESTRICT,

    FOREIGN KEY (performed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 8. APPOINTMENT REMINDERS

CREATE TABLE IF NOT EXISTS appointment_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    reminder_type ENUM('Email','SMS','System')
        NOT NULL DEFAULT 'System',
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    status ENUM('Pending','Sent','Failed','Cancelled')
        NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- 9. AUDIT LOGS

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(150) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 10. DEFAULT DENTAL SERVICES

INSERT IGNORE INTO dental_services
(service_name, description, price, duration_minutes)
VALUES
('Dental Consultation',
 'Dental examination and consultation', 300.00, 30),

('Oral Prophylaxis',
 'Professional teeth cleaning', 800.00, 45),

('Tooth Extraction',
 'Removal of damaged or decayed tooth', 1000.00, 45),

('Dental Filling',
 'Restoration of damaged teeth', 800.00, 45),

('Root Canal Treatment',
 'Treatment of infected tooth pulp', 3500.00, 90),

('Teeth Whitening',
 'Cosmetic teeth whitening procedure', 3000.00, 60),

('Dental Braces',
 'Orthodontic treatment', 5000.00, 60),

('Dentures',
 'Replacement of missing teeth', 4500.00, 60),

('Dental X-Ray',
 'Dental radiographic examination', 500.00, 20),

('Wisdom Tooth Extraction',
 'Removal of wisdom tooth', 3500.00, 90);


-- 11. APPOINTMENT IMPROVEMENTS
-- Run these ALTER statements only once.

ALTER TABLE appointments
ADD COLUMN dentist_id INT NULL,
ADD COLUMN service_id INT NULL;

ALTER TABLE appointments
ADD CONSTRAINT fk_appointment_dentist
FOREIGN KEY (dentist_id)
REFERENCES dentists(id)
ON DELETE SET NULL;

ALTER TABLE appointments
ADD CONSTRAINT fk_appointment_service
FOREIGN KEY (service_id)
REFERENCES dental_services(id)
ON DELETE SET NULL;

CREATE INDEX idx_dentist_schedule
ON appointments (
    dentist_id,
    appointment_date,
    start_time,
    end_time
);
