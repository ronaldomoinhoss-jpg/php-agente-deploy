-- Database schema for Cubagem & Logistics Optimization System
-- Power Distribution Logistics

CREATE DATABASE IF NOT EXISTS `cubagem_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cubagem_db`;

-- Table: usuarios
DROP TABLE IF EXISTS `simulacao_regras_aplicadas`;
DROP TABLE IF EXISTS `simulacao_alertas`;
DROP TABLE IF EXISTS `simulacao_itens`;
DROP TABLE IF EXISTS `simulacoes`;
DROP TABLE IF EXISTS `regras_empilhamento`;
DROP TABLE IF EXISTS `materiais`;
DROP TABLE IF EXISTS `veiculos`;
DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `cargo` VARCHAR(50) DEFAULT 'Operador Logístico',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password for admin: admin123 (or hashed)
INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `cargo`) VALUES
(1, 'Administrador do Sistema', 'admin@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Gerente de Logística'),
(2, 'Operador de Carga', 'operador@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Operador Logístico');

-- Table: veiculos
CREATE TABLE `veiculos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo` ENUM('Munck', 'Truck', 'Carreta') NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `capacidade_kg` DECIMAL(10,2) NOT NULL,
  `capacidade_m3` DECIMAL(10,2) NOT NULL,
  `comprimento_m` DECIMAL(6,2) NOT NULL,
  `largura_m` DECIMAL(6,2) NOT NULL,
  `altura_m` DECIMAL(6,2) NOT NULL,
  `max_lastros` INT DEFAULT 2,
  `observacoes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `veiculos` (`id`, `tipo`, `nome`, `capacidade_kg`, `capacidade_m3`, `comprimento_m`, `largura_m`, `altura_m`, `max_lastros`, `observacoes`) VALUES
(1, 'Munck', 'Caminhão Munck Operational 12T', 12000.00, 24.50, 6.20, 2.45, 1.60, 2, 'Caminhão Munck equipado com guindaste hidráulico articulado. Capacidade útil reduzida devido à lança.'),
(2, 'Truck', 'Caminhão Truck Toco Baú/Aberto 15T', 15000.00, 42.00, 8.50, 2.45, 2.00, 2, 'Caminhão Truck 3 eixos, plataforma reforçada para carga pesada de distribuição elétrica.'),
(3, 'Carreta', 'Carreta Prancha/Grade Alta 30T', 30000.00, 78.00, 13.50, 2.50, 2.30, 2, 'Carreta semi-reboque longo curso para bobinas pesadas, transformadores de potência e materiais de subestação.');

-- Table: materiais
CREATE TABLE `materiais` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `descricao` VARCHAR(150) NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `peso_unitario_kg` DECIMAL(10,2) NOT NULL,
  `comprimento_m` DECIMAL(6,2) NOT NULL,
  `largura_m` DECIMAL(6,2) NOT NULL,
  `altura_m` DECIMAL(6,2) NOT NULL,
  `volume_unitario_m3` DECIMAL(10,4) NOT NULL,
  `quantidade_padrao` INT DEFAULT 1,
  `permite_empilhamento` TINYINT(1) DEFAULT 1,
  `max_lastros` INT DEFAULT 2,
  `fragilidade` ENUM('baixa', 'media', 'alta') DEFAULT 'baixa',
  `observacoes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `materiais` (`id`, `codigo`, `descricao`, `tipo`, `peso_unitario_kg`, `comprimento_m`, `largura_m`, `altura_m`, `volume_unitario_m3`, `quantidade_padrao`, `permite_empilhamento`, `max_lastros`, `fragilidade`, `observacoes`) VALUES
(1, 'BOB-CAB-120', 'Bobina de Cabo Alumínio Multiplexado 120mm² (Carretel 1,4m)', 'bobina_cabo', 850.00, 1.40, 1.40, 1.10, 2.1560, 4, 1, 2, 'baixa', 'Empilhamento piramidal obrigatório. Não posicionar de lado.'),
(2, 'BOB-CAB-185', 'Bobina de Cabo Cobre Subterrâneo 185mm² (Carretel 1,6m)', 'bobina_cabo', 1450.00, 1.60, 1.60, 1.25, 3.2000, 3, 1, 2, 'baixa', 'Empilhamento piramidal obrigatório. Carga muito pesada.'),
(3, 'TRF-TRI-75KVA', 'Transformador Trifásico 75 kVA 13.8kV/220V', 'transformador', 680.00, 1.10, 0.90, 1.20, 1.1880, 2, 0, 1, 'media', 'Não empilhar. Transportar obrigatoriamente na vertical com travas de retenção.'),
(4, 'TRF-MON-15KVA', 'Transformador Monofásico 15 kVA 13.8kV', 'transformador', 180.00, 0.70, 0.60, 0.85, 0.3570, 4, 0, 1, 'media', 'Itens sensíveis com buchas de porcelana superiores.'),
(5, 'POS-CON-11M', 'Poste de Concreto Duplo T 11 Metros / 300daN', 'poste', 1250.00, 11.00, 0.35, 0.30, 1.1550, 2, 1, 2, 'baixa', 'Carga longitudinal. Exige amarração com cintas de aço e apoios de madeira.'),
(6, 'CHV-SEC-15KV', 'Caixa com 6 Chaves Seccionadoras 15kV / 630A', 'chave', 140.00, 1.20, 0.80, 0.60, 0.5760, 6, 1, 2, 'alta', 'Buchas de porcelana e componentes delicados. Fragilidade Alta.'),
(7, 'ISO-POL-15KV', 'Palete de Isoladores Poliméricos de Suspensão 15kV', 'isolador', 220.00, 1.20, 1.00, 0.90, 1.0800, 4, 1, 2, 'baixa', 'Material leve e empilhável em até 2 lastros.'),
(8, 'CX-MED-POL', 'Palete com Caixas de Medição Monofásicas de Policarbonato', 'caixa', 310.00, 1.20, 1.00, 1.10, 1.3200, 3, 1, 2, 'media', 'Empilhável até 2 lastros max.'),
(9, 'FER-AMN-100', 'Caixa de Ferragens e Armações Secundárias (100 pçs)', 'ferragem', 420.00, 1.00, 0.80, 0.70, 0.5600, 5, 1, 2, 'baixa', 'Carga densa e pesada. Boa para base do veículo.');

