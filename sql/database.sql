-- ============================================================
--  VALISTOQUE - Sistema de Controle de Estoque
--  Script de criação do banco de dados completo
--  Banco: MySQL 5.7+ / MariaDB 10.2+
-- ============================================================

DROP DATABASE IF EXISTS projeto_valistoque;
CREATE DATABASE projeto_valistoque CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE projeto_valistoque;

-- ------------------------------------------------------------
--  Tabela: adm  (Administradores)
-- ------------------------------------------------------------
CREATE TABLE adm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    Email VARCHAR(200) NOT NULL UNIQUE,
    Senha VARCHAR(255) NOT NULL,
    cpf VARCHAR(20) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    tentativas_login INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: func  (Funcionários)
-- ------------------------------------------------------------
CREATE TABLE func (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    Email VARCHAR(200) NOT NULL UNIQUE,
    Senha VARCHAR(255) NOT NULL,
    cpf VARCHAR(20) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    tentativas_login INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME DEFAULT NULL,
    id_adm_criador INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_adm_criador) REFERENCES adm(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: produto
-- ------------------------------------------------------------
CREATE TABLE produto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    codigo_barras VARCHAR(50) UNIQUE DEFAULT NULL,
    categoria VARCHAR(80) DEFAULT NULL,
    validade DATE NOT NULL,
    peso FLOAT NOT NULL,
    lote VARCHAR(50) NOT NULL,
    preco_custo DECIMAL(10,2) DEFAULT 0,
    preco_venda DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_produto_nome (nome),
    INDEX idx_produto_categoria (categoria)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: estoque  (Estoque central)
-- ------------------------------------------------------------
CREATE TABLE estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    lote VARCHAR(50) NOT NULL,
    quant_prod INT NOT NULL DEFAULT 0,
    validade DATE NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produto(id) ON DELETE CASCADE,
    INDEX idx_estoque_produto (id_produto),
    INDEX idx_estoque_validade (validade)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: prateleira  (Estoque de prateleira / loja)
-- ------------------------------------------------------------
CREATE TABLE prateleira (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    codigo_prateleira VARCHAR(20) DEFAULT NULL,
    validade DATE NOT NULL,
    quant_item INT NOT NULL DEFAULT 0,
    peso_prat FLOAT NOT NULL DEFAULT 0,
    data_reposicao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produto(id) ON DELETE CASCADE,
    INDEX idx_prateleira_produto (id_produto),
    INDEX idx_prateleira_validade (validade)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: movimentacao  (Histórico de entradas/saídas/transf.)
-- ------------------------------------------------------------
CREATE TABLE movimentacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    tipo ENUM('entrada','saida','transferencia') NOT NULL,
    origem ENUM('estoque','prateleira','externo') NOT NULL,
    destino ENUM('estoque','prateleira','externo') NOT NULL,
    quantidade INT NOT NULL,
    id_usuario INT NOT NULL,
    perfil_usuario ENUM('adm','func') NOT NULL,
    observacao TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produto(id) ON DELETE CASCADE,
    INDEX idx_mov_data (data_hora),
    INDEX idx_mov_tipo (tipo),
    INDEX idx_mov_produto (id_produto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: alertas
-- ------------------------------------------------------------
CREATE TABLE alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_produto INT,
    id_estoque INT,
    id_prateleira INT,
    tipo_alerta ENUM(
        'Validade Próxima',
        'Estoque Baixo Central',
        'Estoque Baixo Prateleira',
        'Produto Vencido'
    ) NOT NULL,
    mensagem VARCHAR(255),
    lido TINYINT(1) NOT NULL DEFAULT 0,
    data_geracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto)    REFERENCES produto(id)    ON DELETE CASCADE,
    FOREIGN KEY (id_estoque)    REFERENCES estoque(id)    ON DELETE CASCADE,
    FOREIGN KEY (id_prateleira) REFERENCES prateleira(id) ON DELETE CASCADE,
    INDEX idx_alerta_lido (lido),
    INDEX idx_alerta_tipo (tipo_alerta)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: config_alertas  (Parâmetros de gatilhos)
-- ------------------------------------------------------------
CREATE TABLE config_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dias_val INT NOT NULL DEFAULT 15,
    quant_min_estoq INT NOT NULL DEFAULT 20,
    quant_min_prat INT NOT NULL DEFAULT 20,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: recuperacao_senha
-- ------------------------------------------------------------
CREATE TABLE recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    perfil ENUM('adm','func') NOT NULL,
    usuario_id INT NOT NULL,
    email VARCHAR(200) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    token VARCHAR(255) NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    expira_em DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recup_codigo (codigo),
    INDEX idx_recup_expira (expira_em)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  Tabela: log_atividade  (Auditoria do sistema)
-- ------------------------------------------------------------
CREATE TABLE log_atividade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    perfil ENUM('adm','func') NOT NULL,
    acao VARCHAR(100) NOT NULL,
    detalhes TEXT,
    ip VARCHAR(45),
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_data (data_hora),
    INDEX idx_log_acao (acao)
) ENGINE=InnoDB;

-- ============================================================
--  DADOS PADRÃO (SEEDS)
-- ============================================================

-- Configuração padrão de alertas
INSERT INTO config_alertas (dias_val, quant_min_estoq, quant_min_prat)
VALUES (15, 20, 20);

-- Administrador padrão  (login: admin@valistoque.com / senha: admin123)
INSERT INTO adm (nome, Email, Senha, cpf) VALUES (
    'Administrador Master',
    'admin@valistoque.com',
    '$2y$10$YDUtqxYqUF8DcWqM2rUE5e3CRJiTfvDxQq5R3l.JZuKqW9NN.U/de',
    '12345678910'
);

-- Funcionário padrão (login: funcionario@valistoque.com / senha: func123)
INSERT INTO func (nome, Email, Senha, cpf, id_adm_criador) VALUES (
    'Funcionário Teste',
    'funcionario@valistoque.com',
    '$2y$10$Ku0X7v8r7eZQy4kQ9oU3oO1K8qB3VuWtKHkR6sJl/5kS5pXl3JEEW',
    '98765432100',
    1
);

-- Produtos de exemplo
INSERT INTO produto (nome, codigo_barras, categoria, validade, peso, lote, preco_custo, preco_venda) VALUES
('Leite Integral 1L', '7891000100103', 'Laticínios', '2026-08-15', 1.0, 'L001', 3.50, 5.99),
('Arroz Branco 5kg',  '7896006711011', 'Grãos',      '2027-06-20', 5.0, 'A002', 18.00, 28.90),
('Iogurte Morango',   '7891000200109', 'Laticínios', '2026-06-05', 0.17,'I003', 2.20, 3.99),
('Pão de Forma',      '7896001000101', 'Padaria',    '2026-05-30', 0.5, 'P004', 4.50, 7.50);

-- Estoque inicial
INSERT INTO estoque (id_produto, lote, quant_prod, validade) VALUES
(1, 'L001', 80,  '2026-08-15'),
(2, 'A002', 50,  '2027-06-20'),
(3, 'I003', 15,  '2026-06-05'),
(4, 'P004', 25,  '2026-05-30');

-- Prateleira inicial
INSERT INTO prateleira (id_produto, codigo_prateleira, validade, quant_item, peso_prat) VALUES
(1, 'PR-A1', '2026-08-15', 30, 30.0),
(2, 'PR-A2', '2027-06-20', 12, 60.0),
(3, 'PR-B1', '2026-06-05', 22, 3.74),
(4, 'PR-B2', '2026-05-30', 18, 9.0);
