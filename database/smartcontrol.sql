-- ==============================================================
-- SmartControl - Sistema de Gerenciamento de Estoque
-- Script de criação do banco de dados
-- ==============================================================

CREATE DATABASE IF NOT EXISTS smartcontrol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartcontrol;

-- --------------------------------------------------------------
-- Tabela: usuarios
-- --------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('administrador', 'funcionario') NOT NULL DEFAULT 'funcionario',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Tabela: categorias
-- --------------------------------------------------------------
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Tabela: fornecedores
-- --------------------------------------------------------------
CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj_cpf VARCHAR(20) NULL,
    telefone VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    endereco VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Tabela: produtos
-- --------------------------------------------------------------
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria_id INT NULL,
    fornecedor_id INT NULL,
    preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0,
    preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantidade_estoque INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0,
    unidade VARCHAR(20) NOT NULL DEFAULT 'UN',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    CONSTRAINT fk_produtos_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Tabela: movimentacoes (entradas e saídas de estoque)
-- --------------------------------------------------------------
CREATE TABLE movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade INT NOT NULL,
    observacao VARCHAR(255) NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Tabela: configuracoes (parâmetros gerais do sistema)
-- --------------------------------------------------------------
CREATE TABLE configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor VARCHAR(255) NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------------
-- Dados iniciais
-- --------------------------------------------------------------

-- Usuário administrador padrão.
-- A senha é definida por um script de instalação (database/install_admin.php),
-- que gera o hash bcrypt corretamente com a versão do PHP do seu ambiente.
-- Por isso o campo "senha" começa vazio e é preenchido ao rodar o instalador.
INSERT INTO usuarios (nome, email, senha, perfil, ativo) VALUES
('Administrador', 'admin@smartcontrol.com', '', 'administrador', 1);

-- Categorias de exemplo
INSERT INTO categorias (nome, descricao, ativo) VALUES
('Informática', 'Produtos de informática em geral', 1),
('Escritório', 'Materiais de escritório', 1),
('Limpeza', 'Produtos de limpeza', 1);

-- Fornecedores de exemplo
INSERT INTO fornecedores (nome, cnpj_cpf, telefone, email, endereco, ativo) VALUES
('Distribuidora Tech LTDA', '12.345.678/0001-90', '(13) 3421-1234', 'contato@distech.com', 'Rua das Indústrias, 100 - Itanhaém/SP', 1),
('Papelaria Central', '98.765.432/0001-10', '(13) 3421-5678', 'vendas@papelariacentral.com', 'Av. Central, 500 - Itanhaém/SP', 1);

-- Configurações iniciais
INSERT INTO configuracoes (chave, valor) VALUES
('nome_sistema', 'SmartControl'),
('nome_empresa', 'Minha Empresa'),
('estoque_minimo_padrao', '5');
