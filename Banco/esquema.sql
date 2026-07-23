-- ============================================================
--  AgroGestão PRO — Esquema Completo do Banco de Dados
--  Banco: producao_vegetal
--  Gerado em: 2026-07-03  (baseado na estrutura real do MySQL)
--  12 tabelas + seed de dados iniciais
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `producao_vegetal`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `producao_vegetal`;

-- ─────────────────────────────────────────────────────────────
-- 1. USUARIOS
--    Tabela central de autenticação e RBAC.
--    perfil: 'admin' | 'operador' | 'visitante'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario`  INT(11)      NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(100) NOT NULL,
  `email`       VARCHAR(120) NOT NULL,
  `senha`       VARCHAR(255) NOT NULL,   -- bcrypt hash
  `foto_perfil` VARCHAR(255)     DEFAULT NULL,
  `perfil`      VARCHAR(50)      DEFAULT 'operador',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. CATEGORIAS
--    Ex: 1=Horta, 2=Pomar
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria`   INT(11)     NOT NULL AUTO_INCREMENT,
  `nome_categoria` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. CONFIGURACOES
--    Preferências por usuário (dark mode, notificações, etc.)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id_configuracao` INT(11)     NOT NULL AUTO_INCREMENT,
  `notificacoes`    TINYINT(1)      DEFAULT 1,
  `unidades_medida` VARCHAR(20)     DEFAULT 'kg',
  `modo_escuro`     TINYINT(1)      DEFAULT 0,
  `id_usuario`      INT(11)         DEFAULT NULL,
  PRIMARY KEY (`id_configuracao`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `configuracoes_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. LOG
