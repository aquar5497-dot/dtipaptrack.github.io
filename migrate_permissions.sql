-- Migration: Add permissions column to users table
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `permissions` JSON DEFAULT NULL;

-- Add notification tracking columns
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `notif_cleared_at` DATETIME DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `notif_seen_at`    DATETIME DEFAULT NULL;

-- Set default permissions for existing users based on their current role
UPDATE `users` SET `permissions` = '["purchase_request","payroll","quotations","purchase_orders","cancelled_pr","sub_pr","iar","disbursement","payroll_dv","reports"]' WHERE `role` = 'Procurement Section' AND `permissions` IS NULL;
UPDATE `users` SET `permissions` = '["iar"]' WHERE `role` = 'Acceptance Section' AND `permissions` IS NULL;
UPDATE `users` SET `permissions` = '["disbursement","payroll_dv"]' WHERE `role` = 'Processing Section' AND `permissions` IS NULL;
