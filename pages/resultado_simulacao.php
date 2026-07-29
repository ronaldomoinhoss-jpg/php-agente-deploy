<?php
$pageTitle = 'Resultado da Simulação';
require_once __DIR__ . '/../controllers/SimulacaoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new SimulacaoController();
$simulacao = $controller->buscar((int) ($_GET['id'] ?? 0));

if (!$simulacao) {
    echo '<div class="container py-5"><div class="alert alert-danger">Simulação não encontrada.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Resultado da simulação <?= htmlspecialchars($simulacao['codigo_simulacao']) ?></h2>
            <p class="text-muted mb-0">Pedido <?= htmlspecialchars($simulacao['codigo_pedido']) ?> | <?= htmlspecialchars($simulacao['pedido_descricao']) ?> | gerado em <?= date('d/m/Y H:i', strtotime($simulacao['created_at'])) ?></p>
        </div>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>Imprimir</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Status</small><h4 class="mb-0 text-<?= $simulacao['status'] === 'reprovado' ? 'danger' : ($simulacao['status'] === 'aprovado_com_alerta' ? 'warning' : 'success') ?>"><?= htmlspecialchars($simulacao['status']) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Veículos usados</small><h4 class="mb-0"><?= (int) $simulacao['total_veiculos'] ?></h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Peso total</small><h4 class="mb-0"><?= number_format($simulacao['peso_total_kg'], 2, ',', '.') ?> kg</h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Volume total</small><h4 class="mb-0"><?= number_format($simulacao['volume_total_m3'], 4, ',', '.') ?> m³</h4></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Visualização 2D / 3D</h5>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#manualAssemblyPanel" aria-expanded="false" aria-controls="manualAssemblyPanel">
                    <i class="fa-solid fa-hand me-1"></i>Montagem manual
                </button>
            </div>
            <div id="visualizadorCargaResultado"></div>
        </div>
    </div>

    <div class="collapse mb-4" id="manualAssemblyPanel">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Editor de montagem manual</h5>
                <small class="text-muted">Ajuste posição e lastro por item. Bobinas permanecem em pé e não podem ficar deitadas de lado.</small>
            </div>
            <div class="card-body p-4">
                <?php foreach ($simulacao['veiculos'] as $veiculo): ?>
                    <div class="border rounded-4 p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($veiculo['slot_codigo']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($veiculo['tipo_veiculo']) ?> | acesso <?= htmlspecialchars($veiculo['acesso_descarga']) ?></small>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="previewManualAssembly(<?= (int) $veiculo['id'] ?>)">Pré-visualizar</button>
                                <button class="btn btn-sm btn-primary" type="button" onclick="saveManualAssembly(<?= (int) $simulacao['id'] ?>, <?= (int) $veiculo['id'] ?>)">Salvar montagem</button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Material</th>
                                        <th>Base</th>
                                        <th>Lastro</th>
                                        <th>X (m)</th>
                                        <th>Y (m)</th>
                                        <th>Comp. x Larg.</th>
                                        <th>Orientação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($veiculo['itens'] as $item): ?>
                                        <?php $isBobina = stripos($item['codigo_material'], 'BOB') !== false || stripos($item['descricao_material'], 'bobina') !== false; ?>
                                        <tr class="manual-item-row" data-vehicle-id="<?= (int) $veiculo['id'] ?>" data-item-id="<?= (int) $item['id'] ?>" data-original-length="<?= htmlspecialchars($item['comprimento_m']) ?>" data-original-width="<?= htmlspecialchars($item['largura_m']) ?>" data-is-bobina="<?= $isBobina ? '1' : '0' ?>">
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['codigo_material']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($item['descricao_material']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($item['base_nome']) ?><br><small class="text-muted">ordem <?= (int) $item['ordem_entrega'] ?></small></td>
                                            <td>
                                                <select class="form-select form-select-sm manual-lastro">
                                                    <option value="1" <?= (int) $item['lastro_posicao'] === 1 ? 'selected' : '' ?>>1</option>
                                                    <option value="2" <?= (int) $item['lastro_posicao'] === 2 ? 'selected' : '' ?>>2</option>
                                                </select>
                                            </td>
                                            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm manual-x" value="<?= htmlspecialchars($item['posicao_x']) ?>"></td>
                                            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm manual-y" value="<?= htmlspecialchars($item['posicao_y']) ?>"></td>
                                            <td><span class="manual-dim-label"><?= number_format((float) $item['comprimento_m'], 2, ',', '.') ?> x <?= number_format((float) $item['largura_m'], 2, ',', '.') ?></span></td>
                                            <td>
                                                <?php if ($isBobina): ?>
                                                    <span class="badge bg-warning text-dark">Em pé obrigatório</span>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-light border" type="button" onclick="rotateManualItem(this)">Girar 90°</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($simulacao['alertas'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Alertas e recomendações</h5></div>
            <div class="card-body p-4">
                <?php foreach ($simulacao['alertas'] as $alerta): ?>
                    <div class="alert alert-<?= $alerta['severidade'] === 'danger' ? 'danger' : ($alerta['severidade'] === 'warning' ? 'warning' : 'info') ?> py-2 mb-2"><?= htmlspecialchars($alerta['mensagem']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Resumo por veículo</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Veículo</th>
                            <th>Acesso</th>
                            <th>Ocupação peso</th>
                            <th>Ocupação volume</th>
                            <th>Centro de gravidade</th>
                            <th>Lastros</th>
                            <th class="pe-4">Ordem de descarga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulacao['veiculos'] as $veiculo): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars($veiculo['slot_codigo']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($veiculo['tipo_veiculo']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($veiculo['acesso_descarga']) ?></td>
                                <td><?= number_format($veiculo['ocupacao_peso_pct'], 2, ',', '.') ?>%</td>
                                <td><?= number_format($veiculo['ocupacao_volume_pct'], 2, ',', '.') ?>%</td>
                                <td>X <?= number_format($veiculo['centro_gravidade_x'], 2, ',', '.') ?>% / Y <?= number_format($veiculo['centro_gravidade_y'], 2, ',', '.') ?>%</td>
                                <td><?= (int) $veiculo['lastros_utilizados'] ?></td>
                                <td class="pe-4"><?= htmlspecialchars($veiculo['ordem_descarga']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Posicionamento alocado</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Veículo</th>
                            <th>Material</th>
                            <th>Base</th>
                            <th>Ordem</th>
                            <th>Lastro</th>
                            <th>Posição</th>
                            <th class="pe-4">Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulacao['veiculos'] as $veiculo): ?>
                            <?php foreach ($veiculo['itens'] as $item): ?>
                                <tr>
                                    <td class="ps-4"><?= htmlspecialchars($veiculo['slot_codigo']) ?></td>
                                    <td><span class="fw-semibold"><?= htmlspecialchars($item['codigo_material']) ?></span><br><small class="text-muted"><?= htmlspecialchars($item['descricao_material']) ?></small></td>
                                    <td><?= htmlspecialchars($item['base_nome']) ?></td>
                                    <td><?= (int) $item['ordem_entrega'] ?></td>
                                    <td><?= (int) $item['lastro_posicao'] ?></td>
                                    <td>X <?= number_format($item['posicao_x'], 2, ',', '.') ?> | Y <?= number_format($item['posicao_y'], 2, ',', '.') ?> | Z <?= number_format($item['posicao_z'], 2, ',', '.') ?></td>
                                    <td class="pe-4 small text-muted"><?= htmlspecialchars($item['observacoes_restricao']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($simulacao['itens_nao_alocados'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0 text-danger">Itens não alocados</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light"><tr><th class="ps-4">Material</th><th>Base</th><th>Ordem</th><th class="pe-4">Motivo</th></tr></thead>
                        <tbody>
                            <?php foreach ($simulacao['itens_nao_alocados'] as $item): ?>
                                <tr>
                                    <td class="ps-4"><?= htmlspecialchars($item['codigo_material']) ?> - <?= htmlspecialchars($item['descricao_material']) ?></td>
                                    <td><?= htmlspecialchars($item['base_nome']) ?></td>
                                    <td><?= (int) $item['ordem_entrega'] ?></td>
                                    <td class="pe-4"><?= htmlspecialchars($item['observacoes_restricao']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.simulationData = <?= json_encode($simulacao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.resultVisualizer = new CargoVisualizer('visualizadorCargaResultado');
    window.resultVisualizer.render(window.simulationData);
});

function rotateManualItem(button) {
    const row = button.closest('.manual-item-row');
    if (!row || row.dataset.isBobina === '1') {
        return;
    }

    const currentLength = parseFloat(row.dataset.currentLength || row.dataset.originalLength);
    const currentWidth = parseFloat(row.dataset.currentWidth || row.dataset.originalWidth);
    row.dataset.currentLength = currentWidth.toFixed(2);
    row.dataset.currentWidth = currentLength.toFixed(2);
    row.querySelector('.manual-dim-label').textContent = `${currentWidth.toFixed(2).replace('.', ',')} x ${currentLength.toFixed(2).replace('.', ',')}`;
}

function collectManualItems(vehicleId) {
    return Array.from(document.querySelectorAll(`.manual-item-row[data-vehicle-id="${vehicleId}"]`)).map((row) => ({
        id: parseInt(row.dataset.itemId, 10),
        lastro_posicao: parseInt(row.querySelector('.manual-lastro').value || '1', 10),
        posicao_x: parseFloat(row.querySelector('.manual-x').value || '0'),
        posicao_y: parseFloat(row.querySelector('.manual-y').value || '0'),
        comprimento_m: parseFloat(row.dataset.currentLength || row.dataset.originalLength),
        largura_m: parseFloat(row.dataset.currentWidth || row.dataset.originalWidth),
        orientacao_manual: row.dataset.isBobina === '1' ? 'em_pe' : 'base_90'
    }));
}

function applyItemsToLocalSimulation(vehicleId, items) {
    const vehicle = (window.simulationData.veiculos || []).find((entry) => parseInt(entry.id, 10) === parseInt(vehicleId, 10));
    if (!vehicle) return;

    items.forEach((updated) => {
        const localItem = (vehicle.itens || []).find((entry) => parseInt(entry.id, 10) === parseInt(updated.id, 10));
        if (!localItem) return;
        localItem.lastro_posicao = updated.lastro_posicao;
        localItem.posicao_x = updated.posicao_x;
        localItem.posicao_y = updated.posicao_y;
        localItem.comprimento_m = updated.comprimento_m;
        localItem.largura_m = updated.largura_m;
        if (updated.lastro_posicao === 1) {
            localItem.posicao_z = 0;
        }
    });
}

function previewManualAssembly(vehicleId) {
    const items = collectManualItems(vehicleId);
    applyItemsToLocalSimulation(vehicleId, items);
    const index = (window.simulationData.veiculos || []).findIndex((entry) => parseInt(entry.id, 10) === parseInt(vehicleId, 10));
    if (index >= 0) {
        window.resultVisualizer.setVehicle(index);
    } else {
        window.resultVisualizer.render(window.simulationData);
    }
}

async function saveManualAssembly(simulacaoId, vehicleId) {
    const items = collectManualItems(vehicleId);
    const response = await fetch('../api/salvar_montagem_manual.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            simulacao_id: simulacaoId,
            simulacao_veiculo_id: vehicleId,
            itens: items
        })
    });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => window.location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
