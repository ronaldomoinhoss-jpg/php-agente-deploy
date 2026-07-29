<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/Veiculo.php';
require_once __DIR__ . '/PedidoCarga.php';
require_once __DIR__ . '/Regra.php';

class Simulacao {
    private PDO $pdo;
    private array $baseColors = ['#2563eb', '#16a34a', '#ea580c', '#dc2626', '#7c3aed', '#0891b2'];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas(): array {
        $sql = 'SELECT s.*, p.codigo_pedido, p.descricao AS pedido_descricao
                FROM simulacoes s
                JOIN pedidos_carga p ON p.id = s.pedido_id
                ORDER BY s.id DESC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.codigo_pedido, p.descricao AS pedido_descricao
             FROM simulacoes s
             JOIN pedidos_carga p ON p.id = s.pedido_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $sim = $stmt->fetch();
        if (!$sim) {
            return null;
        }

        $stmtVeiculos = $this->pdo->prepare(
            'SELECT * FROM simulacao_veiculos WHERE simulacao_id = ? ORDER BY id ASC'
        );
        $stmtVeiculos->execute([$id]);
        $veiculos = $stmtVeiculos->fetchAll();

        foreach ($veiculos as &$veiculo) {
            $stmtPos = $this->pdo->prepare(
                'SELECT p.*, m.categoria, m.formato_fisico, m.empilhavel, m.max_lastros, m.perfil_empilhamento, m.fragilidade, m.amarracao_especial
                 FROM simulacao_posicoes p
                 JOIN materiais m ON m.id = p.material_id
                 WHERE p.simulacao_veiculo_id = ?
                 ORDER BY p.ordem_entrega ASC, p.lastro_posicao ASC, p.id ASC'
            );
            $stmtPos->execute([$veiculo['id']]);
            $veiculo['itens'] = $stmtPos->fetchAll();
        }
        unset($veiculo);

        $stmtPend = $this->pdo->prepare(
            'SELECT p.*, m.categoria, m.formato_fisico, m.empilhavel, m.max_lastros, m.perfil_empilhamento, m.fragilidade, m.amarracao_especial
             FROM simulacao_posicoes p
             JOIN materiais m ON m.id = p.material_id
             WHERE p.simulacao_id = ? AND p.simulacao_veiculo_id IS NULL AND p.status_alocacao = "nao_alocado"
             ORDER BY p.ordem_entrega ASC, p.id ASC'
        );
        $stmtPend->execute([$id]);
        $sim['itens_nao_alocados'] = $stmtPend->fetchAll();

        $stmtAlertas = $this->pdo->prepare(
            'SELECT * FROM simulacao_alertas WHERE simulacao_id = ? ORDER BY id ASC'
        );
        $stmtAlertas->execute([$id]);
        $sim['alertas'] = $stmtAlertas->fetchAll();

        $stmtRegras = $this->pdo->prepare(
            'SELECT * FROM simulacao_regras_aplicadas WHERE simulacao_id = ? ORDER BY id ASC'
        );
        $stmtRegras->execute([$id]);
        $sim['regras_aplicadas'] = $stmtRegras->fetchAll();

        $sim['veiculos'] = $veiculos;
        return $sim;
    }

    public function executar(int $pedidoId, array $frotaSelecionada, string $observacoes = ''): array {
        $pedidoModel = new PedidoCarga();
        $veiculoModel = new Veiculo();
        $regraModel = new Regra();

        $pedido = $pedidoModel->buscarPorId($pedidoId);
        if (!$pedido) {
            throw new Exception('Pedido de carga não encontrado.');
        }

        if (empty($pedido['itens'])) {
            throw new Exception('O pedido selecionado não possui itens para simulação.');
        }

        $regras = $regraModel->listarAtivas();
        $slots = $this->buildVehicleSlots($frotaSelecionada, $veiculoModel);
        if (empty($slots)) {
            throw new Exception('Selecione ao menos um veículo candidato para a simulação.');
        }

        $units = $this->expandPedidoItens($pedido['itens']);
        $bestResult = null;

        $slotCount = count($slots);
        for ($size = 1; $size <= $slotCount; $size++) {
            foreach ($this->generateCombinations($slots, $size) as $combo) {
                $result = $this->simulateCombination($combo, $units, $regras);
                if ($bestResult === null || $result['score_total'] < $bestResult['score_total']) {
                    $bestResult = $result;
                }
            }

            if ($bestResult && $bestResult['qtd_itens_nao_alocados'] === 0) {
                break;
            }
        }

        if ($bestResult === null) {
            throw new Exception('Não foi possível gerar uma proposta de alocação.');
        }

        $simId = $this->persistSimulation($pedido, $bestResult, $observacoes);
        return $this->buscarPorId($simId);
    }

    public function executarManual(int $pedidoId, array $frotaSelecionada, array $placements, string $observacoes = ''): array {
        $pedidoModel = new PedidoCarga();
        $veiculoModel = new Veiculo();
        $regraModel = new Regra();

        $pedido = $pedidoModel->buscarPorId($pedidoId);
        if (!$pedido) {
            throw new Exception('Pedido de carga não encontrado.');
        }

        if (empty($pedido['itens'])) {
            throw new Exception('O pedido selecionado não possui itens para montagem manual.');
        }

        $regras = $regraModel->listarAtivas();
        $slots = $this->buildVehicleSlots($frotaSelecionada, $veiculoModel);
        if (empty($slots)) {
            throw new Exception('Selecione ao menos um veículo candidato para a montagem manual.');
        }

        $units = $this->expandPedidoItens($pedido['itens']);
        $unitsByKey = [];
        foreach ($units as $unit) {
            $unitsByKey[$unit['unit_key']] = $unit;
        }

        $statesBySlotKey = [];
        foreach ($slots as $slot) {
            $statesBySlotKey[$slot['slot_key']] = [
                'veiculo' => $slot,
                'slot_codigo' => $slot['slot_codigo'],
                'placements' => [],
                'peso_total_kg' => 0.0,
                'volume_total_m3' => 0.0,
            ];
        }

        $usedUnitKeys = [];
        foreach ($placements as $placementInput) {
            $unitKey = (string) ($placementInput['unit_key'] ?? '');
            $slotKey = (string) ($placementInput['vehicle_slot_key'] ?? '');
            if ($unitKey === '' || $slotKey === '') {
                throw new Exception('Cada item manual precisa informar a unidade e o veículo de destino.');
            }

            if (!isset($unitsByKey[$unitKey])) {
                throw new Exception("Unidade manual '{$unitKey}' não encontrada no pedido.");
            }

            if (!isset($statesBySlotKey[$slotKey])) {
                throw new Exception("Veículo manual '{$slotKey}' não encontrado na frota selecionada.");
            }

            if (isset($usedUnitKeys[$unitKey])) {
                throw new Exception("A unidade '{$unitKey}' foi posicionada mais de uma vez.");
            }

            $unit = $unitsByKey[$unitKey];
            $placement = $this->buildManualPlacementPayload($unit, $placementInput);
            $statesBySlotKey[$slotKey]['placements'][] = $placement;
            $statesBySlotKey[$slotKey]['peso_total_kg'] += $placement['peso_unitario_kg'];
            $statesBySlotKey[$slotKey]['volume_total_m3'] += $placement['volume_unitario_m3'];
            $usedUnitKeys[$unitKey] = true;
        }

        $unallocated = [];
        foreach ($units as $unit) {
            if (isset($usedUnitKeys[$unit['unit_key']])) {
                continue;
            }
            $unit['status_alocacao'] = 'nao_alocado';
            $unit['observacoes_restricao'] = 'Unidade não posicionada na montagem manual.';
            $unallocated[] = $unit;
        }

        $vehicles = [];
        $alerts = [];
        $rulesApplied = [];
        $totalBlockingViolations = 0;
        $pesoTotal = 0.0;
        $volumeTotal = 0.0;
        $usedVehicles = 0;
        $scoreTotal = 0.0;

        foreach ($statesBySlotKey as $slotKey => $state) {
            if (empty($state['placements'])) {
                continue;
            }

            $validated = $this->validateDraftVehicleState($state, $regras);
            $vehicles[] = $validated;
            $usedVehicles++;
            $pesoTotal += $validated['peso_total_kg'];
            $volumeTotal += $validated['volume_total_m3'];
            $totalBlockingViolations += $validated['blocking_violations'];
            $scoreTotal += ($validated['blocking_violations'] * 100000) + ($validated['remanejo_penalty'] * 250) + $validated['balance_penalty'] + $validated['waste_penalty'];

            $alerts = array_merge($alerts, $validated['alertas']);
            $rulesApplied = array_merge($rulesApplied, $validated['regras_aplicadas']);
        }

        foreach ($unallocated as $item) {
            $alerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'item_nao_alocado',
                'severidade' => 'danger',
                'mensagem' => "{$item['codigo_material']} para {$item['base_nome']} não foi alocado na montagem manual.",
            ];
        }

