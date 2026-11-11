CREATE TABLE IF NOT EXISTS auth_limits (
    bucket CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_auth_limit_expiry (expires_at)
) ENGINE=InnoDB;
