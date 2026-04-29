-- =============================================================
-- DTI PAPtrack — Document Tables Migration
-- Run this SQL file once on your MySQL database: dtipaptrack
-- =============================================================

-- Add office_section column to purchase_requests (if not exists)
ALTER TABLE `purchase_requests`
  ADD COLUMN IF NOT EXISTS `office_section` VARCHAR(255) DEFAULT NULL AFTER `purpose`;

-- pr_document: line items for each Purchase Request
CREATE TABLE IF NOT EXISTS `pr_document` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `pr_id`            INT(11)        NOT NULL,
  `stock_property_no`VARCHAR(100)   DEFAULT NULL,
  `unit`             VARCHAR(100)   DEFAULT NULL,
  `item_description` TEXT           DEFAULT NULL,
  `quantity`         DECIMAL(15,4)  DEFAULT NULL,
  `unit_cost`        DECIMAL(15,4)  DEFAULT NULL,
  `total_cost`       DECIMAL(15,4)  DEFAULT NULL,
  `sort_order`       INT(11)        DEFAULT 0,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pr_document_pr_id` (`pr_id`),
  CONSTRAINT `pr_document_ibfk_1`
    FOREIGN KEY (`pr_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- rfq_document: line items for each Request for Quotation
CREATE TABLE IF NOT EXISTS `rfq_document` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `rfq_id`           INT(11)        NOT NULL,
  `item_description` TEXT           DEFAULT NULL,
  `qty`              DECIMAL(15,4)  DEFAULT NULL,
  `unit`             VARCHAR(100)   DEFAULT NULL,
  `unit_price`       DECIMAL(15,4)  DEFAULT NULL,
  `sort_order`       INT(11)        DEFAULT 0,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rfq_document_rfq_id` (`rfq_id`),
  CONSTRAINT `rfq_document_ibfk_1`
    FOREIGN KEY (`rfq_id`) REFERENCES `rfqs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- po_document: line items for each Purchase Order
CREATE TABLE IF NOT EXISTS `po_document` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `po_id`            INT(11)        NOT NULL,
  `stock_property_no`VARCHAR(100)   DEFAULT NULL,
  `unit`             VARCHAR(100)   DEFAULT NULL,
  `item_description` TEXT           DEFAULT NULL,
  `quantity`         DECIMAL(15,4)  DEFAULT NULL,
  `unit_cost`        DECIMAL(15,4)  DEFAULT NULL,
  `total_cost`       DECIMAL(15,4)  DEFAULT NULL,
  `sort_order`       INT(11)        DEFAULT 0,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_po_document_po_id` (`po_id`),
  CONSTRAINT `po_document_ibfk_1`
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- iar_document: line items for each Inspection and Acceptance Report
CREATE TABLE IF NOT EXISTS `iar_document` (
  `id`               INT(11)        NOT NULL AUTO_INCREMENT,
  `iar_id`           INT(11)        NOT NULL,
  `stock_property_no`VARCHAR(100)   DEFAULT NULL,
  `unit`             VARCHAR(100)   DEFAULT NULL,
  `item_description` TEXT           DEFAULT NULL,
  `quantity`         DECIMAL(15,4)  DEFAULT NULL,
  `unit_cost`        DECIMAL(15,4)  DEFAULT NULL,
  `total_cost`       DECIMAL(15,4)  DEFAULT NULL,
  `sort_order`       INT(11)        DEFAULT 0,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_iar_document_iar_id` (`iar_id`),
  CONSTRAINT `iar_document_ibfk_1`
    FOREIGN KEY (`iar_id`) REFERENCES `iars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- dv_document: accounting entries for each Disbursement Voucher
CREATE TABLE IF NOT EXISTS `dv_document` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `dv_id`         INT(11)       NOT NULL,
  `account_title` VARCHAR(255)  DEFAULT NULL,
  `uacs_code`     VARCHAR(50)   DEFAULT NULL,
  `debit`         DECIMAL(15,4) DEFAULT NULL,
  `credit`        DECIMAL(15,4) DEFAULT NULL,
  `sort_order`    INT(11)       DEFAULT 0,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dv_document_dv_id` (`dv_id`),
  CONSTRAINT `dv_document_ibfk_1`
    FOREIGN KEY (`dv_id`) REFERENCES `disbursement_vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
