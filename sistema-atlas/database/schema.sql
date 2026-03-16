-- =========================================================
-- Sistema Atlas - Script de banco de dados
-- Compatível com MySQL/MariaDB no XAMPP
-- =========================================================

-- Cria o banco de dados com suporte a caracteres acentuados.
CREATE DATABASE IF NOT EXISTS sistema_atlas
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistema_atlas;

-- Remove tabelas antigas para recriação completa em ambiente de desenvolvimento.
DROP TABLE IF EXISTS atividades;
DROP TABLE IF EXISTS oportunidades;
DROP TABLE IF EXISTS clientes;

-- Tabela de clientes atendidos pelo time comercial.
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_empresa VARCHAR(150) NOT NULL,
    contato_email VARCHAR(120) NOT NULL,
    telefone VARCHAR(30) NULL,
    setor VARCHAR(80) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de oportunidades (pipeline de vendas).
CREATE TABLE oportunidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    valor_estimado DECIMAL(12,2) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Novo',
    probabilidade INT NOT NULL DEFAULT 0,
    data_fechamento_prevista DATE NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_oportunidades_clientes
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- Tabela de atividades do time comercial para cada oportunidade.
CREATE TABLE atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    oportunidade_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    data_atividade DATE NOT NULL,
    responsavel VARCHAR(120) NOT NULL,
    concluida TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_atividades_oportunidades
        FOREIGN KEY (oportunidade_id) REFERENCES oportunidades(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- Dados iniciais para demonstração em portfólio.
INSERT INTO clientes (nome_empresa, contato_email, telefone, setor) VALUES
('Grupo Horizonte', 'contato@grupohorizonte.com.br', '(11) 99999-0001', 'Construção Civil'),
('Alfa Distribuição', 'compras@alfadistribuicao.com.br', '(21) 98888-0002', 'Logística'),
('Norte Tech', 'comercial@nortetech.com.br', '(31) 97777-0003', 'Tecnologia');

INSERT INTO oportunidades (cliente_id, titulo, valor_estimado, status, probabilidade, data_fechamento_prevista) VALUES
(1, 'Implantação de CRM corporativo', 85000.00, 'Proposta', 70, '2026-04-30'),
(2, 'Automação de processo logístico', 120000.00, 'Negociação', 55, '2026-05-15'),
(3, 'Licenciamento anual de plataforma', 45000.00, 'Qualificação', 40, '2026-06-10');

INSERT INTO atividades (oportunidade_id, tipo, descricao, data_atividade, responsavel, concluida) VALUES
(1, 'Reunião', 'Apresentação técnica para diretoria', '2026-03-18', 'Camila Rocha', 0),
(2, 'Ligação', 'Alinhamento de escopo e cronograma', '2026-03-20', 'Paulo Mendes', 0),
(3, 'E-mail', 'Envio de proposta comercial inicial', '2026-03-16', 'Ana Souza', 1);
