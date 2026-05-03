-- ============================================================
-- ResumeCraft — Plan Settings Migration
-- Run once after the initial database.sql has been imported.
-- ============================================================

-- Plan-level limits (admin-editable)
CREATE TABLE IF NOT EXISTS plan_settings (
    plan              ENUM('free','pro','enterprise') NOT NULL,
    max_resumes       INT          NOT NULL DEFAULT 3   COMMENT '-1 = unlimited',
    max_daily_exports INT          NOT NULL DEFAULT 3   COMMENT '-1 = unlimited',
    exports_enabled   TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (plan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Admin-configurable limits per plan. Managed via admin/plan-settings.php.';

-- Seed with sensible defaults (mirrors config/app.php constants)
INSERT INTO plan_settings (plan, max_resumes, max_daily_exports, exports_enabled) VALUES
('free',       3,  3,  1),
('pro',        20, -1, 1),
('enterprise', -1, -1, 1)
ON DUPLICATE KEY UPDATE plan = plan; -- no-op if already seeded

-- Export audit log (used for daily rate-limiting)
CREATE TABLE IF NOT EXISTS resume_export_log (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    resume_id   INT UNSIGNED  NOT NULL,
    exported_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_date (user_id, exported_at),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (resume_id) REFERENCES resumes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='One row per PDF download. Used to enforce max_daily_exports.';
