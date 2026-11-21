-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2017 at 06:48 AM
-- Server version: 10.1.26-MariaDB
-- PHP Version: 7.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `commdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `book`
--

CREATE TABLE `commission` (
  `commissions` double NOT NULL,
  `penalties` double NOT NULL,
  `services` double NOT NULL,
  `totals` double NOT NULL,
  `ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `book`
--
ALTER TABLE `commission`
  ADD PRIMARY KEY (`ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `book`
--
ALTER TABLE `commission`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Service amounts are separate from commission data. Amounts use integer cents.
CREATE TABLE IF NOT EXISTS service_amounts (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (restaurant, period, slot_number),
    CONSTRAINT chk_service_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_service_slot CHECK (
        (period = 'weekly' AND slot_number BETWEEN 1 AND 7)
        OR (period = 'monthly' AND slot_number BETWEEN 1 AND 4)
    ),
    CONSTRAINT chk_service_amount CHECK (amount_cents <= 999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run once against the existing foody database; no commission data is changed.
CREATE TABLE IF NOT EXISTS report_amounts (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    amount_cents BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (restaurant, period, slot_number),
    CONSTRAINT chk_report_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_report_slot CHECK (
        (period = 'weekly' AND slot_number BETWEEN 1 AND 7)
        OR (period = 'monthly' AND slot_number BETWEEN 1 AND 4)
    ),
    CONSTRAINT chk_report_amount CHECK (amount_cents <= 999999999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserves legacy commission rows, which have no restaurant/module/period identity.
CREATE TABLE IF NOT EXISTS commission_records (
    restaurant VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    source_module VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    period VARCHAR(7) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    gross_cents BIGINT UNSIGNED NOT NULL,
    penalty_cents BIGINT UNSIGNED NOT NULL,
    delivery_cents BIGINT UNSIGNED NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (restaurant, source_module, period),
    CONSTRAINT chk_commission_restaurant CHECK (restaurant IN ('jamilah', 'greenview', 'lepak')),
    CONSTRAINT chk_commission_module CHECK (source_module IN ('service', 'report')),
    CONSTRAINT chk_commission_period CHECK (period IN ('weekly', 'monthly')),
    CONSTRAINT chk_commission_money CHECK (gross_cents <= 999999999 AND penalty_cents <= 999999999 AND delivery_cents <= 999999999),
    CONSTRAINT chk_commission_revision CHECK (revision >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
