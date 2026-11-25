-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 06:39 PM
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
-- Database: `hs_mobile`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_options`
--

CREATE TABLE `app_options` (
  `id` int(11) NOT NULL,
  `option_type` varchar(40) NOT NULL,
  `option_value` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_options`
--

INSERT INTO `app_options` (`id`, `option_type`, `option_value`, `sort_order`, `status`, `created_at`) VALUES
(77, 'phone_condition', 'Box Pack (New)', 10, 1, '2026-11-24 17:34:48'),
(78, 'phone_condition', 'Open Box', 20, 1, '2026-11-24 17:34:48'),
(79, 'phone_condition', 'Used (10/10 Lush)', 30, 1, '2026-11-24 17:34:48'),
(80, 'phone_condition', 'Used (Normal)', 40, 1, '2026-11-24 17:34:48'),
(81, 'phone_condition', 'Kit Only (Phone Only)', 50, 1, '2026-11-24 17:34:48'),
(82, 'pta_status', 'PTA Approved (Official)', 10, 1, '2026-11-24 17:34:48'),
(83, 'pta_status', 'Non-PTA', 20, 1, '2026-11-24 17:34:48'),
(84, 'pta_status', 'JV / Carrier Locked', 30, 1, '2026-11-24 17:34:48'),
(85, 'pta_status', 'Patch / CPID Approved', 40, 1, '2026-11-24 17:34:48'),
(86, 'warranty', '1 Year Official', 10, 1, '2026-11-24 17:34:48'),
(87, 'warranty', '6 Months', 20, 1, '2026-11-24 17:34:48'),
(88, 'warranty', '3 Months', 30, 1, '2026-11-24 17:34:48'),
(89, 'warranty', '1 Month', 40, 1, '2026-11-24 17:34:48'),
(90, 'warranty', '7 Days Check Warranty', 50, 1, '2026-11-24 17:34:48'),
(91, 'warranty', 'No Warranty', 60, 1, '2026-11-24 17:34:48'),
(92, 'accessory', 'Original Box', 10, 1, '2026-11-24 17:34:48'),
(93, 'accessory', 'Original Charger', 20, 1, '2026-11-24 17:34:48'),
(94, 'accessory', 'Data Cable', 30, 1, '2026-11-24 17:34:48'),
(95, 'accessory', 'Handsfree/Cover', 40, 1, '2026-11-24 17:34:48'),
(96, 'ram', '2GB', 10, 1, '2026-11-24 17:34:48'),
(97, 'ram', '3GB', 20, 1, '2026-11-24 17:34:48'),
(98, 'ram', '4GB', 30, 1, '2026-11-24 17:34:48'),
(99, 'ram', '6GB', 40, 1, '2026-11-24 17:34:48'),
(100, 'ram', '8GB', 50, 1, '2026-11-24 17:34:48'),
(101, 'ram', '12GB', 60, 1, '2026-11-24 17:34:48'),
(102, 'ram', '16GB', 70, 1, '2026-11-24 17:34:48'),
(103, 'storage', '32GB', 10, 1, '2026-11-24 17:34:48'),
(104, 'storage', '64GB', 20, 1, '2026-11-24 17:34:48'),
(105, 'storage', '128GB', 30, 1, '2026-11-24 17:34:48'),
(106, 'storage', '256GB', 40, 1, '2026-11-24 17:34:48'),
(107, 'storage', '512GB', 50, 1, '2026-11-24 17:34:48'),
(108, 'storage', '1TB', 60, 1, '2026-11-24 17:34:48'),
(109, 'network_note', 'Factory Unlocked', 10, 1, '2026-11-24 17:34:48'),
(110, 'network_note', '2-Month SIM Working', 20, 1, '2026-11-24 17:34:48'),
(111, 'network_note', '4-Month SIM Working', 30, 1, '2026-11-24 17:34:48'),
(112, 'network_note', 'Locked (JV)', 40, 1, '2026-11-24 17:34:48');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_no` varchar(50) NOT NULL,
  `account_type` enum('current','savings','loan') DEFAULT 'current',
  `branch_code` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `current_balance` decimal(12,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `account_name`, `bank_name`, `account_no`, `account_type`, `branch_code`, `opening_balance`, `current_balance`, `status`, `created_at`, `updated_at`) VALUES
(1, 'HS Mobiles Account', 'HBL', '0011-223344', 'current', '', 50000.00, 60000.00, 1, '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('deposit','withdrawal','transfer_in','transfer_out') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `cheque_status` enum('pending','cleared','bounced','cancelled') DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_transactions`
--

