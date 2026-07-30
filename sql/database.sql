CREATE DATABASE IF NOT EXISTS `cubagem2_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cubagem2_db`;

DROP TABLE IF EXISTS simulacao_regras_aplicadas;
DROP TABLE IF EXISTS simulacao_alertas;
DROP TABLE IF EXISTS simulacao_posicoes;
DROP TABLE IF EXISTS simulacao_veiculos;
DROP TABLE IF EXISTS simulacoes;
DROP TABLE IF EXISTS planejamento_cargas;
DROP TABLE IF EXISTS planejamento_pedidos;
DROP TABLE IF EXISTS planejamento_rotas;
DROP TABLE IF EXISTS pedido_itens;
DROP TABLE IF EXISTS pedidos_carga;
DROP TABLE IF EXISTS regras_operacionais;
DROP TABLE IF EXISTS materiais;
DROP TABLE IF EXISTS rota_bases;
DROP TABLE IF EXISTS rotas;
DROP TABLE IF EXISTS bases_operacionais;
DROP TABLE IF EXISTS unidades_veiculo;
DROP TABLE IF EXISTS veiculos;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL,
  cargo VARCHAR(80) DEFAULT 'Operador Logístico',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (id, nome, email, senha, cargo) VALUES
(1, 'Administrador do Sistema', 'admin@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Gerente de Logística'),
(2, 'Operador de Carga', 'operador@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Operador Logístico');

CREATE TABLE veiculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  capacidade_kg DECIMAL(12,2) NOT NULL,
  capacidade_m3 DECIMAL(12,2) NOT NULL,
  comprimento_m DECIMAL(8,2) NOT NULL,
  largura_m DECIMAL(8,2) NOT NULL,
  altura_m DECIMAL(8,2) NOT NULL,
  max_lastros INT DEFAULT 2,
  acesso_descarga VARCHAR(20) DEFAULT 'traseira',
  quantidade_disponivel INT DEFAULT 1,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO veiculos (id, tipo, nome, capacidade_kg, capacidade_m3, comprimento_m, largura_m, altura_m, max_lastros, acesso_descarga, quantidade_disponivel, observacoes) VALUES
(1, 'Munck', 'Munck 12T com carroceria aberta', 12000.00, 24.50, 6.20, 2.45, 1.60, 2, 'lateral', 2, 'Ideal para transformadores, bobinas e cargas que exigem içamento lateral.'),
(2, 'Truck', 'Truck distribuição 15T', 15000.00, 42.00, 8.50, 2.45, 2.00, 2, 'traseira', 3, 'Veículo principal para abastecimento das bases regionais.'),
(3, 'Carreta', 'Carreta prancha 30T', 30000.00, 78.00, 13.50, 2.50, 2.30, 2, 'misto', 2, 'Melhor para postes, grandes bobinas e cargas consolidadas.'),
(4, 'VUC', 'VUC 6T urbano', 6000.00, 18.50, 4.80, 2.20, 1.90, 2, 'traseira', 2, 'Suporte para bases menores e redistribuição urbana.');

CREATE TABLE unidades_veiculo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  veiculo_id INT NOT NULL,
  codigo_unidade VARCHAR(60) NOT NULL UNIQUE,
  status_operacional VARCHAR(30) DEFAULT 'disponivel',
  ativo TINYINT(1) DEFAULT 1,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO unidades_veiculo (id, veiculo_id, codigo_unidade, status_operacional, ativo, observacoes) VALUES
(1, 3, 'CRT-001', 'disponivel', 1, 'Carreta operacional principal.'),
(2, 3, 'CRT-002', 'disponivel', 1, 'Carreta operacional reserva.'),
(3, 2, 'TRK-001', 'disponivel', 1, 'Truck de apoio regional.'),
(4, 2, 'TRK-002', 'disponivel', 1, 'Truck para reforço de carga.'),
(5, 1, 'MNK-001', 'disponivel', 1, 'Munck para cargas especiais.');

CREATE TABLE bases_operacionais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL UNIQUE,
  nome VARCHAR(120) NOT NULL,
  endereco VARCHAR(255) NULL,
  ordem_padrao INT DEFAULT 1,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO bases_operacionais (id, codigo, nome, endereco, ordem_padrao, observacoes) VALUES
(1, 'BASE-CENTRO', 'Base Centro', 'Região central de atendimento', 1, 'Base prioritária para entregas matinais.'),
(2, 'BASE-NORTE', 'Base Norte', 'Corredor norte de distribuição', 2, 'Recebe reforço de transformadores e cabos.'),
(3, 'BASE-SUL', 'Base Sul', 'Polo sul / equipes de campo', 3, 'Base com alto giro de ferragens e postes.');

