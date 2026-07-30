<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/PedidoCarga.php';
require_once __DIR__ . '/Rota.php';
require_once __DIR__ . '/Simulacao.php';
require_once __DIR__ . '/UnidadeVeiculo.php';

class PlanejamentoRota {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos(?string $dataInicio = null, ?string $dataFim = null): array {
        $sql = 'SELECT pr.*, r.codigo AS rota_codigo, r.descricao AS rota_descricao, s.codigo_simulacao
                FROM planejamento_rotas pr
                JOIN rotas r ON r.id = pr.rota_id
                JOIN simulacoes s ON s.id = pr.simulacao_id
                WHERE 1 = 1';
        $params = [];
        if ($dataInicio) {
            $sql .= ' AND pr.data_operacao >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim) {
            $sql .= ' AND pr.data_operacao <= ?';
            $params[] = $dataFim;
        }
        $sql .= ' ORDER BY pr.data_operacao ASC, pr.codigo_planejamento ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT pr.*, r.codigo AS rota_codigo, r.descricao AS rota_descricao, r.data_planejada, s.codigo_simulacao
             FROM planejamento_rotas pr
             JOIN rotas r ON r.id = pr.rota_id
             JOIN simulacoes s ON s.id = pr.simulacao_id
             WHERE pr.id = ?'
        );
        $stmt->execute([$id]);
        $planejamento = $stmt->fetch();
        if (!$planejamento) {
            return null;
        }

        $stmtPedidos = $this->pdo->prepare(
            'SELECT pp.*, p.codigo_pedido, p.descricao
             FROM planejamento_pedidos pp
             JOIN pedidos_carga p ON p.id = pp.pedido_id
             WHERE pp.planejamento_id = ?
             ORDER BY p.codigo_pedido ASC'
        );
        $stmtPedidos->execute([$id]);
        $planejamento['pedidos'] = $stmtPedidos->fetchAll();

        $stmtCargas = $this->pdo->prepare(
            'SELECT pc.*, uv.codigo_unidade, v.nome AS veiculo_nome, sv.slot_codigo
             FROM planejamento_cargas pc
             LEFT JOIN unidades_veiculo uv ON uv.id = pc.unidade_veiculo_id
             JOIN veiculos v ON v.id = pc.veiculo_id
             JOIN simulacao_veiculos sv ON sv.id = pc.simulacao_veiculo_id
             WHERE pc.planejamento_id = ?
             ORDER BY pc.codigo_carga ASC'
        );
        $stmtCargas->execute([$id]);
        $planejamento['cargas'] = $stmtCargas->fetchAll();

