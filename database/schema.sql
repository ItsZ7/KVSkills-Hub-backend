SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL UNIQUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zone_id BIGINT UNSIGNED NULL,
    skill_id BIGINT UNSIGNED NULL,
    role ENUM('coach','technical','admin') NOT NULL,
    status ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    ic_number VARCHAR(20) NULL,
    institution VARCHAR(200) NULL,
    image_path VARCHAR(500) NULL,
    shirt_size VARCHAR(10) NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_users_role_status (role,status),
    INDEX idx_users_zone (zone_id),
    INDEX idx_users_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    level ENUM('zone','national') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    host_zone VARCHAR(100) NULL,
    status ENUM('draft','registration','ongoing','completed','archived') NOT NULL DEFAULT 'draft',
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL UNIQUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    assistant_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    model_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    assistant_note VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(220) NOT NULL UNIQUE,
    address TEXT NULL,
    map_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competition_skills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    skill_category ENUM('heavy','light') NULL,
    registration_open_at DATETIME NULL,
    registration_close_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_competition_skill (competition_id,skill_id),
    CONSTRAINT fk_cs_competition FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cs_venue FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS briefings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skill_id BIGINT UNSIGNED NOT NULL,
    level ENUM('zone','national') NOT NULL,
    briefing_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    meeting_url VARCHAR(500) NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_briefing (skill_id,level,briefing_date,start_time),
    CONSTRAINT fk_briefing_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    INDEX idx_briefing_date (briefing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skill_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    category ENUM('official_letter','briefing','national_schedule','national_schedule_source','question','result','other') NOT NULL,
    file_path VARCHAR(500) NOT NULL UNIQUE,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    visibility ENUM('public','authenticated','private') NOT NULL DEFAULT 'private',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_documents_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_documents_category_visibility (category,visibility,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    zone_id BIGINT UNSIGNED NOT NULL,
    skill_id BIGINT UNSIGNED NOT NULL,
    coach_user_id BIGINT UNSIGNED NOT NULL,
    participant_name VARCHAR(180) NOT NULL,
    ic_number VARCHAR(20) NULL,
    participant_email VARCHAR(190) NULL,
    participant_phone VARCHAR(30) NULL,
    institution VARCHAR(200) NOT NULL,
    image_path VARCHAR(500) NULL,
    shirt_size VARCHAR(10) NULL,
    status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
    rejection_reason VARCHAR(500) NULL,
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_comp_zone_skill (competition_id,zone_id,skill_id),
    CONSTRAINT fk_reg_competition FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_zone FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reg_skill FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reg_coach FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_reg_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reg_status (status),
    INDEX idx_reg_coach (coach_user_id),
    INDEX idx_reg_public (competition_id,status,skill_id,zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_helpers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL,
    helper_type ENUM('assistant','model') NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_helper_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    INDEX idx_helper_registration_type (registration_id,helper_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id BIGINT UNSIGNED NOT NULL UNIQUE,
    score DECIMAL(6,2) NOT NULL,
    skill_category ENUM('heavy','light') NOT NULL,
    award ENUM('Emas','Perak','Gangsa','Medallion','Tiada') NOT NULL DEFAULT 'Tiada',
    rank_position SMALLINT UNSIGNED NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    entered_by BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_user FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (score >= 0 AND score <= 100),
    INDEX idx_results_public (is_published,award,rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS award_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('heavy','light') NOT NULL,
    award ENUM('Emas','Perak','Gangsa','Medallion') NOT NULL,
    min_score DECIMAL(6,2) NOT NULL,
    max_score DECIMAL(6,2) NOT NULL,
    UNIQUE KEY uq_award_rule (category,award)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type,entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT NULL,
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, description)
VALUES ('pt_score_entry_enabled', '1', 'Kebenaran kemasukan markah oleh Pegawai Teknikal (1 = Dibuka, 0 = Dikunci)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

SET FOREIGN_KEY_CHECKS=1;

