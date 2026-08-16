-- Non-destructive enrichment for audit_logs (26AGS015)
-- Safe to run on existing DB. Re-run may error if columns already exist — prefer scripts/migrate_audit_logs.php

ALTER TABLE `audit_logs`
  ADD COLUMN `module` VARCHAR(50) NULL DEFAULT NULL AFTER `action`,
  ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL AFTER `entity_id`,
  ADD COLUMN `old_values` JSON NULL AFTER `description`,
  ADD COLUMN `new_values` JSON NULL AFTER `old_values`;

ALTER TABLE `audit_logs`
  ADD KEY `idx_audit_logs_module` (`module`),
  ADD KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`);
