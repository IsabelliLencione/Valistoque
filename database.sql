CREATE DATABASE projeto_valistoque CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE projeto_valistoque;

CREATE TABLE adm (
id INT AUTO_INCREMENT PRIMARY KEY, 
Email VARCHAR(200) NOT NULL UNIQUE,
Senha VARCHAR(255) NOT NULL,
cpf VARCHAR(20) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE func (
id INT AUTO_INCREMENT PRIMARY KEY, 
Email VARCHAR(200) NOT NULL UNIQUE,
Senha VARCHAR(255) NOT NULL,
cpf VARCHAR(20) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE produto(
id INT AUTO_INCREMENT PRIMARY KEY,
nome VARCHAR(200) NOT NULL,
validade VARCHAR(10) NOT NULL,
peso FLOAT NOT NULL,
lote INT(100) NOT NULL
);

CREATE TABLE prateleira(
id INT AUTO_INCREMENT PRIMARY KEY,
id_produto INT NOT NULL,
FOREIGN KEY (id_produto) REFERENCES produto(id),
validade VARCHAR(10) NOT NULL,
quant_item INT NOT NULL,
peso_prat FLOAT NOT NULL
);

CREATE TABLE estoque (
id INT AUTO_INCREMENT PRIMARY KEY,
id_produto INT NOT NULL,
lote INT(100) NOT NULL,
quant_prod INT NOT NULL,
validade VARCHAR(10) NOT NULL
);

CREATE TABLE alertas(
id INT AUTO_INCREMENT PRIMARY KEY,
id_produto INT,
FOREIGN KEY (id_produto) REFERENCES produto(id),
id_estoque INT,
FOREIGN KEY (id_estoque) REFERENCES estoque(id),
id_prateleira INT,
FOREIGN KEY (id_prateleira) REFERENCES prateleira(id),
tipo_alerta ENUM('Validade Próxima', 'Estoque Baixo Central','Estoque Baixo prateleira') NOT NULL,
data_geracao DATETIME NOT NULL
);

CREATE TABLE config_alertas(
id INT AUTO_INCREMENT PRIMARY KEY,
dias_val INT NOT NULL DEFAULT 15,
quant_min_estoq INT NOT NULL DEFAULT 20,
quant_min_prat INT NOT NULL DEFAULT 20 
);
 




INSERT INTO adm (email, senha, cpf)
VALUES('nome.sobradm@gmail.com', '1234', '12345678910');

INSERT INTO func(email, senha, cpf)
VALUES('nome.sobradm@gmail.com', '1234', '12345678910');

SELECT * FROM projeto_valistoque.adm;
SELECT * FROM projeto_valistoque.func;