        $scoreTotal += (count($unallocated) * 25000) + ($usedVehicles * 400);
        $status = 'aprovado';
        if ($totalBlockingViolations > 0 || count($unallocated) > 0) {
            $status = 'reprovado';
        } else {
            foreach ($alerts as $alert) {
                if (in_array($alert['severidade'], ['warning', 'danger'], true)) {
                    $status = 'aprovado_com_alerta';
                    break;
                }
            }
        }

        $result = [
            'score_total' => round($scoreTotal, 2),
            'status' => $status,
            'veiculos' => $vehicles,
            'itens_nao_alocados' => $unallocated,
            'alertas' => $alerts,
            'regras_aplicadas' => $rulesApplied,
            'qtd_itens_total' => count($units),
            'qtd_itens_alocados' => count($units) - count($unallocated),
            'qtd_itens_nao_alocados' => count($unallocated),
            'peso_total_kg' => round($pesoTotal, 2),
            'volume_total_m3' => round($volumeTotal, 4),
            'total_veiculos' => $usedVehicles,
        ];

        $simId = $this->persistSimulation($pedido, $result, $observacoes);
        return $this->buscarPorId($simId);
    }

    public function atualizarMontagemManual(int $simulacaoId, int $simulacaoVeiculoId, array $itens): array {
        $simulacao = $this->buscarPorId($simulacaoId);
        if (!$simulacao) {
            throw new Exception('Simulação não encontrada para atualização manual.');
        }

        $vehicleFound = null;
        foreach ($simulacao['veiculos'] as $vehicle) {
            if ((int) $vehicle['id'] === $simulacaoVeiculoId) {
                $vehicleFound = $vehicle;
                break;
            }
        }

        if (!$vehicleFound) {
            throw new Exception('Veículo da simulação não encontrado.');
        }

        $currentItemsById = [];
        foreach ($vehicleFound['itens'] as $item) {
            $currentItemsById[(int) $item['id']] = $item;
        }

        $updatedPlacements = [];
        foreach ($itens as $itemPayload) {
            $itemId = (int) ($itemPayload['id'] ?? 0);
            if ($itemId <= 0 || !isset($currentItemsById[$itemId])) {
                throw new Exception('Um dos itens enviados não pertence ao veículo selecionado.');
            }

            $stored = $currentItemsById[$itemId];
            $effectiveLength = isset($itemPayload['comprimento_m']) ? round((float) $itemPayload['comprimento_m'], 2) : (float) $stored['comprimento_m'];
            $effectiveWidth = isset($itemPayload['largura_m']) ? round((float) $itemPayload['largura_m'], 2) : (float) $stored['largura_m'];
            $lastro = max(1, min(2, (int) ($itemPayload['lastro_posicao'] ?? $stored['lastro_posicao'])));
            $x = round((float) ($itemPayload['posicao_x'] ?? $stored['posicao_x']), 2);
            $y = round((float) ($itemPayload['posicao_y'] ?? $stored['posicao_y']), 2);

            if ($effectiveLength <= 0 || $effectiveWidth <= 0) {
                throw new Exception("Dimensões inválidas para {$stored['codigo_material']}.");
            }

            if ($this->isBobinaPlacement($stored)) {
                $effectiveLength = (float) $stored['comprimento_m'];
                $effectiveWidth = (float) $stored['largura_m'];
            }

            $placement = array_merge($stored, [
                'lastro_posicao' => $lastro,
                'posicao_x' => $x,
                'posicao_y' => $y,
                'posicao_z' => 0.0,
                'comprimento_m' => $effectiveLength,
                'largura_m' => $effectiveWidth,
                'orientacao_manual' => (string) ($itemPayload['orientacao_manual'] ?? 'base_0'),
                'observacoes_restricao' => $this->appendManualTag(
                    (string) ($stored['observacoes_restricao'] ?? ''),
                    $this->isBobinaPlacement($stored)
                        ? 'Montagem manual com bobina mantida em pe.'
                        : 'Montagem manual atualizada.'
                ),
            ]);

            $updatedPlacements[] = $placement;
        }

        $validatedVehicle = $this->validateManualVehicleState($vehicleFound, $updatedPlacements);

        $this->pdo->beginTransaction();
        try {
            $stmtUpdate = $this->pdo->prepare(
                'UPDATE simulacao_posicoes
                 SET lastro_posicao = ?, posicao_x = ?, posicao_y = ?, posicao_z = ?, comprimento_m = ?, largura_m = ?, observacoes_restricao = ?
                 WHERE id = ? AND simulacao_veiculo_id = ?'
            );

            foreach ($validatedVehicle['placements'] as $placement) {
                $stmtUpdate->execute([
                    $placement['lastro_posicao'],
                    $placement['posicao_x'],
                    $placement['posicao_y'],
                    $placement['posicao_z'],
                    $placement['comprimento_m'],
                    $placement['largura_m'],
                    $placement['observacoes_restricao'],
                    $placement['id'],
                    $simulacaoVeiculoId,
                ]);
            }

            $this->rebuildSimulationAggregates($simulacaoId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->buscarPorId($simulacaoId);
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM simulacoes WHERE id = ?');
        return $stmt->execute([$id]);
    }

    private function buildVehicleSlots(array $frotaSelecionada, Veiculo $veiculoModel): array {
        $slots = [];
        foreach ($frotaSelecionada as $entry) {
            $veiculoId = (int) ($entry['veiculo_id'] ?? 0);
            $qtd = max(0, (int) ($entry['quantidade'] ?? 0));
            if ($veiculoId <= 0 || $qtd <= 0) {
                continue;
            }

            $veiculo = $veiculoModel->buscarPorId($veiculoId);
            if (!$veiculo) {
                continue;
            }

            $limit = min($qtd, max(1, (int) ($veiculo['quantidade_disponivel'] ?? $qtd)));
            for ($i = 1; $i <= $limit; $i++) {
                $slot = $veiculo;
                $slot['slot_codigo'] = sprintf('%s #%d', $veiculo['nome'], $i);
                $slot['slot_key'] = sprintf('%d:%d', (int) $veiculo['id'], $i);
                $slot['slot_ordinal'] = $i;
                $slot['slot_index'] = count($slots) + 1;
                $slots[] = $slot;
            }
        }

        usort($slots, static function ($a, $b) {
            $scoreA = ((float) $a['capacidade_kg'] * 0.6) + ((float) $a['capacidade_m3'] * 30);
            $scoreB = ((float) $b['capacidade_kg'] * 0.6) + ((float) $b['capacidade_m3'] * 30);
            return $scoreB <=> $scoreA;
        });

        return $slots;
    }

    private function expandPedidoItens(array $itens): array {
        $orders = array_values(array_unique(array_map(static fn($item) => (int) $item['ordem_entrega'], $itens)));
        sort($orders);
        $orderMap = [];
        foreach ($orders as $index => $value) {
            $orderMap[$value] = $index + 1;
        }

        $units = [];
        foreach ($itens as $item) {
            $quantidade = max(1, (int) $item['quantidade']);
            $groupKey = implode(':', [(int) $item['id'], $item['material_id'], $item['base_id'], $item['ordem_entrega']]);
            for ($i = 1; $i <= $quantidade; $i++) {
                $units[] = [
                    'unit_key' => $groupKey . ':' . $i,
                    'pedido_item_id' => (int) $item['id'],
                    'material_id' => (int) $item['material_id'],
                    'base_id' => (int) $item['base_id'],
                    'codigo_material' => $item['material_codigo'],
                    'descricao_material' => $item['material_descricao'],
                    'categoria' => $item['categoria'],
                    'formato_fisico' => $item['formato_fisico'],
                    'peso_unitario_kg' => (float) $item['peso_unitario_kg'],
                    'volume_unitario_m3' => (float) $item['volume_unitario_m3'],
                    'comprimento_m' => (float) $item['comprimento_m'],
                    'largura_m' => (float) $item['largura_m'],
                    'altura_m' => (float) $item['altura_m'],
                    'empilhavel' => (int) $item['empilhavel'],
                    'max_lastros' => (int) $item['max_lastros'],
                    'perfil_empilhamento' => $item['perfil_empilhamento'],
                    'fragilidade' => $item['fragilidade'],
                    'amarracao_especial' => (int) $item['amarracao_especial'],
                    'base_nome' => $item['base_nome'],
                    'base_codigo' => $item['base_codigo'],
                    'ordem_entrega' => (int) $item['ordem_entrega'],
                    'ordem_rank' => $orderMap[(int) $item['ordem_entrega']] ?? 1,
                    'order_count' => count($orders),
                    'cor_hex' => $this->baseColors[(count($units)) % count($this->baseColors)],
                    'grupo_alocacao' => $groupKey,
                    'grupo_total' => $quantidade,
                    'grupo_ordem_local' => $i,
                    'observacoes_item' => $item['observacoes_item'] ?? '',
                ];
            }
        }

        usort($units, function ($a, $b) {
            $mustBaseA = $this->mustStayOnBase($a) ? 1 : 0;
            $mustBaseB = $this->mustStayOnBase($b) ? 1 : 0;

            $priorityA = [
                (int) $a['ordem_rank'],
                -$mustBaseA,
                (int) $a['empilhavel'],
                -(float) $a['comprimento_m'],
                -(float) $a['peso_unitario_kg'],
                -$this->fragilityScore($a['fragilidade']),
            ];
            $priorityB = [
                (int) $b['ordem_rank'],
                -$mustBaseB,
                (int) $b['empilhavel'],
                -(float) $b['comprimento_m'],
                -(float) $b['peso_unitario_kg'],
                -$this->fragilityScore($b['fragilidade']),
            ];

            foreach ($priorityA as $idx => $value) {
                if ($value === $priorityB[$idx]) {
                    continue;
                }
                return $value <=> $priorityB[$idx];
            }
            return 0;
        });

        return $units;
    }

    private function simulateCombination(array $combo, array $units, array $regras): array {
        $vehicles = [];
        foreach ($combo as $slot) {
            $vehicles[] = [
                'veiculo' => $slot,
                'slot_codigo' => $slot['slot_codigo'],
                'placements' => [],
                'peso_total_kg' => 0.0,
                'volume_total_m3' => 0.0,
            ];
        }

        $unallocated = [];

        foreach ($units as $unit) {
            $bestCandidate = null;

            foreach ($vehicles as $vehicleIndex => $vehicleState) {
                $candidate = $this->findBestPlacementForVehicle($vehicleState, $unit);
                if ($candidate === null) {
                    continue;
                }

                $candidate['vehicle_index'] = $vehicleIndex;
                $candidate['score'] += empty($vehicleState['placements']) ? 45 : 0;

                if ($bestCandidate === null || $candidate['score'] < $bestCandidate['score']) {
                    $bestCandidate = $candidate;
                }
            }

            if ($bestCandidate === null) {
                $unit['status_alocacao'] = 'nao_alocado';
                $unit['observacoes_restricao'] = 'Nenhum veículo candidato comportou o item respeitando peso, volume e regras básicas.';
                $unallocated[] = $unit;
                continue;
            }

            $vIndex = $bestCandidate['vehicle_index'];
            $placement = $bestCandidate['placement'];
            $vehicles[$vIndex]['placements'][] = $placement;
            $vehicles[$vIndex]['peso_total_kg'] += $placement['peso_unitario_kg'];
            $vehicles[$vIndex]['volume_total_m3'] += $placement['volume_unitario_m3'];
        }

        $alerts = [];
        $rulesApplied = [];
        $totalBlockingViolations = 0;
        $remanejoPenalty = 0;
        $balancePenalty = 0;
        $wastePenalty = 0;
        $usedVehicles = 0;
        $pesoTotal = 0.0;
        $volumeTotal = 0.0;

        foreach ($vehicles as $index => &$vehicleState) {
            if (empty($vehicleState['placements'])) {
                continue;
            }

            $usedVehicles++;
            $metrics = $this->calculateVehicleMetrics($vehicleState);
            $vehicleState = array_merge($vehicleState, $metrics);
            $pesoTotal += $vehicleState['peso_total_kg'];
            $volumeTotal += $vehicleState['volume_total_m3'];

            $validation = $this->validateVehicleRules($vehicleState, $regras);
            $vehicleState['alertas'] = $validation['alertas'];
            $vehicleState['regras_aplicadas'] = $validation['regras'];
            $vehicleState['remanejo_penalty'] = $validation['remanejo_penalty'];
            $vehicleState['blocking_violations'] = $validation['blocking_violations'];

            $alerts = array_merge($alerts, $validation['alertas']);
            $rulesApplied = array_merge($rulesApplied, $validation['regras']);
            $totalBlockingViolations += $validation['blocking_violations'];
            $remanejoPenalty += $validation['remanejo_penalty'];
            $balancePenalty += $validation['balance_penalty'];
            $wastePenalty += $validation['waste_penalty'];
        }
        unset($vehicleState);

        foreach ($unallocated as $item) {
            $alerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'item_nao_alocado',
                'severidade' => 'danger',
                'mensagem' => "{$item['codigo_material']} para {$item['base_nome']} não foi alocado em nenhum veículo.",
            ];
        }

        $score = ($totalBlockingViolations * 100000)
            + (count($unallocated) * 25000)
            + ($remanejoPenalty * 250)
            + ($usedVehicles * 400)
            + $balancePenalty
            + $wastePenalty;

        $status = 'aprovado';
        if ($totalBlockingViolations > 0 || count($unallocated) > 0) {
            $status = 'reprovado';
        } else {
            foreach ($alerts as $alert) {
                if (in_array($alert['severidade'], ['warning', 'danger'], true)) {
                    $status = 'aprovado_com_alerta';
                    break;
                }
            }
        }

        return [
            'score_total' => round($score, 2),
            'status' => $status,
            'veiculos' => array_values(array_filter($vehicles, static fn($vehicle) => !empty($vehicle['placements']))),
            'itens_nao_alocados' => $unallocated,
            'alertas' => $alerts,
            'regras_aplicadas' => $rulesApplied,
            'qtd_itens_total' => count($units),
            'qtd_itens_alocados' => count($units) - count($unallocated),
            'qtd_itens_nao_alocados' => count($unallocated),
            'peso_total_kg' => round($pesoTotal, 2),
            'volume_total_m3' => round($volumeTotal, 4),
            'total_veiculos' => $usedVehicles,
        ];
    }

    private function findBestPlacementForVehicle(array $vehicleState, array $item): ?array {
        $veiculo = $vehicleState['veiculo'];
        $remainingKg = (float) $veiculo['capacidade_kg'] - $vehicleState['peso_total_kg'];
        $remainingM3 = (float) $veiculo['capacidade_m3'] - $vehicleState['volume_total_m3'];

        if ($remainingKg + 0.001 < $item['peso_unitario_kg'] || $remainingM3 + 0.0001 < $item['volume_unitario_m3']) {
            return null;
        }

        $best = $this->findBestGroundPosition($vehicleState, $item);

        if ($item['empilhavel'] && (int) $item['max_lastros'] >= 2 && (int) $veiculo['max_lastros'] >= 2) {
            $stack = $this->findBestStackPosition($vehicleState, $item);
            if ($stack !== null && ($best === null || $stack['score'] < $best['score'])) {
                $best = $stack;
            }
        }

        return $best;
    }

    private function findBestGroundPosition(array $vehicleState, array $item): ?array {
        $veiculo = $vehicleState['veiculo'];
        $step = 0.1;
        $maxX = max(0.0, (float) $veiculo['comprimento_m'] - $item['comprimento_m']);
        $maxY = max(0.0, (float) $veiculo['largura_m'] - $item['largura_m']);
        $xs = $this->buildAxisSequence($maxX, $veiculo['acesso_descarga'] === 'traseira' || $veiculo['acesso_descarga'] === 'misto');
        $ys = $this->buildAxisSequence($maxY, false);

        $best = null;
        foreach ($xs as $x) {
            foreach ($ys as $y) {
                $candidate = [
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                    'z' => 0.0,
                    'lastro_posicao' => 1,
                ];

                if ($this->collides($vehicleState['placements'], $candidate, $item)) {
                    continue;
                }

                $score = $this->scorePosition($vehicleState, $item, $candidate);
                $placement = $this->buildPlacementPayload($item, $candidate, 'Lastro 1');
                if ($best === null || $score < $best['score']) {
                    $best = ['score' => $score, 'placement' => $placement];
                }
            }
        }

        return $best;
    }

    private function findBestStackPosition(array $vehicleState, array $item): ?array {
        $best = null;
        foreach ($vehicleState['placements'] as $support) {
            if ((int) $support['lastro_posicao'] !== 1) {
                continue;
            }

            if (!$this->canSupport($support, $item)) {
                continue;
            }

            $candidate = [
                'x' => $support['posicao_x'],
                'y' => $support['posicao_y'],
                'z' => round($support['altura_m'], 2),
                'lastro_posicao' => 2,
            ];

            if ($this->collides($vehicleState['placements'], $candidate, $item, 2)) {
                continue;
            }

            $score = $this->scorePosition($vehicleState, $item, $candidate) + 15;
            if ((int) $support['ordem_entrega'] < (int) $item['ordem_entrega']) {
                $score += 80;
            }

            $placement = $this->buildPlacementPayload($item, $candidate, 'Lastro 2 sobre apoio do lastro 1');
            if ($best === null || $score < $best['score']) {
                $best = ['score' => $score, 'placement' => $placement];
            }
        }

        if ($item['perfil_empilhamento'] === 'piramidal') {
            $pairSupport = $this->findPyramidalPairSupport($vehicleState['placements'], $item);
            if ($pairSupport !== null) {
                $candidate = [
                    'x' => $pairSupport['x'],
                    'y' => $pairSupport['y'],
                    'z' => round($pairSupport['z'], 2),
                    'lastro_posicao' => 2,
                ];

                if (!$this->collides($vehicleState['placements'], $candidate, $item, 2)) {
                    $score = $this->scorePosition($vehicleState, $item, $candidate) - 12;
                    $placement = $this->buildPlacementPayload($item, $candidate, 'Topo de pirâmide de bobinas');
                    if ($best === null || $score < $best['score']) {
                        $best = ['score' => $score, 'placement' => $placement];
                    }
                }
            }
        }

        return $best;
    }

    private function scorePosition(array $vehicleState, array $item, array $candidate): float {
        $veiculo = $vehicleState['veiculo'];
        $orderCount = max(1, (int) $item['order_count']);
        $rank = max(1, (int) $item['ordem_rank']);
        $target = 1 - (($rank - 0.5) / $orderCount);

        $centerX = ($candidate['x'] + ($item['comprimento_m'] / 2)) / max(0.1, (float) $veiculo['comprimento_m']);
        $centerY = ($candidate['y'] + ($item['largura_m'] / 2)) / max(0.1, (float) $veiculo['largura_m']);
        $rearCloseness = $centerX;
        $sideCloseness = 1 - $centerY;

        if ($veiculo['acesso_descarga'] === 'lateral') {
            $accessPenalty = abs($sideCloseness - $target) * 100;
        } elseif ($veiculo['acesso_descarga'] === 'misto') {
            $accessPenalty = abs((($rearCloseness + $sideCloseness) / 2) - $target) * 110;
        } else {
            $accessPenalty = abs($rearCloseness - $target) * 100;
        }

        $sameBaseBonus = 0;
        foreach ($vehicleState['placements'] as $placement) {
            if ((int) $placement['base_id'] === (int) $item['base_id']) {
                $sameBaseBonus -= 2;
            }
        }

        $fragilityPenalty = $this->fragilityScore($item['fragilidade']) * ($candidate['lastro_posicao'] === 2 ? 6 : 2);
        $weightBias = $this->mustStayOnBase($item) && $candidate['lastro_posicao'] === 2 ? 120 : 0;

        return round($accessPenalty + $fragilityPenalty + $weightBias + $sameBaseBonus, 4);
    }

    private function calculateVehicleMetrics(array $vehicleState): array {
        $veiculo = $vehicleState['veiculo'];
        $placements = $vehicleState['placements'];
        $peso = $vehicleState['peso_total_kg'];
        $volume = $vehicleState['volume_total_m3'];

        $momentX = 0.0;
        $momentY = 0.0;
        $usedLastro = 1;

        foreach ($placements as $placement) {
            $centerX = $placement['posicao_x'] + ($placement['comprimento_m'] / 2);
            $centerY = $placement['posicao_y'] + ($placement['largura_m'] / 2);
            $momentX += $centerX * $placement['peso_unitario_kg'];
            $momentY += $centerY * $placement['peso_unitario_kg'];
            $usedLastro = max($usedLastro, (int) $placement['lastro_posicao']);
        }

        $cgX = $peso > 0 ? round((($momentX / $peso) / max(0.1, (float) $veiculo['comprimento_m'])) * 100, 2) : 50.0;
        $cgY = $peso > 0 ? round((($momentY / $peso) / max(0.1, (float) $veiculo['largura_m'])) * 100, 2) : 50.0;

        $ordens = [];
        $bases = [];
        foreach ($placements as $placement) {
            $ordens[] = $placement['codigo_material'] . ' -> ' . $placement['base_nome'];
            $bases[$placement['ordem_entrega']] = $placement['base_nome'];
        }

        ksort($bases);

        return [
            'ocupacao_peso_pct' => round(($peso / max(0.1, (float) $veiculo['capacidade_kg'])) * 100, 2),
            'ocupacao_volume_pct' => round(($volume / max(0.1, (float) $veiculo['capacidade_m3'])) * 100, 2),
            'centro_gravidade_x' => $cgX,
            'centro_gravidade_y' => $cgY,
            'lastros_utilizados' => $usedLastro,
            'ordem_carga' => implode(' | ', $ordens),
            'ordem_descarga' => implode(' -> ', array_values($bases)),
        ];
    }

    private function validateVehicleRules(array $vehicleState, array $regras): array {
        $alerts = [];
        $rulesApplied = [];
        $blockingViolations = 0;
        $remanejoPenalty = 0;
        $balancePenalty = 0;
        $wastePenalty = ((float) $vehicleState['veiculo']['capacidade_m3'] - $vehicleState['volume_total_m3']) * 5;

        if ($vehicleState['centro_gravidade_x'] < 30 || $vehicleState['centro_gravidade_x'] > 70) {
            $alerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'centro_gravidade',
                'severidade' => 'warning',
                'mensagem' => "{$vehicleState['slot_codigo']}: centro de gravidade longitudinal em {$vehicleState['centro_gravidade_x']}%.",
            ];
            $balancePenalty += 120;
        }

        if ($vehicleState['centro_gravidade_y'] < 20 || $vehicleState['centro_gravidade_y'] > 80) {
            $alerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'centro_gravidade_lateral',
                'severidade' => 'warning',
                'mensagem' => "{$vehicleState['slot_codigo']}: distribuição lateral em {$vehicleState['centro_gravidade_y']}%.",
            ];
            $balancePenalty += 90;
        }

        foreach ($vehicleState['placements'] as $placement) {
            if ($this->mustStayOnBase($placement) && (int) $placement['lastro_posicao'] !== 1) {
                $alerts[] = [
                    'simulacao_veiculo_id' => null,
                    'tipo_alerta' => 'regra_bloqueante',
                    'severidade' => 'danger',
                    'mensagem' => "{$placement['codigo_material']} deve permanecer no lastro 1.",
                ];
                $rulesApplied[] = [
                    'regra_id' => null,
                    'descricao_regra' => "{$placement['codigo_material']} no lastro 1",
                    'status' => 'violada',
                ];
                $blockingViolations++;
            }
        }

        foreach ($regras as $regra) {
            $matches = $this->findRuleMatches($vehicleState['placements'], $regra);
            foreach ($matches as $match) {
                $violated = $this->isRuleViolated($match, $regra, $vehicleState['placements']);
                $rulesApplied[] = [
                    'regra_id' => $regra['id'],
                    'descricao_regra' => $regra['tipo_regra'] . ': ' . ($regra['justificativa'] ?: 'Regra operacional'),
                    'status' => $violated ? 'violada' : 'cumprida',
                ];

                if ($violated) {
                    $isBlocking = $regra['severidade'] === 'bloqueante';
                    $alerts[] = [
                        'simulacao_veiculo_id' => null,
                        'tipo_alerta' => 'regra_operacional',
                        'severidade' => $isBlocking ? 'danger' : 'warning',
                        'mensagem' => "{$vehicleState['slot_codigo']}: {$regra['justificativa']}",
                    ];
                    if ($isBlocking) {
                        $blockingViolations++;
                    }
                }
            }
        }

        $remanejoPenalty = $this->calculateRemanejoPenalty($vehicleState['placements'], $vehicleState['veiculo']['acesso_descarga']);
        if ($remanejoPenalty > 0) {
            $alerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'remanejo',
                'severidade' => 'warning',
                'mensagem' => "{$vehicleState['slot_codigo']}: há indício de remanejo entre bases na descarga.",
            ];
        }

        return [
            'alertas' => $alerts,
            'regras' => $rulesApplied,
            'blocking_violations' => $blockingViolations,
            'remanejo_penalty' => $remanejoPenalty,
            'balance_penalty' => $balancePenalty,
            'waste_penalty' => round($wastePenalty, 2),
        ];
    }

    private function persistSimulation(array $pedido, array $result, string $observacoes): int {
        $codigo = 'SIM-' . date('Ymd-His') . '-' . random_int(100, 999);
        $usuario = get_logged_user();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO simulacoes
                    (codigo_simulacao, pedido_id, usuario_id, score_total, status, total_veiculos, qtd_itens_total, qtd_itens_alocados, qtd_itens_nao_alocados, peso_total_kg, volume_total_m3, observacoes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $codigo,
                $pedido['id'],
                $usuario['id'],
                $result['score_total'],
                $result['status'],
                $result['total_veiculos'],
                $result['qtd_itens_total'],
                $result['qtd_itens_alocados'],
                $result['qtd_itens_nao_alocados'],
                $result['peso_total_kg'],
                $result['volume_total_m3'],
                $observacoes,
            ]);
            $simulacaoId = (int) $this->pdo->lastInsertId();

            $stmtVeiculo = $this->pdo->prepare(
                'INSERT INTO simulacao_veiculos
                    (simulacao_id, veiculo_id, slot_codigo, veiculo_nome, tipo_veiculo, acesso_descarga, comprimento_m, largura_m, altura_m,
                     capacidade_kg, capacidade_m3, peso_total_kg, volume_total_m3, ocupacao_peso_pct, ocupacao_volume_pct, centro_gravidade_x,
                     centro_gravidade_y, lastros_utilizados, ordem_carga, ordem_descarga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmtPos = $this->pdo->prepare(
                'INSERT INTO simulacao_posicoes
                    (simulacao_id, simulacao_veiculo_id, pedido_item_id, material_id, base_id, codigo_material, descricao_material, base_nome, ordem_entrega,
                     lastro_posicao, posicao_x, posicao_y, posicao_z, comprimento_m, largura_m, altura_m, peso_unitario_kg, volume_unitario_m3,
                     status_alocacao, cor_hex, observacoes_restricao)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmtAlerta = $this->pdo->prepare(
                'INSERT INTO simulacao_alertas (simulacao_id, simulacao_veiculo_id, tipo_alerta, severidade, mensagem) VALUES (?, ?, ?, ?, ?)'
            );
            $stmtRegra = $this->pdo->prepare(
                'INSERT INTO simulacao_regras_aplicadas (simulacao_id, simulacao_veiculo_id, regra_id, descricao_regra, status) VALUES (?, ?, ?, ?, ?)'
            );

            foreach ($result['veiculos'] as $vehicle) {
                $v = $vehicle['veiculo'];
                $stmtVeiculo->execute([
                    $simulacaoId,
                    $v['id'],
                    $vehicle['slot_codigo'],
                    $v['nome'],
                    $v['tipo'],
                    $v['acesso_descarga'],
                    $v['comprimento_m'],
                    $v['largura_m'],
                    $v['altura_m'],
                    $v['capacidade_kg'],
                    $v['capacidade_m3'],
                    $vehicle['peso_total_kg'],
                    $vehicle['volume_total_m3'],
                    $vehicle['ocupacao_peso_pct'],
                    $vehicle['ocupacao_volume_pct'],
                    $vehicle['centro_gravidade_x'],
                    $vehicle['centro_gravidade_y'],
                    $vehicle['lastros_utilizados'],
                    $vehicle['ordem_carga'],
                    $vehicle['ordem_descarga'],
                ]);
                $simulacaoVeiculoId = (int) $this->pdo->lastInsertId();

                foreach ($vehicle['placements'] as $placement) {
                    $stmtPos->execute([
                        $simulacaoId,
                        $simulacaoVeiculoId,
                        $placement['pedido_item_id'],
                        $placement['material_id'],
                        $placement['base_id'],
                        $placement['codigo_material'],
                        $placement['descricao_material'],
                        $placement['base_nome'],
                        $placement['ordem_entrega'],
                        $placement['lastro_posicao'],
                        $placement['posicao_x'],
                        $placement['posicao_y'],
                        $placement['posicao_z'],
                        $placement['comprimento_m'],
                        $placement['largura_m'],
                        $placement['altura_m'],
                        $placement['peso_unitario_kg'],
                        $placement['volume_unitario_m3'],
                        'alocado',
                        $placement['cor_hex'],
                        $placement['observacoes_restricao'],
                    ]);
                }

                foreach ($vehicle['alertas'] as $alerta) {
                    $stmtAlerta->execute([$simulacaoId, $simulacaoVeiculoId, $alerta['tipo_alerta'], $alerta['severidade'], $alerta['mensagem']]);
                }

                foreach ($vehicle['regras_aplicadas'] as $regra) {
                    $stmtRegra->execute([$simulacaoId, $simulacaoVeiculoId, $regra['regra_id'], $regra['descricao_regra'], $regra['status']]);
                }
            }

            foreach ($result['itens_nao_alocados'] as $item) {
                $stmtPos->execute([
                    $simulacaoId,
                    null,
                    $item['pedido_item_id'],
                    $item['material_id'],
                    $item['base_id'],
                    $item['codigo_material'],
                    $item['descricao_material'],
                    $item['base_nome'],
                    $item['ordem_entrega'],
                    0,
                    0,
                    0,
                    0,
                    $item['comprimento_m'],
                    $item['largura_m'],
                    $item['altura_m'],
                    $item['peso_unitario_kg'],
                    $item['volume_unitario_m3'],
                    'nao_alocado',
                    $item['cor_hex'],
                    $item['observacoes_restricao'],
                ]);
            }

            foreach ($result['alertas'] as $alerta) {
                if (!isset($alerta['mensagem']) || $alerta['tipo_alerta'] !== 'item_nao_alocado') {
                    continue;
                }
                $stmtAlerta->execute([$simulacaoId, null, $alerta['tipo_alerta'], $alerta['severidade'], $alerta['mensagem']]);
            }

            $this->pdo->commit();
            return $simulacaoId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function generateCombinations(array $items, int $size): array {
        $results = [];
        $this->combinationWalk($items, $size, 0, [], $results);
        return $results;
    }

    private function combinationWalk(array $items, int $size, int $offset, array $current, array &$results): void {
        if (count($current) === $size) {
            $results[] = $current;
            return;
        }

        $remaining = $size - count($current);
        for ($i = $offset; $i <= count($items) - $remaining; $i++) {
            $next = $current;
            $next[] = $items[$i];
            $this->combinationWalk($items, $size, $i + 1, $next, $results);
        }
    }

    private function mustStayOnBase(array $item): bool {
        return in_array($item['categoria'], ['transformador', 'poste'], true) || (int) ($item['empilhavel'] ?? 1) === 0;
    }

    private function fragilityScore(string $fragilidade): int {
        return match ($fragilidade) {
            'alta' => 3,
            'media' => 2,
            default => 1,
        };
    }

    private function buildPlacementPayload(array $item, array $candidate, string $obs): array {
        return [
            'unit_key' => $item['unit_key'] ?? null,
            'pedido_item_id' => $item['pedido_item_id'],
            'material_id' => $item['material_id'],
            'base_id' => $item['base_id'],
            'codigo_material' => $item['codigo_material'],
            'descricao_material' => $item['descricao_material'],
            'categoria' => $item['categoria'],
            'base_nome' => $item['base_nome'],
            'ordem_entrega' => $item['ordem_entrega'],
            'lastro_posicao' => $candidate['lastro_posicao'],
            'posicao_x' => $candidate['x'],
            'posicao_y' => $candidate['y'],
            'posicao_z' => $candidate['z'],
            'comprimento_m' => $item['comprimento_m'],
            'largura_m' => $item['largura_m'],
            'altura_m' => $item['altura_m'],
            'peso_unitario_kg' => $item['peso_unitario_kg'],
            'volume_unitario_m3' => $item['volume_unitario_m3'],
            'fragilidade' => $item['fragilidade'],
            'empilhavel' => $item['empilhavel'],
            'cor_hex' => $item['cor_hex'],
            'observacoes_restricao' => trim($obs . '. ' . ($item['observacoes_item'] ?? '')),
        ];
    }

    private function buildManualPlacementPayload(array $unit, array $placementInput): array {
        $length = isset($placementInput['comprimento_m']) ? round((float) $placementInput['comprimento_m'], 2) : (float) $unit['comprimento_m'];
        $width = isset($placementInput['largura_m']) ? round((float) $placementInput['largura_m'], 2) : (float) $unit['largura_m'];
        $orientation = (string) ($placementInput['orientacao_manual'] ?? 'base_0');

        if ($this->isBobinaPlacement($unit)) {
            $length = (float) $unit['comprimento_m'];
            $width = (float) $unit['largura_m'];
        }

        return [
            'id' => 0,
            'unit_key' => $unit['unit_key'],
            'pedido_item_id' => $unit['pedido_item_id'],
            'material_id' => $unit['material_id'],
            'base_id' => $unit['base_id'],
            'codigo_material' => $unit['codigo_material'],
            'descricao_material' => $unit['descricao_material'],
            'categoria' => $unit['categoria'],
            'formato_fisico' => $unit['formato_fisico'],
            'base_nome' => $unit['base_nome'],
            'ordem_entrega' => $unit['ordem_entrega'],
            'lastro_posicao' => max(1, min(2, (int) ($placementInput['lastro_posicao'] ?? 1))),
            'posicao_x' => round((float) ($placementInput['posicao_x'] ?? 0), 2),
            'posicao_y' => round((float) ($placementInput['posicao_y'] ?? 0), 2),
            'posicao_z' => 0.0,
            'comprimento_m' => $length,
            'largura_m' => $width,
            'altura_m' => (float) $unit['altura_m'],
            'peso_unitario_kg' => (float) $unit['peso_unitario_kg'],
            'volume_unitario_m3' => (float) $unit['volume_unitario_m3'],
            'fragilidade' => $unit['fragilidade'],
            'empilhavel' => $unit['empilhavel'],
            'max_lastros' => $unit['max_lastros'],
            'perfil_empilhamento' => $unit['perfil_empilhamento'],
            'amarracao_especial' => $unit['amarracao_especial'],
            'cor_hex' => $unit['cor_hex'],
            'orientacao_manual' => $orientation,
            'observacoes_restricao' => $this->appendManualTag(
                trim((string) ($placementInput['grupo_label'] ?? '')) !== ''
                    ? 'Bloco manual: ' . trim((string) $placementInput['grupo_label'])
                    : 'Posicionado manualmente',
                $this->isBobinaPlacement($unit) ? 'Bobina mantida em pe.' : 'Montagem manual.'
            ),
        ];
    }

    private function validateDraftVehicleState(array $vehicleState, array $regras): array {
        $veiculo = $vehicleState['veiculo'];

        foreach ($vehicleState['placements'] as &$placement) {
            if ($placement['posicao_x'] < 0 || $placement['posicao_y'] < 0) {
                throw new Exception("Posições negativas não são permitidas para {$placement['codigo_material']}.");
            }

            if ($placement['posicao_x'] + $placement['comprimento_m'] > (float) $veiculo['comprimento_m'] + 0.001) {
                throw new Exception("{$placement['codigo_material']} ultrapassa o comprimento útil de {$vehicleState['slot_codigo']}.");
            }

            if ($placement['posicao_y'] + $placement['largura_m'] > (float) $veiculo['largura_m'] + 0.001) {
                throw new Exception("{$placement['codigo_material']} ultrapassa a largura útil de {$vehicleState['slot_codigo']}.");
            }

            if ($this->isBobinaPlacement($placement) && $this->wasManualSideLayRequested($placement)) {
                throw new Exception("{$placement['codigo_material']} não pode ficar deitado de lado. Bobinas devem permanecer em pé.");
            }

            if ((int) $placement['lastro_posicao'] === 2) {
                if ($this->mustStayOnBase($placement)) {
                    throw new Exception("{$placement['codigo_material']} deve permanecer no lastro 1.");
                }

                if ((int) ($placement['empilhavel'] ?? 0) !== 1 || (int) ($placement['max_lastros'] ?? 1) < 2) {
                    throw new Exception("{$placement['codigo_material']} não pode ser posicionado no lastro 2.");
                }
            }
        }
        unset($placement);

        foreach ($vehicleState['placements'] as &$placement) {
            if ((int) $placement['lastro_posicao'] === 1) {
                $placement['posicao_z'] = 0.0;
                continue;
            }

            $support = $this->findManualSupport($vehicleState['placements'], $placement);
            if ($support === null) {
                throw new Exception("{$placement['codigo_material']} no lastro 2 precisa de apoio total no lastro 1.");
            }

            $placement['posicao_z'] = round((float) $support['altura_m'], 2);
        }
        unset($placement);

        foreach ($vehicleState['placements'] as $index => $placement) {
            for ($i = $index + 1; $i < count($vehicleState['placements']); $i++) {
                $other = $vehicleState['placements'][$i];
                if ((int) $placement['lastro_posicao'] !== (int) $other['lastro_posicao']) {
                    continue;
                }

                if ($this->overlaps2d(
                    (float) $placement['posicao_x'],
                    (float) $placement['posicao_y'],
                    (float) $placement['comprimento_m'],
                    (float) $placement['largura_m'],
                    (float) $other['posicao_x'],
                    (float) $other['posicao_y'],
                    (float) $other['comprimento_m'],
                    (float) $other['largura_m']
                )) {
                    throw new Exception("Colisão detectada entre {$placement['codigo_material']} e {$other['codigo_material']} em {$vehicleState['slot_codigo']}.");
                }
            }
        }

        if ($vehicleState['peso_total_kg'] > (float) $veiculo['capacidade_kg'] + 0.001) {
            throw new Exception("{$vehicleState['slot_codigo']} excedeu a capacidade de peso.");
        }

        if ($vehicleState['volume_total_m3'] > (float) $veiculo['capacidade_m3'] + 0.001) {
            throw new Exception("{$vehicleState['slot_codigo']} excedeu a capacidade de cubagem.");
        }

        $metrics = $this->calculateVehicleMetrics($vehicleState);
        $state = array_merge($vehicleState, $metrics);
        $validation = $this->validateVehicleRules($state, $regras);
        $state['alertas'] = $validation['alertas'];
        $state['regras_aplicadas'] = $validation['regras'];
        $state['blocking_violations'] = $validation['blocking_violations'];
        $state['remanejo_penalty'] = $validation['remanejo_penalty'];
        $state['balance_penalty'] = $validation['balance_penalty'];
        $state['waste_penalty'] = $validation['waste_penalty'];
        return $state;
    }

    private function validateManualVehicleState(array $vehicleRecord, array $placements): array {
        $vehicleState = [
            'id' => (int) $vehicleRecord['id'],
            'veiculo' => [
                'id' => (int) $vehicleRecord['veiculo_id'],
                'nome' => $vehicleRecord['veiculo_nome'],
                'tipo' => $vehicleRecord['tipo_veiculo'],
                'acesso_descarga' => $vehicleRecord['acesso_descarga'],
                'comprimento_m' => (float) $vehicleRecord['comprimento_m'],
                'largura_m' => (float) $vehicleRecord['largura_m'],
                'altura_m' => (float) $vehicleRecord['altura_m'],
                'capacidade_kg' => (float) $vehicleRecord['capacidade_kg'],
                'capacidade_m3' => (float) $vehicleRecord['capacidade_m3'],
                'max_lastros' => max(1, (int) $vehicleRecord['lastros_utilizados']),
            ],
            'slot_codigo' => $vehicleRecord['slot_codigo'],
            'placements' => [],
            'peso_total_kg' => 0.0,
            'volume_total_m3' => 0.0,
        ];

        foreach ($placements as &$placement) {
            if ($placement['posicao_x'] < 0 || $placement['posicao_y'] < 0) {
                throw new Exception("Posições negativas não são permitidas para {$placement['codigo_material']}.");
            }

            if ($placement['posicao_x'] + $placement['comprimento_m'] > (float) $vehicleRecord['comprimento_m'] + 0.001) {
                throw new Exception("{$placement['codigo_material']} ultrapassa o comprimento útil do veículo.");
            }

            if ($placement['posicao_y'] + $placement['largura_m'] > (float) $vehicleRecord['largura_m'] + 0.001) {
                throw new Exception("{$placement['codigo_material']} ultrapassa a largura útil do veículo.");
            }

            if ($this->isBobinaPlacement($placement) && $this->wasManualSideLayRequested($placement)) {
                throw new Exception("{$placement['codigo_material']} não pode ficar deitado de lado. Bobinas devem permanecer em pé.");
            }

            if ((int) $placement['lastro_posicao'] === 2) {
                if ($this->mustStayOnBase($placement)) {
                    throw new Exception("{$placement['codigo_material']} deve permanecer no lastro 1.");
                }

                if ((int) ($placement['empilhavel'] ?? 0) !== 1) {
                    throw new Exception("{$placement['codigo_material']} não pode ser posicionado no lastro 2.");
                }
            }
        }
        unset($placement);

        foreach ($placements as &$placement) {
            if ((int) $placement['lastro_posicao'] === 1) {
                $placement['posicao_z'] = 0.0;
                continue;
            }

            $support = $this->findManualSupport($placements, $placement);
            if ($support === null) {
                throw new Exception("{$placement['codigo_material']} no lastro 2 precisa de apoio total no lastro 1.");
            }

            $placement['posicao_z'] = round((float) $support['altura_m'], 2);
            $placement['observacoes_restricao'] = $this->appendManualTag(
                (string) $placement['observacoes_restricao'],
                "Apoiado manualmente sobre {$support['codigo_material']}."
            );
        }
        unset($placement);

        foreach ($placements as $index => $placement) {
            for ($i = $index + 1; $i < count($placements); $i++) {
                $other = $placements[$i];
                if ((int) $placement['lastro_posicao'] !== (int) $other['lastro_posicao']) {
                    continue;
                }

                if ($this->overlaps2d(
                    (float) $placement['posicao_x'],
                    (float) $placement['posicao_y'],
                    (float) $placement['comprimento_m'],
                    (float) $placement['largura_m'],
                    (float) $other['posicao_x'],
                    (float) $other['posicao_y'],
                    (float) $other['comprimento_m'],
                    (float) $other['largura_m']
                )) {
                    throw new Exception("Colisão detectada entre {$placement['codigo_material']} e {$other['codigo_material']} no mesmo lastro.");
                }
            }
        }

        foreach ($placements as $placement) {
            $vehicleState['placements'][] = $placement;
            $vehicleState['peso_total_kg'] += (float) $placement['peso_unitario_kg'];
            $vehicleState['volume_total_m3'] += (float) $placement['volume_unitario_m3'];
        }

        $metrics = $this->calculateVehicleMetrics($vehicleState);
        $vehicleState = array_merge($vehicleState, $metrics);
        $validation = $this->validateVehicleRules($vehicleState, (new Regra())->listarAtivas());
        $vehicleState['alertas'] = $validation['alertas'];
        $vehicleState['regras_aplicadas'] = $validation['regras'];

        if ($validation['blocking_violations'] > 0) {
            throw new Exception('A montagem manual viola uma ou mais regras bloqueantes. Ajuste os itens e tente novamente.');
        }

        return $vehicleState;
    }

    private function rebuildSimulationAggregates(int $simulacaoId): void {
        $stmtVehicles = $this->pdo->prepare('SELECT * FROM simulacao_veiculos WHERE simulacao_id = ? ORDER BY id ASC');
        $stmtVehicles->execute([$simulacaoId]);
        $vehicles = $stmtVehicles->fetchAll();
        $regras = (new Regra())->listarAtivas();

        $allAlerts = [];
        $allRules = [];
        $pesoTotal = 0.0;
        $volumeTotal = 0.0;
        $totalBloqueios = 0;
        $stmtCount = $this->pdo->prepare('SELECT COUNT(*) FROM simulacao_posicoes WHERE simulacao_id = ? AND status_alocacao = "nao_alocado"');
        $stmtCount->execute([$simulacaoId]);
        $totalNaoAlocados = (int) $stmtCount->fetchColumn();

        $stmtVehicleItems = $this->pdo->prepare(
            'SELECT p.*, m.categoria, m.formato_fisico, m.empilhavel, m.max_lastros, m.perfil_empilhamento, m.fragilidade, m.amarracao_especial
             FROM simulacao_posicoes p
             JOIN materiais m ON m.id = p.material_id
             WHERE p.simulacao_veiculo_id = ? AND p.status_alocacao = "alocado"
             ORDER BY p.ordem_entrega ASC, p.lastro_posicao ASC, p.id ASC'
        );
        $stmtUpdateVehicle = $this->pdo->prepare(
            'UPDATE simulacao_veiculos
             SET peso_total_kg = ?, volume_total_m3 = ?, ocupacao_peso_pct = ?, ocupacao_volume_pct = ?, centro_gravidade_x = ?, centro_gravidade_y = ?, lastros_utilizados = ?, ordem_carga = ?, ordem_descarga = ?
             WHERE id = ?'
        );

        foreach ($vehicles as $vehicle) {
            $stmtVehicleItems->execute([$vehicle['id']]);
            $placements = $stmtVehicleItems->fetchAll();

            $state = [
                'id' => (int) $vehicle['id'],
                'veiculo' => [
                    'id' => (int) $vehicle['veiculo_id'],
                    'nome' => $vehicle['veiculo_nome'],
                    'tipo' => $vehicle['tipo_veiculo'],
                    'acesso_descarga' => $vehicle['acesso_descarga'],
                    'comprimento_m' => (float) $vehicle['comprimento_m'],
                    'largura_m' => (float) $vehicle['largura_m'],
                    'altura_m' => (float) $vehicle['altura_m'],
                    'capacidade_kg' => (float) $vehicle['capacidade_kg'],
                    'capacidade_m3' => (float) $vehicle['capacidade_m3'],
                ],
                'slot_codigo' => $vehicle['slot_codigo'],
                'placements' => $placements,
                'peso_total_kg' => array_sum(array_map(static fn($p) => (float) $p['peso_unitario_kg'], $placements)),
                'volume_total_m3' => array_sum(array_map(static fn($p) => (float) $p['volume_unitario_m3'], $placements)),
            ];

            $metrics = $this->calculateVehicleMetrics($state);
            $state = array_merge($state, $metrics);
            $validation = $this->validateVehicleRules($state, $regras);

            $stmtUpdateVehicle->execute([
                $state['peso_total_kg'],
                $state['volume_total_m3'],
                $state['ocupacao_peso_pct'],
                $state['ocupacao_volume_pct'],
                $state['centro_gravidade_x'],
                $state['centro_gravidade_y'],
                $state['lastros_utilizados'],
                $state['ordem_carga'],
                $state['ordem_descarga'],
                $vehicle['id'],
            ]);

            $pesoTotal += $state['peso_total_kg'];
            $volumeTotal += $state['volume_total_m3'];
            $totalBloqueios += $validation['blocking_violations'];

            foreach ($validation['alertas'] as $alert) {
                $allAlerts[] = [
                    'simulacao_veiculo_id' => (int) $vehicle['id'],
                    'tipo_alerta' => $alert['tipo_alerta'],
                    'severidade' => $alert['severidade'],
                    'mensagem' => $alert['mensagem'],
                ];
            }

            foreach ($validation['regras'] as $regra) {
                $allRules[] = [
                    'simulacao_veiculo_id' => (int) $vehicle['id'],
                    'regra_id' => $regra['regra_id'],
                    'descricao_regra' => $regra['descricao_regra'],
                    'status' => $regra['status'],
                ];
            }
        }

        $stmtUnallocated = $this->pdo->prepare(
            'SELECT codigo_material, base_nome FROM simulacao_posicoes WHERE simulacao_id = ? AND status_alocacao = "nao_alocado" ORDER BY id ASC'
        );
        $stmtUnallocated->execute([$simulacaoId]);
        foreach ($stmtUnallocated->fetchAll() as $unallocated) {
            $allAlerts[] = [
                'simulacao_veiculo_id' => null,
                'tipo_alerta' => 'item_nao_alocado',
                'severidade' => 'danger',
                'mensagem' => "{$unallocated['codigo_material']} para {$unallocated['base_nome']} continua sem alocação.",
            ];
        }

        $status = 'aprovado';
        if ($totalBloqueios > 0 || $totalNaoAlocados > 0) {
            $status = 'reprovado';
        } else {
            foreach ($allAlerts as $alert) {
                if (in_array($alert['severidade'], ['warning', 'danger'], true)) {
                    $status = 'aprovado_com_alerta';
                    break;
                }
            }
        }

        $stmtDeleteAlerts = $this->pdo->prepare('DELETE FROM simulacao_alertas WHERE simulacao_id = ?');
        $stmtDeleteRules = $this->pdo->prepare('DELETE FROM simulacao_regras_aplicadas WHERE simulacao_id = ?');
        $stmtDeleteAlerts->execute([$simulacaoId]);
        $stmtDeleteRules->execute([$simulacaoId]);

        $stmtInsertAlert = $this->pdo->prepare(
            'INSERT INTO simulacao_alertas (simulacao_id, simulacao_veiculo_id, tipo_alerta, severidade, mensagem) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($allAlerts as $alert) {
            $stmtInsertAlert->execute([$simulacaoId, $alert['simulacao_veiculo_id'], $alert['tipo_alerta'], $alert['severidade'], $alert['mensagem']]);
        }

        $stmtInsertRule = $this->pdo->prepare(
            'INSERT INTO simulacao_regras_aplicadas (simulacao_id, simulacao_veiculo_id, regra_id, descricao_regra, status) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($allRules as $rule) {
            $stmtInsertRule->execute([$simulacaoId, $rule['simulacao_veiculo_id'], $rule['regra_id'], $rule['descricao_regra'], $rule['status']]);
        }

        $stmtUpdateSimulation = $this->pdo->prepare(
            'UPDATE simulacoes
             SET status = ?, peso_total_kg = ?, volume_total_m3 = ?, qtd_itens_alocados = ?, qtd_itens_nao_alocados = ?, total_veiculos = ?, score_total = ?
             WHERE id = ?'
        );
        $scoreTotal = ($totalBloqueios * 100000) + ($totalNaoAlocados * 25000) + (count($vehicles) * 400);
        $stmtUpdateSimulation->execute([
            $status,
            round($pesoTotal, 2),
            round($volumeTotal, 4),
            (int) $this->countAllocatedItems($simulacaoId),
            $totalNaoAlocados,
            count($vehicles),
            round($scoreTotal, 2),
            $simulacaoId,
        ]);
    }

    private function countAllocatedItems(int $simulacaoId): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM simulacao_posicoes WHERE simulacao_id = ? AND status_alocacao = "alocado"');
        $stmt->execute([$simulacaoId]);
        return (int) $stmt->fetchColumn();
    }

    private function findManualSupport(array $placements, array $target): ?array {
        foreach ($placements as $candidate) {
            if ((int) $candidate['lastro_posicao'] !== 1) {
                continue;
            }

            if (!$this->canSupport($candidate, $target)) {
                continue;
            }

            $coversX = (float) $target['posicao_x'] >= (float) $candidate['posicao_x']
                && ((float) $target['posicao_x'] + (float) $target['comprimento_m']) <= ((float) $candidate['posicao_x'] + (float) $candidate['comprimento_m'] + 0.001);
            $coversY = (float) $target['posicao_y'] >= (float) $candidate['posicao_y']
                && ((float) $target['posicao_y'] + (float) $target['largura_m']) <= ((float) $candidate['posicao_y'] + (float) $candidate['largura_m'] + 0.001);

            if ($coversX && $coversY) {
                return $candidate;
            }
        }

        return null;
    }

    private function isBobinaPlacement(array $item): bool {
        $codigo = strtolower((string) ($item['codigo_material'] ?? ''));
        $descricao = strtolower((string) ($item['descricao_material'] ?? ''));
        return str_contains($codigo, 'bob') || str_contains($descricao, 'bobina');
    }

    private function wasManualSideLayRequested(array $placement): bool {
        return isset($placement['orientacao_manual']) && $placement['orientacao_manual'] === 'deitado_lado';
    }

    private function appendManualTag(string $text, string $tag): string {
        $text = trim($text);
        if ($text === '') {
            return $tag;
        }

        if (str_contains($text, $tag)) {
            return $text;
        }

        return rtrim($text, '.') . '. ' . $tag;
    }

    private function collides(array $placements, array $candidate, array $item, ?int $forcedLayer = null): bool {
        $layer = $forcedLayer ?? $candidate['lastro_posicao'];
        foreach ($placements as $placement) {
            if ((int) $placement['lastro_posicao'] !== $layer) {
                continue;
            }

            if ($this->overlaps2d(
                $candidate['x'],
                $candidate['y'],
                $item['comprimento_m'],
                $item['largura_m'],
                $placement['posicao_x'],
                $placement['posicao_y'],
                $placement['comprimento_m'],
                $placement['largura_m']
            )) {
                return true;
            }
        }
        return false;
    }

    private function overlaps2d(float $ax, float $ay, float $aw, float $ah, float $bx, float $by, float $bw, float $bh): bool {
        return $ax < ($bx + $bw)
            && ($ax + $aw) > $bx
            && $ay < ($by + $bh)
            && ($ay + $ah) > $by;
    }

    private function canSupport(array $support, array $item): bool {
        if ((int) $support['empilhavel'] !== 1) {
            return false;
        }

        if ($support['fragilidade'] === 'alta' || in_array($support['categoria'], ['transformador', 'poste'], true)) {
            return false;
        }

        if ((float) $support['comprimento_m'] + 0.05 < (float) $item['comprimento_m']) {
            return false;
        }

        if ((float) $support['largura_m'] + 0.05 < (float) $item['largura_m']) {
            return false;
        }

        return true;
    }

    private function findPyramidalPairSupport(array $placements, array $item): ?array {
        $matches = array_values(array_filter($placements, function ($placement) use ($item) {
            return (int) $placement['lastro_posicao'] === 1
                && $placement['codigo_material'] === $item['codigo_material']
                && (int) $placement['base_id'] === (int) $item['base_id']
                && (int) $placement['ordem_entrega'] === (int) $item['ordem_entrega'];
        }));

        if (count($matches) < 2) {
            return null;
        }

        for ($i = 0; $i < count($matches) - 1; $i++) {
            for ($j = $i + 1; $j < count($matches); $j++) {
                $a = $matches[$i];
                $b = $matches[$j];
                $sameDepth = abs($a['posicao_x'] - $b['posicao_x']) <= 0.25;
                $adjacent = abs(($a['posicao_y'] + $a['largura_m']) - $b['posicao_y']) <= 0.3
                    || abs(($b['posicao_y'] + $b['largura_m']) - $a['posicao_y']) <= 0.3;

                if ($sameDepth && $adjacent) {
                    return [
                        'x' => min($a['posicao_x'], $b['posicao_x']) + (min($a['comprimento_m'], $b['comprimento_m']) * 0.15),
                        'y' => min($a['posicao_y'], $b['posicao_y']) + (abs($a['posicao_y'] - $b['posicao_y']) / 2),
                        'z' => max($a['altura_m'], $b['altura_m']),
                    ];
                }
            }
        }

        return null;
    }

    private function buildAxisSequence(float $max, bool $descending): array {
        $values = [];
        for ($cursor = 0.0; $cursor <= $max + 0.0001; $cursor += 0.1) {
            $values[] = round($cursor, 2);
        }
        return $descending ? array_reverse($values) : $values;
    }

    private function findRuleMatches(array $placements, array $regra): array {
        $matches = [];
        foreach ($placements as $placement) {
            $originMatch = true;
            if (!empty($regra['material_origem_id'])) {
                $originMatch = (int) $regra['material_origem_id'] === (int) $placement['material_id'];
            } elseif (!empty($regra['categoria_origem'])) {
                $originMatch = $regra['categoria_origem'] === $placement['categoria'];
            }

            if (!$originMatch) {
                continue;
            }

            $matches[] = $placement;
        }
        return $matches;
    }

    private function isRuleViolated(array $placement, array $regra, array $placements): bool {
        return match ($regra['tipo_regra']) {
            'obrigatorio_lastro_1' => (int) $placement['lastro_posicao'] !== 1,
            'sem_carga_superior' => $this->hasItemAbove($placement, $placements),
            'nao_sobrepor' => $this->isAboveForbiddenTarget($placement, $regra, $placements),
            'separacao_fisica' => $this->isTooCloseToOther($placement, $placements),
            'preferir_lastro_1' => (int) $placement['lastro_posicao'] !== 1,
            default => false,
        };
    }

    private function hasItemAbove(array $placement, array $placements): bool {
        foreach ($placements as $other) {
            if ((int) $other['lastro_posicao'] <= (int) $placement['lastro_posicao']) {
                continue;
            }

            if ($this->overlaps2d(
                $placement['posicao_x'],
                $placement['posicao_y'],
                $placement['comprimento_m'],
                $placement['largura_m'],
                $other['posicao_x'],
                $other['posicao_y'],
                $other['comprimento_m'],
                $other['largura_m']
            )) {
                return true;
            }
        }
        return false;
    }

    private function isAboveForbiddenTarget(array $placement, array $regra, array $placements): bool {
        if ((int) $placement['lastro_posicao'] !== 2) {
            return false;
        }

        foreach ($placements as $support) {
            if ((int) $support['lastro_posicao'] !== 1) {
                continue;
            }

            $targetMatch = true;
            if (!empty($regra['material_destino_id'])) {
                $targetMatch = (int) $support['material_id'] === (int) $regra['material_destino_id'];
            } elseif (!empty($regra['categoria_destino'])) {
                $targetMatch = $support['categoria'] === $regra['categoria_destino'];
            }

            if (!$targetMatch) {
                continue;
            }

            if ($this->overlaps2d(
                $placement['posicao_x'],
                $placement['posicao_y'],
                $placement['comprimento_m'],
                $placement['largura_m'],
                $support['posicao_x'],
                $support['posicao_y'],
                $support['comprimento_m'],
                $support['largura_m']
            )) {
                return true;
            }
        }

        return false;
    }

    private function isTooCloseToOther(array $placement, array $placements): bool {
        foreach ($placements as $other) {
            if ($other === $placement) {
                continue;
            }

            $distanceX = abs(($placement['posicao_x'] + $placement['comprimento_m'] / 2) - ($other['posicao_x'] + $other['comprimento_m'] / 2));
            $distanceY = abs(($placement['posicao_y'] + $placement['largura_m'] / 2) - ($other['posicao_y'] + $other['largura_m'] / 2));
            if ($distanceX < 0.15 && $distanceY < 0.15) {
                return true;
            }
        }
        return false;
    }

    private function calculateRemanejoPenalty(array $placements, string $acesso): int {
        $penalty = 0;
        for ($i = 0; $i < count($placements); $i++) {
            for ($j = $i + 1; $j < count($placements); $j++) {
                $a = $placements[$i];
                $b = $placements[$j];
                if ((int) $a['ordem_entrega'] === (int) $b['ordem_entrega']) {
                    continue;
                }

                $earlier = (int) $a['ordem_entrega'] < (int) $b['ordem_entrega'] ? $a : $b;
                $later = $earlier === $a ? $b : $a;

                $sameCorridor = $this->overlaps2d(
                    $earlier['posicao_x'],
                    $earlier['posicao_y'],
                    $earlier['comprimento_m'],
                    $earlier['largura_m'],
                    $later['posicao_x'],
                    $later['posicao_y'],
                    $later['comprimento_m'],
                    $later['largura_m']
                ) || abs($earlier['posicao_y'] - $later['posicao_y']) < 0.9;

                if (!$sameCorridor) {
                    continue;
                }

                if ($acesso === 'lateral' && $later['posicao_y'] < $earlier['posicao_y']) {
                    $penalty++;
                } elseif ($acesso === 'misto' && ($later['posicao_x'] > $earlier['posicao_x'] || $later['posicao_y'] < $earlier['posicao_y'])) {
                    $penalty++;
                } elseif ($acesso === 'traseira' && $later['posicao_x'] > $earlier['posicao_x']) {
                    $penalty++;
                }
            }
        }

        return $penalty;
    }
}
