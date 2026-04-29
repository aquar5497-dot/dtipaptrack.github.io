-- =====================================================
-- DTI PAPtrack — Migration: Document Drafts Table
-- Run this once on your dtipaptrack database
-- =====================================================

-- (Optional) Adds a server-side draft storage table.
-- The current implementation uses browser localStorage,
-- so this table is optional — only needed if you want
-- drafts to persist across devices / browsers.

CREATE TABLE IF NOT EXISTS `document_drafts` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL,
  `doc_type`    ENUM('pr','rfq','po','iar','dv') NOT NULL,
  `doc_id`      INT(11) NOT NULL,
  `draft_data`  LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
                CHECK (json_valid(`draft_data`)),
  `is_final`    TINYINT(1) NOT NULL DEFAULT 0,
  `saved_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_doc` (`user_id`, `doc_type`, `doc_id`),
  KEY `idx_doc_type_id` (`doc_type`, `doc_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- No other schema changes required.
-- The view file permission system uses the existing
-- `users.permissions` JSON column already in the database.
-- =====================================================
