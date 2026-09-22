-- =====================================================================
-- SmartControl - profissionalização do TCC
-- Data: 2026-09-22
-- Alvo: MariaDB 10.4+ / XAMPP
-- Execute uma única vez após fazer backup do banco.
-- =====================================================================

USE smartcontrol;

ALTER TABLE fornecedores
    ADD COLUMN IF NOT EXISTS cep VARCHAR(9) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS logradouro VARCHAR(150) NULL AFTER cep,
    ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL AFTER logradouro,
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero,
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) NULL AFTER complemento,
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(100) NULL AFTER bairro,
    ADD COLUMN IF NOT EXISTS uf CHAR(2) NULL AFTER cidade;

ALTER TABLE movimentacoes
    ADD COLUMN IF NOT EXISTS fornecedor_id INT UNSIGNED NULL AFTER usuario_id,
    ADD COLUMN IF NOT EXISTS saldo_anterior INT NULL AFTER quantidade,
    ADD COLUMN IF NOT EXISTS saldo_posterior INT NULL AFTER saldo_anterior,
    ADD COLUMN IF NOT EXISTS origem VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER saldo_posterior,
    ADD COLUMN IF NOT EXISTS motivo VARCHAR(100) NULL AFTER origem,
    ADD COLUMN IF NOT EXISTS valor_unitario DECIMAL(10, 2) NULL AFTER motivo,
    ADD COLUMN IF NOT EXISTS referencia VARCHAR(100) NULL AFTER valor_unitario;

ALTER TABLE movimentacoes
    MODIFY observacao VARCHAR(500) NULL;

-- Movimentações antigas permanecem com saldos nulos, pois o saldo histórico
-- não pode ser reconstruído com segurança apenas a partir do estado atual.

CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NULL,
    ultimo_uso_em DATETIME NULL,
    revogado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_tokens_hash (token_hash),
    KEY idx_api_tokens_usuario (usuario_id),
    KEY idx_api_tokens_validade (expira_em, revogado_em),
    CONSTRAINT fk_api_tokens_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices acrescentados de forma condicional para instalações antigas.
