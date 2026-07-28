-- Database schema for Cubagem & Logistics Optimization System (SQLite Local)

CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nome TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  senha TEXT NOT NULL,
  cargo TEXT DEFAULT 'Operador Logístico',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO usuarios (id, nome, email, senha, cargo) VALUES
(1, 'Administrador do Sistema', 'admin@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Gerente de Logística'),
(2, 'Operador de Carga', 'operador@energia.com.br', '$2y$10$e.w2hP20zQ0WjEw/W2xXbO5QY9.6iW1mZg0lWwG5r1pXw8q8z3V8e', 'Operador Logístico');

CREATE TABLE IF NOT EXISTS veiculos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tipo TEXT NOT NULL,
  nome TEXT NOT NULL,
  capacidade_kg REAL NOT NULL,
  capacidade_m3 REAL NOT NULL,
  comprimento_m REAL NOT NULL,
  largura_m REAL NOT NULL,
  altura_m REAL NOT NULL,
  max_lastros INTEGER DEFAULT 2,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO veiculos (id, tipo, nome, capacidade_kg, capacidade_m3, comprimento_m, largura_m, altura_m, max_lastros, observacoes) VALUES
(1, 'Munck', 'Caminhão Munck Operational 12T', 12000.00, 24.50, 6.20, 2.45, 1.60, 2, 'Caminhão Munck equipado com guindaste hidráulico articulado. Capacidade útil reduzida devido à lança.'),
(2, 'Truck', 'Caminhão Truck Toco Baú/Aberto 15T', 15000.00, 42.00, 8.50, 2.45, 2.00, 2, 'Caminhão Truck 3 eixos, plataforma reforçada para carga pesada de distribuição elétrica.'),
(3, 'Carreta', 'Carreta Prancha/Grade Alta 30T', 30000.00, 78.00, 13.50, 2.50, 2.30, 2, 'Carreta semi-reboque longo curso para bobinas pesadas, transformadores de potência e materiais de subestação.');

