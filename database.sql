-- ============================================================
-- Resume Maker - Database Schema
-- Future-ready: UUIDs, roles, plans, sharing, versioning
-- ============================================================

CREATE DATABASE IF NOT EXISTS resume_maker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE resume_maker;

CREATE TABLE users (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    uuid          VARCHAR(36)       NOT NULL UNIQUE,
    name          VARCHAR(100)      NOT NULL,
    email         VARCHAR(150)      NOT NULL UNIQUE,
    password      VARCHAR(255)      NOT NULL,
    avatar        VARCHAR(255)      DEFAULT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    plan          ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
    email_verified_at TIMESTAMP     NULL DEFAULT NULL,
    is_active     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_uuid  (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_sessions (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED      NOT NULL,
    token         VARCHAR(64)       NOT NULL UNIQUE,
    ip_address    VARCHAR(45)       DEFAULT NULL,
    user_agent    TEXT              DEFAULT NULL,
    expires_at    TIMESTAMP         NOT NULL,
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_token   (token),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE templates (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(50)       NOT NULL UNIQUE,
    name          VARCHAR(100)      NOT NULL,
    description   TEXT              DEFAULT NULL,
    thumbnail     VARCHAR(255)      DEFAULT NULL,
    category      ENUM('classic','modern','minimal','creative','professional') NOT NULL DEFAULT 'modern',
    is_ats_friendly TINYINT(1)      NOT NULL DEFAULT 1,
    is_premium    TINYINT(1)        NOT NULL DEFAULT 0,
    is_active     TINYINT(1)        NOT NULL DEFAULT 1,
    sort_order    INT               NOT NULL DEFAULT 0,
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE template_themes (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    template_id   INT UNSIGNED      NOT NULL,
    name          VARCHAR(50)       NOT NULL,
    primary_color VARCHAR(7)        NOT NULL DEFAULT '#2c3e50',
    accent_color  VARCHAR(7)        NOT NULL DEFAULT '#3498db',
    is_default    TINYINT(1)        NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resumes (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    uuid          VARCHAR(36)       NOT NULL UNIQUE,
    user_id       INT UNSIGNED      NOT NULL,
    template_id   INT UNSIGNED      NOT NULL,
    title         VARCHAR(200)      NOT NULL DEFAULT 'My Resume',
    is_public     TINYINT(1)        NOT NULL DEFAULT 0,
    share_token   VARCHAR(32)       DEFAULT NULL,
    last_exported_at TIMESTAMP      NULL DEFAULT NULL,
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_uuid    (uuid),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_sections (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    resume_id     INT UNSIGNED      NOT NULL,
    type          ENUM('personal','summary','experience','education','skills',
                       'certifications','projects','languages','awards',
                       'publications','references','custom') NOT NULL,
    title         VARCHAR(100)      NOT NULL,
    sort_order    INT               NOT NULL DEFAULT 0,
    is_visible    TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    INDEX idx_resume_id (resume_id),
    FOREIGN KEY (resume_id) REFERENCES resumes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_items (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    section_id    INT UNSIGNED      NOT NULL,
    sort_order    INT               NOT NULL DEFAULT 0,
    created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_section_id (section_id),
    FOREIGN KEY (section_id) REFERENCES resume_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_fields (
    id            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    item_id       INT UNSIGNED      NOT NULL,
    field_key     VARCHAR(100)      NOT NULL,
    field_value   MEDIUMTEXT        DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_item_id (item_id),
    FOREIGN KEY (item_id) REFERENCES resume_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE resume_customizations (
    id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    resume_id        INT UNSIGNED   NOT NULL UNIQUE,
    primary_color    VARCHAR(7)     NOT NULL DEFAULT '#2c3e50',
    accent_color     VARCHAR(7)     NOT NULL DEFAULT '#3498db',
    font_heading     VARCHAR(100)   NOT NULL DEFAULT 'Arial',
    font_body        VARCHAR(100)   NOT NULL DEFAULT 'Calibri',
    font_size_heading INT           NOT NULL DEFAULT 16,
    font_size_body   INT           NOT NULL DEFAULT 11,
    line_height      DECIMAL(3,1)  NOT NULL DEFAULT 1.5,
    section_spacing  INT           NOT NULL DEFAULT 16,
    page_margin      INT           NOT NULL DEFAULT 20,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (resume_id) REFERENCES resumes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED: Templates
-- ============================================================
INSERT INTO templates (slug, name, description, category, is_ats_friendly, is_premium, sort_order) VALUES
('classic',       'Classic',        'Timeless single-column layout. Universally ATS-safe.',         'classic',      1, 0, 1),
('modern',        'Modern',         'Clean design with a bold accent header bar.',                   'modern',       1, 0, 2),
('minimal',       'Minimal',        'Maximum whitespace, distraction-free. Highly ATS-safe.',        'minimal',      1, 0, 3),
('sidebar-left',  'Sidebar Left',   'Contact & skills in left sidebar, experience on the right.',    'professional', 1, 0, 4),
('sidebar-right', 'Sidebar Right',  'Main content left, supporting info right sidebar.',             'professional', 1, 0, 5),
('executive',     'Executive',      'Bold header with ruled dividers. Senior professional look.',    'classic',      1, 0, 6),
('tech',          'Tech',           'Compact, skills-forward layout built for developers.',          'modern',       1, 0, 7),
('creative',      'Creative',       'Geometric accent, contemporary typography. Design-friendly.',   'creative',     0, 0, 8);

-- ============================================================
-- SEED: Users  (admin + demo)
-- admin  → admin@example.com  / Admin@123
-- demo   → demo@example.com   / Demo@123
-- ============================================================
INSERT INTO users (uuid, name, email, password, role, plan, email_verified_at, is_active) VALUES
(
    UUID(),
    'Admin',
    'admin@example.com',
    '$2y$12$Rd9OjWgGFijOX9ZX0aZpru463aT0c0QdYHtrd1ri3g6/hZtNL.IeC',
    'admin',
    'enterprise',
    NOW(),
    1
),
(
    UUID(),
    'Demo User',
    'demo@example.com',
    '$2y$12$uZkaMC35SNEx23OQy7NISuHsI1iZY7bbpmxp8HqoOyJTEjm7zPJnm',
    'user',
    'free',
    NOW(),
    1
);

-- ============================================================
INSERT INTO template_themes (template_id, name, primary_color, accent_color, is_default) VALUES
(1,'Navy','#1a2e4a','#2c5f8a',1),(1,'Charcoal','#2d2d2d','#555555',0),(1,'Forest','#1e3a2f','#2d6a4f',0),
(2,'Ocean','#0d3b66','#1e88e5',1),(2,'Slate','#2c3e50','#3498db',0),(2,'Teal','#00514a','#00897b',0),(2,'Burgundy','#6b1e2e','#c0392b',0),
(3,'Black','#111111','#444444',1),(3,'Navy','#1a2e4a','#3a6ea8',0),(3,'Olive','#3d3d00','#6b6b00',0),
(4,'Dark Blue','#1c2951','#2e4a8e',1),(4,'Graphite','#1a1a1a','#4a4a4a',0),(4,'Teal','#00514a','#00796b',0),
(5,'Indigo','#283593','#3949ab',1),(5,'Charcoal','#2d2d2d','#616161',0),(5,'Green','#1b5e20','#388e3c',0),
(6,'Classic','#1a1a1a','#8b6914',1),(6,'Navy','#0d2137','#1565c0',0),
(7,'Dark','#0f1117','#00bcd4',1),(7,'Matrix','#0a1628','#00e676',0),(7,'Purple','#1a0533','#9c27b0',0),
(8,'Coral','#2d2d2d','#e05c4b',1),(8,'Purple','#2d2d2d','#7b1fa2',0),(8,'Teal','#2d2d2d','#009688',0);
