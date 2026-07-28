<?php
$pageTitle = 'Relatório de Simulação de Carga';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

$simulacao_id = (int)($_GET['id'] ?? 0);
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

$controller = new SimulacaoController();
$simulacao = $controller->buscar($simulacao_id);

if (!$simulacao) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Simulação não encontrada.</div></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container-fluid p-0">
    <!-- Top Action Bar (Escondida em modo de impressão PDF) -->
    <div class="d-flex justify-content-between align-items-center mb-4 btn-action-no-print">
        <div>
            <h2 class="h3 mb-1 text-dark">Relatório Técnico de Alocação</h2>
            <p class="text-muted mb-0">Simulação <strong><?= htmlspecialchars($simulacao['codigo_simulacao']) ?></strong> realizada em <?= date('d/m/Y \à\s H:i', strtotime($simulacao['created_at'])) ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Imprimir / Salvar PDF
            </button>
            <a href="../api/exportar_relatorio.php?id=<?= $simulacao['id'] ?>&formato=excel" class="btn btn-outline-success">
                <i class="fa-solid fa-file-excel me-1"></i> Exportar Excel / CSV
            </a>
            <button class="btn btn-primary" onclick="duplicarSimulacao(<?= $simulacao['id'] ?>)">
                <i class="fa-solid fa-copy me-1"></i> Duplicar Simulação
            </button>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <?php if ($simulacao['status'] === 'aprovado'): ?>
                        <div class="badge-icon bg-success-subtle text-success p-3 rounded-circle fs-3">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-success mb-1">Carga Aprovada para Transporte</h4>
                            <span class="text-muted">Carga 100% alocada dentro das capacidades operacionais do veículo.</span>
                        </div>
                    <?php elseif ($simulacao['status'] === 'aprovado_com_alerta'): ?>
                        <div class="badge-icon bg-warning-subtle text-warning p-3 rounded-circle fs-3">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-warning mb-1">Carga Aprovada com Alertas de Atenção</h4>
                            <span class="text-muted">A carga cabe no veículo, mas existem recomendações operacionais importantes.</span>
                        </div>
                    <?php else: ?>
                        <div class="badge-icon bg-danger-subtle text-danger p-3 rounded-circle fs-3">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-danger mb-1">Simulação Reprovada / Carga Excedida</h4>
                            <span class="text-muted">Carga excede a capacidade máxima ou viola regras bloqueantes de segurança.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-end">
                    <span class="badge badge-<?= strtolower($simulacao['veiculo_tipo']) ?> px-3 py-2 fs-6 rounded-pill">
                        <?= htmlspecialchars($simulacao['veiculo_tipo']) ?>: <?= htmlspecialchars($simulacao['veiculo_nome']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo dos KPIs Principais -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <small class="text-muted d-block uppercase fw-bold">PESO TOTAL DA CARGA</small>
                    <h3 class="fw-extrabold text-dark my-1"><?= number_format($simulacao['peso_total_kg'], 2, ',', '.') ?> <small class="fs-6">kg</small></h3>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-<?= $simulacao['ocupacao_peso_pct'] > 95 ? 'danger' : 'primary' ?>" style="width: <?= min(100, $simulacao['ocupacao_peso_pct']) ?>%"></div>
                    </div>
                    <small class="text-muted mt-1 d-block">Capacidade: <?= number_format($simulacao['veiculo_capacidade_kg'], 0, ',', '.') ?> kg (<?= $simulacao['ocupacao_peso_pct'] ?>%)</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <small class="text-muted d-block uppercase fw-bold">VOLUME & CUBAGEM</small>
                    <h3 class="fw-extrabold text-purple my-1" style="color: #8b5cf6;"><?= number_format($simulacao['volume_total_m3'], 2, ',', '.') ?> <small class="fs-6">m³</small></h3>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-purple" style="width: <?= min(100, $simulacao['ocupacao_volume_pct']) ?>%; background: #8b5cf6;"></div>
                    </div>
                    <small class="text-muted mt-1 d-block">Capacidade: <?= number_format($simulacao['veiculo_capacidade_m3'], 2, ',', '.') ?> m³ (<?= $simulacao['ocupacao_volume_pct'] ?>%)</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <small class="text-muted d-block uppercase fw-bold">LASTROS UTILIZADOS</small>
                    <h3 class="fw-extrabold text-success my-1"><?= $simulacao['lastros_utilizados'] ?> / <?= $simulacao['max_lastros_permitido'] ?></h3>
                    <small class="text-muted mt-1 d-block"><i class="fa-solid fa-layer-group me-1"></i> Camadas na Carroceria</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <small class="text-muted d-block uppercase fw-bold">EQUILÍBRIO LONGITUDINAL</small>
                    <h3 class="fw-extrabold text-info my-1"><?= $simulacao['centro_gravidade_x'] ?>%</h3>
                    <small class="text-muted mt-1 d-block"><i class="fa-solid fa-crosshair me-1"></i> Centro de Gravidade</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Visualização Gráfica da Carroceria -->
    <div class="mb-4">
        <div id="visualizadorCargaRelatorio"></div>
    </div>

    <!-- Alertas Operacionais Emitidos -->
    <?php if (!empty($simulacao['alertas'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-warning">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Alertas e Recomendações Operacionais
                </h5>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($simulacao['alertas'] as $al): ?>
                        <div class="alert alert-<?= $al['severidade'] === 'danger' ? 'danger' : ($al['severidade'] === 'warning' ? 'warning' : 'info') ?> mb-0 py-2">
                            <i class="fa-solid fa-shield-cat me-2"></i> <?= htmlspecialchars($al['mensagem']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabela Detalhada de Materiais Alocados -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Detalhamento da Carga por Material
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Descrição</th>
                            <th>Qtd</th>
                            <th>Peso Unit.</th>
                            <th>Peso Total</th>
                            <th>Vol Unit.</th>
                            <th>Vol Total</th>
                            <th>Posição Lastro</th>
                            <th>Status</th>
                            <th class="pe-4">Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simulacao['itens'] as $it): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($it['codigo_material']) ?></td>
                                <td><span class="fw-semibold text-dark"><?= htmlspecialchars($it['descricao_material']) ?></span></td>
                                <td class="fw-bold text-center"><?= $it['quantidade'] ?></td>
                                <td><?= number_format($it['peso_unitario_kg'], 2, ',', '.') ?> kg</td>
                                <td class="fw-bold"><?= number_format($it['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                <td><?= number_format($it['volume_unitario_m3'], 4, ',', '.') ?> m³</td>
                                <td class="fw-bold text-purple" style="color: #8b5cf6;"><?= number_format($it['volume_total_m3'], 4, ',', '.') ?> m³</td>
                                <td>
                                    <?php if ($it['lastro_posicao'] > 0): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            Lastro <?= $it['lastro_posicao'] ?> (Z: <?= $it['posicao_z'] ?>m)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Não Alocado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($it['status_alocacao'] === 'alocado'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Alocado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 small text-muted"><?= htmlspecialchars($it['observacoes_restricao'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const simData = <?= json_encode($simulacao) ?>;
    const vis = new CargoVisualizer('visualizadorCargaRelatorio');
    vis.render(simData);

    if (<?= $isPrint ? 'true' : 'false' ?>) {
        setTimeout(() => window.print(), 600);
    }
});

async function duplicarSimulacao(id) {
    const formData = new FormData();
    formData.append('id', id);

    try {
        const resp = await fetch('../api/duplicar_simulacao.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => window.location.href = `resultado_simulacao.php?id=${res.data.id}`, 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao duplicar simulação.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
