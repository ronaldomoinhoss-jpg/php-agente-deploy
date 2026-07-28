<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/Veiculo.php';
require_once __DIR__ . '/Material.php';
require_once __DIR__ . '/Regra.php';

class Simulacao {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas() {
        $sql = "SELECT s.*, v.nome as veiculo_nome, v.tipo as veiculo_tipo, u.nome as usuario_nome
                FROM simulacoes s
                JOIN veiculos v ON s.veiculo_id = v.id
                LEFT JOIN usuarios u ON s.usuario_id = u.id
                ORDER BY s.id DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function buscarPorId($id) {
        $stmt = $this->pdo->prepare("SELECT s.*, v.nome as veiculo_nome, v.tipo as veiculo_tipo, 
                v.capacidade_kg as veiculo_capacidade_kg, v.capacidade_m3 as veiculo_capacidade_m3,
                v.comprimento_m as veiculo_comprimento_m, v.largura_m as veiculo_largura_m, v.altura_m as veiculo_altura_m,
                u.nome as usuario_nome
                FROM simulacoes s
                JOIN veiculos v ON s.veiculo_id = v.id
                LEFT JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.id = ?");
        $stmt->execute([$id]);
        $simulacao = $stmt->fetch();

        if ($simulacao) {
            // Buscar itens
            $stmtItens = $this->pdo->prepare("SELECT * FROM simulacao_itens WHERE simulacao_id = ? ORDER BY lastro_posicao ASC, id ASC");
            $stmtItens->execute([$id]);
            $simulacao['itens'] = $stmtItens->fetchAll();

            // Buscar alertas
            $stmtAlertas = $this->pdo->prepare("SELECT * FROM simulacao_alertas WHERE simulacao_id = ? ORDER BY id ASC");
            $stmtAlertas->execute([$id]);
            $simulacao['alertas'] = $stmtAlertas->fetchAll();

            // Buscar regras aplicadas
            $stmtRegras = $this->pdo->prepare("SELECT * FROM simulacao_regras_aplicadas WHERE simulacao_id = ? ORDER BY id ASC");
            $stmtRegras->execute([$id]);
            $simulacao['regras_aplicadas'] = $stmtRegras->fetchAll();
        }

        return $simulacao;
    }

    public function executar($veiculo_id, $itens_solicitados, $max_lastros_permitido = 2, $observacoes = '') {
        $veiculoModel = new Veiculo();
        $materialModel = new Material();
        $regraModel = new Regra();

        $veiculo = $veiculoModel->buscarPorId($veiculo_id);
        if (!$veiculo) {
            throw new Exception("Veículo não encontrado.");
        }

        $regrasAtivas = $regraModel->listarAtivas();

        // 1. Expandir e enriquecer lista de materiais
        $listaMateriais = [];
        $pesoTotalSolicitado = 0;
        $volumeTotalSolicitado = 0;

        foreach ($itens_solicitados as $itemReq) {
            $matId = (int)$itemReq['material_id'];
            $qtd = max(1, (int)$itemReq['quantidade']);
            $material = $materialModel->buscarPorId($matId);

            if (!$material) continue;

            $pesoItem = $material['peso_unitario_kg'] * $qtd;
            $volItem = $material['volume_unitario_m3'] * $qtd;

            $pesoTotalSolicitado += $pesoItem;
            $volumeTotalSolicitado += $volItem;

            $listaMateriais[] = [
                'material_id' => $material['id'],
                'codigo' => $material['codigo'],
                'descricao' => $material['descricao'],
                'tipo' => $material['tipo'],
                'peso_unitario_kg' => (float)$material['peso_unitario_kg'],
                'comprimento_m' => (float)$material['comprimento_m'],
                'largura_m' => (float)$material['largura_m'],
                'altura_m' => (float)$material['altura_m'],
                'volume_unitario_m3' => (float)$material['volume_unitario_m3'],
                'permite_empilhamento' => (int)$material['permite_empilhamento'],
                'max_lastros' => min(2, (int)$material['max_lastros']),
                'fragilidade' => $material['fragilidade'],
                'quantidade_solicitada' => $qtd,
                'observacoes' => $material['observacoes']
            ];
        }

        if (empty($listaMateriais)) {
            throw new Exception("Nenhum material válido foi selecionado para a simulação.");
        }

        // Limitador global de lastros do veículo vs parâmetro do usuário
        $maxLastrosEfetivo = min((int)$veiculo['max_lastros'], (int)$max_lastros_permitido, 2);

        // 2. Classificar e separar itens por regras
        // Bobinas de cabo separadas para distribuição piramidal
        $bobinas = [];
        $outrosItens = [];

        foreach ($listaMateriais as $mat) {
            if ($mat['tipo'] === 'bobina_cabo') {
                $bobinas[] = $mat;
            } else {
                $outrosItens[] = $mat;
            }
        }

        // Ordenar outros itens: Pesados e Não Empilháveis no início (Prioridade Lastro 1)
        usort($outrosItens, function($a, $b) {
            if ($a['permite_empilhamento'] != $b['permite_empilhamento']) {
                return $a['permite_empilhamento'] - $b['permite_empilhamento']; // não empilháveis (0) primeiro
            }
            return $b['peso_unitario_kg'] <=> $a['peso_unitario_kg']; // mais pesados primeiro
        });

        // 3. Processamento de Alocação Espacial (Grade 3D / Bin Packing Heurístico por Lastros)
        $comprimentoVeiculo = (float)$veiculo['comprimento_m'];
        $larguraVeiculo = (float)$veiculo['largura_m'];
        $alturaVeiculo = (float)$veiculo['altura_m'];
        $capacidadeKgVeiculo = (float)$veiculo['capacidade_kg'];
        $capacidadeM3Veiculo = (float)$veiculo['capacidade_m3'];

        $itensAlocados = [];
        $itensNaoAlocados = [];
        $alertas = [];
        $regrasAplicadas = [];

        $pesoAtualCarga = 0;
        $volumeAtualCarga = 0;

        // Ocupação de área útil em cada lastro
        // Lastro 1 (Piso)
        $lastro1_ocupado = []; // Matriz ou lista de áreas [x, y, w, h]
        // Lastro 2 (Camada Superior)
        $lastro2_ocupado = [];

        $currentX_l1 = 0.1;
        $currentY_l1 = 0.1;
        $maxRowHeight_l1 = 0;

        $currentX_l2 = 0.1;
        $currentY_l2 = 0.1;
        $maxRowHeight_l2 = 0;

        // 3.A Alocação de Bobinas (Regra Piramidal)
        $temBobinas = !empty($bobinas);
        if ($temBobinas) {
            foreach ($bobinas as $b) {
                $qtdTotal = $b['quantidade_solicitada'];
                $alocadasUnidades = 0;

                // Cálculo de divisão piramidal: Base (L1) recebe teto(qtd/2) ou superior, L2 recebe o restante
                if ($qtdTotal == 1) {
                    $qtdBase = 1;
                    $qtdTopo = 0;
                } else if ($qtdTotal == 2) {
                    $qtdBase = 2;
                    $qtdTopo = 0;
                } else {
                    $qtdTopo = floor($qtdTotal / 2);
                    $qtdBase = $qtdTotal - $qtdTopo;
                }

                // Alocar Base das Bobinas no Lastro 1
                for ($i = 0; $i < $qtdBase; $i++) {
                    if (($pesoAtualCarga + $b['peso_unitario_kg'] <= $capacidadeKgVeiculo) &&
                        ($volumeAtualCarga + $b['volume_unitario_m3'] <= $capacidadeM3Veiculo)) {
                        
                        if ($currentX_l1 + $b['comprimento_m'] > $comprimentoVeiculo) {
                            $currentX_l1 = 0.1;
                            $currentY_l1 += $maxRowHeight_l1 + 0.1;
                            $maxRowHeight_l1 = 0;
                        }

                        if ($currentY_l1 + $b['largura_m'] <= $larguraVeiculo) {
                            $itensAlocados[] = [
                                'material_id' => $b['material_id'],
                                'codigo_material' => $b['codigo'],
                                'descricao_material' => $b['descricao'],
                                'quantidade' => 1,
                                'peso_unitario_kg' => $b['peso_unitario_kg'],
                                'peso_total_kg' => $b['peso_unitario_kg'],
                                'volume_unitario_m3' => $b['volume_unitario_m3'],
                                'volume_total_m3' => $b['volume_unitario_m3'],
                                'lastro_posicao' => 1,
                                'posicao_x' => round($currentX_l1, 2),
                                'posicao_y' => round($currentY_l1, 2),
                                'posicao_z' => 0.00,
                                'status_alocacao' => 'alocado',
                                'observacoes_restricao' => 'Base da pirâmide (Lastro 1)'
                            ];

                            $pesoAtualCarga += $b['peso_unitario_kg'];
                            $volumeAtualCarga += $b['volume_unitario_m3'];
                            $alocadasUnidades++;

                            $currentX_l1 += $b['comprimento_m'] + 0.1;
                            $maxRowHeight_l1 = max($maxRowHeight_l1, $b['largura_m']);
                        }
                    }
                }

                // Alocar Topo da Pirâmide no Lastro 2 (Centralizado sobre a base)
                if ($qtdTopo > 0 && $maxLastrosEfetivo >= 2) {
                    for ($i = 0; $i < $qtdTopo; $i++) {
                        if (($pesoAtualCarga + $b['peso_unitario_kg'] <= $capacidadeKgVeiculo) &&
                            ($volumeAtualCarga + $b['volume_unitario_m3'] <= $capacidadeM3Veiculo)) {

                            if ($currentX_l2 + $b['comprimento_m'] > $comprimentoVeiculo) {
                                $currentX_l2 = 0.1;
                                $currentY_l2 += $maxRowHeight_l2 + 0.1;
                                $maxRowHeight_l2 = 0;
                            }

                            if ($currentY_l2 + $b['largura_m'] <= $larguraVeiculo) {
                                $itensAlocados[] = [
                                    'material_id' => $b['material_id'],
                                    'codigo_material' => $b['codigo'],
                                    'descricao_material' => $b['descricao'],
                                    'quantidade' => 1,
                                    'peso_unitario_kg' => $b['peso_unitario_kg'],
                                    'peso_total_kg' => $b['peso_unitario_kg'],
                                    'volume_unitario_m3' => $b['volume_unitario_m3'],
                                    'volume_total_m3' => $b['volume_unitario_m3'],
                                    'lastro_posicao' => 2,
                                    'posicao_x' => round($currentX_l2 + 0.2, 2), // deslocado centralizado
                                    'posicao_y' => round($currentY_l2, 2),
                                    'posicao_z' => round($b['altura_m'], 2),
                                    'status_alocacao' => 'alocado',
                                    'observacoes_restricao' => 'Topo da pirâmide (Lastro 2)'
                                ];

                                $pesoAtualCarga += $b['peso_unitario_kg'];
                                $volumeAtualCarga += $b['volume_unitario_m3'];
                                $alocadasUnidades++;

                                $currentX_l2 += $b['comprimento_m'] + 0.1;
                                $maxRowHeight_l2 = max($maxRowHeight_l2, $b['largura_m']);
                            }
                        }
                    }
                }

                $regrasAplicadas[] = [
                    'regra_id' => 2,
                    'descricao_regra' => "Bobinas de cabo ({$b['codigo']}) alocadas obrigatoriamente em formato piramidal.",
                    'status' => 'cumprida'
                ];

                if ($alocadasUnidades < $qtdTotal) {
                    $pendente = $qtdTotal - $alocadasUnidades;
                    $itensNaoAlocados[] = [
                        'material_id' => $b['material_id'],
                        'codigo_material' => $b['codigo'],
                        'descricao_material' => $b['descricao'],
                        'quantidade' => $pendente,
                        'peso_total_kg' => $b['peso_unitario_kg'] * $pendente,
                        'volume_total_m3' => $b['volume_unitario_m3'] * $pendente,
                        'motivo' => 'Excesso de peso/volume ou limitação física de espaço piramidal.'
                    ];
                }
            }
        }

        // 3.B Alocação dos Demais Materiais (Transformadores, Postes, Chaves, Isoladores, etc.)
        foreach ($outrosItens as $mat) {
            $qtd = $mat['quantidade_solicitada'];
            $alocadosItem = 0;

            for ($k = 0; $k < $qtd; $k++) {
                // Verificar se ultrapassa limite de peso ou volume do veículo
                if ($pesoAtualCarga + $mat['peso_unitario_kg'] > $capacidadeKgVeiculo ||
                    $volumeAtualCarga + $mat['volume_unitario_m3'] > $capacidadeM3Veiculo) {
                    break;
                }

                $alocado = false;

                // Tentar Lastro 1 primeiro
                if ($currentX_l1 + $mat['comprimento_m'] > $comprimentoVeiculo) {
                    $currentX_l1 = 0.1;
                    $currentY_l1 += $maxRowHeight_l1 + 0.1;
                    $maxRowHeight_l1 = 0;
                }

                if ($currentY_l1 + $mat['largura_m'] <= $larguraVeiculo) {
                    $itensAlocados[] = [
                        'material_id' => $mat['material_id'],
                        'codigo_material' => $mat['codigo'],
                        'descricao_material' => $mat['descricao'],
                        'quantidade' => 1,
                        'peso_unitario_kg' => $mat['peso_unitario_kg'],
                        'peso_total_kg' => $mat['peso_unitario_kg'],
                        'volume_unitario_m3' => $mat['volume_unitario_m3'],
                        'volume_total_m3' => $mat['volume_unitario_m3'],
                        'lastro_posicao' => 1,
                        'posicao_x' => round($currentX_l1, 2),
                        'posicao_y' => round($currentY_l1, 2),
                        'posicao_z' => 0.00,
                        'status_alocacao' => 'alocado',
                        'observacoes_restricao' => 'Base da carroceria (Lastro 1)'
                    ];

                    $pesoAtualCarga += $mat['peso_unitario_kg'];
                    $volumeAtualCarga += $mat['volume_unitario_m3'];
                    $alocadosItem++;
                    $alocado = true;

                    $currentX_l1 += $mat['comprimento_m'] + 0.1;
                    $maxRowHeight_l1 = max($maxRowHeight_l1, $mat['largura_m']);
                }

                // Se não coube no Lastro 1, tentar Lastro 2 (se empilhável e permitido)
                if (!$alocado && $mat['permite_empilhamento'] == 1 && $mat['max_lastros'] >= 2 && $maxLastrosEfetivo >= 2) {
                    if ($currentX_l2 + $mat['comprimento_m'] > $comprimentoVeiculo) {
                        $currentX_l2 = 0.1;
                        $currentY_l2 += $maxRowHeight_l2 + 0.1;
                        $maxRowHeight_l2 = 0;
                    }

                    if ($currentY_l2 + $mat['largura_m'] <= $larguraVeiculo) {
                        $itensAlocados[] = [
                            'material_id' => $mat['material_id'],
                            'codigo_material' => $mat['codigo'],
                            'descricao_material' => $mat['descricao'],
                            'quantidade' => 1,
                            'peso_unitario_kg' => $mat['peso_unitario_kg'],
                            'peso_total_kg' => $mat['peso_unitario_kg'],
                            'volume_unitario_m3' => $mat['volume_unitario_m3'],
                            'volume_total_m3' => $mat['volume_unitario_m3'],
                            'lastro_posicao' => 2,
                            'posicao_x' => round($currentX_l2, 2),
                            'posicao_y' => round($currentY_l2, 2),
                            'posicao_z' => round($mat['altura_m'], 2),
                            'status_alocacao' => 'alocado',
                            'observacoes_restricao' => 'Segunda Camada (Lastro 2)'
                        ];

                        $pesoAtualCarga += $mat['peso_unitario_kg'];
                        $volumeAtualCarga += $mat['volume_unitario_m3'];
                        $alocadosItem++;
                        $alocado = true;

                        $currentX_l2 += $mat['comprimento_m'] + 0.1;
                        $maxRowHeight_l2 = max($maxRowHeight_l2, $mat['largura_m']);
                    }
                }
            }

            if ($alocadosItem < $qtd) {
                $pendente = $qtd - $alocadosItem;
                $itensNaoAlocados[] = [
                    'material_id' => $mat['material_id'],
                    'codigo_material' => $mat['codigo'],
                    'descricao_material' => $mat['descricao'],
                    'quantidade' => $pendente,
                    'peso_total_kg' => $mat['peso_unitario_kg'] * $pendente,
                    'volume_total_m3' => $mat['volume_unitario_m3'] * $pendente,
                    'motivo' => $mat['permite_empilhamento'] == 0 ? 'Item não empilhável. Lastro 1 lotado.' : 'Limite de capacidade/espaço atingido.'
                ];
            }
        }

        // 4. Validação de Regras do Usuário e Operacionais
        foreach ($regrasAtivas as $r) {
            $aplicada = false;

            // Regra: Não empilhar/sobrepor (ex: transformador)
            if ($r['tipo_regra'] === 'obrigatorio_lastro_1') {
                foreach ($itensAlocados as $it) {
                    if (($r['tipo_material_origem'] && $it['material_id'] == $r['material_origem_id']) ||
                        ($r['tipo_material_origem'] && strtolower($it['descricao_material']) == strtolower($r['tipo_material_origem']))) {
                        if ($it['lastro_posicao'] != 1) {
                            if ($r['prioridade'] == 'bloqueante') {
                                $alertas[] = [
                                    'tipo_alerta' => 'regra_violada',
                                    'mensagem' => "Regra Bloqueante Violada: {$it['descricao_material']} deve obrigatoriamente ser alocado no Lastro 1.",
                                    'severidade' => 'danger'
                                ];
                            }
                        }
                    }
                }
            }

            $regrasAplicadas[] = [
                'regra_id' => $r['id'],
                'descricao_regra' => "Regra ({$r['prioridade']}): " . ($r['justificativa'] ? $r['justificativa'] : "Restrição operacional para {$r['tipo_regra']}"),
                'status' => 'cumprida'
            ];
        }

        // 5. Cálculos dos KPIs Finais
        $pctPeso = round(($pesoAtualCarga / $capacidadeKgVeiculo) * 100, 2);
        $pctVolume = round(($volumeAtualCarga / $capacidadeM3Veiculo) * 100, 2);
        $cubagemTotal = round($volumeAtualCarga, 4);

        $lastrosUtilizados = 1;
        foreach ($itensAlocados as $it) {
            if ($it['lastro_posicao'] > $lastrosUtilizados) {
                $lastrosUtilizados = $it['lastro_posicao'];
            }
        }

        // Cálculo de Centro de Gravidade Aproximado (%)
        $somaMomentoX = 0;
        $somaMomentoY = 0;
        $somaPeso = 0;

        foreach ($itensAlocados as $it) {
            $somaMomentoX += ($it['posicao_x'] + 0.5) * $it['peso_unitario_kg'];
            $somaMomentoY += ($it['posicao_y'] + 0.5) * $it['peso_unitario_kg'];
            $somaPeso += $it['peso_unitario_kg'];
        }

        $centroGravidadeX = $somaPeso > 0 ? round((($somaMomentoX / $somaPeso) / $comprimentoVeiculo) * 100, 2) : 50.00;
        $centroGravidadeY = $somaPeso > 0 ? round((($somaMomentoY / $somaPeso) / $larguraVeiculo) * 100, 2) : 50.00;

        // Desequilíbrio de Carga Alert
        if ($centroGravidadeX < 30 || $centroGravidadeX > 70) {
            $alertas[] = [
                'tipo_alerta' => 'desequilibrio_peso',
                'mensagem' => "Atenção ao Centro de Gravidade Longitudinal ({$centroGravidadeX}%). Peso concentrado nas extremidades da carroceria.",
                'severidade' => 'warning'
            ];
        }

        // Alertas de Excesso de Carga e Itens não Alocados
        $qtdNaoAlocadosTotal = 0;
        foreach ($itensNaoAlocados as $na) {
            $qtdNaoAlocadosTotal += $na['quantidade'];
        }

        if ($qtdNaoAlocadosTotal > 0) {
            $alertas[] = [
                'tipo_alerta' => 'item_nao_alocado',
                'mensagem' => "Atenção: {$qtdNaoAlocadosTotal} unidade(s) de material não couberam no veículo selecionado.",
                'severidade' => 'danger'
            ];

            // Sugestão de veículo maior
            if ($veiculo['tipo'] === 'Munck') {
                $alertas[] = [
                    'tipo_alerta' => 'atencao',
                    'mensagem' => "Sugestão Operacional: Altere o veículo para um Caminhão Truck (15T) ou Carreta (30T) para acomodar a carga pendente.",
                    'severidade' => 'info'
                ];
            } else if ($veiculo['tipo'] === 'Truck') {
                $alertas[] = [
                    'tipo_alerta' => 'atencao',
                    'mensagem' => "Sugestão Operacional: Altere o veículo para uma Carreta Prancha (30T) para transportar a carga completa em uma só viagem.",
                    'severidade' => 'info'
                ];
            }
        }

        // Definição do Status Final da Simulação
        $statusFinal = 'aprovado';
        if ($qtdNaoAlocadosTotal > 0 || $pctPeso > 100 || $pctVolume > 100) {
            $statusFinal = 'reprovado';
        } else {
            foreach ($alertas as $al) {
                if ($al['severidade'] === 'warning') {
                    $statusFinal = 'aprovado_com_alerta';
                }
            }
        }

        // Salvar Simulação no Banco de Dados
        $codigoSimulacao = 'SIM-' . date('Ymd-His') . '-' . rand(100, 999);
        $usuarioLogado = get_logged_user();

        $stmt = $this->pdo->prepare("INSERT INTO simulacoes (
            codigo_simulacao, usuario_id, veiculo_id, max_lastros_permitido, 
            peso_total_kg, volume_total_m3, ocupacao_peso_pct, ocupacao_volume_pct, 
            cubagem_total_m3, lastros_utilizados, qtd_itens_alocados, qtd_itens_nao_alocados, 
            status, centro_gravidade_x, centro_gravidade_y, observacoes_operacionais
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $codigoSimulacao,
            $usuarioLogado['id'],
            $veiculo['id'],
            $maxLastrosEfetivo,
            $pesoAtualCarga,
            $volumeAtualCarga,
            $pctPeso,
            $pctVolume,
            $cubagemTotal,
            $lastrosUtilizados,
            count($itensAlocados),
            $qtdNaoAlocadosTotal,
            $statusFinal,
            $centroGravidadeX,
            $centroGravidadeY,
            $observacoes
        ]);

        $simulacaoId = $this->pdo->lastInsertId();

        // Salvar Itens Alocados
        $stmtItem = $this->pdo->prepare("INSERT INTO simulacao_itens (
            simulacao_id, material_id, codigo_material, descricao_material, quantidade,
            peso_unitario_kg, peso_total_kg, volume_unitario_m3, volume_total_m3,
            lastro_posicao, posicao_x, posicao_y, posicao_z, status_alocacao, observacoes_restricao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($itensAlocados as $it) {
            $stmtItem->execute([
                $simulacaoId, $it['material_id'], $it['codigo_material'], $it['descricao_material'], $it['quantidade'],
                $it['peso_unitario_kg'], $it['peso_total_kg'], $it['volume_unitario_m3'], $it['volume_total_m3'],
                $it['lastro_posicao'], $it['posicao_x'], $it['posicao_y'], $it['posicao_z'], $it['status_alocacao'], $it['observacoes_restricao']
            ]);
        }

        // Salvar Itens NÃO Alocados
        foreach ($itensNaoAlocados as $na) {
            $stmtItem->execute([
                $simulacaoId, $na['material_id'], $na['codigo_material'], $na['descricao_material'], $na['quantidade'],
                $na['peso_total_kg'] / $na['quantidade'], $na['peso_total_kg'], $na['volume_total_m3'] / $na['quantidade'], $na['volume_total_m3'],
                0, 0, 0, 0, 'nao_alocado', $na['motivo']
            ]);
        }

        // Salvar Alertas
        $stmtAlerta = $this->pdo->prepare("INSERT INTO simulacao_alertas (simulacao_id, tipo_alerta, mensagem, severidade) VALUES (?, ?, ?, ?)");
        foreach ($alertas as $al) {
            $stmtAlerta->execute([$simulacaoId, $al['tipo_alerta'], $al['mensagem'], $al['severidade']]);
        }

        // Salvar Regras Aplicadas
        $stmtRegra = $this->pdo->prepare("INSERT INTO simulacao_regras_aplicadas (simulacao_id, regra_id, descricao_regra, status) VALUES (?, ?, ?, ?)");
        foreach ($regrasAplicadas as $rg) {
            $stmtRegra->execute([$simulacaoId, $rg['regra_id'], $rg['descricao_regra'], $rg['status']]);
        }

        return $this->buscarPorId($simulacaoId);
    }

    public function excluir($id) {
        $stmt = $this->pdo->prepare("DELETE FROM simulacoes WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function getEstatísticasDashboard() {
        $stmtSim = $this->pdo->query("SELECT 
            COUNT(*) as total_simulacoes,
            COALESCE(AVG(ocupacao_peso_pct), 0) as ocupacao_media_peso,
            COALESCE(AVG(ocupacao_volume_pct), 0) as ocupacao_media_volume,
            COALESCE(MAX(cubagem_total_m3), 0) as ultima_cubagem
            FROM simulacoes");
        $resSim = $stmtSim->fetch();

        $stmtAlertas = $this->pdo->query("SELECT COUNT(*) as total_alertas FROM simulacao_alertas WHERE severidade = 'danger'");
        $resAlertas = $stmtAlertas->fetch();

        return array_merge($resSim, ['total_alertas' => $resAlertas['total_alertas'] ?? 0]);
    }
}