CREATE TABLE rotas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL UNIQUE,
  descricao VARCHAR(160) NOT NULL,
  data_planejada DATE NULL,
  origem_base_id INT NULL,
  status VARCHAR(30) DEFAULT 'planejada',
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (origem_base_id) REFERENCES bases_operacionais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rota_bases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rota_id INT NOT NULL,
  base_id INT NOT NULL,
  sequencia INT NOT NULL,
  FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE CASCADE,
  FOREIGN KEY (base_id) REFERENCES bases_operacionais(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rotas (id, codigo, descricao, data_planejada, origem_base_id, status, observacoes) VALUES
(1, 'ROT-20260730-NORTE', 'Rota Norte de abastecimento multi-base', '2026-07-30', 1, 'planejada', 'Rota exemplo para consolidação de pedidos.');

INSERT INTO rota_bases (id, rota_id, base_id, sequencia) VALUES
(1, 1, 1, 1),
(2, 1, 2, 2),
(3, 1, 3, 3);

CREATE TABLE materiais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) NOT NULL UNIQUE,
  descricao VARCHAR(180) NOT NULL,
  categoria VARCHAR(80) NOT NULL,
  formato_fisico VARCHAR(40) NOT NULL,
  peso_unitario_kg DECIMAL(12,2) NOT NULL,
  comprimento_m DECIMAL(8,2) NOT NULL,
  largura_m DECIMAL(8,2) NOT NULL,
  altura_m DECIMAL(8,2) NOT NULL,
  volume_unitario_m3 DECIMAL(12,4) NOT NULL,
  empilhavel TINYINT(1) DEFAULT 1,
  max_lastros INT DEFAULT 2,
  perfil_empilhamento VARCHAR(20) DEFAULT 'reto',
  fragilidade VARCHAR(20) DEFAULT 'baixa',
  amarracao_especial TINYINT(1) DEFAULT 0,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO materiais (id, codigo, descricao, categoria, formato_fisico, peso_unitario_kg, comprimento_m, largura_m, altura_m, volume_unitario_m3, empilhavel, max_lastros, perfil_empilhamento, fragilidade, amarracao_especial, observacoes) VALUES
(1, 'BOB-AL-120', 'Bobina de cabo alumínio 120 mm2', 'condutor', 'bobina', 850.00, 1.40, 1.40, 1.10, 2.1560, 1, 2, 'piramidal', 'baixa', 1, 'Preferir 2 embaixo + 1 em cima quando houver suporte e ganho operacional.'),
(2, 'BOB-CU-185', 'Bobina de cabo cobre subterrâneo 185 mm2', 'condutor', 'bobina', 1450.00, 1.60, 1.60, 1.25, 3.2000, 1, 2, 'piramidal', 'baixa', 1, 'Bobina pesada; avaliar 3 na base quando a descarga exigir acesso rápido.'),
(3, 'TRF-TRI-75', 'Transformador trifásico 75 kVA', 'transformador', 'transformador', 680.00, 1.10, 0.90, 1.20, 1.1880, 0, 1, 'nenhum', 'media', 1, 'Obrigatório no lastro 1 e sem carga superior.'),
(4, 'TRF-MON-15', 'Transformador monofásico 15 kVA', 'transformador', 'transformador', 180.00, 0.70, 0.60, 0.85, 0.3570, 0, 1, 'nenhum', 'media', 1, 'Buchas superiores exigem travamento e proteção.'),
(5, 'POS-CON-11', 'Poste de concreto DT 11m', 'poste', 'poste', 1250.00, 11.00, 0.35, 0.30, 1.1550, 0, 1, 'nenhum', 'baixa', 1, 'Carga longitudinal, preferir carreta e base.'),
(6, 'CRZ-11-90', 'Cruzeta concreto 11 kV', 'estrutura', 'caixa', 220.00, 2.40, 0.25, 0.25, 0.1500, 1, 2, 'reto', 'baixa', 0, 'Pode compor base da carga se bem travada.'),
(7, 'ISO-POL-15', 'Palete de isoladores poliméricos 15 kV', 'isolador', 'palete', 220.00, 1.20, 1.00, 0.90, 1.0800, 1, 2, 'reto', 'baixa', 0, 'Empilhamento permitido em 2 lastros.'),
(8, 'CHV-SEC-15', 'Caixa com chaves seccionadoras 15 kV', 'chave', 'caixa', 140.00, 1.20, 0.80, 0.60, 0.5760, 1, 2, 'reto', 'alta', 0, 'Não posicionar sob carga metálica pesada.'),
(9, 'CX-MED-MONO', 'Palete caixas de medição monofásicas', 'caixa_medicao', 'palete', 310.00, 1.20, 1.00, 1.10, 1.3200, 1, 2, 'reto', 'media', 0, 'Pode ir no lastro 2 se apoiado em base adequada.'),
(10, 'FER-AMR-100', 'Ferragens e armações secundárias', 'ferragem', 'caixa', 420.00, 1.00, 0.80, 0.70, 0.5600, 1, 2, 'reto', 'baixa', 0, 'Boa composição de base para baixar centro de gravidade.');

