-- SmartControl - Fase 5: limitação de solicitações sensíveis

USE smartcontrol;

CREATE TABLE IF NOT EXISTS limites_requisicao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao VARCHAR(50) NOT NULL,
    identificador_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_limites_consulta (acao, identificador_hash, ip, criado_em),
    KEY idx_limites_limpeza (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor)
VALUES ('migration_2026_09_21_limites_requisicao', NOW())
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