--    Auditoria de operações críticas
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `log` (
  `id_log`        INT(11)      NOT NULL AUTO_INCREMENT,
  `operacao`      VARCHAR(100)     DEFAULT NULL,
  `data_operacao` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_usuario`    INT(11)          DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `log_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. CULTURAS
--    Catálogo de espécies por usuário-tenant.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `culturas` (
  `id_cultura`              INT(11)      NOT NULL AUTO_INCREMENT,
  `nome_cultura`            VARCHAR(100) NOT NULL,
  `tempo_medio_crescimento` VARCHAR(50)      DEFAULT NULL,  -- dias (numérico)
  `estacao_ano_ideal`       VARCHAR(50)      DEFAULT NULL,
  `estacao_primavera`       VARCHAR(255)     DEFAULT NULL,
  `estacao_verao`           VARCHAR(255)     DEFAULT NULL,
  `estacao_outono`          VARCHAR(255)     DEFAULT NULL,
  `estacao_inverno`         VARCHAR(255)     DEFAULT NULL,
  `observacoes`             TEXT             DEFAULT NULL,
  `id_categoria`            INT(11)          DEFAULT NULL,
  `id_usuario`              INT(11)          DEFAULT NULL,
  PRIMARY KEY (`id_cultura`),
  KEY `id_categoria` (`id_categoria`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `culturas_ibfk_1`
    FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `culturas_ibfk_2`
    FOREIGN KEY (`id_usuario`)   REFERENCES `usuarios`   (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. ESTOQUE
--    Inventário de insumos por usuário-tenant.
--    custo_aquisicao: preço pago por unidade (base da baixa automática)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `estoque` (
  `id_item`        INT(11)      NOT NULL AUTO_INCREMENT,
  `nome_item`      VARCHAR(100) NOT NULL,
  `categoria`      VARCHAR(50)      DEFAULT NULL,  -- 'Semente' | 'Adubo' | 'Defensivo'
  `quantidade`     DECIMAL(10,2)    DEFAULT NULL,
  `unidade_medida` VARCHAR(20)      DEFAULT NULL,
  `nivel_alerta`   INT(11)          DEFAULT NULL,  -- alerta quando qtd <= nivel_alerta
  `status_estoque` VARCHAR(20)      DEFAULT NULL,  -- 'Normal' | 'Alerta'
  `id_usuario`     INT(11)          DEFAULT NULL,
  `lote_fabricante` VARCHAR(60)     DEFAULT NULL,
  `data_validade`  DATE             DEFAULT NULL,
  `custo_aquisicao` DECIMAL(10,2)   DEFAULT 0.00,
  PRIMARY KEY (`id_item`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `estoque_ibfk_1`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 7. MOVIMENTACAO_ESTOQUE
--    Histórico de entradas e saídas do estoque.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `movimentacao_estoque` (
  `id_movimentacao`   INT(11)       NOT NULL AUTO_INCREMENT,
  `id_item`           INT(11)       NOT NULL,
  `tipo`              ENUM('Entrada','Saída') NOT NULL,
  `quantidade`        DECIMAL(10,2) NOT NULL,
  `data_movimentacao` DATE          NOT NULL,
  `origem`            VARCHAR(100)      DEFAULT NULL,  -- Ex: 'Manejo #23'
  PRIMARY KEY (`id_movimentacao`),
  KEY `id_item` (`id_item`),
  CONSTRAINT `movimentacao_estoque_ibfk_1`
    FOREIGN KEY (`id_item`) REFERENCES `estoque` (`id_item`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 8. PLANTIOS
--    Registros de plantio por lote.
--    codigo_lote: gerado automaticamente (ex: LT-TOM-C1-012345)
--    tamanho_area + unidade_area: dimensões da área cultivada
--    dias_irrigados: contador incremental de rega
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `plantios` (
  `id_plantio`         INT(11)  NOT NULL AUTO_INCREMENT,
  `id_cultura`         INT(11)      DEFAULT NULL,
  `data_plantio`       DATE     NOT NULL,
  `local_canteiro`     VARCHAR(100) DEFAULT NULL,
  `quantidade_plantada` INT(11)     DEFAULT NULL,
  `progresso_colheita` VARCHAR(50)  DEFAULT NULL,  -- '0'..'100' (legado compatível)
  `notas_plantio`      TEXT         DEFAULT NULL,
  `irrigado`           TINYINT(1)   DEFAULT 0,
  `colhido`            TINYINT(1)   DEFAULT 0,
  `unidade_area`       VARCHAR(10)  DEFAULT NULL,  -- 'm²' | 'Hectares' | 'Alqueire'
  `codigo_lote`        VARCHAR(50)  DEFAULT NULL,
  `tamanho_area`       DECIMAL(10,2) DEFAULT 0.00,
  `dias_irrigados`     INT(11)      DEFAULT 0,
  `id_usuario`         INT(11)      DEFAULT NULL,
  PRIMARY KEY (`id_plantio`),
  KEY `id_cultura` (`id_cultura`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `plantios_ibfk_1`
    FOREIGN KEY (`id_cultura`) REFERENCES `culturas` (`id_cultura`),
  CONSTRAINT `plantios_ibfk_2`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 9. COLHEITAS
--    Cada linha representa uma colheita parcial ou total.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `colheitas` (
  `id_colheita`       INT(11)       NOT NULL AUTO_INCREMENT,
  `data_colheita`     DATE              DEFAULT NULL,
  `quantidade_colhida` DECIMAL(10,2)    DEFAULT NULL,
  `id_plantio`        INT(11)           DEFAULT NULL,
  `id_usuario`        INT(11)           DEFAULT NULL,
  PRIMARY KEY (`id_colheita`),
  KEY `id_plantio` (`id_plantio`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `colheitas_ibfk_1`
    FOREIGN KEY (`id_plantio`) REFERENCES `plantios` (`id_plantio`),
  CONSTRAINT `fk_colheitas_usuario`
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 10. CUIDADOS_PLANTIO  (Caderno de Campo)
--     Registro de manejos: irrigação, adubação, defensivos.
--     tipo_manejo: 'Irrigação' | 'Adubação' | 'Aplicação de Defensivos' | 'Outro'
--     custo_calculado: qty_usada × custo_aquisicao do insumo no momento
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cuidados_plantio` (
  `id_cuidado`      INT(11)       NOT NULL AUTO_INCREMENT,
  `data_cuidado`    DATETIME          DEFAULT NULL,
  `id_plantio`      INT(11)           DEFAULT NULL,
  `responsavel`     VARCHAR(100)      DEFAULT NULL,
  `quantidade_usada` DECIMAL(10,2)    DEFAULT 0.00,
  `id_item`         INT(11)           DEFAULT NULL,
  `tipo_manejo`     VARCHAR(50)       DEFAULT NULL,
  `custo_calculado` DECIMAL(10,2)     DEFAULT 0.00,
  `observacoes`     TEXT              DEFAULT NULL,
  PRIMARY KEY (`id_cuidado`),
  KEY `id_plantio` (`id_plantio`),
  KEY `id_item` (`id_item`),
  CONSTRAINT `cuidados_plantio_ibfk_1`
    FOREIGN KEY (`id_plantio`) REFERENCES `plantios` (`id_plantio`),
  CONSTRAINT `fk_item`
    FOREIGN KEY (`id_item`)    REFERENCES `estoque`  (`id_item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 11. AREAS
--     Áreas físicas da fazenda (canteiros, glebas, talhões).
--     Usada como referência pela tabela lotes.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `areas` (
  `id_area`    INT(11)      NOT NULL AUTO_INCREMENT,
  `nome_area`  VARCHAR(100)     DEFAULT NULL,
  `tamanho`    DECIMAL(10,2)    DEFAULT NULL,
  `unidade`    VARCHAR(20)      DEFAULT NULL,
  `descricao`  TEXT             DEFAULT NULL,
  PRIMARY KEY (`id_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 12. LOTES
--     Vínculo entre área física e plantio com controle de DAP
--     e estádio fenológico estruturado.
--     estadio: ciclo de vida da cultura
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lotes` (
  `id_lote`    INT(11) NOT NULL AUTO_INCREMENT,
  `codigo_lote` VARCHAR(30)  DEFAULT NULL,
  `id_area`    INT(11) NOT NULL,
  `id_plantio` INT(11) NOT NULL,
  `data_inicio` DATE         DEFAULT NULL,
  `dap`        INT(11)       DEFAULT 0,
  `estadio`    ENUM('Preparação','Germinação','Vegetativo','Floração','Frutificação','Colheita')
                             DEFAULT 'Preparação',
  `custo_total` DECIMAL(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id_lote`),
  UNIQUE KEY `codigo_lote` (`codigo_lote`),
  KEY `id_area`    (`id_area`),
  KEY `id_plantio` (`id_plantio`),
  CONSTRAINT `lotes_ibfk_1`
    FOREIGN KEY (`id_area`)    REFERENCES `areas`   (`id_area`),
  CONSTRAINT `lotes_ibfk_2`
    FOREIGN KEY (`id_plantio`) REFERENCES `plantios` (`id_plantio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─────────────────────────────────────────────────────────────
-- 13. MANEJOS  (tabela auxiliar ao módulo lotes)
--     Registros de campo vinculados a lotes (módulo futuro).
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `manejos` (
  `id_manejo`   INT(11) NOT NULL AUTO_INCREMENT,
  `id_lote`     INT(11)     DEFAULT NULL,
  `tipo`        ENUM('Irrigação','Adubação','Pulverização','Capina','Colheita','Outro')
                            DEFAULT NULL,
  `data_manejo` DATE        DEFAULT NULL,
  `responsavel` VARCHAR(100) DEFAULT NULL,
  `id_insumo`   INT(11)     DEFAULT NULL,
  `quantidade`  DECIMAL(10,2) DEFAULT NULL,
  `observacoes` TEXT        DEFAULT NULL,
  PRIMARY KEY (`id_manejo`),
  KEY `id_lote` (`id_lote`),
  CONSTRAINT `manejos_ibfk_1`
    FOREIGN KEY (`id_lote`) REFERENCES `lotes` (`id_lote`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  DADOS INICIAIS (SEED)
-- ============================================================

-- Categorias
INSERT IGNORE INTO `categorias` (`id_categoria`, `nome_categoria`) VALUES
(1, 'Horta'),
(2, 'Pomar');

-- Usuários padrão  (senhas: bcrypt de "12345")
-- Troque as hashes abaixo por novas geradas em produção!
INSERT IGNORE INTO `usuarios` (`id_usuario`, `nome`, `email`, `senha`, `perfil`) VALUES
(1, 'Administrador', 'admin@gmail.com',     '$2y$10$/vjckEfvfMQyAf0ZvGyEKeXo1lsB/.p1/pGqZJ/OuazF3.TYY5MiO', 'admin'),
(2, 'Operador',      'operador@gmail.com',  '$2y$10$/vjckEfvfMQyAf0ZvGyEKeXo1lsB/.p1/pGqZJ/OuazF3.TYY5MiO', 'operador');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  REFERÊNCIA RÁPIDA — DEPENDÊNCIAS DE FOREIGN KEY
-- ============================================================
--
--  usuarios
--    ├── categorias  (sem FK)
--    ├── configuracoes.id_usuario → usuarios
--    ├── log.id_usuario           → usuarios
--    ├── culturas.id_usuario      → usuarios
--    │     culturas.id_categoria  → categorias
--    │       ├── plantios.id_cultura         → culturas
--    │       │     ├── colheitas.id_plantio  → plantios
--    │       │     ├── cuidados_plantio.id_plantio → plantios
--    │       │     │     cuidados_plantio.id_item  → estoque
--    │       │     └── lotes.id_plantio      → plantios
--    │       │           lotes.id_area       → areas
--    │       │             manejos.id_lote   → lotes
--    │       └── areas (sem FK de entrada)
--    └── estoque.id_usuario       → usuarios
--          movimentacao_estoque.id_item → estoque
--
-- ============================================================
