USE artcare_db;
-- New, namespaced tables avoid conflicts with tables from an earlier upgrade.sql.
CREATE TABLE IF NOT EXISTS artcare_clinical_records (
 id INT AUTO_INCREMENT PRIMARY KEY,
 patient_id INT NOT NULL,
 appointment_id INT NULL,
 service_name VARCHAR(150) NOT NULL,
 dentist_name VARCHAR(150) NOT NULL,
 treatment_date DATE NOT NULL,
 tooth_number VARCHAR(30) NULL,
 diagnosis TEXT NOT NULL,
 treatment_notes TEXT NOT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
 FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX (patient_id, treatment_date)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS artcare_invoices (
 id INT AUTO_INCREMENT PRIMARY KEY,
 record_id INT NOT NULL UNIQUE,
 patient_id INT NOT NULL,
 total DECIMAL(12,2) NOT NULL,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (record_id) REFERENCES artcare_clinical_records(id) ON DELETE RESTRICT,
 FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 CHECK (total >= 0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS artcare_payments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 invoice_id INT NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 method ENUM('Cash','GCash','Bank Transfer') NOT NULL,
 reference_number VARCHAR(120) NULL,
 received_by INT NULL,
 paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (invoice_id) REFERENCES artcare_invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
 CHECK (amount > 0),
 INDEX (invoice_id,paid_at)
) ENGINE=InnoDB;
