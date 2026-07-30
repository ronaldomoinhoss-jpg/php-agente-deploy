<?php
$pageTitle = 'Planejamento por Rota';
require_once __DIR__ . '/../controllers/PlanejamentoRotaController.php';
require_once __DIR__ . '/../controllers/RotaController.php';
require_once __DIR__ . '/../controllers/PedidoCargaController.php';
require_once __DIR__ . '/../controllers/UnidadeVeiculoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new PlanejamentoRotaController();
$rotaController = new RotaController();
$pedidoController = new PedidoCargaController();
$unidadeController = new UnidadeVeiculoController();

$mensagem = null;
$erro = null;
$planejamentoGerado = null;
$dataOperacao = $_POST['data_operacao'] ?? date('Y-m-d');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $planejamentoGerado = $controller->gerar($_POST);
        $mensagem = 'Planejamento gerado com sucesso.';
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$rotas = $rotaController->listar();
$pedidos = $pedidoController->listar();
$unidadesDisponiveis = $unidadeController->listarDisponiveisNaData($dataOperacao);
$historico = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Planejamento por rota</h2>
            <p class="text-muted mb-0">Consolide vários pedidos em uma rota e gere múltiplas cargas com a cubagem atual.</p>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Nova proposta de cargas</h5></div>
                <div class="card-body p-4">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">ROTA</label>
                            <select name="rota_id" class="form-select bg-light" required>
                                <option value="">Selecione</option>
                                <?php foreach ($rotas as $rota): ?>
                                    <option value="<?= (int) $rota['id'] ?>" <?= (int) ($_POST['rota_id'] ?? 0) === (int) $rota['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rota['codigo']) ?> - <?= htmlspecialchars($rota['descricao']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">DATA DE OPERAÇÃO</label>
                            <input type="date" name="data_operacao" class="form-control bg-light" value="<?= htmlspecialchars($dataOperacao) ?>" required>
                            <small class="text-muted">A lista abaixo é calculada para a data exibida na tela.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">PEDIDOS DA ROTA</label>
                            <div class="border rounded-3 p-3 bg-light" style="max-height: 260px; overflow-y: auto;">
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php $checked = in_array((string) $pedido['id'], array_map('strval', $_POST['pedido_ids'] ?? []), true); ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="pedido_ids[]" value="<?= (int) $pedido['id'] ?>" id="pedido_<?= (int) $pedido['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="pedido_<?= (int) $pedido['id'] ?>">
                                            <?= htmlspecialchars($pedido['codigo_pedido']) ?> - <?= htmlspecialchars($pedido['descricao']) ?>
                                            <small class="text-muted d-block"><?= (int) $pedido['unidades'] ?> unidade(s)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">UNIDADES DISPONÍVEIS</label>
                            <div class="border rounded-3 p-3 bg-light" style="max-height: 260px; overflow-y: auto;">
                                <?php if (empty($unidadesDisponiveis)): ?>
                                    <div class="text-muted">Nenhuma unidade disponível nesta data.</div>
                                <?php else: ?>
                                    <?php foreach ($unidadesDisponiveis as $unidade): ?>
                                        <?php $checked = in_array((string) $unidade['id'], array_map('strval', $_POST['unidade_ids'] ?? []), true); ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="unidade_ids[]" value="<?= (int) $unidade['id'] ?>" id="unidade_<?= (int) $unidade['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="unidade_<?= (int) $unidade['id'] ?>">
                                                <?= htmlspecialchars($unidade['codigo_unidade']) ?> - <?= htmlspecialchars($unidade['veiculo_tipo']) ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($unidade['veiculo_nome']) ?></small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">OBSERVAÇÕES</label>
                            <textarea name="observacoes" class="form-control bg-light" rows="3"><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Gerar proposta de cargas</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <?php if ($planejamentoGerado): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Planejamento gerado <?= htmlspecialchars($planejamentoGerado['codigo_planejamento']) ?></h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="border rounded-3 p-3 bg-light"><small class="text-muted d-block">Rota</small><strong><?= htmlspecialchars($planejamentoGerado['rota_codigo']) ?></strong></div></div>
                            <div class="col-md-3"><div class="border rounded-3 p-3 bg-light"><small class="text-muted d-block">Data</small><strong><?= date('d/m/Y', strtotime($planejamentoGerado['data_operacao'])) ?></strong></div></div>
                            <div class="col-md-3"><div class="border rounded-3 p-3 bg-light"><small class="text-muted d-block">Cargas</small><strong><?= (int) $planejamentoGerado['total_cargas'] ?></strong></div></div>
                            <div class="col-md-3"><div class="border rounded-3 p-3 bg-light"><small class="text-muted d-block">Score</small><strong><?= number_format($planejamentoGerado['score_total'], 0, ',', '.') ?></strong></div></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Carga</th>
                                        <th>Unidade</th>
                                        <th>Veículo</th>
                                        <th>Bases</th>
                                        <th>Peso</th>
                                        <th>Volume</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($planejamentoGerado['cargas'] as $carga): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($carga['codigo_carga']) ?></td>
                                            <td><?= htmlspecialchars($carga['codigo_unidade'] ?? 'Sem unidade') ?></td>
                                            <td><?= htmlspecialchars($carga['veiculo_nome']) ?></td>
                                            <td><?= htmlspecialchars($carga['bases_atendidas']) ?></td>
                                            <td><?= number_format($carga['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                            <td><?= number_format($carga['volume_total_m3'], 4, ',', '.') ?> m³</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="resultado_simulacao.php?id=<?= (int) $planejamentoGerado['simulacao_id'] ?>" class="btn btn-outline-primary">Abrir cubagem detalhada</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Histórico de planejamentos</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Código</th>
                                    <th>Data</th>
                                    <th>Rota</th>
                                    <th>Cargas</th>
                                    <th>Peso</th>
                                    <th class="pe-4">Simulação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historico)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum planejamento gerado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historico as $item): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($item['codigo_planejamento']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($item['data_operacao'])) ?></td>
                                            <td><?= htmlspecialchars($item['rota_codigo']) ?> - <?= htmlspecialchars($item['rota_descricao']) ?></td>
                                            <td><?= (int) $item['total_cargas'] ?></td>
                                            <td><?= number_format($item['total_peso_kg'], 2, ',', '.') ?> kg</td>
                                            <td class="pe-4"><a href="resultado_simulacao.php?id=<?= (int) $item['simulacao_id'] ?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
