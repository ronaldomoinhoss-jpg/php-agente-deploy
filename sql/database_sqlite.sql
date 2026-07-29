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
  acesso_descarga TEXT DEFAULT 'traseira',
  quantidade_disponivel INTEGER DEFAULT 1,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO veiculos (id, tipo, nome, capacidade_kg, capacidade_m3, comprimento_m, largura_m, altura_m, max_lastros, acesso_descarga, quantidade_disponivel, observacoes) VALUES
(1, 'Munck', 'Munck 12T com carroceria aberta', 12000.00, 24.50, 6.20, 2.45, 1.60, 2, 'lateral', 2, 'Ideal para transformadores, bobinas e cargas que exigem içamento lateral.'),
(2, 'Truck', 'Truck distribuição 15T', 15000.00, 42.00, 8.50, 2.45, 2.00, 2, 'traseira', 3, 'Veículo principal para abastecimento das bases regionais.'),
(3, 'Carreta', 'Carreta prancha 30T', 30000.00, 78.00, 13.50, 2.50, 2.30, 2, 'misto', 2, 'Melhor para postes, grandes bobinas e cargas consolidadas.'),
(4, 'VUC', 'VUC 6T urbano', 6000.00, 18.50, 4.80, 2.20, 1.90, 2, 'traseira', 2, 'Suporte para bases menores e redistribuição urbana.');