INSERT INTO `bank_transactions` (`id`, `bank_account_id`, `transaction_date`, `transaction_type`, `amount`, `description`, `reference_type`, `reference_id`, `cheque_no`, `cheque_date`, `cheque_status`, `created_by`, `created_at`) VALUES
(1, 1, '2026-11-24', 'withdrawal', 46000.00, 'Supplier payment - Ali Traders - Oppo A78', 'supplier_payment', 3, NULL, NULL, NULL, 1, '2026-11-24'),
(2, 1, '2026-11-24', 'deposit', 142000.00, 'Sale (bank transfer) - INV-261124-004', 'payment', 3, NULL, NULL, NULL, 1, '2026-11-24'),
(3, 1, '2026-11-24', 'deposit', 10000.00, 'Credit received (bank) - Customer #2', 'payment', 6, NULL, NULL, NULL, 1, '2026-11-24'),
(4, 1, '2026-11-24', 'withdrawal', 32000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 3', 'supplier_payment', 10, NULL, NULL, NULL, 1, '2026-11-24'),
(7, 1, '2026-11-24', 'withdrawal', 32000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 3', 'supplier_payment', 14, NULL, NULL, NULL, 1, '2026-11-24'),
(9, 1, '2026-11-24', 'withdrawal', 32000.00, 'Supplier payment - Ali Traders - Samsung Zed Phone 3', 'supplier_payment', 17, NULL, NULL, NULL, 1, '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `address`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'AL NOOR MOBILE ACCESSORIES', 'Ouch, Lower Dir', '0341-1817181 / 0306-', 1, '2026-08-10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Apple', NULL, 1, '2026-11-20', NULL),
(2, 'Samsung', NULL, 1, '2026-11-20', NULL),
(3, 'Vivo', NULL, 1, '2026-11-20', NULL),
(4, 'Oppo', NULL, 1, '2026-11-20', NULL),
(5, 'Infinix', NULL, 1, '2026-11-20', NULL),
(6, 'Tecno', NULL, 1, '2026-11-20', NULL),
(7, 'Xiaomi/Redmi', NULL, 1, '2026-11-20', NULL),
(8, 'Realme', NULL, 1, '2026-11-20', NULL),
(9, 'OnePlus', NULL, 1, '2026-11-20', NULL),
(10, 'Google Pixel', NULL, 1, '2026-11-20', NULL),
(11, 'Other', NULL, 1, '2026-11-20', NULL),
(17, 'Nokia', NULL, 1, '2026-11-24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cash_book`
--

CREATE TABLE `cash_book` (
  `id` int(11) NOT NULL,
  `daily_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `transaction_type` enum('opening_balance','inflow','outflow','closing_balance') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g. sale, payment, expense',
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_book`
--

INSERT INTO `cash_book` (`id`, `daily_id`, `transaction_date`, `transaction_type`, `amount`, `description`, `reference_type`, `reference_id`, `created_by`, `created_at`) VALUES
(1, 1, '2026-11-24', 'outflow', 40000.00, 'Supplier payment - Ali Traders - Samsung Galaxy A15', 'supplier_payment', 1, 1, '2026-11-24'),
(2, 1, '2026-11-24', 'outflow', 40000.00, 'Supplier payment - Ali Traders - Samsung Galaxy A15', 'supplier_payment', 2, 1, '2026-11-24'),
(3, 1, '2026-11-24', 'outflow', 130000.00, 'Purchase from Bilal Gul - Apple iPhone 13 (PUR-261124-005)', 'purchase', 5, 1, '2026-11-24'),
(4, 1, '2026-11-24', 'outflow', 22000.00, 'Supplier payment - Ali Traders - Tecno Spark 10', 'supplier_payment', 4, 1, '2026-11-24'),
(5, 1, '2026-11-24', 'outflow', 29500.00, 'Supplier payment - Ali Traders - Infinix Hot 30', 'supplier_payment', 5, 1, '2026-11-24'),
(6, 1, '2026-11-24', 'inflow', 45000.00, 'Sale - INV-261124-001', 'payment', 1, 1, '2026-11-24'),
(7, 1, '2026-11-24', 'inflow', 20000.00, 'Sale - INV-261124-003', 'payment', 2, 1, '2026-11-24'),
(8, 1, '2026-11-24', 'inflow', 56000.00, 'Sale - INV-261124-005', 'payment', 4, 1, '2026-11-24'),
(9, 1, '2026-11-24', 'inflow', 12000.00, 'Credit received - Customer #1', 'payment', 5, 1, '2026-11-24'),
(10, 1, '2026-11-24', 'outflow', 10000.00, 'Supplier payment - Ali Traders', 'supplier_payment', 6, 1, '2026-11-24'),
(13, 1, '2026-11-24', 'outflow', 31000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 2', 'supplier_payment', 9, 1, '2026-11-24'),
(14, 1, '2026-11-24', 'outflow', 20000.00, 'Purchase from Walk Seller - Samsung Return Phone 7 (PUR-261124-014)', 'purchase', 16, 1, '2026-11-24'),
(15, 1, '2026-11-24', 'outflow', 28000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 8', 'supplier_payment', 11, 1, '2026-11-24'),
(16, 1, '2026-11-24', 'inflow', 32000.00, 'Sale - INV-261124-007', 'payment', 7, 1, '2026-11-24'),
(18, 1, '2026-11-24', 'inflow', 20000.00, 'Purchase return refund - walk-in seller (PRT-261124-006)', 'purchase_return', 6, 1, '2026-11-24'),
(19, 1, '2026-11-24', 'outflow', 20000.00, 'Purchase from Walk Seller - Samsung Return Phone 7 (PUR-261124-019)', 'purchase', 21, 1, '2026-11-24'),
(20, 1, '2026-11-24', 'outflow', 28000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 8', 'supplier_payment', 12, 1, '2026-11-24'),
(21, 1, '2026-11-24', 'inflow', 32000.00, 'Sale - INV-261124-008', 'payment', 8, 1, '2026-11-24'),
(24, 1, '2026-11-24', 'outflow', 31000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 2', 'supplier_payment', 13, 1, '2026-11-24'),
(25, 1, '2026-11-24', 'outflow', 20000.00, 'Purchase from Walk Seller - Samsung Return Phone 7 (PUR-261124-029)', 'purchase', 31, 1, '2026-11-24'),
(26, 1, '2026-11-24', 'outflow', 28000.00, 'Supplier payment - Ali Traders - Samsung Return Phone 8', 'supplier_payment', 15, 1, '2026-11-24'),
(27, 1, '2026-11-24', 'inflow', 32000.00, 'Sale - INV-261124-009', 'payment', 9, 1, '2026-11-24'),
(30, 1, '2026-11-24', 'outflow', 31000.00, 'Supplier payment - Ali Traders - Samsung Zed Phone 2', 'supplier_payment', 16, 1, '2026-11-24'),
(31, 1, '2026-11-24', 'outflow', 20000.00, 'Purchase from Walk Seller - Samsung Zed Phone 7 (PUR-261124-039)', 'purchase', 41, 1, '2026-11-24'),
(32, 1, '2026-11-24', 'outflow', 28000.00, 'Supplier payment - Ali Traders - Samsung Zed Phone 8', 'supplier_payment', 18, 1, '2026-11-24'),
(33, 1, '2026-11-24', 'inflow', 32000.00, 'Sale - INV-261124-010', 'payment', 10, 1, '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `cash_book_daily`
--

CREATE TABLE `cash_book_daily` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `total_inflow` decimal(12,2) DEFAULT 0.00,
  `total_outflow` decimal(12,2) DEFAULT 0.00,
  `closing_balance` decimal(12,2) DEFAULT 0.00,
  `status` enum('open','closed') DEFAULT 'open',
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cash_book_daily`
--

INSERT INTO `cash_book_daily` (`id`, `date`, `opening_balance`, `total_inflow`, `total_outflow`, `closing_balance`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-11-24', 0.00, 281000.00, 556500.00, -275500.00, 'open', 1, '2026-11-24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `product_type` varchar(50) DEFAULT 'general' COMMENT 'general, mobile',
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `product_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Mobile', 'Mobile phones', 'mobile', 1, '2026-08-10', '2026-08-10'),
(3, 'General', 'General products', 'general', 1, '2026-08-10', '2026-08-10');

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('asset','liability','equity','income','expense') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `current_balance` decimal(12,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_no` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `cnic` varchar(15) DEFAULT NULL,
  `cnic_expiry` date DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_relation` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT 0.00,
  `opening_due` decimal(12,2) DEFAULT 0.00 COMMENT 'Previous dues before system',
  `opening_paid` decimal(12,2) DEFAULT 0.00 COMMENT 'Amount already paid before system',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_no`, `full_name`, `phone`, `email`, `address`, `city`, `cnic`, `cnic_expiry`, `guardian_name`, `guardian_relation`, `occupation`, `monthly_income`, `opening_due`, `opening_paid`, `notes`, `branch_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'CUS-2611-0001', 'Ahmed Khan', '0301-1234567', '', 'Ouch', NULL, '15101-1111111-1', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(2, 'CUS-2611-0002', 'Zahid Shah', '0302-7654321', '', 'Dir', NULL, '15101-2222222-2', NULL, NULL, NULL, NULL, 0.00, 10000.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(3, 'CUS-2611-0003', 'Rashid', '0303-1112223', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(4, 'CUS-2611-0004', 'Imran', '0304-0000001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(5, 'CUS-2611-0005', 'RetBuyer', '0300-4040404', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(6, 'CUS-2611-0006', 'RetBuyer', '0300-4040404', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(7, 'CUS-2611-0007', 'RetBuyer', '0300-4040404', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(8, 'CUS-2611-0008', 'RetBuyer', '0300-4040404', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `customer_documents`
--

CREATE TABLE `customer_documents` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `document_type` enum('cnic','utility_bill','salary_slip','other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(12,2) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `min_purchase_amount` decimal(12,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `bill_no` varchar(50) DEFAULT NULL,
  `payment_method` enum('cash','card','bank_transfer','bank') DEFAULT 'cash',
  `bank_account_id` int(11) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `general_parties`
--

CREATE TABLE `general_parties` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `general_transactions`
--

CREATE TABLE `general_transactions` (
  `id` int(11) NOT NULL,
  `party_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` enum('receipt','payment') NOT NULL COMMENT 'receipt = money in, payment = money out',
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','bank') DEFAULT 'cash',
  `bank_account_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_items`
--

CREATE TABLE `journal_entry_items` (
  `id` int(11) NOT NULL,
  `journal_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_type` enum('sale','credit_payment','advance','partial') DEFAULT 'sale',
  `payment_method` enum('cash','card','bank_transfer','bank') DEFAULT 'cash',
  `reference_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `sale_id`, `payment_date`, `amount`, `payment_type`, `payment_method`, `reference_no`, `notes`, `branch_id`, `received_by`, `created_at`) VALUES
(1, 1, '2026-11-24', 45000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-001', NULL, 1, '2026-11-24'),
(2, 3, '2026-11-24', 20000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-003', NULL, 1, '2026-11-24'),
(3, 4, '2026-11-24', 142000.00, 'sale', 'bank', NULL, 'Payment for INV-261124-004', NULL, 1, '2026-11-24'),
(4, 5, '2026-11-24', 56000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-005', NULL, 1, '2026-11-24'),
(5, 2, '2026-11-24', 12000.00, 'credit_payment', 'cash', NULL, 'Credit received', NULL, 1, '2026-11-24'),
(6, 3, '2026-11-24', 10000.00, 'credit_payment', 'bank', NULL, 'Credit received', NULL, 1, '2026-11-24'),
(7, 8, '2026-11-24', 32000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-007', NULL, 1, '2026-11-24'),
(8, 9, '2026-11-24', 32000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-008', NULL, 1, '2026-11-24'),
(9, 10, '2026-11-24', 32000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-009', NULL, 1, '2026-11-24'),
(10, 11, '2026-11-24', 32000.00, 'sale', 'cash', NULL, 'Payment for INV-261124-010', NULL, 1, '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Item Code',
  `name` varchar(100) NOT NULL,
  `model_name` varchar(100) DEFAULT NULL COMMENT 'Mobile/laptop model, used for stock grouping',
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `imei_no_1` varchar(100) DEFAULT NULL COMMENT 'Mobile IMEI 1',
  `imei_no_2` varchar(100) DEFAULT NULL COMMENT 'Mobile IMEI 2 (dual SIM)',
  `storage` varchar(50) DEFAULT NULL COMMENT 'e.g. 64GB, 128GB',
  `ram` varchar(50) DEFAULT NULL COMMENT 'e.g. 4GB, 6GB, 8GB',
  `warranty_months` int(11) DEFAULT NULL,
  `product_condition` varchar(50) DEFAULT 'New' COMMENT 'New, Used, Refurbished',
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 0 COMMENT 'Low stock alert threshold',
  `unit` varchar(20) DEFAULT 'pcs',
  `product_type` varchar(50) DEFAULT 'general' COMMENT 'general, mobile',
  `has_serial` tinyint(1) DEFAULT 0 COMMENT '1 = track serial/IMEI',
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `code`, `name`, `model_name`, `description`, `category_id`, `brand_id`, `supplier_id`, `color`, `imei_no_1`, `imei_no_2`, `storage`, `ram`, `warranty_months`, `product_condition`, `purchase_price`, `sale_price`, `stock_quantity`, `min_stock_level`, `unit`, `product_type`, `has_serial`, `status`, `created_at`, `updated_at`) VALUES
(1, 'MOB-0001', 'Samsung Galaxy A15', 'Galaxy A15', NULL, 1, 2, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 40000.00, 45000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(2, 'MOB-0002', 'Vivo Y36', 'Y36', NULL, 1, 3, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 38000.00, 42000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(3, 'MOB-0003', 'Oppo A78', 'A78', NULL, 1, 4, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 46000.00, 50500.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(4, 'MOB-0004', 'Apple iPhone 13', 'iPhone 13', NULL, 1, 1, NULL, 'Blue', NULL, NULL, '128GB', '4GB', NULL, 'Used', 130000.00, 142000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(5, 'MOB-0005', 'Tecno Spark 10', 'Spark 10', NULL, 1, 6, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 22000.00, 25000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(6, 'MOB-0006', 'Infinix Hot 30', 'Hot 30', NULL, 1, 5, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 29500.00, 33000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(7, 'MOB-0007', 'Realme C55', 'C55', NULL, 1, 8, 1, 'Black', NULL, NULL, '128GB', '8GB', NULL, 'New', 36000.00, 39500.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(9, 'MOB-0008', 'Samsung Return Phone 1', 'Return Phone 1', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 30000.00, 34000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(10, 'MOB-0009', 'Samsung Return Phone 2', 'Return Phone 2', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 31000.00, 35000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(11, 'MOB-0010', 'Samsung Return Phone 3', 'Return Phone 3', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 32000.00, 36000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(12, 'MOB-0011', 'Samsung Return Phone 4', 'Return Phone 4', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 33000.00, 37000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(13, 'MOB-0012', 'Samsung Return Phone 5', 'Return Phone 5', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 34000.00, 38000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(14, 'MOB-0013', 'Samsung Return Phone 6', 'Return Phone 6', NULL, 1, 2, 2, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 35000.00, 39000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(15, 'MOB-0014', 'Samsung Return Phone 7', 'Return Phone 7', NULL, 1, 2, NULL, 'Blue', NULL, NULL, '64GB', '4GB', NULL, 'Used', 20000.00, 24000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(16, 'MOB-0015', 'Samsung Return Phone 8', 'Return Phone 8', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 28000.00, 32000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(17, 'MOB-0016', 'Samsung Return Phone 9', 'Return Phone 9', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 30000.00, 34000.00, 2, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(18, 'MOB-0017', 'Samsung Zed Phone 1', 'Zed Phone 1', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 30000.00, 34000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(19, 'MOB-0018', 'Samsung Zed Phone 2', 'Zed Phone 2', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 31000.00, 35000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(20, 'MOB-0019', 'Samsung Zed Phone 3', 'Zed Phone 3', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 32000.00, 36000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(21, 'MOB-0020', 'Samsung Zed Phone 4', 'Zed Phone 4', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 33000.00, 37000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(22, 'MOB-0021', 'Samsung Zed Phone 5', 'Zed Phone 5', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 34000.00, 38000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(23, 'MOB-0022', 'Samsung Zed Phone 6', 'Zed Phone 6', NULL, 1, 2, 2, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 35000.00, 39000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(24, 'MOB-0023', 'Samsung Zed Phone 7', 'Zed Phone 7', NULL, 1, 2, NULL, 'Blue', NULL, NULL, '64GB', '4GB', NULL, 'Used', 20000.00, 24000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(25, 'MOB-0024', 'Samsung Zed Phone 8', 'Zed Phone 8', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 28000.00, 32000.00, 0, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24'),
(26, 'MOB-0025', 'Samsung Zed Phone 9', 'Zed Phone 9', NULL, 1, 2, 1, 'QA', NULL, NULL, '64GB', '4GB', NULL, 'New', 30000.00, 34000.00, 1, 0, 'pcs', 'mobile', 1, 1, '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `product_serials`
--

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `device_serial_no` varchar(100) DEFAULT NULL COMMENT 'Manufacturer serial number',
  `phone_condition` varchar(30) DEFAULT NULL COMMENT 'Box Pack, Open Box, Used 10/10, Used Normal, Kit Only',
  `pta_status` varchar(30) DEFAULT NULL COMMENT 'PTA Approved, Non-PTA, JV/Locked, Patch/CPID',
  `network_note` varchar(150) DEFAULT NULL,
  `battery_health` varchar(10) DEFAULT NULL COMMENT 'e.g. 95% or N/A',
  `warranty_status` varchar(60) DEFAULT NULL,
  `accessories` varchar(255) DEFAULT NULL COMMENT 'Comma list: Box, Charger, Cable, Handsfree/Cover',
  `fault_notes` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `seller_name` varchar(100) DEFAULT NULL COMMENT 'Walk-in seller',
  `seller_cnic` varchar(15) DEFAULT NULL COMMENT 'XXXXX-XXXXXXX-X',
  `seller_phone` varchar(20) DEFAULT NULL,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) DEFAULT NULL COMMENT 'Per-unit asking price',
  `imei_number` varchar(100) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `status` enum('available','sold','returned') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_serials`
--

INSERT INTO `product_serials` (`id`, `product_id`, `serial_number`, `device_serial_no`, `phone_condition`, `pta_status`, `network_note`, `battery_health`, `warranty_status`, `accessories`, `fault_notes`, `supplier_id`, `seller_name`, `seller_cnic`, `seller_phone`, `purchase_price`, `sale_price`, `imei_number`, `purchase_id`, `sale_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '380000000000097', NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 40000.00, 45000.00, '380000000000014', 1, 1, 'sold', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(2, 1, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 40000.00, 45000.00, '380000000000022', 2, NULL, 'available', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(3, 2, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 38000.00, 42000.00, '380000000000030', 3, 7, 'sold', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(4, 3, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 46000.00, 50500.00, '380000000000048', 4, 3, 'sold', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(5, 4, NULL, NULL, 'Used (10/10 Lush)', 'PTA Approved (Official)', NULL, '88%', NULL, NULL, NULL, NULL, 'Bilal Gul', '15101-3333333-3', '0345-5556667', 130000.00, 142000.00, '380000000000055', 5, 4, 'sold', 'Mobile - Blue - 128GB/4GB - Used (10/10 Lush)', '2026-11-24', '2026-11-24'),
(6, 5, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 22000.00, 25000.00, '380000000000063', 6, 5, 'sold', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(7, 6, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 29500.00, 33000.00, '380000000000071', 7, 5, 'sold', 'Mobile - Black - 128GB/8GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(10, 9, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '400000000000019', 10, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(11, 10, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 31000.00, 35000.00, '400000000000027', 11, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(12, 11, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 32000.00, 36000.00, '400000000000035', 12, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(13, 12, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 33000.00, 37000.00, '400000000000043', 24, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(14, 13, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 34000.00, 38000.00, '400000000000050', 20, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(15, 14, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, 35000.00, 39000.00, '400000000000068', 15, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(16, 15, NULL, NULL, 'Used (Normal)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, NULL, 'Walk Seller', '15101-4444444-4', '0345-0000000', 20000.00, 24000.00, '400000000000076', 21, NULL, 'available', 'Mobile - Blue - 64GB/4GB - Used (Normal)', '2026-11-24', '2026-11-24'),
(17, 16, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 28000.00, 32000.00, '400000000000084', 22, 9, 'sold', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(18, 17, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '400000000000092', 23, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(19, 9, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '410000000000019', 25, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(20, 10, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 31000.00, 35000.00, '410000000000027', 26, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(21, 11, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 32000.00, 36000.00, '410000000000035', 27, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(22, 12, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 33000.00, 37000.00, '410000000000043', 34, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(23, 13, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 34000.00, 38000.00, '410000000000050', 29, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(24, 14, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, 35000.00, 39000.00, '410000000000068', 30, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(25, 15, NULL, NULL, 'Used (Normal)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, NULL, 'Walk Seller', '15101-4444444-4', '0345-0000000', 20000.00, 24000.00, '410000000000076', 31, NULL, 'available', 'Mobile - Blue - 64GB/4GB - Used (Normal)', '2026-11-24', '2026-11-24'),
(26, 16, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 28000.00, 32000.00, '410000000000084', 32, 10, 'sold', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(27, 17, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '410000000000092', 33, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(28, 18, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '420000000000019', 35, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(29, 19, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 31000.00, 35000.00, '420000000000027', 36, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(30, 20, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 32000.00, 36000.00, '420000000000035', 37, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(31, 21, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 33000.00, 37000.00, '420000000000043', 44, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(32, 22, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 34000.00, 38000.00, '420000000000050', 39, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(33, 23, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, 35000.00, 39000.00, '420000000000068', 40, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(34, 24, NULL, NULL, 'Used (Normal)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, NULL, 'Walk Seller', '15101-4444444-4', '0345-0000000', 20000.00, 24000.00, '420000000000076', 41, NULL, 'available', 'Mobile - Blue - 64GB/4GB - Used (Normal)', '2026-11-24', '2026-11-24'),
(35, 25, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 28000.00, 32000.00, '420000000000084', 42, 11, 'sold', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24'),
(36, 26, NULL, NULL, 'Box Pack (New)', 'PTA Approved (Official)', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 30000.00, 34000.00, '420000000000092', 43, NULL, 'available', 'Mobile - QA - 64GB/4GB - Box Pack (New)', '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `person_name` varchar(100) DEFAULT NULL COMMENT 'Random person (no supplier)',
  `person_phone` varchar(20) DEFAULT NULL,
  `person_cnic` varchar(15) DEFAULT NULL,
  `person_address` varchar(255) DEFAULT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `due_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','received','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `person_name`, `person_phone`, `person_cnic`, `person_address`, `invoice_no`, `purchase_date`, `total_amount`, `paid_amount`, `due_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, NULL, 'PUR-261124-001', '2026-11-24', 40000.00, 40000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(2, 1, NULL, NULL, NULL, NULL, 'PUR-261124-002', '2026-11-24', 40000.00, 40000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(3, 1, NULL, NULL, NULL, NULL, 'PUR-261124-003', '2026-11-24', 38000.00, 0.00, 38000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(4, 1, NULL, NULL, NULL, NULL, 'PUR-261124-004', '2026-11-24', 46000.00, 46000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(5, NULL, 'Bilal Gul', '0345-5556667', '15101-3333333-3', NULL, 'PUR-261124-005', '2026-11-24', 130000.00, 130000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(6, 1, NULL, NULL, NULL, NULL, 'PUR-261124-006', '2026-11-24', 22000.00, 22000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(7, 1, NULL, NULL, NULL, NULL, 'PUR-261124-007', '2026-11-24', 29500.00, 29500.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(10, 1, NULL, NULL, NULL, NULL, 'PUR-261124-008', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(11, 1, NULL, NULL, NULL, NULL, 'PUR-261124-009', '2026-11-24', 31000.00, 31000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(12, 1, NULL, NULL, NULL, NULL, 'PUR-261124-010', '2026-11-24', 32000.00, 32000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(13, 1, NULL, NULL, NULL, NULL, 'PUR-261124-011', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(14, 1, NULL, NULL, NULL, NULL, 'PUR-261124-012', '2026-11-24', 34000.00, 0.00, 34000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(15, 2, NULL, NULL, NULL, NULL, 'PUR-261124-013', '2026-11-24', 35000.00, 0.00, 35000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(16, NULL, 'Walk Seller', '0345-0000000', '15101-4444444-4', NULL, 'PUR-261124-014', '2026-11-24', 20000.00, 20000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(17, 1, NULL, NULL, NULL, NULL, 'PUR-261124-015', '2026-11-24', 28000.00, 28000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(18, 1, NULL, NULL, NULL, NULL, 'PUR-261124-016', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(19, 1, NULL, NULL, NULL, NULL, 'PUR-261124-017', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(20, 1, NULL, NULL, NULL, NULL, 'PUR-261124-018', '2026-11-24', 34000.00, 0.00, 34000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(21, NULL, 'Walk Seller', '0345-0000000', '15101-4444444-4', NULL, 'PUR-261124-019', '2026-11-24', 20000.00, 20000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(22, 1, NULL, NULL, NULL, NULL, 'PUR-261124-020', '2026-11-24', 28000.00, 28000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(23, 1, NULL, NULL, NULL, NULL, 'PUR-261124-021', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(24, 1, NULL, NULL, NULL, NULL, 'PUR-261124-022', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(25, 1, NULL, NULL, NULL, NULL, 'PUR-261124-023', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(26, 1, NULL, NULL, NULL, NULL, 'PUR-261124-024', '2026-11-24', 31000.00, 31000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(27, 1, NULL, NULL, NULL, NULL, 'PUR-261124-025', '2026-11-24', 32000.00, 32000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(28, 1, NULL, NULL, NULL, NULL, 'PUR-261124-026', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(29, 1, NULL, NULL, NULL, NULL, 'PUR-261124-027', '2026-11-24', 34000.00, 0.00, 34000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(30, 2, NULL, NULL, NULL, NULL, 'PUR-261124-028', '2026-11-24', 35000.00, 0.00, 35000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(31, NULL, 'Walk Seller', '0345-0000000', '15101-4444444-4', NULL, 'PUR-261124-029', '2026-11-24', 20000.00, 20000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(32, 1, NULL, NULL, NULL, NULL, 'PUR-261124-030', '2026-11-24', 28000.00, 28000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(33, 1, NULL, NULL, NULL, NULL, 'PUR-261124-031', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(34, 1, NULL, NULL, NULL, NULL, 'PUR-261124-032', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(35, 1, NULL, NULL, NULL, NULL, 'PUR-261124-033', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(36, 1, NULL, NULL, NULL, NULL, 'PUR-261124-034', '2026-11-24', 31000.00, 31000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(37, 1, NULL, NULL, NULL, NULL, 'PUR-261124-035', '2026-11-24', 32000.00, 32000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(38, 1, NULL, NULL, NULL, NULL, 'PUR-261124-036', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(39, 1, NULL, NULL, NULL, NULL, 'PUR-261124-037', '2026-11-24', 34000.00, 0.00, 34000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(40, 2, NULL, NULL, NULL, NULL, 'PUR-261124-038', '2026-11-24', 35000.00, 0.00, 35000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(41, NULL, 'Walk Seller', '0345-0000000', '15101-4444444-4', NULL, 'PUR-261124-039', '2026-11-24', 20000.00, 20000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(42, 1, NULL, NULL, NULL, NULL, 'PUR-261124-040', '2026-11-24', 28000.00, 28000.00, 0.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(43, 1, NULL, NULL, NULL, NULL, 'PUR-261124-041', '2026-11-24', 30000.00, 0.00, 30000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL),
(44, 1, NULL, NULL, NULL, NULL, 'PUR-261124-042', '2026-11-24', 33000.00, 0.00, 33000.00, 'received', 'Added via Add Mobile / Product', 1, '2026-11-24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `purchase_price`, `subtotal`) VALUES
(1, 1, 1, 1, 40000.00, 40000.00),
(2, 2, 1, 1, 40000.00, 40000.00),
(3, 3, 2, 1, 38000.00, 38000.00),
(4, 4, 3, 1, 46000.00, 46000.00),
(5, 5, 4, 1, 130000.00, 130000.00),
(6, 6, 5, 1, 22000.00, 22000.00),
(7, 7, 6, 1, 29500.00, 29500.00),
(10, 10, 9, 1, 30000.00, 30000.00),
(11, 11, 10, 1, 31000.00, 31000.00),
(12, 12, 11, 1, 32000.00, 32000.00),
(13, 13, 12, 1, 33000.00, 33000.00),
(14, 14, 13, 1, 34000.00, 34000.00),
(15, 15, 14, 1, 35000.00, 35000.00),
(16, 16, 15, 1, 20000.00, 20000.00),
(17, 17, 16, 1, 28000.00, 28000.00),
(18, 18, 17, 1, 30000.00, 30000.00),
(19, 19, 12, 1, 33000.00, 33000.00),
(20, 20, 13, 1, 34000.00, 34000.00),
(21, 21, 15, 1, 20000.00, 20000.00),
(22, 22, 16, 1, 28000.00, 28000.00),
(23, 23, 17, 1, 30000.00, 30000.00),
(24, 24, 12, 1, 33000.00, 33000.00),
(25, 25, 9, 1, 30000.00, 30000.00),
(26, 26, 10, 1, 31000.00, 31000.00),
(27, 27, 11, 1, 32000.00, 32000.00),
(28, 28, 12, 1, 33000.00, 33000.00),
(29, 29, 13, 1, 34000.00, 34000.00),
(30, 30, 14, 1, 35000.00, 35000.00),
(31, 31, 15, 1, 20000.00, 20000.00),
(32, 32, 16, 1, 28000.00, 28000.00),
(33, 33, 17, 1, 30000.00, 30000.00),
(34, 34, 12, 1, 33000.00, 33000.00),
(35, 35, 18, 1, 30000.00, 30000.00),
(36, 36, 19, 1, 31000.00, 31000.00),
(37, 37, 20, 1, 32000.00, 32000.00),
(38, 38, 21, 1, 33000.00, 33000.00),
(39, 39, 22, 1, 34000.00, 34000.00),
(40, 40, 23, 1, 35000.00, 35000.00),
(41, 41, 24, 1, 20000.00, 20000.00),
(42, 42, 25, 1, 28000.00, 28000.00),
(43, 43, 26, 1, 30000.00, 30000.00),
(44, 44, 21, 1, 33000.00, 33000.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `taxable_amount` decimal(12,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Amount received at sale time',
  `due_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Remaining balance (credit/khata)',
  `payment_method` enum('cash','card','bank_transfer','bank') DEFAULT 'cash',
  `payment_status` enum('paid','partial','pending') DEFAULT 'pending' COMMENT 'pending = full credit, partial = partly paid, paid = cleared',
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `invoice_no`, `customer_id`, `sale_date`, `subtotal`, `discount_id`, `discount_amount`, `taxable_amount`, `tax_rate`, `tax_amount`, `total_amount`, `paid_amount`, `due_amount`, `payment_method`, `payment_status`, `status`, `notes`, `branch_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-261124-001', 3, '2026-11-24', 45000.00, NULL, 0.00, 0.00, 0.00, 0.00, 45000.00, 45000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(2, 'INV-261124-002', 1, '2026-11-24', 42000.00, NULL, 0.00, 0.00, 0.00, 0.00, 42000.00, 12000.00, 30000.00, NULL, 'partial', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(3, 'INV-261124-003', 2, '2026-11-24', 50500.00, NULL, 0.00, 0.00, 0.00, 0.00, 50500.00, 30000.00, 20500.00, 'cash', 'partial', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(4, 'INV-261124-004', 4, '2026-11-24', 142000.00, NULL, 0.00, 0.00, 0.00, 0.00, 142000.00, 142000.00, 0.00, 'bank', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(5, 'INV-261124-005', 1, '2026-11-24', 58000.00, NULL, 2000.00, 0.00, 0.00, 0.00, 56000.00, 56000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(7, 'INV-261124-006', 1, '2026-11-24', 40500.00, NULL, 0.00, 0.00, 0.00, 0.00, 40500.00, 0.00, 40500.00, NULL, 'pending', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(8, 'INV-261124-007', 5, '2026-11-24', 32000.00, NULL, 0.00, 0.00, 0.00, 0.00, 32000.00, 32000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(9, 'INV-261124-008', 6, '2026-11-24', 32000.00, NULL, 0.00, 0.00, 0.00, 0.00, 32000.00, 32000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(10, 'INV-261124-009', 7, '2026-11-24', 32000.00, NULL, 0.00, 0.00, 0.00, 0.00, 32000.00, 32000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24'),
(11, 'INV-261124-010', 8, '2026-11-24', 32000.00, NULL, 0.00, 0.00, 0.00, 0.00, 32000.00, 32000.00, 0.00, 'cash', 'paid', 'active', NULL, NULL, 1, '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL COMMENT 'Free text description',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `serial_id`, `item_description`, `quantity`, `price`, `subtotal`) VALUES
(1, 1, 1, 1, 'Samsung Galaxy A15 [IMEI: 380000000000014 / 380000000000097]', 1, 45000.00, 45000.00),
(2, 2, 2, 3, 'Vivo Y36 [IMEI: 380000000000030]', 1, 42000.00, 42000.00),
(3, 3, 3, 4, 'Oppo A78 [IMEI: 380000000000048]', 1, 50500.00, 50500.00),
(4, 4, 4, 5, 'Apple iPhone 13 [IMEI: 380000000000055]', 1, 142000.00, 142000.00),
(5, 5, 5, 6, 'Tecno Spark 10 [IMEI: 380000000000063]', 1, 25000.00, 25000.00),
(6, 5, 6, 7, 'Infinix Hot 30 [IMEI: 380000000000071]', 1, 33000.00, 33000.00),
(8, 7, 2, 3, 'Vivo Y36', 1, 40500.00, 40500.00),
(9, 8, 16, 17, 'Samsung Return Phone 8 [IMEI: 400000000000084]', 1, 32000.00, 32000.00),
(10, 9, 16, 17, 'Samsung Return Phone 8 [IMEI: 400000000000084]', 1, 32000.00, 32000.00),
(11, 10, 16, 26, 'Samsung Return Phone 8 [IMEI: 410000000000084]', 1, 32000.00, 32000.00),
(12, 11, 25, 35, 'Samsung Zed Phone 8 [IMEI: 420000000000084]', 1, 32000.00, 32000.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_returns`
--

CREATE TABLE `sale_returns` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_returns`
--

INSERT INTO `sale_returns` (`id`, `sale_id`, `product_id`, `customer_id`, `return_date`, `amount`, `quantity`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, 2, 1, '2026-11-24', 42000.00, 1, NULL, 1, '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `label` varchar(120) DEFAULT NULL,
  `group_name` varchar(40) NOT NULL DEFAULT 'shop',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `label`, `group_name`, `sort_order`, `updated_at`) VALUES
('invoice_terms', 'Please check the IMEI and the box contents before leaving the shop.\nWarranty applies only as written against each item; physical damage, water and software tampering are not covered.\nGoods once sold are not returnable. Exchange only with the original box and this invoice.', 'Invoice terms (one per line)', 'receipt', 7, '2026-11-24 17:34:46'),
('receipt_thanks', 'Thank you for visiting', 'Thank-you line on receipts', 'receipt', 6, '2026-11-24 17:34:46'),
('shop_address', '', 'Address', 'shop', 4, '2026-11-24 17:34:46'),
('shop_logo', 'assets/img/logo.svg', 'Logo image path', 'shop', 5, '2026-11-24 17:34:46'),
('shop_name', 'HS Mobiles', 'Shop name', 'shop', 1, '2026-11-24 17:34:46'),
('shop_phone', '03268711118', 'Phone / Cell', 'shop', 3, '2026-11-24 17:34:46'),
('shop_tagline', '', 'Tagline (printed under the name)', 'shop', 2, '2026-11-24 17:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `adjustment` decimal(12,2) DEFAULT 0.00 COMMENT 'Positive = we owe, Negative = supplier owes us',
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `city`, `opening_balance`, `adjustment`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ali Mobile House', 'Ali Traders', '0300-1111111', '', '', 'Peshawar', 0.00, 0.00, 1, '2026-11-24', '2026-11-24'),
(2, 'Usman Imports', 'Usman Imports', '0300-2222222', '', '', 'Lahore', 20000.00, 0.00, 1, '2026-11-24', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_payments`
--

CREATE TABLE `supplier_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','bank') DEFAULT 'cash',
  `bank_account_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_payments`
--

INSERT INTO `supplier_payments` (`id`, `supplier_id`, `purchase_id`, `amount`, `payment_method`, `bank_account_id`, `description`, `payment_date`, `created_by`, `created_at`) VALUES
(1, 1, 1, 40000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-001', '2026-11-24', 1, '2026-11-24'),
(2, 1, 2, 40000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-002', '2026-11-24', 1, '2026-11-24'),
(3, 1, 4, 46000.00, 'bank', 1, 'Purchase payment - Invoice PUR-261124-004', '2026-11-24', 1, '2026-11-24'),
(4, 1, 6, 22000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-006', '2026-11-24', 1, '2026-11-24'),
(5, 1, 7, 29500.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-007', '2026-11-24', 1, '2026-11-24'),
(6, 1, NULL, 10000.00, 'cash', NULL, 'part', '2026-11-24', 1, '2026-11-24'),
(9, 1, 11, 31000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-009', '2026-11-24', 1, '2026-11-24'),
(10, 1, 12, 32000.00, 'bank', 1, 'Purchase payment - Invoice PUR-261124-010', '2026-11-24', 1, '2026-11-24'),
(11, 1, 17, 28000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-015', '2026-11-24', 1, '2026-11-24'),
(12, 1, 22, 28000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-020', '2026-11-24', 1, '2026-11-24'),
(13, 1, 26, 31000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-024', '2026-11-24', 1, '2026-11-24'),
(14, 1, 27, 32000.00, 'bank', 1, 'Purchase payment - Invoice PUR-261124-025', '2026-11-24', 1, '2026-11-24'),
(15, 1, 32, 28000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-030', '2026-11-24', 1, '2026-11-24'),
(16, 1, 36, 31000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-034', '2026-11-24', 1, '2026-11-24'),
(17, 1, 37, 32000.00, 'bank', 1, 'Purchase payment - Invoice PUR-261124-035', '2026-11-24', 1, '2026-11-24'),
(18, 1, 42, 28000.00, 'cash', NULL, 'Purchase payment - Invoice PUR-261124-040', '2026-11-24', 1, '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','cashier','salesperson','accountant') DEFAULT 'cashier',
  `branch_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` date NOT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `branch_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin123', 'Administrator', NULL, NULL, 'admin', 1, 1, '2026-08-10', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_module` (`module`);

--
-- Indexes for table `app_options`
--
ALTER TABLE `app_options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_option` (`option_type`,`option_value`),
  ADD KEY `idx_type_sort` (`option_type`,`status`,`sort_order`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_no` (`account_no`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_banktxn_date` (`transaction_date`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_book`
--
ALTER TABLE `cash_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_id` (`daily_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_cashbook_date` (`transaction_date`);

--
-- Indexes for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_no` (`customer_no`),
  ADD UNIQUE KEY `cnic` (`cnic`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_customers_cnic` (`cnic`),
  ADD KEY `idx_customers_phone` (`phone`);

--
-- Indexes for table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_expenses_date` (`expense_date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `general_parties`
--
ALTER TABLE `general_parties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `general_transactions`
--
ALTER TABLE `general_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `party_id` (`party_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_journal_date` (`entry_date`);

--
-- Indexes for table `journal_entry_items`
--
ALTER TABLE `journal_entry_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_id` (`journal_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_payments_date` (`payment_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `idx_products_code` (`code`),
  ADD KEY `idx_products_name` (`name`),
  ADD KEY `idx_products_group` (`product_type`,`brand_id`,`model_name`,`ram`,`storage`,`color`);

--
-- Indexes for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_serial_imei` (`imei_number`),
  ADD UNIQUE KEY `uq_serial_imei2` (`serial_number`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_serials_imei` (`imei_number`),
  ADD KEY `idx_serials_imei2` (`serial_number`),
  ADD KEY `idx_serials_status` (`status`),
  ADD KEY `idx_serials_seller` (`seller_cnic`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_purchase_invoice` (`invoice_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `discount_id` (`discount_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_sales_invoice` (`invoice_no`),
  ADD KEY `idx_sales_customer` (`customer_id`),
  ADD KEY `idx_sales_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `serial_id` (`serial_id`);

--
-- Indexes for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `branch_id` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_options`
--
ALTER TABLE `app_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `cash_book`
--
ALTER TABLE `cash_book`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customer_documents`
--
ALTER TABLE `customer_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `general_parties`
--
ALTER TABLE `general_parties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `general_transactions`
--
ALTER TABLE `general_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entry_items`
--
ALTER TABLE `journal_entry_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `product_serials`
--
ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sale_returns`
--
ALTER TABLE `sale_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_book`
--
ALTER TABLE `cash_book`
  ADD CONSTRAINT `cash_book_ibfk_1` FOREIGN KEY (`daily_id`) REFERENCES `cash_book_daily` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cash_book_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_book_daily`
--
ALTER TABLE `cash_book_daily`
  ADD CONSTRAINT `cash_book_daily_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD CONSTRAINT `chart_of_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD CONSTRAINT `customer_documents_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `general_transactions`
--
ALTER TABLE `general_transactions`
  ADD CONSTRAINT `general_transactions_ibfk_1` FOREIGN KEY (`party_id`) REFERENCES `general_parties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `general_transactions_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `general_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_entry_items`
--
ALTER TABLE `journal_entry_items`
  ADD CONSTRAINT `journal_entry_items_ibfk_1` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `journal_entry_items_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_returns`
--
ALTER TABLE `sale_returns`
  ADD CONSTRAINT `sale_returns_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sale_returns_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sale_returns_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_returns_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_payments`
--
ALTER TABLE `supplier_payments`
  ADD CONSTRAINT `supplier_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `supplier_payments_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `supplier_payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
