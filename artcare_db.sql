-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2026 at 04:37 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `artcare_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `service` varchar(120) NOT NULL,
  `status` enum('Pending','Confirmed','Rescheduled','Completed','Cancelled','No-Show') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `dentist_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `appointment_date`, `start_time`, `end_time`, `service`, `status`, `notes`, `created_by`, `created_at`, `dentist_id`, `service_id`, `arrived_at`) VALUES
(6, 2, '2026-11-19', '14:30:00', '16:00:00', 'Tooth Extraction', 'Rescheduled', '', 2, '2026-11-18 16:16:19', NULL, NULL, NULL),
(7, 6, '2026-11-20', '09:00:00', '11:00:00', 'Dental Filling', 'Confirmed', '', 1, '2026-11-19 09:30:33', NULL, NULL, '2026-11-19 23:10:56'),
(8, 3, '2026-11-20', '15:20:00', '17:00:00', 'Teeth Cleaning', 'Completed', '', 1, '2026-11-19 12:10:07', NULL, NULL, '2026-11-19 23:10:34'),
(9, 1, '2026-11-26', '10:30:00', '12:00:00', 'Teeth Whitening', 'Confirmed', '', 1, '2026-11-19 12:33:22', NULL, NULL, '2026-11-19 22:59:27'),
(10, 2, '2026-11-26', '13:30:00', '14:30:00', 'Teeth Cleaning', 'Rescheduled', '', 1, '2026-11-19 14:20:05', NULL, NULL, '2026-11-19 22:58:53');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_history`
--

CREATE TABLE `appointment_history` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_history`
--

