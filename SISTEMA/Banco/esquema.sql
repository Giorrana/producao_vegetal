CREATE DATABASE producao_vegetal;
USE producao_vegetal;


CREATE TABLE usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    foto_perfil VARCHAR(255),
    perfil VARCHAR(50) DEFAULT 'operador'
);


CREATE TABLE categorias (
    id_categoria INT PRIMARY KEY AUTO_INCREMENT,
    nome_categoria VARCHAR(50) NOT NULL
);


CREATE TABLE culturas (
    id_cultura INT PRIMARY KEY AUTO_INCREMENT,
    nome_cultura VARCHAR(100) NOT NULL,
    tempo_medio_crescimento VARCHAR(50),
    estacao_ano_ideal VARCHAR(50),
    estacao_primavera VARCHAR(255),
    estacao_verao VARCHAR(255),
    estacao_outono VARCHAR(255),
    estacao_inverno VARCHAR(255),
    observacoes TEXT,
    id_categoria INT,
    id_usuario INT,
    FOREIGN KEY (id_categoria)
    REFERENCES categorias(id_categoria),
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
);


CREATE TABLE plantios (
    id_plantio INT PRIMARY KEY AUTO_INCREMENT,
    id_cultura INT,
    data_plantio DATE NOT NULL,
    local_canteiro VARCHAR(100),
    quantidade_plantada INT,
    progresso_colheita VARCHAR(50),
    data_prevista DATE,
    notas_plantio TEXT,
    irrigado TINYINT(1) DEFAULT 0,
    colhido TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_cultura)
    REFERENCES culturas(id_cultura)
);


CREATE TABLE cuidados_plantio (
    id_cuidado INT PRIMARY KEY AUTO_INCREMENT,
    irrigar VARCHAR(20),
    adubar VARCHAR(20),
    data_cuidado DATE,
    id_plantio INT,
    FOREIGN KEY (id_plantio)
    REFERENCES plantios(id_plantio)
);


CREATE TABLE estoque (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    nome_item VARCHAR(100) NOT NULL,
    categoria VARCHAR(50),
    quantidade DECIMAL(10,2),
    unidade_medida VARCHAR(20),
    nivel_alerta INT,
    status_estoque VARCHAR(20),
    id_usuario INT,
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
);


CREATE TABLE colheitas (
    id_colheita INT PRIMARY KEY AUTO_INCREMENT,
    data_colheita DATE,
    quantidade_colhida DECIMAL(10,2),
    id_plantio INT,
    FOREIGN KEY (id_plantio)
    REFERENCES plantios(id_plantio)
);


CREATE TABLE log (
    id_log INT PRIMARY KEY AUTO_INCREMENT,
    operacao VARCHAR(100),
    data_operacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT,
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
);


CREATE TABLE configuracoes (
    id_configuracao INT PRIMARY KEY AUTO_INCREMENT,
    notificacoes BOOLEAN,
    unidades_medida VARCHAR(20),
    modo_escuro BOOLEAN,
    id_usuario INT,
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
);