CREATE TABLE regras_operacionais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  material_origem_id INT NULL,
  categoria_origem VARCHAR(80) NULL,
  material_destino_id INT NULL,
  categoria_destino VARCHAR(80) NULL,
  tipo_regra VARCHAR(50) NOT NULL,
  severidade VARCHAR(20) DEFAULT 'alerta',
  justificativa TEXT NULL,
  ativo TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (material_origem_id) REFERENCES materiais(id) ON DELETE SET NULL,
  FOREIGN KEY (material_destino_id) REFERENCES materiais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO regras_operacionais (id, material_origem_id, categoria_origem, material_destino_id, categoria_destino, tipo_regra, severidade, justificativa, ativo) VALUES
(1, NULL, 'transformador', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Transformadores devem permanecer no piso.', 1),
(2, NULL, 'poste', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Postes devem ficar apoiados na base da carroceria.', 1),
(3, NULL, 'condutor', NULL, NULL, 'piramidal_bobinas', 'alerta', 'Bobinas devem avaliar empilhamento piramidal antes de ocupar toda a base.', 1),
(4, NULL, 'chave', NULL, 'transformador', 'nao_sobrepor', 'bloqueante', 'Chaves seccionadoras não podem ficar sobre transformadores.', 1),
(5, NULL, 'transformador', NULL, NULL, 'sem_carga_superior', 'bloqueante', 'Proibido apoiar qualquer item sobre transformadores.', 1),
(6, NULL, 'ferragem', NULL, NULL, 'preferir_lastro_1', 'alerta', 'Ferragens densas ajudam a estabilizar a carga.', 1),
(7, NULL, 'chave', NULL, NULL, 'separacao_fisica', 'alerta', 'Material frágil deve ter folga operacional.', 1),
(8, NULL, 'transformador', NULL, NULL, 'amarracao_especial', 'alerta', 'Transformadores exigem travas e cintamento extra.', 1);

CREATE TABLE pedidos_carga (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_pedido VARCHAR(50) NOT NULL UNIQUE,
  descricao VARCHAR(180) NOT NULL,
  status VARCHAR(20) DEFAULT 'rascunho',
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pedido_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  material_id INT NOT NULL,
  base_id INT NOT NULL,
  quantidade INT NOT NULL,
  ordem_entrega INT NOT NULL,
  observacoes_item TEXT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_carga(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE RESTRICT,
  FOREIGN KEY (base_id) REFERENCES bases_operacionais(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pedidos_carga (id, codigo_pedido, descricao, status, observacoes) VALUES
(1, 'PED-20260729-001', 'Abastecimento semanal bases Centro e Norte', 'aberto', 'Pedido inicial para demonstração da cubagem multi-base.');

INSERT INTO pedido_itens (id, pedido_id, material_id, base_id, quantidade, ordem_entrega, observacoes_item) VALUES
(1, 1, 1, 1, 3, 1, 'Base Centro recebe primeiro lote de bobinas.'),
(2, 1, 3, 1, 2, 1, 'Transformadores com descarga prioritária.'),
(3, 1, 8, 2, 4, 2, 'Material frágil para Base Norte.'),
(4, 1, 10, 2, 3, 2, 'Ferragens complementares.'),
(5, 1, 7, 2, 2, 2, 'Paletes de isoladores.');

CREATE TABLE simulacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_simulacao VARCHAR(50) NOT NULL UNIQUE,
  pedido_id INT NOT NULL,
  usuario_id INT NULL,
  score_total DECIMAL(14,2) NOT NULL,
  status VARCHAR(20) NOT NULL,
  total_veiculos INT NOT NULL,
  qtd_itens_total INT NOT NULL,
  qtd_itens_alocados INT NOT NULL,
  qtd_itens_nao_alocados INT NOT NULL,
  peso_total_kg DECIMAL(14,2) NOT NULL,
  volume_total_m3 DECIMAL(14,4) NOT NULL,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_carga(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE simulacao_veiculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simulacao_id INT NOT NULL,
  veiculo_id INT NOT NULL,
  slot_codigo VARCHAR(60) NOT NULL,
  veiculo_nome VARCHAR(120) NOT NULL,
  tipo_veiculo VARCHAR(50) NOT NULL,
  acesso_descarga VARCHAR(20) NOT NULL,
  comprimento_m DECIMAL(8,2) NOT NULL,
  largura_m DECIMAL(8,2) NOT NULL,
  altura_m DECIMAL(8,2) NOT NULL,
  capacidade_kg DECIMAL(12,2) NOT NULL,
  capacidade_m3 DECIMAL(12,2) NOT NULL,
  peso_total_kg DECIMAL(12,2) NOT NULL,
  volume_total_m3 DECIMAL(12,4) NOT NULL,
  ocupacao_peso_pct DECIMAL(8,2) NOT NULL,
  ocupacao_volume_pct DECIMAL(8,2) NOT NULL,
  centro_gravidade_x DECIMAL(8,2) NOT NULL,
  centro_gravidade_y DECIMAL(8,2) NOT NULL,
  lastros_utilizados INT NOT NULL,
  ordem_carga TEXT NULL,
  ordem_descarga TEXT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE simulacao_posicoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simulacao_id INT NOT NULL,
  simulacao_veiculo_id INT NULL,
  pedido_item_id INT NOT NULL,
  material_id INT NOT NULL,
  base_id INT NOT NULL,
  codigo_material VARCHAR(50) NOT NULL,
  descricao_material VARCHAR(180) NOT NULL,
  base_nome VARCHAR(120) NOT NULL,
  ordem_entrega INT NOT NULL,
  lastro_posicao INT DEFAULT 1,
  posicao_x DECIMAL(8,2) DEFAULT 0,
  posicao_y DECIMAL(8,2) DEFAULT 0,
  posicao_z DECIMAL(8,2) DEFAULT 0,
  comprimento_m DECIMAL(8,2) NOT NULL,
  largura_m DECIMAL(8,2) NOT NULL,
  altura_m DECIMAL(8,2) NOT NULL,
  peso_unitario_kg DECIMAL(12,2) NOT NULL,
  volume_unitario_m3 DECIMAL(12,4) NOT NULL,
  status_alocacao VARCHAR(20) NOT NULL,
  cor_hex VARCHAR(7) NOT NULL,
  observacoes_restricao TEXT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens(id) ON DELETE RESTRICT,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE RESTRICT,
  FOREIGN KEY (base_id) REFERENCES bases_operacionais(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE simulacao_alertas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simulacao_id INT NOT NULL,
  simulacao_veiculo_id INT NULL,
  tipo_alerta VARCHAR(50) NOT NULL,
  severidade VARCHAR(20) NOT NULL,
  mensagem TEXT NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE simulacao_regras_aplicadas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simulacao_id INT NOT NULL,
  simulacao_veiculo_id INT NULL,
  regra_id INT NULL,
  descricao_regra VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (regra_id) REFERENCES regras_operacionais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planejamento_rotas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_planejamento VARCHAR(60) NOT NULL UNIQUE,
  rota_id INT NOT NULL,
  pedido_consolidado_id INT NOT NULL,
  simulacao_id INT NOT NULL,
  data_operacao DATE NOT NULL,
  status VARCHAR(30) DEFAULT 'planejado',
  total_cargas INT DEFAULT 0,
  total_peso_kg DECIMAL(12,2) DEFAULT 0,
  total_volume_m3 DECIMAL(12,4) DEFAULT 0,
  score_total DECIMAL(12,2) DEFAULT 0,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_consolidado_id) REFERENCES pedidos_carga(id) ON DELETE RESTRICT,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planejamento_pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  planejamento_id INT NOT NULL,
  pedido_id INT NOT NULL,
  FOREIGN KEY (planejamento_id) REFERENCES planejamento_rotas(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_carga(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planejamento_cargas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  planejamento_id INT NOT NULL,
  simulacao_veiculo_id INT NOT NULL,
  unidade_veiculo_id INT NULL,
  veiculo_id INT NOT NULL,
  codigo_carga VARCHAR(80) NOT NULL,
  bases_atendidas TEXT NULL,
  peso_total_kg DECIMAL(12,2) DEFAULT 0,
  volume_total_m3 DECIMAL(12,4) DEFAULT 0,
  ocupacao_peso_pct DECIMAL(8,2) DEFAULT 0,
  ocupacao_volume_pct DECIMAL(8,2) DEFAULT 0,
  status VARCHAR(30) DEFAULT 'planejada',
  FOREIGN KEY (planejamento_id) REFERENCES planejamento_rotas(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (unidade_veiculo_id) REFERENCES unidades_veiculo(id) ON DELETE SET NULL,
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
