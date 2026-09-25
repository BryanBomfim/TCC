-- =====================================================================
-- SmartControl - banco de dados completo
-- Compatível com MySQL 8.0.16+ e MariaDB 10.4+
-- Use este arquivo para uma instalação NOVA do sistema.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smartcontrol
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smartcontrol;

SET NAMES utf8mb4;
-- Sem DROP: a execução sobre uma base existente deve ser interrompida.
-- Para atualizar, utilize somente a migração aplicável à sua versão.

-- ---------------------------------------------------------------------
-- Usuários
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    email_verificado_em DATETIME NULL,
    senha VARCHAR(255) NOT NULL,
    auth_version INT UNSIGNED NOT NULL DEFAULT 0,
    perfil ENUM('administrador', 'funcionario') NOT NULL DEFAULT 'funcionario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_ativo (ativo),
    KEY idx_usuarios_perfil (perfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Categorias
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_categorias_nome (nome),
    KEY idx_categorias_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fornecedores
-- ---------------------------------------------------------------------
CREATE TABLE fornecedores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    razao_social VARCHAR(180) NULL,
    cnpj_cpf VARCHAR(20) NULL,
    telefone VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    cep CHAR(8) NULL,
    logradouro VARCHAR(180) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    endereco VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fornecedores_nome (nome),
    KEY idx_fornecedores_ativo (ativo),
    UNIQUE KEY uk_fornecedores_cnpj_cpf (cnpj_cpf),
    KEY idx_fornecedores_cep (cep)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Produtos
-- ---------------------------------------------------------------------
CREATE TABLE produtos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(20) NULL,
    codigo_barras VARCHAR(50) NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria_id INT UNSIGNED NULL,
    fornecedor_id INT UNSIGNED NULL,
    preco_custo DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    preco_venda DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantidade_estoque INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0,
    unidade VARCHAR(20) NOT NULL DEFAULT 'UN',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_produtos_sku (sku),
    UNIQUE KEY uk_produtos_codigo_barras (codigo_barras),
    KEY idx_produtos_nome (nome),
    KEY idx_produtos_ativo (ativo),
    KEY idx_produtos_categoria (categoria_id),
    KEY idx_produtos_fornecedor (fornecedor_id),
    CONSTRAINT fk_produtos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_produtos_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Movimentações de estoque
-- ---------------------------------------------------------------------
CREATE TABLE movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    fornecedor_id INT UNSIGNED NULL,
    inventario_id INT UNSIGNED NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    saldo_anterior INT NOT NULL,
    saldo_posterior INT NOT NULL,
    valor_unitario DECIMAL(10, 2) NULL,
    motivo VARCHAR(100) NULL,
    origem VARCHAR(30) NOT NULL DEFAULT 'manual',
    observacao VARCHAR(255) NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mov_produto (produto_id),
    KEY idx_mov_usuario (usuario_id),
    KEY idx_mov_fornecedor (fornecedor_id),
    KEY idx_mov_inventario (inventario_id),
    KEY idx_mov_tipo (tipo),
    KEY idx_mov_origem (origem),
    KEY idx_mov_data (data_movimentacao),
    CONSTRAINT fk_mov_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_mov_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações gerais
-- ---------------------------------------------------------------------
CREATE TABLE configuracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    valor VARCHAR(255) NULL,
    UNIQUE KEY uk_configuracoes_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Segurança de login
-- ---------------------------------------------------------------------
CREATE TABLE tentativas_login (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tentativas_bloqueio (email, ip, sucesso, criado_em),
    KEY idx_tentativas_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE limites_requisicao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao VARCHAR(50) NOT NULL,
    identificador_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_limites_consulta (acao, identificador_hash, ip, criado_em),
    KEY idx_limites_limpeza (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_tokens (
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

CREATE TABLE recuperacoes_senha (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_recuperacoes_token (token_hash),
    KEY idx_recuperacoes_usuario (usuario_id),
    KEY idx_recuperacoes_validade (expira_em, utilizado_em),
    CONSTRAINT fk_recuperacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE verificacoes_email (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_verificacoes_token (token_hash),
    KEY idx_verificacoes_usuario (usuario_id),
    KEY idx_verificacoes_validade (expira_em, utilizado_em),
    CONSTRAINT fk_verificacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Auditoria
-- ---------------------------------------------------------------------
CREATE TABLE logs_auditoria (
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
    KEY idx_auditoria_acao (acao),
    KEY idx_auditoria_modulo (modulo),
    KEY idx_auditoria_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Notificações
-- ---------------------------------------------------------------------
CREATE TABLE notificacoes (
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
    KEY idx_notificacoes_usuario (usuario_id, lida_em, criado_em),
    CONSTRAINT fk_notificacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Inventário físico
-- ---------------------------------------------------------------------
CREATE TABLE inventarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revisao INT UNSIGNED NOT NULL DEFAULT 0,
    titulo VARCHAR(150) NOT NULL,
    status ENUM('aberto', 'finalizado', 'cancelado') NOT NULL DEFAULT 'aberto',
    observacao VARCHAR(500) NULL,
    criado_por INT UNSIGNED NULL,
    criado_por_nome VARCHAR(100) NOT NULL,
    finalizado_por INT UNSIGNED NULL,
    finalizado_por_nome VARCHAR(100) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    inventario_aberto_unico TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN status = 'aberto' THEN 1 ELSE NULL END
        ) STORED,
    UNIQUE KEY uk_inventarios_um_aberto (inventario_aberto_unico),
    KEY idx_inventarios_status (status),
    KEY idx_inventarios_criado_em (criado_em),
    CONSTRAINT fk_inventarios_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_inventarios_finalizado_por
        FOREIGN KEY (finalizado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE movimentacoes
    ADD CONSTRAINT fk_mov_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE inventario_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventario_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    ultimo_movimento_id BIGINT NOT NULL DEFAULT 0,
    estoque_sistema INT NOT NULL,
    quantidade_contada INT NULL,
    diferenca INT NULL,
    contado_por INT UNSIGNED NULL,
    contado_por_nome VARCHAR(100) NULL,
    contado_em DATETIME NULL,
    ajustado TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_inventario_produto (inventario_id, produto_id),
    KEY idx_inventario_itens_produto (produto_id),
    CONSTRAINT fk_inventario_itens_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_inventario_itens_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inventario_itens_contado_por
        FOREIGN KEY (contado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Anexos de produtos
-- ---------------------------------------------------------------------
CREATE TABLE anexos (
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
    KEY idx_anexos_entidade (entidade, entidade_id),
    KEY idx_anexos_usuario (usuario_id),
    CONSTRAINT fk_anexos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dados iniciais
-- Senha temporária do administrador de demonstração: SmartControl@2026!
-- Troque a senha após o primeiro acesso em "Minha Conta".
-- ---------------------------------------------------------------------
INSERT INTO usuarios
    (nome, email, email_verificado_em, senha, perfil, ativo)
VALUES
    (
        'Administrador',
        'admin@smartcontrol.com',
        NOW(),
        '$2y$12$3aTIwaP3U3POvCo47Ub35embXnQ7Si/Kj6YBIjH7HeOakHh1RnHBG',
        'administrador',
        1
    );

INSERT INTO categorias (nome, descricao, ativo) VALUES
    ('Informática', 'Produtos de informática em geral', 1),
    ('Escritório', 'Materiais de escritório', 1),
    ('Limpeza', 'Produtos de limpeza', 1);

INSERT INTO fornecedores
    (nome, cnpj_cpf, telefone, email, endereco, ativo)
VALUES
    (
        'Distribuidora Tech LTDA',
        '12345678000190',
        '(13) 3421-1234',
        'contato@distech.com',
        'Rua das Indústrias, 100 - Itanhaém/SP',
        1
    ),
    (
        'Papelaria Central',
        '98765432000110',
        '(13) 3421-5678',
        'vendas@papelariacentral.com',
        'Av. Central, 500 - Itanhaém/SP',
        1
    );

INSERT INTO configuracoes (chave, valor) VALUES
    ('nome_sistema', 'SmartControl'),
    ('nome_empresa', 'Minha Empresa'),
    ('estoque_minimo_padrao', '5'),
    ('limite_movimentacao_incomum', '100');