SET @idx_fornecedor_doc = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fornecedores' AND INDEX_NAME = 'idx_fornecedores_documento'
);
SET @sql = IF(@idx_fornecedor_doc = 0,
    'CREATE INDEX idx_fornecedores_documento ON fornecedores (cnpj_cpf)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_mov_fornecedor = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes' AND INDEX_NAME = 'idx_mov_fornecedor'
);
SET @sql = IF(@idx_mov_fornecedor = 0,
    'CREATE INDEX idx_mov_fornecedor ON movimentacoes (fornecedor_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_mov_origem = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes' AND INDEX_NAME = 'idx_mov_origem'
);
SET @sql = IF(@idx_mov_origem = 0,
    'CREATE INDEX idx_mov_origem ON movimentacoes (origem)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- A coluna fornecedor_id é nova e inicia nula nos registros legados,
-- portanto a FK pode ser adicionada sem inventar relacionamento histórico.
SET @fk_mov_fornecedor = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'movimentacoes'
      AND CONSTRAINT_NAME = 'fk_mov_fornecedor'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@fk_mov_fornecedor = 0,
    'ALTER TABLE movimentacoes ADD CONSTRAINT fk_mov_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- Integridade adicional para instalações atualizadas
-- As FKs só são criadas quando não existem registros órfãos.
-- Nada é apagado automaticamente pela migração.
-- =====================================================================

-- Índices úteis ausentes em instalações legadas.
SET @idx_tentativas_data = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tentativas_login' AND INDEX_NAME = 'idx_tentativas_criado_em'
);
SET @sql = IF(@idx_tentativas_data = 0,
    'CREATE INDEX idx_tentativas_criado_em ON tentativas_login (criado_em)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_rec_validade = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacoes_senha' AND INDEX_NAME = 'idx_recuperacoes_validade'
);
SET @sql = IF(@idx_rec_validade = 0,
    'CREATE INDEX idx_recuperacoes_validade ON recuperacoes_senha (expira_em, utilizado_em)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_ver_validade = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'verificacoes_email' AND INDEX_NAME = 'idx_verificacoes_validade'
);
SET @sql = IF(@idx_ver_validade = 0,
    'CREATE INDEX idx_verificacoes_validade ON verificacoes_email (expira_em, utilizado_em)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_audit_acao = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND INDEX_NAME = 'idx_auditoria_acao'
);
SET @sql = IF(@idx_audit_acao = 0,
    'CREATE INDEX idx_auditoria_acao ON logs_auditoria (acao)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_audit_modulo = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND INDEX_NAME = 'idx_auditoria_modulo'
);
SET @sql = IF(@idx_audit_modulo = 0,
    'CREATE INDEX idx_auditoria_modulo ON logs_auditoria (modulo)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_anexo_usuario = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anexos' AND INDEX_NAME = 'idx_anexos_usuario'
);
SET @sql = IF(@idx_anexo_usuario = 0,
    'CREATE INDEX idx_anexos_usuario ON anexos (usuario_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Unicidade de SKU/código de barras: só aplica quando a base está limpa.
SET @dup_sku = (
    SELECT COUNT(*) FROM (
        SELECT sku FROM produtos
        WHERE sku IS NOT NULL AND TRIM(sku) <> ''
        GROUP BY sku HAVING COUNT(*) > 1
    ) AS duplicados
);
SET @idx_sku = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND INDEX_NAME = 'uk_produtos_sku'
);
SET @sql = IF(@idx_sku = 0 AND @dup_sku = 0,
    'CREATE UNIQUE INDEX uk_produtos_sku ON produtos (sku)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dup_codigo = (
    SELECT COUNT(*) FROM (
        SELECT codigo_barras FROM produtos
        WHERE codigo_barras IS NOT NULL AND TRIM(codigo_barras) <> ''
        GROUP BY codigo_barras HAVING COUNT(*) > 1
    ) AS duplicados
);
SET @idx_codigo = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produtos' AND INDEX_NAME = 'uk_produtos_codigo_barras'
);
SET @sql = IF(@idx_codigo = 0 AND @dup_codigo = 0,
    'CREATE UNIQUE INDEX uk_produtos_codigo_barras ON produtos (codigo_barras)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FKs de segurança e rastreabilidade. Em caso de órfãos, a constraint é
-- simplesmente adiada até que database/check_integrity.sql seja revisado.
SET @orf = (SELECT COUNT(*) FROM movimentacoes m LEFT JOIN produtos p ON p.id = m.produto_id WHERE p.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes' AND CONSTRAINT_NAME = 'fk_mov_produto' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE movimentacoes ADD CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM movimentacoes m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'movimentacoes' AND CONSTRAINT_NAME = 'fk_mov_usuario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE movimentacoes ADD CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM recuperacoes_senha r LEFT JOIN usuarios u ON u.id = r.usuario_id WHERE u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacoes_senha' AND CONSTRAINT_NAME = 'fk_recuperacoes_usuario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE recuperacoes_senha ADD CONSTRAINT fk_recuperacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM verificacoes_email v LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'verificacoes_email' AND CONSTRAINT_NAME = 'fk_verificacoes_usuario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE verificacoes_email ADD CONSTRAINT fk_verificacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM notificacoes n LEFT JOIN usuarios u ON u.id = n.usuario_id WHERE u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes' AND CONSTRAINT_NAME = 'fk_notificacoes_usuario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE notificacoes ADD CONSTRAINT fk_notificacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM inventarios i LEFT JOIN usuarios u ON u.id = i.criado_por WHERE i.criado_por IS NOT NULL AND u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventarios' AND CONSTRAINT_NAME = 'fk_inventarios_criado_por' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE inventarios ADD CONSTRAINT fk_inventarios_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM inventarios i LEFT JOIN usuarios u ON u.id = i.finalizado_por WHERE i.finalizado_por IS NOT NULL AND u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventarios' AND CONSTRAINT_NAME = 'fk_inventarios_finalizado_por' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE inventarios ADD CONSTRAINT fk_inventarios_finalizado_por FOREIGN KEY (finalizado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM inventario_itens ii LEFT JOIN inventarios i ON i.id = ii.inventario_id WHERE i.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_itens' AND CONSTRAINT_NAME = 'fk_inventario_itens_inventario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE inventario_itens ADD CONSTRAINT fk_inventario_itens_inventario FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM inventario_itens ii LEFT JOIN produtos p ON p.id = ii.produto_id WHERE p.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_itens' AND CONSTRAINT_NAME = 'fk_inventario_itens_produto' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE inventario_itens ADD CONSTRAINT fk_inventario_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM inventario_itens ii LEFT JOIN usuarios u ON u.id = ii.contado_por WHERE ii.contado_por IS NOT NULL AND u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_itens' AND CONSTRAINT_NAME = 'fk_inventario_itens_contado_por' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE inventario_itens ADD CONSTRAINT fk_inventario_itens_contado_por FOREIGN KEY (contado_por) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @orf = (SELECT COUNT(*) FROM anexos a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE a.usuario_id IS NOT NULL AND u.id IS NULL);
SET @fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'anexos' AND CONSTRAINT_NAME = 'fk_anexos_usuario' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql = IF(@orf = 0 AND @fk = 0,
    'ALTER TABLE anexos ADD CONSTRAINT fk_anexos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
