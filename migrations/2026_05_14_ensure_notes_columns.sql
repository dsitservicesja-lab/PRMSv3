-- ============================================================
-- Migration: 2026_05_14_ensure_notes_columns.sql
-- Purpose  : Ensure legacy databases include `notes` columns
--            required by current INSERT statements.
-- ============================================================

-- 1) audit_log.notes (used widely by workflow/audit inserts)
SET @has_audit_notes := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_log'
      AND column_name = 'notes'
);
SET @sql := IF(@has_audit_notes > 0,
    'SELECT ''Column audit_log.notes already exists''',
    'ALTER TABLE `audit_log` ADD COLUMN `notes` TEXT NULL AFTER `change_date`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) request_documents.notes (used by procurement document upload)
SET @has_request_doc_notes := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'request_documents'
      AND column_name = 'notes'
);
SET @sql := IF(@has_request_doc_notes > 0,
    'SELECT ''Column request_documents.notes already exists''',
    'ALTER TABLE `request_documents` ADD COLUMN `notes` TEXT NULL AFTER `uploaded_at`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Log migration
INSERT INTO `audit_log` (`table_name`, `record_id`, `action`, `changed_by`, `notes`)
VALUES ('MIGRATION', NULL, 'SCHEMA_FIX', 'system',
        '2026_05_14_ensure_notes_columns applied');
