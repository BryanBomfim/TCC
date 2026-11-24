-- Execute no MESMO banco smartcontrol já usado pelo site.
-- Migration aditiva e repetível: não apaga nem altera os dados do site.
CREATE TABLE IF NOT EXISTS mobile_api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    auth_version INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    revogado_em DATETIME NULL,
    INDEX idx_mobile_tokens_usuario (usuario_id),
    INDEX idx_mobile_tokens_expira (expira_em),
    CONSTRAINT fk_mobile_tokens_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE mobile_api_tokens
    ADD COLUMN IF NOT EXISTS auth_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER token_hash;

CREATE TABLE IF NOT EXISTS mobile_api_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile_login_email (email_hash, criado_em),
    INDEX idx_mobile_login_ip (ip_hash, criado_em)
) ENGINE=InnoDB;
