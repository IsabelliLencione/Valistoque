CREATE DATABASE IF NOT EXISTS projeto_valistoque CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE projeto_valistoque;

CREATE TABLE IF NOT EXISTS adm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    password_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS func (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    password_updated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) NULL,
    marca VARCHAR(100) NULL,
    codigo_barras VARCHAR(50) NULL,
    unidade_medida VARCHAR(20) NOT NULL DEFAULT 'un',
    peso_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    lote VARCHAR(60) NOT NULL,
    validade DATE NOT NULL,
    quantidade_caixas INT NOT NULL DEFAULT 0,
    produtos_por_caixa INT NOT NULL DEFAULT 1,
    peso_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_estoque_produto FOREIGN KEY (id_produto) REFERENCES produto(id) ON DELETE CASCADE,
    UNIQUE KEY uk_estoque_produto_lote (id_produto, lote)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prateleira (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    codigo_prateleira VARCHAR(30) NOT NULL,
    lote VARCHAR(60) NOT NULL,
    validade DATE NOT NULL,
    quantidade_caixas INT NOT NULL DEFAULT 0,
    unidades INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prateleira_produto FOREIGN KEY (id_produto) REFERENCES produto(id) ON DELETE CASCADE,
    UNIQUE KEY uk_prateleira_item (codigo_prateleira, id_produto, lote)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimentacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NULL,
    tipo VARCHAR(40) NOT NULL,
    origem VARCHAR(80) NULL,
    destino VARCHAR(80) NULL,
    lote VARCHAR(60) NULL,
    quantidade_caixas INT NOT NULL DEFAULT 0,
    quantidade_unidades INT NOT NULL DEFAULT 0,
    observacao TEXT NULL,
    usuario_id INT NULL,
    usuario_nome VARCHAR(120) NULL,
    usuario_perfil VARCHAR(20) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mov_produto (id_produto),
    INDEX idx_mov_tipo (tipo),
    INDEX idx_mov_data (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS config_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dias_antes_validade INT NOT NULL DEFAULT 30,
    caixas_minimas_central INT NOT NULL DEFAULT 10,
    caixas_minimas_prateleira INT NOT NULL DEFAULT 5,
    intervalo_minutos INT NOT NULL DEFAULT 15,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL,
    produto VARCHAR(150) NOT NULL,
    lote VARCHAR(60) NULL,
    codigo_prateleira VARCHAR(30) NULL,
    referencia VARCHAR(120) NULL,
    mensagem TEXT NOT NULL,
    lido TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil VARCHAR(20) NOT NULL,
    usuario_id INT NOT NULL,
    email VARCHAR(120) NOT NULL,
    codigo VARCHAR(12) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rec_email (email),
    INDEX idx_rec_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_atividade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario_nome VARCHAR(120) NULL,
    usuario_perfil VARCHAR(20) NULL,
    acao VARCHAR(80) NOT NULL,
    entidade VARCHAR(80) NOT NULL,
    entidade_id VARCHAR(80) NULL,
    detalhes JSON NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_data (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_tentativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    tentativas INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_login_bloqueio (email, ip)
) ENGINE=InnoDB;

INSERT INTO config_alertas (id, dias_antes_validade, caixas_minimas_central, caixas_minimas_prateleira, intervalo_minutos)
VALUES (1, 30, 10, 5, 15)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO adm (nome, email, cpf, senha, ativo)
SELECT 'Administrador Padrão', 'admin@valistoque.com', '111.444.777-35', '$2y$10$0rU5tSe0fn3zEq6SMH2lO.dyn6P/uddtfkjz/.HlDYqwk9jbQhy9G', 1
WHERE NOT EXISTS (SELECT 1 FROM adm WHERE email = 'admin@valistoque.com');

INSERT INTO func (nome, email, cpf, senha, ativo)
SELECT 'Funcionário Padrão', 'funcionario@valistoque.com', '529.982.247-25', '$2y$10$Nwa.3A8SwcWNdnyP35WIaePhDnYbTALSQ2HSGIMPLR887iuGrJNUa', 1
WHERE NOT EXISTS (SELECT 1 FROM func WHERE email = 'funcionario@valistoque.com');
