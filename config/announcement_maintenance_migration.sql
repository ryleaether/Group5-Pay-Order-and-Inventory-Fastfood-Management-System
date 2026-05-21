-- ============================================================
--  Global Announcement & Maintenance Mode
--  Run this once against ipos_db
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT         NULL,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    INT          NULL,
    FOREIGN KEY (updated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- Default rows
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('announcement_enabled',  '0'),
    ('announcement_message',  ''),
    ('announcement_type',     'info'),   -- info | warning | success | danger
    ('maintenance_enabled',   '0'),
    ('maintenance_message',   'We are currently performing scheduled maintenance. We will be back shortly. Thank you for your patience.'),
    ('maintenance_end_time',  NULL);
