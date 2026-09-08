-- =====================================================================
-- SmartControl - atualização de banco existente
-- Alvo principal: MariaDB 10.4+ / XAMPP.
-- Faça backup do banco antes de executar qualquer migração.
-- =====================================================================

USE smartcontrol;

-- Campos adicionados nas versões mais recentes.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email_verificado_em DATETIME NULL AFTER email;

-- Usuários que já existiam antes da verificação de e-mail continuam válidos.
UPDATE usuarios
SET email_verificado_em = COALESCE(email_verificado_em, data_cadastro, NOW())
WHERE email_verificado_em IS NULL;

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS sku VARCHAR(20) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS codigo_barras VARCHAR(50) NULL AFTER sku;

UPDATE produtos
SET sku = CONCAT('SC-', LPAD(id, 6, '0'))
WHERE sku IS NULL OR TRIM(sku) = '';

CREATE TABLE IF NOT EXISTS tentativas_login (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tentativas_bloqueio (email, ip, sucesso, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recuperacoes_senha (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_recuperacoes_token (token_hash),
    KEY idx_recuperacoes_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verificacoes_email (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_verificacoes_token (token_hash),
    KEY idx_verificacoes_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    usuario_nome VARCHAR(100) NULL,
    acao VARCHAR(50) NOT NULL,
    modulo VARCHAR(80) NOT NULL,
    registro_id BIGINT NULL,
    descricao VARCHAR(500) NOT NULL,
    dados_anteriores LONGTEXT NULL,
    dados_novos LONGTEXT NULL,
    ip VARCHAR(45) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auditoria_usuario (usuario_id),
    KEY idx_auditoria_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('info', 'sucesso', 'alerta', 'critico') NOT NULL DEFAULT 'info',
    titulo VARCHAR(150) NOT NULL,
    mensagem VARCHAR(500) NOT NULL,
    entidade VARCHAR(50) NULL,
    entidade_id BIGINT NULL,
    link VARCHAR(255) NULL,
    lida_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notificacoes_usuario (usuario_id, lida_em, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    status ENUM('aberto', 'finalizado', 'cancelado') NOT NULL DEFAULT 'aberto',
    observacao VARCHAR(500) NULL,
    criado_por INT UNSIGNED NULL,
    criado_por_nome VARCHAR(100) NOT NULL,
    finalizado_por INT UNSIGNED NULL,
    finalizado_por_nome VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    KEY idx_inventarios_status (status),
    KEY idx_inventarios_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventario_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventario_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    estoque_sistema INT NOT NULL,
    quantidade_contada INT NULL,
    diferenca INT NULL,
    contado_por INT UNSIGNED NULL,
    contado_por_nome VARCHAR(100) NULL,
    contado_em DATETIME NULL,
    ajustado TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_inventario_produto (inventario_id, produto_id),
    KEY idx_inventario_itens_produto (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anexos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entidade VARCHAR(50) NOT NULL,
    entidade_id BIGINT UNSIGNED NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    extensao VARCHAR(12) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    usuario_nome VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_anexos_nome_arquivo (nome_arquivo),
    KEY idx_anexos_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