CREATE TABLE IF NOT EXISTS materiais (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo TEXT NOT NULL UNIQUE,
  descricao TEXT NOT NULL,
  tipo TEXT NOT NULL,
  peso_unitario_kg REAL NOT NULL,
  comprimento_m REAL NOT NULL,
  largura_m REAL NOT NULL,
  altura_m REAL NOT NULL,
  volume_unitario_m3 REAL NOT NULL,
  quantidade_padrao INTEGER DEFAULT 1,
  permite_empilhamento INTEGER DEFAULT 1,
  max_lastros INTEGER DEFAULT 2,
  fragilidade TEXT DEFAULT 'baixa',
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO materiais (id, codigo, descricao, tipo, peso_unitario_kg, comprimento_m, largura_m, altura_m, volume_unitario_m3, quantidade_padrao, permite_empilhamento, max_lastros, fragilidade, observacoes) VALUES
(1, 'BOB-CAB-120', 'Bobina de Cabo Alumínio Multiplexado 120mm² (Carretel 1,4m)', 'bobina_cabo', 850.00, 1.40, 1.40, 1.10, 2.1560, 4, 1, 2, 'baixa', 'Empilhamento piramidal obrigatório. Não posicionar de lado.'),
(2, 'BOB-CAB-185', 'Bobina de Cabo Cobre Subterrâneo 185mm² (Carretel 1,6m)', 'bobina_cabo', 1450.00, 1.60, 1.60, 1.25, 3.2000, 3, 1, 2, 'baixa', 'Empilhamento piramidal obrigatório. Carga muito pesada.'),
(3, 'TRF-TRI-75KVA', 'Transformador Trifásico 75 kVA 13.8kV/220V', 'transformador', 680.00, 1.10, 0.90, 1.20, 1.1880, 2, 0, 1, 'media', 'Não empilhar. Transportar obrigatoriamente na vertical com travas de retenção.'),
(4, 'TRF-MON-15KVA', 'Transformador Monofásico 15 kVA 13.8kV', 'transformador', 180.00, 0.70, 0.60, 0.85, 0.3570, 4, 0, 1, 'media', 'Itens sensíveis com buchas de porcelana superiores.'),
(5, 'POS-CON-11M', 'Poste de Concreto Duplo T 11 Metros / 300daN', 'poste', 1250.00, 11.00, 0.35, 0.30, 1.1550, 2, 1, 2, 'baixa', 'Carga longitudinal. Exige amarração com cintas de aço e apoios de madeira.'),
(6, 'CHV-SEC-15KV', 'Caixa com 6 Chaves Seccionadoras 15kV / 630A', 'chave', 140.00, 1.20, 0.80, 0.60, 0.5760, 6, 1, 2, 'alta', 'Buchas de porcelana e componentes delicados. Fragilidade Alta.'),
(7, 'ISO-POL-15KV', 'Palete de Isoladores Poliméricos de Suspensão 15kV', 'isolador', 220.00, 1.20, 1.00, 0.90, 1.0800, 4, 1, 2, 'baixa', 'Material leve e empilhável em até 2 lastros.'),
(8, 'CX-MED-POL', 'Palete com Caixas de Medição Monofásicas de Policarbonato', 'caixa', 310.00, 1.20, 1.00, 1.10, 1.3200, 3, 1, 2, 'media', 'Empilhável até 2 lastros max.'),
(9, 'FER-AMN-100', 'Caixa de Ferragens e Armações Secundárias (100 pçs)', 'ferragem', 420.00, 1.00, 0.80, 0.70, 0.5600, 5, 1, 2, 'baixa', 'Carga densa e pesada. Boa para base do veículo.');

CREATE TABLE IF NOT EXISTS regras_empilhamento (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  material_origem_id INTEGER NULL,
  tipo_material_origem TEXT NULL,
  material_destino_id INTEGER NULL,
  tipo_material_destino TEXT NULL,
  tipo_regra TEXT NOT NULL,
  prioridade TEXT DEFAULT 'media',
  justificativa TEXT NULL,
  ativo INTEGER DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO regras_empilhamento (id, material_origem_id, tipo_material_origem, material_destino_id, tipo_material_destino, tipo_regra, prioridade, justificativa, ativo) VALUES
(1, NULL, 'transformador', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Transformadores de distribuição possuem fluido isolante e buchas superiores frágeis, não suportando peso superior.', 1),
(2, NULL, 'bobina_cabo', NULL, NULL, 'piramidal_bobinas', 'bloqueante', 'Bobinas de cabo elétrico devem obrigatoriamente ser empilhadas no formato piramidal para evitar rolamento e acidentes.', 1),
(3, NULL, 'chave', NULL, 'transformador', 'nao_sobrepor', 'alta', 'Chaves seccionadoras de alta fragilidade não podem ser sobrepostas em transformadores.', 1),
(4, NULL, 'poste', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Postes de concreto ocupam todo o comprimento e devem ficar na base da carroceria.', 1),
(5, NULL, 'ferragem', NULL, NULL, 'obrigatorio_lastro_1', 'media', 'Ferragens densas são ideais para o primeiro lastro para baixar o centro de gravidade.', 1);

CREATE TABLE IF NOT EXISTS simulacoes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo_simulacao TEXT NOT NULL UNIQUE,
  usuario_id INTEGER NULL,
  veiculo_id INTEGER NOT NULL,
  max_lastros_permitido INTEGER DEFAULT 2,
  peso_total_kg REAL NOT NULL,
  volume_total_m3 REAL NOT NULL,
  ocupacao_peso_pct REAL NOT NULL,
  ocupacao_volume_pct REAL NOT NULL,
  cubagem_total_m3 REAL NOT NULL,
  lastros_utilizados INTEGER NOT NULL,
  qtd_itens_alocados INTEGER NOT NULL,
  qtd_itens_nao_alocados INTEGER NOT NULL,
  status TEXT NOT NULL,
  centro_gravidade_x REAL NULL,
  centro_gravidade_y REAL NULL,
  observacoes_operacionais TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulacao_itens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  material_id INTEGER NOT NULL,
  codigo_material TEXT NOT NULL,
  descricao_material TEXT NOT NULL,
  quantidade INTEGER NOT NULL,
  peso_unitario_kg REAL NOT NULL,
  peso_total_kg REAL NOT NULL,
  volume_unitario_m3 REAL NOT NULL,
  volume_total_m3 REAL NOT NULL,
  lastro_posicao INTEGER DEFAULT 1,
  posicao_x REAL DEFAULT 0,
  posicao_y REAL DEFAULT 0,
  posicao_z REAL DEFAULT 0,
  status_alocacao TEXT NOT NULL,
  observacoes_restricao TEXT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulacao_alertas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  tipo_alerta TEXT NOT NULL,
  mensagem TEXT NOT NULL,
  severidade TEXT NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulacao_regras_aplicadas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  regra_id INTEGER NULL,
  descricao_regra TEXT NOT NULL,
  status TEXT NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE
);