INSERT INTO `appointment_history` (`id`, `appointment_id`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(8, 6, 'Pending', 'Confirmed', 2, '2026-11-18 16:35:09'),
(9, 8, 'Pending', 'Confirmed', 1, '2026-11-19 12:46:51'),
(10, 7, 'Pending', 'Confirmed', 1, '2026-11-19 12:47:20'),
(11, 10, 'Pending', 'Confirmed', 1, '2026-11-19 14:20:16'),
(12, 10, 'Confirmed', 'Pending', 1, '2026-11-19 14:20:38'),
(13, 10, 'Pending', 'Confirmed', 1, '2026-11-19 14:26:38'),
(14, 6, 'Confirmed', 'Pending', 1, '2026-11-19 14:30:08'),
(15, 6, 'Pending', 'Confirmed', 1, '2026-11-19 14:30:49'),
(16, 6, 'Confirmed', 'Rescheduled', 1, '2026-11-19 14:41:26'),
(17, 9, 'Pending', 'Confirmed', 1, '2026-11-19 14:59:22'),
(18, 10, 'Confirmed', 'Rescheduled', 1, '2026-11-19 15:00:16'),
(19, 8, 'Confirmed', 'Completed', 1, '2026-11-19 15:10:46');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_reminders`
--

CREATE TABLE `appointment_reminders` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `reminder_type` enum('Email','SMS','System') NOT NULL DEFAULT 'System',
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('Pending','Sent','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `artcare_appointment_deposits`
--

CREATE TABLE `artcare_appointment_deposits` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `service_price` decimal(12,2) NOT NULL,
  `required_deposit` decimal(12,2) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('GCash','Maya','MariBank') NOT NULL,
  `reference_number` varchar(120) NOT NULL,
  `verification_status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `artcare_appointment_deposits`
--

INSERT INTO `artcare_appointment_deposits` (`id`, `appointment_id`, `service_id`, `service_name`, `service_price`, `required_deposit`, `amount`, `method`, `reference_number`, `verification_status`, `submitted_by`, `verified_by`, `submitted_at`, `verified_at`) VALUES
(1, 7, 4, 'Dental Filling', 2000.00, 1000.00, 1000.00, 'GCash', 'TEST123456', 'Verified', 1, 1, '2026-11-19 12:08:13', '2026-11-19 20:08:50'),
(2, 8, 2, 'Teeth Cleaning', 1000.00, 500.00, 500.00, 'GCash', 'TEST1236532', 'Verified', 1, 1, '2026-11-19 12:10:39', '2026-11-19 20:10:49'),
(3, 9, 6, 'Teeth Whitening', 4000.00, 2000.00, 2000.00, 'GCash', 'TEST362148', 'Verified', 1, 1, '2026-11-19 12:44:05', '2026-11-19 20:44:12'),
(4, 10, 2, 'Teeth Cleaning', 1000.00, 500.00, 500.00, 'GCash', 'TEST54265', 'Verified', 1, 1, '2026-11-19 14:21:02', '2026-11-19 22:26:10'),
(5, 6, 3, 'Tooth Extraction', 1500.00, 750.00, 750.00, 'GCash', '750', 'Verified', 1, 1, '2026-11-19 14:30:29', '2026-11-19 22:30:36');

-- --------------------------------------------------------

--
-- Table structure for table `artcare_appointment_payments`
--

CREATE TABLE `artcare_appointment_payments` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('Cash','GCash','Maya') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `payment_status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `payment_type` enum('Remaining','Additional') NOT NULL DEFAULT 'Remaining',
  `recorded_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `artcare_appointment_payments`
--

INSERT INTO `artcare_appointment_payments` (`id`, `appointment_id`, `amount`, `method`, `reference_number`, `payment_status`, `payment_type`, `recorded_by`, `verified_by`, `paid_at`, `verified_at`) VALUES
(1, 7, 1000.00, 'GCash', 'TEST123', 'Verified', 'Remaining', 1, 1, '2026-11-19 14:01:28', '2026-11-19 22:01:44'),
(2, 9, 2000.00, 'GCash', 'TEST123', 'Verified', 'Remaining', 1, 1, '2026-11-19 14:06:03', '2026-11-19 22:06:35'),
(3, 10, 500.00, 'GCash', 'TEST365213', 'Verified', 'Remaining', 1, 1, '2026-11-19 14:28:35', '2026-11-19 22:28:57'),
(4, 6, 750.00, 'GCash', 'TEST3652134', 'Verified', 'Remaining', 1, 1, '2026-11-19 14:31:09', '2026-11-19 22:31:14');

-- --------------------------------------------------------

--
-- Table structure for table `artcare_appointment_prices`
--

CREATE TABLE `artcare_appointment_prices` (
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `service_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `artcare_clinical_records`
--

CREATE TABLE `artcare_clinical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `service_name` varchar(150) NOT NULL,
  `dentist_name` varchar(150) NOT NULL,
  `treatment_date` date NOT NULL,
  `tooth_number` varchar(30) DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `treatment_notes` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `artcare_clinical_records`
--

INSERT INTO `artcare_clinical_records` (`id`, `patient_id`, `appointment_id`, `service_name`, `dentist_name`, `treatment_date`, `tooth_number`, `diagnosis`, `treatment_notes`, `created_by`, `created_at`) VALUES
(1, 1, NULL, 'Root Canal Treatment', 'Dr.Jasper P. Acero', '2026-11-17', '32', 'Teeth Canal damage', 'Root Canal Treatment Successfully', 1, '2026-11-17 20:14:26');

-- --------------------------------------------------------

--
-- Table structure for table `artcare_invoices`
--

CREATE TABLE `artcare_invoices` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `artcare_invoices`
--

INSERT INTO `artcare_invoices` (`id`, `record_id`, `patient_id`, `total`, `created_by`, `created_at`) VALUES
(1, 1, 1, 1200.00, 1, '2026-11-17 20:15:01');

-- --------------------------------------------------------

--
-- Table structure for table `artcare_payments`
--

CREATE TABLE `artcare_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('Cash','GCash','Bank Transfer') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `artcare_payments`
--

INSERT INTO `artcare_payments` (`id`, `invoice_id`, `amount`, `method`, `reference_number`, `received_by`, `paid_at`) VALUES
(1, 1, 1200.00, 'Cash', '', 1, '2026-11-17 20:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `artcare_services`
--

CREATE TABLE `artcare_services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `artcare_services`
--

INSERT INTO `artcare_services` (`id`, `name`, `price`, `is_active`, `created_at`) VALUES
(1, 'Dental Checkup / Consultation', 500.00, 1, '2026-11-19 11:37:40'),
(2, 'Teeth Cleaning', 1000.00, 1, '2026-11-19 11:37:40'),
(3, 'Tooth Extraction', 1500.00, 1, '2026-11-19 11:37:40'),
(4, 'Dental Filling', 2000.00, 1, '2026-11-19 11:37:40'),
(5, 'Braces / Orthodontics', 15000.00, 1, '2026-11-19 11:37:40'),
(6, 'Teeth Whitening', 4000.00, 1, '2026-11-19 11:37:40'),
(7, 'Root Canal Treatment', 6000.00, 1, '2026-11-19 11:37:40'),
(8, 'Dentures', 8000.00, 1, '2026-11-19 11:37:40'),
(9, 'Dental Crown / Bridge', 7000.00, 1, '2026-11-19 11:37:40'),
(10, 'Other Treatment', 1000.00, 1, '2026-11-19 11:37:40');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_settings`
--

CREATE TABLE `clinic_settings` (
  `id` tinyint(4) NOT NULL,
  `clinic_name` varchar(150) NOT NULL,
  `contact` varchar(40) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `opening_time` time NOT NULL,
  `closing_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinic_settings`
--

INSERT INTO `clinic_settings` (`id`, `clinic_name`, `contact`, `address`, `opening_time`, `closing_time`, `duration_minutes`) VALUES
(1, 'ART CARE Dental Clinic', '', '', '08:00:00', '17:00:00', 30);

-- --------------------------------------------------------

--
-- Table structure for table `dental_records`
--

CREATE TABLE `dental_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `dentist_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `tooth_number` varchar(30) DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `treatment` text NOT NULL,
  `prescription` text DEFAULT NULL,
  `treatment_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dental_services`
--

CREATE TABLE `dental_services` (
  `id` int(11) NOT NULL,
  `service_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dental_services`
--

INSERT INTO `dental_services` (`id`, `service_name`, `description`, `price`, `duration_minutes`, `status`, `created_at`) VALUES
(1, 'Dental Consultation', 'Dental examination and consultation', 300.00, 30, 'Active', '2026-11-17 19:27:58'),
(2, 'Oral Prophylaxis', 'Professional teeth cleaning', 800.00, 45, 'Active', '2026-11-17 19:27:58'),
(3, 'Tooth Extraction', 'Removal of damaged or decayed tooth', 1000.00, 45, 'Active', '2026-11-17 19:27:58'),
(4, 'Dental Filling', 'Restoration of damaged teeth', 800.00, 45, 'Active', '2026-11-17 19:27:58'),
(5, 'Root Canal Treatment', 'Treatment of infected tooth pulp', 3500.00, 90, 'Active', '2026-11-17 19:27:58'),
(6, 'Teeth Whitening', 'Cosmetic teeth whitening procedure', 3000.00, 60, 'Active', '2026-11-17 19:27:58'),
(7, 'Dental Braces', 'Orthodontic treatment', 5000.00, 60, 'Active', '2026-11-17 19:27:58'),
(8, 'Dentures', 'Replacement of missing teeth', 4500.00, 60, 'Active', '2026-11-17 19:27:58'),
(9, 'Dental X-Ray', 'Dental radiographic examination', 500.00, 20, 'Active', '2026-11-17 19:27:58'),
(10, 'Wisdom Tooth Extraction', 'Removal of wisdom tooth', 3500.00, 90, 'Active', '2026-11-17 19:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `dentists`
--

CREATE TABLE `dentists` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `specialization` varchar(120) DEFAULT NULL,
  `contact` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `minimum_quantity` int(11) NOT NULL DEFAULT 5,
  `unit` varchar(40) DEFAULT 'pcs',
  `expiration_date` date DEFAULT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `transaction_type` enum('Stock In','Stock Out','Adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` enum('Unpaid','Partial','Paid','Cancelled') NOT NULL DEFAULT 'Unpaid',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 1, '2026-11-18 16:16:19'),
(2, 2, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 1, '2026-11-18 16:16:19'),
(3, 1, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 1, '2026-11-19 09:30:33'),
(4, 2, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 09:30:33'),
(5, 1, 'Downpayment submitted', 'Appointment #7 payment requires admin verification.', 'info', 0, '2026-11-19 12:08:13'),
(6, 2, 'Downpayment submitted', 'Appointment #7 payment requires admin verification.', 'info', 0, '2026-11-19 12:08:13'),
(7, 1, 'Downpayment Verified', 'Appointment #7 payment marked Verified.', 'info', 0, '2026-11-19 12:08:50'),
(8, 2, 'Downpayment Verified', 'Appointment #7 payment marked Verified.', 'info', 0, '2026-11-19 12:08:50'),
(9, 1, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 12:10:07'),
(10, 2, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 12:10:07'),
(11, 1, 'Downpayment submitted', 'Appointment #8 payment requires admin verification.', 'info', 0, '2026-11-19 12:10:39'),
(12, 2, 'Downpayment submitted', 'Appointment #8 payment requires admin verification.', 'info', 0, '2026-11-19 12:10:39'),
(13, 1, 'Downpayment Verified', 'Appointment #8 payment marked Verified.', 'info', 0, '2026-11-19 12:10:49'),
(14, 2, 'Downpayment Verified', 'Appointment #8 payment marked Verified.', 'info', 0, '2026-11-19 12:10:49'),
(15, 1, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 12:33:22'),
(16, 2, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 12:33:22'),
(17, 1, 'Downpayment submitted', 'Appointment #9 payment requires admin verification.', 'info', 0, '2026-11-19 12:44:05'),
(18, 2, 'Downpayment submitted', 'Appointment #9 payment requires admin verification.', 'info', 0, '2026-11-19 12:44:05'),
(19, 1, 'Downpayment Verified', 'Appointment #9 payment marked Verified.', 'info', 0, '2026-11-19 12:44:12'),
(20, 2, 'Downpayment Verified', 'Appointment #9 payment marked Verified.', 'info', 0, '2026-11-19 12:44:12'),
(21, 1, 'Remaining payment submitted', 'Appointment #7 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:01:28'),
(22, 2, 'Remaining payment submitted', 'Appointment #7 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:01:28'),
(23, 1, 'Remaining payment Verified', 'Appointment #7 payment marked Verified.', 'info', 0, '2026-11-19 14:01:44'),
(24, 2, 'Remaining payment Verified', 'Appointment #7 payment marked Verified.', 'info', 0, '2026-11-19 14:01:44'),
(25, 1, 'Remaining payment submitted', 'Appointment #9 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:06:03'),
(26, 2, 'Remaining payment submitted', 'Appointment #9 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:06:03'),
(27, 1, 'Remaining payment Verified', 'Appointment #9 payment marked Verified.', 'info', 0, '2026-11-19 14:06:35'),
(28, 2, 'Remaining payment Verified', 'Appointment #9 payment marked Verified.', 'info', 0, '2026-11-19 14:06:35'),
(29, 1, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 14:20:05'),
(30, 2, 'New Appointment', 'A new dental appointment has been scheduled.', 'appointment', 0, '2026-11-19 14:20:05'),
(31, 1, 'Downpayment submitted', 'Appointment #10 payment requires admin verification.', 'info', 0, '2026-11-19 14:21:02'),
(32, 2, 'Downpayment submitted', 'Appointment #10 payment requires admin verification.', 'info', 0, '2026-11-19 14:21:02'),
(33, 1, 'Downpayment Verified', 'Appointment #10 payment marked Verified.', 'info', 0, '2026-11-19 14:26:10'),
(34, 2, 'Downpayment Verified', 'Appointment #10 payment marked Verified.', 'info', 0, '2026-11-19 14:26:10'),
(35, 1, 'Remaining payment submitted', 'Appointment #10 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:28:35'),
(36, 2, 'Remaining payment submitted', 'Appointment #10 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:28:35'),
(37, 1, 'Remaining payment Verified', 'Appointment #10 payment marked Verified.', 'info', 0, '2026-11-19 14:28:57'),
(38, 2, 'Remaining payment Verified', 'Appointment #10 payment marked Verified.', 'info', 0, '2026-11-19 14:28:57'),
(39, 1, 'Downpayment submitted', 'Appointment #6 payment requires admin verification.', 'info', 0, '2026-11-19 14:30:29'),
(40, 2, 'Downpayment submitted', 'Appointment #6 payment requires admin verification.', 'info', 0, '2026-11-19 14:30:29'),
(41, 1, 'Downpayment Verified', 'Appointment #6 payment marked Verified.', 'info', 0, '2026-11-19 14:30:36'),
(42, 2, 'Downpayment Verified', 'Appointment #6 payment marked Verified.', 'info', 0, '2026-11-19 14:30:36'),
(43, 1, 'Remaining payment submitted', 'Appointment #6 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:31:09'),
(44, 2, 'Remaining payment submitted', 'Appointment #6 has a payment awaiting admin verification.', 'info', 0, '2026-11-19 14:31:09'),
(45, 1, 'Remaining payment Verified', 'Appointment #6 payment marked Verified.', 'info', 0, '2026-11-19 14:31:14'),
(46, 2, 'Remaining payment Verified', 'Appointment #6 payment marked Verified.', 'info', 0, '2026-11-19 14:31:14');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `contact` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `contact`, `email`, `address`, `birth_date`, `notes`, `created_at`) VALUES
(1, 'Justin Ihong', '09631144988', 'justin.ihong@gmail.com', 'Lukong, Purok 1, Villanueva Misamis Oriental', '2005-06-12', '', '2026-11-17 19:38:57'),
(2, 'Jaime Ihong', '097314467889', '', 'Lukong, Purok 1, Villanueva Misamis Oriental', '1976-03-09', 'A patient want a dental cleaning', '2026-11-17 22:03:50'),
(3, 'Mary Chris Tan', '', 'marychris203@gmail.com', 'Katipunan, Villanueva Misamis Oriental', '1999-05-20', 'Want a Pasta', '2026-11-17 22:06:13'),
(4, 'Reymark Macapanas', '097864072890', 'ReymarkMcpns@gmail.com', '21st Nazareth, Cagayan De Oro City', '1998-09-06', 'Dental Cleaning', '2026-11-17 22:22:01'),
(5, 'Kerby G Silay', '095311456089', 'Silay.kerby21@gmail.com', 'Zone 2, katipunan, Villanueva Misamis Oriental', '2002-06-24', '', '2026-11-17 23:16:34'),
(6, 'Justeen Pacaldo', '09362534916', '', 'Agusan, Cagayan de Oro City', '2003-04-01', '', '2026-11-18 15:17:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','GCash','Bank Transfer','Card') NOT NULL,
  `reference_number` varchar(120) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Vince Bryant Caasalan', 'bryan123', '$2y$10$gzkRZC071C5oaVV1C69.HemRQGSGDw.89ny7JfELfygEFIearHh4K', 'admin', '2026-11-17 17:36:26'),
(2, 'Justeen Limpag', 'justeen123', '$2y$10$L9l.ou0Oc1Lr.S4q50C.g.QI/axPqpVH6UtaA.OvXhUYjaJi/DWtW', 'staff', '2026-11-17 17:38:31'),
(4, 'Administrator', 'admin', '$2y$10$SR96xZVbh3Myz6sBV0tHvu1akv7jk2PBgEaVHSL4sH85479S0SkBC', 'admin', '2026-11-19 15:32:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_schedule` (`appointment_date`,`start_time`,`end_time`),
  ADD KEY `fk_appointment_service` (`service_id`),
  ADD KEY `idx_dentist_schedule` (`dentist_id`,`appointment_date`,`start_time`,`end_time`);

--
-- Indexes for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `artcare_appointment_deposits`
--
ALTER TABLE `artcare_appointment_deposits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `verification_status` (`verification_status`);

--
-- Indexes for table `artcare_appointment_payments`
--
ALTER TABLE `artcare_appointment_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointment` (`appointment_id`),
  ADD KEY `idx_recorded_by` (`recorded_by`),
  ADD KEY `idx_verified_by` (`verified_by`);

--
-- Indexes for table `artcare_appointment_prices`
--
ALTER TABLE `artcare_appointment_prices`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `artcare_clinical_records`
--
ALTER TABLE `artcare_clinical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `patient_id` (`patient_id`,`treatment_date`);

--
-- Indexes for table `artcare_invoices`
--
ALTER TABLE `artcare_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `record_id` (`record_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `artcare_payments`
--
ALTER TABLE `artcare_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `invoice_id` (`invoice_id`,`paid_at`);

--
-- Indexes for table `artcare_services`
--
ALTER TABLE `artcare_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `clinic_settings`
--
ALTER TABLE `clinic_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dental_records`
--
ALTER TABLE `dental_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `dentist_id` (`dentist_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `dental_services`
--
ALTER TABLE `dental_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `dentists`
--
ALTER TABLE `dentists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `appointment_history`
--
ALTER TABLE `appointment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artcare_appointment_deposits`
--
ALTER TABLE `artcare_appointment_deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artcare_appointment_payments`
--
ALTER TABLE `artcare_appointment_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artcare_clinical_records`
--
ALTER TABLE `artcare_clinical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `artcare_invoices`
--
ALTER TABLE `artcare_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artcare_payments`
--
ALTER TABLE `artcare_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `artcare_services`
--
ALTER TABLE `artcare_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dental_records`
--
ALTER TABLE `dental_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dental_services`
--
ALTER TABLE `dental_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dentists`
--
ALTER TABLE `dentists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointment_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointment_service` FOREIGN KEY (`service_id`) REFERENCES `dental_services` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_reminders`
--
ALTER TABLE `appointment_reminders`
  ADD CONSTRAINT `appointment_reminders_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `artcare_appointment_deposits`
--
ALTER TABLE `artcare_appointment_deposits`
  ADD CONSTRAINT `artcare_appointment_deposits_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `artcare_appointment_deposits_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `artcare_services` (`id`),
  ADD CONSTRAINT `artcare_appointment_deposits_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `artcare_appointment_deposits_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `artcare_appointment_payments`
--
ALTER TABLE `artcare_appointment_payments`
  ADD CONSTRAINT `fk_artcare_appt_payment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `fk_artcare_appt_recorded` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_artcare_appt_verified` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `artcare_appointment_prices`
--
ALTER TABLE `artcare_appointment_prices`
  ADD CONSTRAINT `artcare_appointment_prices_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `artcare_appointment_prices_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `artcare_services` (`id`);

--
-- Constraints for table `artcare_clinical_records`
--
ALTER TABLE `artcare_clinical_records`
  ADD CONSTRAINT `artcare_clinical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `artcare_clinical_records_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `artcare_clinical_records_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `artcare_invoices`
--
ALTER TABLE `artcare_invoices`
  ADD CONSTRAINT `artcare_invoices_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `artcare_clinical_records` (`id`),
  ADD CONSTRAINT `artcare_invoices_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `artcare_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `artcare_payments`
--
ALTER TABLE `artcare_payments`
  ADD CONSTRAINT `artcare_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `artcare_invoices` (`id`),
  ADD CONSTRAINT `artcare_payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dental_records`
--
ALTER TABLE `dental_records`
  ADD CONSTRAINT `dental_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `dental_records_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_records_ibfk_3` FOREIGN KEY (`dentist_id`) REFERENCES `dentists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_records_ibfk_4` FOREIGN KEY (`service_id`) REFERENCES `dental_services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dental_records_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