-- Table: regras_empilhamento
CREATE TABLE `regras_empilhamento` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_origem_id` INT NULL,
  `tipo_material_origem` VARCHAR(50) NULL,
  `material_destino_id` INT NULL,
  `tipo_material_destino` VARCHAR(50) NULL,
  `tipo_regra` VARCHAR(50) NOT NULL,
  `prioridade` ENUM('baixa', 'media', 'alta', 'bloqueante') DEFAULT 'media',
  `justificativa` TEXT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `regras_empilhamento` (`id`, `material_origem_id`, `tipo_material_origem`, `material_destino_id`, `tipo_material_destino`, `tipo_regra`, `prioridade`, `justificativa`, `ativo`) VALUES
(1, NULL, 'transformador', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Transformadores de distribuição possuem fluido isolante e buchas superiores frágeis, não suportando peso superior.', 1),
(2, NULL, 'bobina_cabo', NULL, NULL, 'piramidal_bobinas', 'bloqueante', 'Bobinas de cabo elétrico devem obrigatoriamente ser empilhadas no formato piramidal para evitar rolamento e acidentes.', 1),
(3, NULL, 'chave', NULL, 'transformador', 'nao_sobrepor', 'alta', 'Chaves seccionadoras de alta fragilidade não podem ser sobrepostas em transformadores.', 1),
(4, NULL, 'poste', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Postes de concreto ocupam todo o comprimento e devem ficar na base da carroceria.', 1),
(5, NULL, 'ferragem', NULL, NULL, 'obrigatorio_lastro_1', 'media', 'Ferragens densas são ideais para o primeiro lastro para baixar o centro de gravidade.', 1);

-- Table: simulacoes
CREATE TABLE `simulacoes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_simulacao` VARCHAR(50) NOT NULL UNIQUE,
  `usuario_id` INT NULL,
  `veiculo_id` INT NOT NULL,
  `max_lastros_permitido` INT DEFAULT 2,
  `peso_total_kg` DECIMAL(10,2) NOT NULL,
  `volume_total_m3` DECIMAL(10,4) NOT NULL,
  `ocupacao_peso_pct` DECIMAL(5,2) NOT NULL,
  `ocupacao_volume_pct` DECIMAL(5,2) NOT NULL,
  `cubagem_total_m3` DECIMAL(10,4) NOT NULL,
  `lastros_utilizados` INT NOT NULL,
  `qtd_itens_alocados` INT NOT NULL,
  `qtd_itens_nao_alocados` INT NOT NULL,
  `status` ENUM('aprovado', 'aprovado_com_alerta', 'reprovado') NOT NULL,
  `centro_gravidade_x` DECIMAL(5,2) NULL,
  `centro_gravidade_y` DECIMAL(5,2) NULL,
  `observacoes_operacionais` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: simulacao_itens
CREATE TABLE `simulacao_itens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `simulacao_id` INT NOT NULL,
  `material_id` INT NOT NULL,
  `codigo_material` VARCHAR(50) NOT NULL,
  `descricao_material` VARCHAR(150) NOT NULL,
  `quantidade` INT NOT NULL,
  `peso_unitario_kg` DECIMAL(10,2) NOT NULL,
  `peso_total_kg` DECIMAL(10,2) NOT NULL,
  `volume_unitario_m3` DECIMAL(10,4) NOT NULL,
  `volume_total_m3` DECIMAL(10,4) NOT NULL,
  `lastro_posicao` INT DEFAULT 1,
  `posicao_x` DECIMAL(6,2) DEFAULT 0,
  `posicao_y` DECIMAL(6,2) DEFAULT 0,
  `posicao_z` DECIMAL(6,2) DEFAULT 0,
  `status_alocacao` ENUM('alocado', 'parcial', 'nao_alocado') NOT NULL,
  `observacoes_restricao` TEXT NULL,
  FOREIGN KEY (`simulacao_id`) REFERENCES `simulacoes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: simulacao_alertas
CREATE TABLE `simulacao_alertas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `simulacao_id` INT NOT NULL,
  `tipo_alerta` ENUM('peso_excedido', 'volume_excedido', 'altura_excedida', 'regra_violada', 'incompatibilidade', 'item_nao_alocado', 'piramide_invalida', 'desequilibrio_peso', 'atencao') NOT NULL,
  `mensagem` TEXT NOT NULL,
  `severidade` ENUM('info', 'warning', 'danger') NOT NULL,
  FOREIGN KEY (`simulacao_id`) REFERENCES `simulacoes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: simulacao_regras_aplicadas
CREATE TABLE `simulacao_regras_aplicadas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `simulacao_id` INT NOT NULL,
  `regra_id` INT NULL,
  `descricao_regra` VARCHAR(255) NOT NULL,
  `status` ENUM('cumprida', 'violada', 'alerta') NOT NULL,
  FOREIGN KEY (`simulacao_id`) REFERENCES `simulacoes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