CREATE TABLE IF NOT EXISTS bases_operacionais (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo TEXT NOT NULL UNIQUE,
  nome TEXT NOT NULL,
  endereco TEXT NULL,
  ordem_padrao INTEGER DEFAULT 1,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO bases_operacionais (id, codigo, nome, endereco, ordem_padrao, observacoes) VALUES
(1, 'BASE-CENTRO', 'Base Centro', 'Região central de atendimento', 1, 'Base prioritária para entregas matinais.'),
(2, 'BASE-NORTE', 'Base Norte', 'Corredor norte de distribuição', 2, 'Recebe reforço de transformadores e cabos.'),
(3, 'BASE-SUL', 'Base Sul', 'Polo sul / equipes de campo', 3, 'Base com alto giro de ferragens e postes.');

CREATE TABLE IF NOT EXISTS materiais (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo TEXT NOT NULL UNIQUE,
  descricao TEXT NOT NULL,
  categoria TEXT NOT NULL,
  formato_fisico TEXT NOT NULL,
  peso_unitario_kg REAL NOT NULL,
  comprimento_m REAL NOT NULL,
  largura_m REAL NOT NULL,
  altura_m REAL NOT NULL,
  volume_unitario_m3 REAL NOT NULL,
  empilhavel INTEGER DEFAULT 1,
  max_lastros INTEGER DEFAULT 2,
  perfil_empilhamento TEXT DEFAULT 'reto',
  fragilidade TEXT DEFAULT 'baixa',
  amarracao_especial INTEGER DEFAULT 0,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO materiais (id, codigo, descricao, categoria, formato_fisico, peso_unitario_kg, comprimento_m, largura_m, altura_m, volume_unitario_m3, empilhavel, max_lastros, perfil_empilhamento, fragilidade, amarracao_especial, observacoes) VALUES
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

CREATE TABLE IF NOT EXISTS regras_operacionais (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  material_origem_id INTEGER NULL,
  categoria_origem TEXT NULL,
  material_destino_id INTEGER NULL,
  categoria_destino TEXT NULL,
  tipo_regra TEXT NOT NULL,
  severidade TEXT DEFAULT 'alerta',
  justificativa TEXT NULL,
  ativo INTEGER DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (material_origem_id) REFERENCES materiais(id) ON DELETE SET NULL,
  FOREIGN KEY (material_destino_id) REFERENCES materiais(id) ON DELETE SET NULL
);

INSERT OR IGNORE INTO regras_operacionais (id, material_origem_id, categoria_origem, material_destino_id, categoria_destino, tipo_regra, severidade, justificativa, ativo) VALUES
(1, NULL, 'transformador', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Transformadores devem permanecer no piso.', 1),
(2, NULL, 'poste', NULL, NULL, 'obrigatorio_lastro_1', 'bloqueante', 'Postes devem ficar apoiados na base da carroceria.', 1),
(3, NULL, 'condutor', NULL, NULL, 'piramidal_bobinas', 'alerta', 'Bobinas devem avaliar empilhamento piramidal antes de ocupar toda a base.', 1),
(4, NULL, 'chave', NULL, 'transformador', 'nao_sobrepor', 'bloqueante', 'Chaves seccionadoras não podem ficar sobre transformadores.', 1),
(5, NULL, 'transformador', NULL, NULL, 'sem_carga_superior', 'bloqueante', 'Proibido apoiar qualquer item sobre transformadores.', 1),
(6, NULL, 'ferragem', NULL, NULL, 'preferir_lastro_1', 'alerta', 'Ferragens densas ajudam a estabilizar a carga.', 1),
(7, NULL, 'chave', NULL, NULL, 'separacao_fisica', 'alerta', 'Material frágil deve ter folga operacional.', 1),
(8, NULL, 'transformador', NULL, NULL, 'amarracao_especial', 'alerta', 'Transformadores exigem travas e cintamento extra.', 1);

CREATE TABLE IF NOT EXISTS pedidos_carga (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo_pedido TEXT NOT NULL UNIQUE,
  descricao TEXT NOT NULL,
  status TEXT DEFAULT 'rascunho',
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pedido_itens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pedido_id INTEGER NOT NULL,
  material_id INTEGER NOT NULL,
  base_id INTEGER NOT NULL,
  quantidade INTEGER NOT NULL,
  ordem_entrega INTEGER NOT NULL,
  observacoes_item TEXT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_carga(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE RESTRICT,
  FOREIGN KEY (base_id) REFERENCES bases_operacionais(id) ON DELETE RESTRICT
);

INSERT OR IGNORE INTO pedidos_carga (id, codigo_pedido, descricao, status, observacoes) VALUES
(1, 'PED-20260729-001', 'Abastecimento semanal bases Centro e Norte', 'aberto', 'Pedido inicial para demonstração da cubagem multi-base.');

INSERT OR IGNORE INTO pedido_itens (id, pedido_id, material_id, base_id, quantidade, ordem_entrega, observacoes_item) VALUES
(1, 1, 1, 1, 3, 1, 'Base Centro recebe primeiro lote de bobinas.'),
(2, 1, 3, 1, 2, 1, 'Transformadores com descarga prioritária.'),
(3, 1, 8, 2, 4, 2, 'Material frágil para Base Norte.'),
(4, 1, 10, 2, 3, 2, 'Ferragens complementares.'),
(5, 1, 7, 2, 2, 2, 'Paletes de isoladores.');

CREATE TABLE IF NOT EXISTS simulacoes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo_simulacao TEXT NOT NULL UNIQUE,
  pedido_id INTEGER NOT NULL,
  usuario_id INTEGER NULL,
  score_total REAL NOT NULL,
  status TEXT NOT NULL,
  total_veiculos INTEGER NOT NULL,
  qtd_itens_total INTEGER NOT NULL,
  qtd_itens_alocados INTEGER NOT NULL,
  qtd_itens_nao_alocados INTEGER NOT NULL,
  peso_total_kg REAL NOT NULL,
  volume_total_m3 REAL NOT NULL,
  observacoes TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pedido_id) REFERENCES pedidos_carga(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulacao_veiculos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  veiculo_id INTEGER NOT NULL,
  slot_codigo TEXT NOT NULL,
  veiculo_nome TEXT NOT NULL,
  tipo_veiculo TEXT NOT NULL,
  acesso_descarga TEXT NOT NULL,
  comprimento_m REAL NOT NULL,
  largura_m REAL NOT NULL,
  altura_m REAL NOT NULL,
  capacidade_kg REAL NOT NULL,
  capacidade_m3 REAL NOT NULL,
  peso_total_kg REAL NOT NULL,
  volume_total_m3 REAL NOT NULL,
  ocupacao_peso_pct REAL NOT NULL,
  ocupacao_volume_pct REAL NOT NULL,
  centro_gravidade_x REAL NOT NULL,
  centro_gravidade_y REAL NOT NULL,
  lastros_utilizados INTEGER NOT NULL,
  ordem_carga TEXT NULL,
  ordem_descarga TEXT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS simulacao_posicoes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  simulacao_veiculo_id INTEGER NULL,
  pedido_item_id INTEGER NOT NULL,
  material_id INTEGER NOT NULL,
  base_id INTEGER NOT NULL,
  codigo_material TEXT NOT NULL,
  descricao_material TEXT NOT NULL,
  base_nome TEXT NOT NULL,
  ordem_entrega INTEGER NOT NULL,
  lastro_posicao INTEGER DEFAULT 1,
  posicao_x REAL DEFAULT 0,
  posicao_y REAL DEFAULT 0,
  posicao_z REAL DEFAULT 0,
  comprimento_m REAL NOT NULL,
  largura_m REAL NOT NULL,
  altura_m REAL NOT NULL,
  peso_unitario_kg REAL NOT NULL,
  volume_unitario_m3 REAL NOT NULL,
  status_alocacao TEXT NOT NULL,
  cor_hex TEXT NOT NULL,
  observacoes_restricao TEXT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens(id) ON DELETE RESTRICT,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE RESTRICT,
  FOREIGN KEY (base_id) REFERENCES bases_operacionais(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS simulacao_alertas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  simulacao_veiculo_id INTEGER NULL,
  tipo_alerta TEXT NOT NULL,
  severidade TEXT NOT NULL,
  mensagem TEXT NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulacao_regras_aplicadas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  simulacao_id INTEGER NOT NULL,
  simulacao_veiculo_id INTEGER NULL,
  regra_id INTEGER NULL,
  descricao_regra TEXT NOT NULL,
  status TEXT NOT NULL,
  FOREIGN KEY (simulacao_id) REFERENCES simulacoes(id) ON DELETE CASCADE,
  FOREIGN KEY (simulacao_veiculo_id) REFERENCES simulacao_veiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (regra_id) REFERENCES regras_operacionais(id) ON DELETE SET NULL
);
