-- ============================================================
-- ResumeCraft — Admin Features Migration
-- Run after database.sql and migration_plan_settings.sql
-- ============================================================

-- Site-wide settings (app name, logo, tagline, etc.)
CREATE TABLE IF NOT EXISTS site_settings (
    `key`        VARCHAR(100)   NOT NULL,
    `value`      TEXT           DEFAULT NULL,
    updated_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Key-value store for admin-editable site settings.';

-- Seed defaults
INSERT INTO site_settings (`key`, `value`) VALUES
('app_name',    'ResumeCraft'),
('app_tagline', 'Build ATS-Ready Resumes in Minutes'),
('app_logo_url',''),
('primary_color','#6366f1'),
('support_email','support@example.com'),
('allow_registration','1'),
('maintenance_mode','0')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Activity / audit log
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED  DEFAULT NULL,
    action     VARCHAR(100)  NOT NULL,
    entity     VARCHAR(50)   DEFAULT NULL  COMMENT 'e.g. resume, user, plan',
    entity_id  INT UNSIGNED  DEFAULT NULL,
    detail     TEXT          DEFAULT NULL,
    ip         VARCHAR(45)   DEFAULT NULL,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user   (user_id),
    INDEX idx_action (action),
    INDEX idx_time   (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Audit trail for all significant admin and user actions.';
