-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 05:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dtipaptrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `action` varchar(30) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `before_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_data`)),
  `after_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT utc_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disbursement_vouchers`
--

CREATE TABLE `disbursement_vouchers` (
  `id` int(11) NOT NULL,
  `dv_number` varchar(50) DEFAULT NULL,
  `dv_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `gross_amount` decimal(15,2) DEFAULT 0.00,
  `tax` varchar(100) DEFAULT NULL,
  `status` enum('Complete','Lacking') DEFAULT 'Lacking',
  `pr_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tax_type` varchar(100) DEFAULT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disbursement_vouchers`
--

INSERT INTO `disbursement_vouchers` (`id`, `dv_number`, `dv_date`, `supplier`, `gross_amount`, `tax`, `status`, `pr_id`, `created_at`, `tax_type`, `tax_amount`, `net_amount`) VALUES
(1, 'DV-2026-001', '2026-01-20', 'Davao Office Supplies Co.', 118750.00, '5%', 'Complete', 1, '2026-01-20 01:00:00', 'Tax Based Classification (VAT: 5301.34 + Non-VAT: 3562.50 = 8863.84)', 8863.84, 109886.16),
(2, 'DV-2026-002', '2026-01-27', 'TechHub Solutions Inc.', 82000.00, '5%', 'Complete', 2, '2026-01-27 02:30:00', 'Tax Based Classification (VAT: 3660.71 + Non-VAT: 2460.00 = 6120.71)', 6120.71, 75879.29),
(3, 'DV-2026-003', '2026-02-02', 'Mindanao Construction Supply', 245000.00, '5%', 'Complete', 3, '2026-02-02 03:15:00', 'Tax Based Classification (VAT: 10937.50 + Non-VAT: 7350.00 = 18287.50)', 18287.50, 226712.50),
(4, 'DV-2026-004', '2026-02-07', 'Philippine Training Materials Corp.', 43500.00, '3%', 'Complete', 4, '2026-02-07 00:45:00', 'Professional Withholding Tax', 4350.00, 39150.00),
(5, 'DV-2026-005', '2026-02-15', 'Davao Auto Parts Center', 175500.00, '5%', 'Complete', 5, '2026-02-15 01:30:00', 'Goods Withholding Tax', 1755.00, 173745.00),
(6, 'DV-2026-006', '2026-02-20', 'Furniture World Philippines', 72000.00, '5%', 'Complete', 6, '2026-02-20 02:00:00', 'Tax Based Classification (VAT: 3214.29 + Non-VAT: 2160.00 = 5374.29)', 5374.29, 66625.71),
(7, 'DV-2026-007', '2026-02-28', 'IT Solutions Davao', 315000.00, '5%', 'Complete', 7, '2026-02-28 03:00:00', 'Goods Withholding Tax', 3150.00, 311850.00),
(8, 'DV-2026-008', '2026-03-05', 'Marketing Creatives Co.', 92000.00, '3%', 'Complete', 8, '2026-03-05 00:30:00', 'Professional Withholding Tax', 9200.00, 82800.00),
(9, 'DV-2026-009', '2026-03-12', 'Safety First Equipment', 138000.00, '5%', 'Complete', 9, '2026-03-12 01:15:00', 'Tax Based Classification (VAT: 6160.71 + Non-VAT: 4140.00 = 10300.71)', 10300.71, 127699.29),
(10, 'DV-2026-010', '2026-03-18', 'Audio Visual Systems Inc.', 205000.00, '5%', 'Complete', 10, '2026-03-18 02:45:00', 'Services Withholding Tax', 4100.00, 200900.00),
(11, 'DV-2026-011', '2026-03-25', 'Electrical Supplies Philippines', 66000.00, '3%', 'Complete', 11, '2026-03-25 03:30:00', 'Tax Based Classification (VAT: 2946.43 + Non-VAT: 1980.00 = 4926.43)', 4926.43, 61073.57),
(12, 'DV-2026-012', '2026-04-01', 'Cool Air Conditioning Services', 268000.00, '5%', 'Complete', 12, '2026-04-01 00:00:00', 'Tax Based Classification (VAT: 11964.29 + Non-VAT: 8040.00 = 20004.29)', 20004.29, 247995.71),
(13, 'DV-2026-013', '2026-04-08', 'Print Masters Davao', 49500.00, '3%', 'Complete', 13, '2026-04-08 01:45:00', 'Tax Based Classification (VAT: 2209.82 + Non-VAT: 1485.00 = 3694.82)', 3694.82, 45805.18),
(14, 'DV-2026-014', '2026-04-15', 'Network Solutions PH', 158000.00, '5%', 'Complete', 14, '2026-04-15 02:30:00', 'Tax Based Classification (VAT: 7053.57 + Non-VAT: 4740.00 = 11793.57)', 11793.57, 146206.43),
(15, 'DV-2026-015', '2026-04-22', 'Clean World Janitorial Supply', 85000.00, '3%', 'Complete', 15, '2026-04-22 03:15:00', 'Goods Withholding Tax', 850.00, 84150.00),
(16, 'DV-2026-016', '2026-04-29', 'Fire Safety Systems Inc.', 188000.00, '5%', 'Complete', 16, '2026-04-29 00:30:00', 'Tax Based Classification (VAT: 8392.86 + Non-VAT: 5640.00 = 14032.86)', 14032.86, 173967.14),
(17, 'DV-2026-017', '2026-05-05', 'Power Generator Supply Co.', 75000.00, '3%', 'Complete', 17, '2026-05-05 01:00:00', 'Rent Withholding Tax', 3750.00, 71250.00),
(18, 'DV-2026-018', '2026-05-12', 'Aqua Pure Water Systems', 128000.00, '5%', 'Complete', 18, '2026-05-12 02:00:00', 'Tax Based Classification (VAT: 5714.29 + Non-VAT: 3840.00 = 9554.29)', 9554.29, 118445.71),
(19, 'DV-2026-019', '2026-05-18', 'Solar Power Philippines', 215000.00, '5%', 'Complete', 19, '2026-05-18 03:00:00', 'Tax Based Classification (VAT: 9598.21 + Non-VAT: 6450.00 = 16048.21)', 16048.21, 198951.79),
(20, 'DV-2026-020', '2026-05-25', 'MediCare Supply Co.', 55000.00, '3%', 'Complete', 20, '2026-05-25 00:45:00', 'Goods Withholding Tax', 550.00, 54450.00);

-- --------------------------------------------------------

--
-- Table structure for table `iars`
--

CREATE TABLE `iars` (
  `id` int(11) NOT NULL,
  `iar_number` varchar(50) DEFAULT NULL,
  `iar_date` date DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `date_inspected` date DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `status` enum('Complete','Lacking') NOT NULL DEFAULT 'Lacking',
  `po_id` int(11) DEFAULT NULL,
  `receipt_status` enum('Complete','Partial') DEFAULT 'Complete',
  `pr_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iars`
--

INSERT INTO `iars` (`id`, `iar_number`, `iar_date`, `invoice_number`, `invoice_date`, `date_inspected`, `date_received`, `status`, `po_id`, `receipt_status`, `pr_id`, `created_at`, `updated_at`) VALUES
(1, 'IAR-2026-001', '2026-01-15', 'INV-2026-1001', '2026-01-14', '2026-01-15', '2026-01-15', 'Complete', 1, 'Complete', 1, '2026-01-15 02:00:00', '2026-01-15 06:30:00'),
(2, 'IAR-2026-002', '2026-01-22', 'INV-2026-2050', '2026-01-21', '2026-01-22', '2026-01-22', 'Complete', 2, 'Complete', 2, '2026-01-22 03:00:00', '2026-01-22 07:00:00'),
(3, 'IAR-2026-003', '2026-01-28', 'INV-2026-3080', '2026-01-27', '2026-01-28', '2026-01-28', 'Complete', 3, 'Complete', 3, '2026-01-28 01:30:00', '2026-01-28 05:45:00'),
(4, 'IAR-2026-004', '2026-02-02', 'INV-2026-0042', '2026-02-01', '2026-02-02', '2026-02-02', 'Complete', 4, 'Complete', 4, '2026-02-02 02:15:00', '2026-02-02 08:00:00'),
(5, 'IAR-2026-005', '2026-02-10', 'INV-2026-5120', '2026-02-09', '2026-02-10', '2026-02-10', 'Complete', 5, 'Complete', 5, '2026-02-10 00:45:00', '2026-02-10 04:30:00'),
(6, 'IAR-2026-006', '2026-02-17', 'INV-2026-6180', '2026-02-16', '2026-02-17', '2026-02-26', 'Complete', 6, 'Partial', 6, '2026-02-17 01:20:00', '2026-02-24 16:14:33'),
(7, 'IAR-2026-007', '2026-02-22', 'INV-2026-7200', '2026-02-21', '2026-02-22', '2026-02-22', 'Complete', 7, 'Complete', 7, '2026-02-22 02:30:00', '2026-02-22 06:15:00'),
(8, 'IAR-2026-008', '2026-03-01', 'INV-2026-8150', '2026-02-28', '2026-03-01', '2026-03-01', 'Complete', 8, 'Complete', 8, '2026-03-01 03:00:00', '2026-03-01 07:30:00'),
(9, 'IAR-2026-009', '2026-03-08', 'INV-2026-9020', '2026-03-07', '2026-03-08', '2026-02-26', 'Complete', 9, 'Partial', 9, '2026-03-08 00:15:00', '2026-02-24 16:14:19'),
(10, 'IAR-2026-010', '2026-03-15', 'INV-2026-1025', '2026-03-14', '2026-03-15', '2026-03-15', 'Complete', 10, 'Complete', 10, '2026-03-15 01:45:00', '2026-03-15 05:20:00'),
(11, 'IAR-2026-011', '2026-03-22', 'INV-2026-1105', '2026-03-21', '2026-03-22', '2026-03-22', 'Complete', 11, 'Complete', 11, '2026-03-22 02:30:00', '2026-03-22 08:00:00'),
(12, 'IAR-2026-012', '2026-03-28', 'INV-2026-1250', '2026-03-27', '2026-03-28', '2026-03-28', 'Complete', 12, 'Complete', 12, '2026-03-28 03:15:00', '2026-03-28 06:45:00'),
(13, 'IAR-2026-013', '2026-04-05', 'INV-2026-1308', '2026-04-04', '2026-04-05', '2026-02-28', 'Complete', 13, 'Partial', 13, '2026-04-05 00:50:00', '2026-02-24 16:14:00'),
(14, 'IAR-2026-014', '2026-04-10', 'INV-2026-1420', '2026-04-09', '2026-04-10', '2026-04-10', 'Complete', 14, 'Complete', 14, '2026-04-10 01:30:00', '2026-04-10 07:00:00'),
(15, 'IAR-2026-015', '2026-04-17', 'INV-2026-1505', '2026-04-16', '2026-04-17', '2026-04-17', 'Complete', 15, 'Complete', 15, '2026-04-17 02:20:00', '2026-04-17 05:30:00'),
(16, 'IAR-2026-016', '2026-04-24', 'INV-2026-1610', '2026-04-23', '2026-04-24', '2026-02-27', 'Complete', 16, 'Partial', 16, '2026-04-24 03:00:00', '2026-02-24 16:13:49'),
(17, 'IAR-2026-017', '2026-05-01', 'INV-2026-1702', '2026-04-30', '2026-05-01', '2026-05-01', 'Complete', 17, 'Complete', 17, '2026-05-01 00:40:00', '2026-05-01 06:20:00'),
(18, 'IAR-2026-018', '2026-05-07', 'INV-2026-1820', '2026-05-06', '2026-05-07', '2026-05-07', 'Complete', 18, 'Complete', 18, '2026-05-07 01:25:00', '2026-05-07 08:00:00'),
(19, 'IAR-2026-019', '2026-05-14', 'INV-2026-1905', '2026-05-13', '2026-05-14', '2026-02-26', 'Complete', 19, 'Partial', 19, '2026-05-14 02:15:00', '2026-02-24 16:13:38'),
(20, 'IAR-2026-020', '2026-05-20', 'INV-2026-2008', '2026-05-19', '2026-05-20', '2026-05-20', 'Complete', 20, 'Complete', 20, '2026-05-20 03:30:00', '2026-05-20 07:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_dvs`
--

CREATE TABLE `payroll_dvs` (
  `id` int(11) NOT NULL,
  `dv_number` varchar(50) NOT NULL,
  `dv_date` date NOT NULL,
  `payee` varchar(255) NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payroll_id` int(11) DEFAULT NULL,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `net_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_dvs`
--

INSERT INTO `payroll_dvs` (`id`, `dv_number`, `dv_date`, `payee`, `gross_amount`, `remarks`, `created_at`, `payroll_id`, `tax_percentage`, `tax_amount`, `net_amount`) VALUES
(1, 'PDV-2026-001', '2026-01-31', 'Alice Henderson', 25000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:00:00', 201, 10.00, 2500.00, 22500.00),
(2, 'PDV-2026-002', '2026-01-31', 'Bob Martinez', 18500.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:05:00', 202, 10.00, 1850.00, 16650.00),
(3, 'PDV-2026-003', '2026-01-31', 'Catherine Low', 32000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:10:00', 203, 15.00, 4800.00, 27200.00),
(4, 'PDV-2026-004', '2026-01-31', 'David Wilson', 45000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:15:00', 204, 15.00, 6750.00, 38250.00),
(5, 'PDV-2026-005', '2026-01-31', 'Elena Rodriguez', 21000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:20:00', 205, 10.00, 2100.00, 18900.00),
(6, 'PDV-2026-006', '2026-01-31', 'Franklin Pierce', 15000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:25:00', 206, 5.00, 750.00, 14250.00),
(7, 'PDV-2026-007', '2026-01-31', 'Grace Hopper', 55000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:30:00', 207, 20.00, 11000.00, 44000.00),
(8, 'PDV-2026-008', '2026-01-31', 'Henry Ford', 28000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:35:00', 208, 10.00, 2800.00, 25200.00),
(9, 'PDV-2026-009', '2026-01-31', 'Isabel Archer', 19000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:40:00', 209, 10.00, 1900.00, 17100.00),
(10, 'PDV-2026-010', '2026-01-31', 'Jack Sparrow', 24000.00, 'February 2026 Salary - Batch 1', '2026-01-31 01:45:00', 210, 10.00, 2400.00, 21600.00),
(11, 'PDV-2026-011', '2026-02-28', 'Kelly Clarkson', 30000.00, 'February 2026 Salary - Batch 2 - Pending', '2026-02-28 02:00:00', 211, 15.00, 4500.00, 25500.00),
(12, 'PDV-2026-012', '2026-02-28', 'Liam Neeson', 42000.00, 'February 2026 Salary - Batch 2 - Pending', '2026-02-28 02:05:00', 212, 15.00, 6300.00, 35700.00),
(13, 'PDV-2026-013', '2026-02-28', 'Mia Hamm', 22500.00, 'February 2026 Salary - Batch 2 - Pending', '2026-02-28 02:10:00', 213, 10.00, 2250.00, 20250.00),
(14, 'PDV-2026-014', '2026-02-28', 'Noah Webster', 17000.00, 'February 2026 Salary - Batch 2 - Pending', '2026-02-28 02:15:00', 214, 5.00, 850.00, 16150.00),
(15, 'PDV-2026-015', '2026-02-28', 'Olivia Pope', 38000.00, 'February 2026 Salary - Batch 2 - Pending', '2026-02-28 02:20:00', 215, 15.00, 5700.00, 32300.00),
(16, 'PDV-2026-016', '2026-03-31', 'Peter Parker', 25000.00, 'March 2026 Salary - Pending', '2026-03-31 03:00:00', 216, 10.00, 2500.00, 22500.00),
(17, 'PDV-2026-017', '2026-03-31', 'Quinn Fabray', 21000.00, 'March 2026 Salary - Pending', '2026-03-31 03:05:00', 217, 10.00, 2100.00, 18900.00),
(18, 'PDV-2026-018', '2026-03-31', 'Riley Reid', 19500.00, 'March 2026 Salary - Pending', '2026-03-31 03:10:00', 218, 10.00, 1950.00, 17550.00),
(19, 'PDV-2026-019', '2026-03-31', 'Steven Strange', 60000.00, 'March 2026 Salary - Pending', '2026-03-31 03:15:00', 219, 20.00, 12000.00, 48000.00),
(20, 'PDV-2026-020', '2026-03-31', 'Tony Stark', 58000.00, 'March 2026 Salary - Pending', '2026-03-31 03:20:00', 220, 20.00, 11600.00, 46400.00);

-- --------------------------------------------------------

--
-- Table structure for table `payroll_requests`
--

CREATE TABLE `payroll_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `payroll_number` varchar(50) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `salary_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dv_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_requests`
--

INSERT INTO `payroll_requests` (`id`, `payroll_number`, `employee_name`, `salary_amount`, `status`, `created_at`, `dv_id`) VALUES
(201, 'PYR-2026-FEB-001', 'Alice Henderson', 25000.00, 'Paid', '2026-01-15 00:30:00', 1),
(202, 'PYR-2026-FEB-002', 'Bob Martinez', 18500.00, 'Paid', '2026-01-15 00:35:00', 2),
(203, 'PYR-2026-FEB-003', 'Catherine Low', 32000.00, 'Paid', '2026-01-15 00:40:00', 3),
(204, 'PYR-2026-FEB-004', 'David Wilson', 45000.00, 'Paid', '2026-01-15 00:45:00', 4),
(205, 'PYR-2026-FEB-005', 'Elena Rodriguez', 21000.00, 'Paid', '2026-01-15 00:50:00', 5),
(206, 'PYR-2026-FEB-006', 'Franklin Pierce', 15000.00, 'Paid', '2026-01-15 00:55:00', 6),
(207, 'PYR-2026-FEB-007', 'Grace Hopper', 55000.00, 'Paid', '2026-01-15 01:00:00', 7),
(208, 'PYR-2026-FEB-008', 'Henry Ford', 28000.00, 'Paid', '2026-01-15 01:05:00', 8),
(209, 'PYR-2026-FEB-009', 'Isabel Archer', 19000.00, 'Paid', '2026-01-15 01:10:00', 9),
(210, 'PYR-2026-FEB-010', 'Jack Sparrow', 24000.00, 'Paid', '2026-01-15 01:15:00', 10),
(211, 'PYR-2026-FEB-011', 'Kelly Clarkson', 30000.00, 'Pending', '2026-02-10 00:30:00', 11),
(212, 'PYR-2026-FEB-012', 'Liam Neeson', 42000.00, 'Pending', '2026-02-10 00:35:00', 12),
(213, 'PYR-2026-FEB-013', 'Mia Hamm', 22500.00, 'Pending', '2026-02-10 00:40:00', 13),
(214, 'PYR-2026-FEB-014', 'Noah Webster', 17000.00, 'Pending', '2026-02-10 00:45:00', 14),
(215, 'PYR-2026-FEB-015', 'Olivia Pope', 38000.00, 'Pending', '2026-02-10 00:50:00', 15),
(216, 'PYR-2026-MAR-001', 'Peter Parker', 25000.00, 'Pending', '2026-03-05 01:00:00', 16),
(217, 'PYR-2026-MAR-002', 'Quinn Fabray', 21000.00, 'Pending', '2026-03-05 01:05:00', 17),
(218, 'PYR-2026-MAR-003', 'Riley Reid', 19500.00, 'Pending', '2026-03-05 01:10:00', 18),
(219, 'PYR-2026-MAR-004', 'Steven Strange', 60000.00, 'Pending', '2026-03-05 01:15:00', 19),
(220, 'PYR-2026-MAR-005', 'Tony Stark', 58000.00, 'Pending', '2026-03-05 01:20:00', 20);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `date_of_award` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `pr_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `po_date`, `supplier`, `date_of_award`, `total_amount`, `pr_id`, `created_at`, `status`) VALUES
(1, 'PO-2026-001', '2026-01-10', 'Davao Office Supplies Co.', '2026-01-08', 118750.00, 1, '2026-01-10 00:30:00', 'Completed'),
(2, 'PO-2026-002', '2026-01-17', 'TechHub Solutions Inc.', '2026-01-15', 82000.00, 2, '2026-01-17 01:15:00', 'Completed'),
(3, 'PO-2026-003', '2026-01-23', 'Mindanao Construction Supply', '2026-01-21', 245000.00, 3, '2026-01-23 02:00:00', 'Completed'),
(4, 'PO-2026-004', '2026-01-30', 'Philippine Training Materials Corp.', '2026-01-28', 43500.00, 4, '2026-01-30 03:30:00', 'Completed'),
(5, 'PO-2026-005', '2026-02-05', 'Davao Auto Parts Center', '2026-02-03', 175500.00, 5, '2026-02-05 00:45:00', 'Completed'),
(6, 'PO-2026-006', '2026-02-12', 'Furniture World Philippines', '2026-02-10', 72000.00, 6, '2026-02-12 01:20:00', 'Pending'),
(7, 'PO-2026-007', '2026-02-18', 'IT Solutions Davao', '2026-02-16', 315000.00, 7, '2026-02-18 02:10:00', 'Completed'),
(8, 'PO-2026-008', '2026-02-25', 'Marketing Creatives Co.', '2026-02-23', 92000.00, 8, '2026-02-25 03:00:00', 'Completed'),
(9, 'PO-2026-009', '2026-03-05', 'Safety First Equipment', '2026-03-03', 138000.00, 9, '2026-03-05 00:15:00', 'Pending'),
(10, 'PO-2026-010', '2026-03-10', 'Audio Visual Systems Inc.', '2026-03-08', 205000.00, 10, '2026-03-10 01:45:00', 'Completed'),
(11, 'PO-2026-011', '2026-03-17', 'Electrical Supplies Philippines', '2026-03-15', 66000.00, 11, '2026-03-17 02:30:00', 'Completed'),
(12, 'PO-2026-012', '2026-03-23', 'Cool Air Conditioning Services', '2026-03-21', 268000.00, 12, '2026-03-23 03:15:00', 'Completed'),
(13, 'PO-2026-013', '2026-03-30', 'Print Masters Davao', '2026-03-28', 49500.00, 13, '2026-03-30 00:50:00', 'Pending'),
(14, 'PO-2026-014', '2026-04-05', 'Network Solutions PH', '2026-04-03', 158000.00, 14, '2026-04-05 01:30:00', 'Completed'),
(15, 'PO-2026-015', '2026-04-12', 'Clean World Janitorial Supply', '2026-04-10', 85000.00, 15, '2026-04-12 02:20:00', 'Completed'),
(16, 'PO-2026-016', '2026-04-19', 'Fire Safety Systems Inc.', '2026-04-17', 188000.00, 16, '2026-04-19 03:00:00', 'Pending'),
(17, 'PO-2026-017', '2026-04-26', 'Power Generator Supply Co.', '2026-04-24', 75000.00, 17, '2026-04-26 00:40:00', 'Completed'),
(18, 'PO-2026-018', '2026-05-02', 'Aqua Pure Water Systems', '2026-04-30', 128000.00, 18, '2026-05-02 01:25:00', 'Completed'),
(19, 'PO-2026-019', '2026-05-09', 'Solar Power Philippines', '2026-05-07', 215000.00, 19, '2026-05-09 02:15:00', 'Pending'),
(20, 'PO-2026-020', '2026-05-16', 'MediCare Supply Co.', '2026-05-14', 55000.00, 20, '2026-05-16 03:30:00', 'Completed');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `pr_number` varchar(50) DEFAULT NULL,
  `pr_date` date DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(50) NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT NULL,
  `purpose` text NOT NULL,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_id` int(11) DEFAULT NULL,
  `is_inserted` tinyint(1) DEFAULT 0,
  `parent_pr_id` int(11) DEFAULT NULL,
  `pr_type` enum('MAIN','SUB','LATE') NOT NULL DEFAULT 'MAIN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `pr_number`, `pr_date`, `entity_name`, `fund_cluster`, `total_amount`, `status`, `purpose`, `is_cancelled`, `created_at`, `parent_id`, `is_inserted`, `parent_pr_id`, `pr_type`) VALUES
(1, 'PR-2026-0001', '2026-01-05', 'DTI Regional Office XI', '101', 125000.00, 'Approved', 'Procurement of office supplies and equipment for Q1 operations', 0, '2026-01-05 00:30:00', NULL, 0, NULL, 'MAIN'),
(2, 'PR-2026-0002', '2026-01-12', 'DTI Provincial Office - Davao del Sur', '102', 85000.00, 'Approved', 'Purchase of computer hardware and peripherals', 0, '2026-01-12 01:15:00', NULL, 0, NULL, 'MAIN'),
(3, 'PR-2026-0003', '2026-01-18', 'DTI Provincial Office - Davao Oriental', '101', 250000.00, 'Approved', 'Construction materials for office renovation', 0, '2026-01-18 02:00:00', NULL, 0, NULL, 'MAIN'),
(4, 'PR-2026-0004', '2026-01-25', 'DTI Regional Office XI', '103', 45000.00, 'Approved', 'Training materials and seminar supplies', 0, '2026-01-25 03:30:00', NULL, 0, NULL, 'MAIN'),
(5, 'PR-2026-0005', '2026-02-01', 'DTI Provincial Office - Davao del Norte', '102', 180000.00, 'Approved', 'Vehicle maintenance and spare parts', 0, '2026-02-01 00:45:00', NULL, 0, NULL, 'MAIN'),
(6, 'PR-2026-0006', '2026-02-08', 'DTI Regional Office XI', '101', 75000.00, 'Pending', 'Office furniture and fixtures', 0, '2026-02-08 01:20:00', NULL, 0, NULL, 'MAIN'),
(7, 'PR-2026-0007', '2026-02-15', 'DTI Provincial Office - Compostela Valley', '103', 320000.00, 'Approved', 'IT equipment and software licenses', 0, '2026-02-15 02:10:00', NULL, 0, NULL, 'MAIN'),
(8, 'PR-2026-0008', '2026-02-20', 'DTI Regional Office XI', '102', 95000.00, 'Approved', 'Marketing and promotional materials', 0, '2026-02-20 03:00:00', NULL, 0, NULL, 'MAIN'),
(9, 'PR-2026-0009', '2026-02-28', 'DTI Provincial Office - Davao Occidental', '101', 145000.00, 'Pending', 'Safety equipment and uniforms', 0, '2026-02-28 00:15:00', NULL, 0, NULL, 'MAIN'),
(10, 'PR-2026-0010', '2026-03-05', 'DTI Regional Office XI', '103', 210000.00, 'Approved', 'Conference room equipment and AV systems', 0, '2026-03-05 01:45:00', NULL, 0, NULL, 'MAIN'),
(11, 'PR-2026-0011', '2026-03-12', 'DTI Provincial Office - Davao del Sur', '102', 68000.00, 'Approved', 'Electrical supplies and fixtures', 0, '2026-03-12 02:30:00', NULL, 0, NULL, 'MAIN'),
(12, 'PR-2026-0012', '2026-03-18', 'DTI Regional Office XI', '101', 275000.00, 'Approved', 'Air conditioning units and installation', 0, '2026-03-18 03:15:00', NULL, 0, NULL, 'MAIN'),
(13, 'PR-2026-0013', '2026-03-25', 'DTI Provincial Office - Davao Oriental', '103', 52000.00, 'Pending', 'Printing supplies and consumables', 0, '2026-03-25 00:50:00', NULL, 0, NULL, 'MAIN'),
(14, 'PR-2026-0014', '2026-04-01', 'DTI Regional Office XI', '102', 165000.00, 'Approved', 'Network equipment and cables', 0, '2026-04-01 01:30:00', NULL, 0, NULL, 'MAIN'),
(15, 'PR-2026-0015', '2026-04-08', 'DTI Provincial Office - Davao del Norte', '101', 89000.00, 'Approved', 'Janitorial supplies and cleaning equipment', 0, '2026-04-08 02:20:00', NULL, 0, NULL, 'MAIN'),
(16, 'PR-2026-0016', '2026-04-15', 'DTI Regional Office XI', '103', 195000.00, 'Pending', 'Fire safety equipment and extinguishers', 0, '2026-04-15 03:00:00', NULL, 0, NULL, 'MAIN'),
(17, 'PR-2026-0017', '2026-04-22', 'DTI Provincial Office - Compostela Valley', '102', 78000.00, 'Approved', 'Generator set and accessories', 0, '2026-04-22 00:40:00', NULL, 0, NULL, 'MAIN'),
(18, 'PR-2026-0018', '2026-04-28', 'DTI Regional Office XI', '101', 135000.00, 'Approved', 'Water filtration system and dispensers', 0, '2026-04-28 01:25:00', NULL, 0, NULL, 'MAIN'),
(19, 'PR-2026-0019', '2026-05-05', 'DTI Provincial Office - Davao Occidental', '103', 225000.00, 'Pending', 'Solar panel installation and accessories', 0, '2026-05-05 02:15:00', NULL, 0, NULL, 'MAIN'),
(20, 'PR-2026-0020', '2026-05-12', 'DTI Regional Office XI', '102', 58000.00, 'Approved', 'First aid kits and medical supplies', 0, '2026-05-12 03:30:00', NULL, 0, NULL, 'MAIN');

-- --------------------------------------------------------

--
-- Table structure for table `rfqs`
--

CREATE TABLE `rfqs` (
  `id` int(11) NOT NULL,
  `rfq_number` varchar(50) DEFAULT NULL,
  `rfq_date` date DEFAULT NULL,
  `pr_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfqs`
--

INSERT INTO `rfqs` (`id`, `rfq_number`, `rfq_date`, `pr_id`, `created_at`) VALUES
(1, 'RFQ-2026-001', '2026-01-06', 1, '2026-01-06 00:30:00'),
(2, 'RFQ-2026-002', '2026-01-13', 2, '2026-01-13 01:15:00'),
(3, 'RFQ-2026-003', '2026-01-19', 3, '2026-01-19 02:00:00'),
(4, 'RFQ-2026-004', '2026-01-26', 4, '2026-01-26 03:30:00'),
(5, 'RFQ-2026-005', '2026-02-02', 5, '2026-02-02 00:45:00'),
(6, 'RFQ-2026-006', '2026-02-09', 6, '2026-02-09 01:20:00'),
(7, 'RFQ-2026-007', '2026-02-16', 7, '2026-02-16 02:10:00'),
(8, 'RFQ-2026-008', '2026-02-21', 8, '2026-02-21 03:00:00'),
(9, 'RFQ-2026-009', '2026-03-01', 9, '2026-03-01 00:15:00'),
(10, 'RFQ-2026-010', '2026-03-06', 10, '2026-03-06 01:45:00'),
(11, 'RFQ-2026-011', '2026-03-13', 11, '2026-03-13 02:30:00'),
(12, 'RFQ-2026-012', '2026-03-19', 12, '2026-03-19 03:15:00'),
(13, 'RFQ-2026-013', '2026-03-26', 13, '2026-03-26 00:50:00'),
(14, 'RFQ-2026-014', '2026-04-02', 14, '2026-04-02 01:30:00'),
(15, 'RFQ-2026-015', '2026-04-09', 15, '2026-04-09 02:20:00'),
(16, 'RFQ-2026-016', '2026-04-16', 16, '2026-04-16 03:00:00'),
(17, 'RFQ-2026-017', '2026-04-23', 17, '2026-04-23 00:40:00'),
(18, 'RFQ-2026-018', '2026-04-29', 18, '2026-04-29 01:25:00'),
(19, 'RFQ-2026-019', '2026-05-06', 19, '2026-05-06 02:15:00'),
(20, 'RFQ-2026-020', '2026-05-13', 20, '2026-05-13 03:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) DEFAULT NULL,
  `value_text` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value_text`) VALUES
(1, 'pr_counter', '21'),
(2, 'rfq_counter', '11'),
(3, 'po_counter', '10'),
(4, 'iar_counter', '10'),
(5, 'dv_counter', '10');

-- --------------------------------------------------------

--
-- Table structure for table `sub_prs`
--

CREATE TABLE `sub_prs` (
  `id` int(11) NOT NULL,
  `pr_id` int(11) DEFAULT NULL,
  `sub_pr_number` varchar(50) DEFAULT NULL,
  `sub_pr_date` date DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(50) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `purpose` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`) VALUES
(2, 'System Administrator', 'admin', '$2y$10$LtREQl1ncyHOwquVxaBUoOZN1mTBhsKNGBriexQmwRRHtP1yGUcGq', 'Administrator', '2025-10-19 08:40:49'),
(3, 'Data Encoder', 'encoder', '$2y$10$GONELm6/bCKRuWCyqu9SSekvMQzTpi./EEDb.1pbdhv0niVxOO07C', 'Procurement Section', '2025-10-19 09:11:59'),
(4, 'Accounting Staff', 'accounting', '$2y$10$vF7kvwDUxnWFQutimJmtEunahZQ1O9Od6OaHZuT.JJTSvHv.01Hmy', 'Acceptance Section', '2025-10-19 09:19:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `disbursement_vouchers`
--
ALTER TABLE `disbursement_vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dv_number` (`dv_number`),
  ADD KEY `pr_id` (`pr_id`);

--
-- Indexes for table `iars`
--
ALTER TABLE `iars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `iar_number` (`iar_number`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `fk_iar_po` (`po_id`);

--
-- Indexes for table `payroll_dvs`
--
ALTER TABLE `payroll_dvs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payroll_requests`
--
ALTER TABLE `payroll_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_number_unq` (`payroll_number`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `pr_id` (`pr_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_number` (`pr_number`);

--
-- Indexes for table `rfqs`
--
ALTER TABLE `rfqs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfq_number` (`rfq_number`),
  ADD KEY `pr_id` (`pr_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `sub_prs`
--
ALTER TABLE `sub_prs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disbursement_vouchers`
--
ALTER TABLE `disbursement_vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `iars`
--
ALTER TABLE `iars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payroll_dvs`
--
ALTER TABLE `payroll_dvs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payroll_requests`
--
ALTER TABLE `payroll_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT for table `rfqs`
--
ALTER TABLE `rfqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sub_prs`
--
ALTER TABLE `sub_prs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `disbursement_vouchers`
--
ALTER TABLE `disbursement_vouchers`
  ADD CONSTRAINT `disbursement_vouchers_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `iars`
--
ALTER TABLE `iars`
  ADD CONSTRAINT `fk_iar_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `iars_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rfqs`
--
ALTER TABLE `rfqs`
  ADD CONSTRAINT `rfqs_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_prs`
--
ALTER TABLE `sub_prs`
  ADD CONSTRAINT `sub_prs_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