        return $planejamento;
    }

    public function gerar(array $data): array {
        $rotaId = (int) ($data['rota_id'] ?? 0);
        $dataOperacao = sanitize_input($data['data_operacao'] ?? '');
        $pedidoIds = array_map('intval', $data['pedido_ids'] ?? []);
        $unidadeIds = array_map('intval', $data['unidade_ids'] ?? []);
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if ($rotaId <= 0 || $dataOperacao === '') {
            throw new Exception('Informe a rota e a data de operação.');
        }
        if (empty($pedidoIds)) {
            throw new Exception('Selecione ao menos um pedido para o planejamento.');
        }
        if (empty($unidadeIds)) {
            throw new Exception('Selecione ao menos uma unidade de frota.');
        }

        $rotaModel = new Rota();
        $pedidoModel = new PedidoCarga();
        $simulacaoModel = new Simulacao();
        $unidadeModel = new UnidadeVeiculo();

        $rota = $rotaModel->buscarPorId($rotaId);
        if (!$rota) {
            throw new Exception('Rota não encontrada.');
        }

        $baseOrder = [];
        foreach ($rota['bases'] as $base) {
            $baseOrder[(int) $base['base_id']] = (int) $base['sequencia'];
        }
        if (empty($baseOrder)) {
            throw new Exception('A rota selecionada não possui bases configuradas.');
        }

        $selectedUnits = [];
        foreach ($unidadeIds as $unidadeId) {
            if ($unidadeId <= 0) {
                continue;
            }
            $unit = $unidadeModel->buscarPorId($unidadeId);
            if ($unit && (int) $unit['ativo'] === 1 && $unit['status_operacional'] === 'disponivel') {
                $selectedUnits[] = $unit;
            }
        }

        if (count($selectedUnits) !== count(array_filter($unidadeIds))) {
            throw new Exception('Uma ou mais unidades selecionadas não estão disponíveis.');
        }

        $this->validarConflitosUnidades($selectedUnits, $dataOperacao);

        $consolidatedItems = [];
        $origins = [];
        foreach ($pedidoIds as $pedidoId) {
            $pedido = $pedidoModel->buscarPorId($pedidoId);
            if (!$pedido) {
                throw new Exception("Pedido {$pedidoId} não encontrado.");
            }

            foreach ($pedido['itens'] as $item) {
                $baseId = (int) $item['base_id'];
                if (!isset($baseOrder[$baseId])) {
                    throw new Exception("O pedido {$pedido['codigo_pedido']} possui base fora da rota selecionada.");
                }

                $key = implode(':', [$item['material_id'], $baseId, $baseOrder[$baseId]]);
                if (!isset($consolidatedItems[$key])) {
                    $consolidatedItems[$key] = [
                        'material_id' => (int) $item['material_id'],
                        'base_id' => $baseId,
                        'quantidade' => 0,
                        'ordem_entrega' => $baseOrder[$baseId],
                        'observacoes_item' => '',
                    ];
                }

                $consolidatedItems[$key]['quantidade'] += (int) $item['quantidade'];
                $origins[$key][] = $pedido['codigo_pedido'];
            }
        }

        if (empty($consolidatedItems)) {
            throw new Exception('Não há itens elegíveis para consolidar nessa rota.');
        }

        foreach ($consolidatedItems as $key => &$item) {
            $origemPedidos = array_values(array_unique($origins[$key] ?? []));
            $item['observacoes_item'] = 'Pedidos consolidados: ' . implode(', ', $origemPedidos);
        }
        unset($item);

        usort($consolidatedItems, static function ($a, $b) {
            return [$a['ordem_entrega'], $a['material_id'], $a['base_id']] <=> [$b['ordem_entrega'], $b['material_id'], $b['base_id']];
        });

        $pedidoConsolidadoId = $pedidoModel->salvar([
            'codigo_pedido' => 'PLN-' . date('Ymd-His'),
            'descricao' => 'Consolidado de rota ' . $rota['codigo'] . ' em ' . $dataOperacao,
            'status' => 'planejamento_rota',
            'observacoes' => 'Pedido técnico gerado automaticamente para planejamento de rota.',
            'itens' => $consolidatedItems,
        ]);

        $frotaAgrupada = [];
        $unitQueues = [];
        foreach ($selectedUnits as $unit) {
            $veiculoId = (int) $unit['veiculo_id'];
            $frotaAgrupada[$veiculoId] = ($frotaAgrupada[$veiculoId] ?? 0) + 1;
            $unitQueues[$veiculoId][] = $unit;
        }

        $frotaSelecionada = [];
        foreach ($frotaAgrupada as $veiculoId => $quantidade) {
            $frotaSelecionada[] = ['veiculo_id' => $veiculoId, 'quantidade' => $quantidade];
        }

        $simulacao = $simulacaoModel->executar($pedidoConsolidadoId, $frotaSelecionada, 'Planejamento de rota ' . $rota['codigo']);

        $codigoPlanejamento = 'PRT-' . date('Ymd-His') . '-' . random_int(100, 999);

        $this->pdo->beginTransaction();
        try {
            $stmtPlan = $this->pdo->prepare(
                'INSERT INTO planejamento_rotas
                    (codigo_planejamento, rota_id, pedido_consolidado_id, simulacao_id, data_operacao, status, total_cargas, total_peso_kg, total_volume_m3, score_total, observacoes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtPlan->execute([
                $codigoPlanejamento,
                $rotaId,
                $pedidoConsolidadoId,
                $simulacao['id'],
                $dataOperacao,
                'planejado',
                (int) $simulacao['total_veiculos'],
                (float) $simulacao['peso_total_kg'],
                (float) $simulacao['volume_total_m3'],
                (float) $simulacao['score_total'],
                $observacoes,
            ]);
            $planejamentoId = (int) $this->pdo->lastInsertId();

            $stmtPedido = $this->pdo->prepare(
                'INSERT INTO planejamento_pedidos (planejamento_id, pedido_id) VALUES (?, ?)'
            );
            foreach ($pedidoIds as $pedidoId) {
                $stmtPedido->execute([$planejamentoId, $pedidoId]);
            }

            $stmtCarga = $this->pdo->prepare(
                'INSERT INTO planejamento_cargas
                    (planejamento_id, simulacao_veiculo_id, unidade_veiculo_id, veiculo_id, codigo_carga, bases_atendidas, peso_total_kg, volume_total_m3, ocupacao_peso_pct, ocupacao_volume_pct, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $cargaSequencial = 1;
            foreach ($simulacao['veiculos'] as $veiculoSimulado) {
                $veiculoId = (int) $veiculoSimulado['veiculo_id'];
                $assignedUnit = array_shift($unitQueues[$veiculoId]);
                $basesAtendidas = [];
                foreach ($veiculoSimulado['itens'] as $item) {
                    $basesAtendidas[(int) $item['ordem_entrega']] = $item['base_nome'];
                }
                ksort($basesAtendidas);

                $stmtCarga->execute([
                    $planejamentoId,
                    (int) $veiculoSimulado['id'],
                    $assignedUnit['id'] ?? null,
                    $veiculoId,
                    sprintf('%s-C%02d', $codigoPlanejamento, $cargaSequencial),
                    implode(' -> ', array_values($basesAtendidas)),
                    (float) $veiculoSimulado['peso_total_kg'],
                    (float) $veiculoSimulado['volume_total_m3'],
                    (float) $veiculoSimulado['ocupacao_peso_pct'],
                    (float) $veiculoSimulado['ocupacao_volume_pct'],
                    'planejada',
                ]);
                $cargaSequencial++;
            }

            $this->pdo->commit();
            return $this->buscarPorId($planejamentoId);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listarSemana(string $dataReferencia): array {
        $date = new DateTimeImmutable($dataReferencia);
        $dayOfWeek = (int) $date->format('N');
        $inicio = $date->modify('-' . ($dayOfWeek - 1) . ' days')->format('Y-m-d');
        $fim = $date->modify('+' . (7 - $dayOfWeek) . ' days')->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'SELECT pr.data_operacao, pr.codigo_planejamento, r.codigo AS rota_codigo, r.descricao AS rota_descricao,
                    pc.codigo_carga, pc.bases_atendidas, pc.peso_total_kg, pc.volume_total_m3, pc.ocupacao_peso_pct, pc.ocupacao_volume_pct,
                    uv.codigo_unidade, v.nome AS veiculo_nome
             FROM planejamento_cargas pc
             JOIN planejamento_rotas pr ON pr.id = pc.planejamento_id
             JOIN rotas r ON r.id = pr.rota_id
             LEFT JOIN unidades_veiculo uv ON uv.id = pc.unidade_veiculo_id
             JOIN veiculos v ON v.id = pc.veiculo_id
             WHERE pr.data_operacao BETWEEN ? AND ?
               AND pr.status <> "cancelado"
             ORDER BY pr.data_operacao ASC, uv.codigo_unidade ASC, pc.codigo_carga ASC'
        );
        $stmt->execute([$inicio, $fim]);
        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'linhas' => $stmt->fetchAll(),
        ];
    }

    private function validarConflitosUnidades(array $units, string $dataOperacao): void {
        if (empty($units)) {
            return;
        }

        $ids = array_map(static fn($unit) => (int) $unit['id'], $units);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT uv.codigo_unidade
                FROM planejamento_cargas pc
                JOIN planejamento_rotas pr ON pr.id = pc.planejamento_id
                JOIN unidades_veiculo uv ON uv.id = pc.unidade_veiculo_id
                WHERE pr.data_operacao = ?
                  AND pr.status <> "cancelado"
                  AND pc.unidade_veiculo_id IN (' . $placeholders . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$dataOperacao], $ids));
        $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($ocupadas)) {
            throw new Exception('As seguintes unidades já estão comprometidas nessa data: ' . implode(', ', $ocupadas));
        }
    }
}
