-- Tokens de acesso da API REST v1.
-- O token em texto puro nunca é persistido; somente o hash SHA-256.

CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    escopos VARCHAR(255) NOT NULL DEFAULT 'leitura',
    expira_em DATETIME NULL,
    ultimo_uso_em DATETIME NULL,
    revogado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_tokens_hash (token_hash),
    KEY idx_api_tokens_usuario (usuario_id, revogado_em),
    KEY idx_api_tokens_validade (expira_em, revogado_em),
    CONSTRAINT fk_api_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
