<?php
$pageTitle = 'Dashboard Logístico';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

$veiculoController = new VeiculoController();
$materialController = new MaterialController();
$simulacaoController = new SimulacaoController();

$veiculos = $veiculoController->listar();
$materiais = $materialController->listar();
$simulacoes = $simulacaoController->listar();

$simulacaoModel = new Simulacao();
$statsDash = $simulacaoModel->getEstatísticasDashboard();
$veiculoModel = new Veiculo();
$statsVeiculos = $veiculoModel->getEstatísticas();

$totalVeiculos = count($veiculos);
$totalMateriais = count($materiais);
$totalSimulacoes = count($simulacoes);
$ultimaCubagem = number_format($statsDash['ultima_cubagem'] ?? 0, 2, ',', '.');
$ocupacaoMediaPeso = number_format($statsDash['ocupacao_media_peso'] ?? 0, 1, ',', '.');
$totalAlertas = $statsDash['total_alertas'] ?? 0;
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Visão Geral de Operações</h2>
            <p class="text-muted mb-0">Indicadores de capacidade, simulações e alertas de cubagem em tempo real.</p>
        </div>
        <div>
            <a href="simulacao.php" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-cube me-2"></i> Nova Simulação Visual
            </a>
        </div>
    </div>

    <!-- 6 KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Veículos</div>
                        <div class="kpi-value text-primary"><?= $totalVeiculos ?></div>
                    </div>
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Materiais</div>
                        <div class="kpi-value text-info"><?= $totalMateriais ?></div>
                    </div>
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Simulações</div>
                        <div class="kpi-value text-success"><?= $totalSimulacoes ?></div>
                    </div>
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Última Cubagem</div>
                        <div class="kpi-value text-purple" style="color: #8b5cf6;"><?= $ultimaCubagem ?> <small class="fs-6">m³</small></div>
                    </div>
                    <div class="kpi-icon bg-purple-subtle text-purple" style="background: #f3e8ff; color: #8b5cf6;">
                        <i class="fa-solid fa-ruler-combined"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Ocupação Média</div>
                        <div class="kpi-value text-warning"><?= $ocupacaoMediaPeso ?>%</div>
                    </div>
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="fa-solid fa-weight-hanging"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6">
            <div class="card-kpi">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="kpi-title">Alertas Críticos</div>
                        <div class="kpi-value text-danger"><?= $totalAlertas ?></div>
                    </div>
                    <div class="kpi-icon bg-danger-subtle text-danger">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Charts Section -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fa-solid fa-chart-column text-primary me-2"></i>Frota por Tipo de Veículo
                    </h5>
                    <small class="text-muted">Munck, Truck e Carreta Prancha disponíveis</small>
                </div>
                <div class="card-body p-4">
                    <canvas id="chartVeiculos" height="220"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fa-solid fa-chart-pie text-warning me-2"></i>Distribuição por Categoria de Material
                    </h5>
                    <small class="text-muted">Percentual em simulações operacionais</small>
                </div>
                <div class="card-body p-4">
                    <canvas id="chartMateriais" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Simulations Table -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title fw-bold mb-0">
                    <i class="fa-solid fa-list-check text-success me-2"></i>Últimas Simulações Realizadas
                </h5>
                <small class="text-muted">Histórico recente de alocação de carga</small>
            </div>
            <a href="historico.php" class="btn btn-sm btn-outline-primary">Ver Histórico Completo</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código / Data</th>
                            <th>Veículo</th>
                            <th>Peso Total (kg)</th>
                            <th>Volume (m³)</th>
                            <th>Ocupação Peso</th>
                            <th>Lastros</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($simulacoes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Nenhuma simulação realizada ainda. <a href="simulacao.php">Clique aqui para criar a primeira!</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($simulacoes, 0, 5) as $sim): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($sim['codigo_simulacao']) ?></div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($sim['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($sim['veiculo_tipo']) ?> px-2 py-1">
                                            <?= htmlspecialchars($sim['veiculo_tipo']) ?>
                                        </span>
                                        <div class="small fw-semibold mt-1"><?= htmlspecialchars($sim['veiculo_nome']) ?></div>
                                    </td>
                                    <td><?= number_format($sim['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                    <td><?= number_format($sim['volume_total_m3'], 2, ',', '.') ?> m³</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                <div class="progress-bar bg-<?= $sim['ocupacao_peso_pct'] > 95 ? 'danger' : ($sim['ocupacao_peso_pct'] > 80 ? 'warning' : 'success') ?>" 
                                                     style="width: <?= min(100, $sim['ocupacao_peso_pct']) ?>%"></div>
                                            </div>
                                            <small class="fw-bold"><?= $sim['ocupacao_peso_pct'] ?>%</small>
                                        </div>
                                    </td>
                                    <td><?= $sim['lastros_utilizados'] ?>/<?= $sim['max_lastros_permitido'] ?></td>
                                    <td>
                                        <?php if ($sim['status'] === 'aprovado'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Aprovado</span>
                                        <?php elseif ($sim['status'] === 'aprovado_com_alerta'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Atenção</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Reprovado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="resultado_simulacao.php?id=<?= $sim['id'] ?>" class="btn btn-sm btn-light border">
                                            <i class="fa-solid fa-eye me-1"></i> Ver Carga
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderDashboardGraficos({
        veiculos: {
            total_munck: <?= $statsVeiculos['total_munck'] ?? 0 ?>,
            total_truck: <?= $statsVeiculos['total_truck'] ?? 0 ?>,
            total_carreta: <?= $statsVeiculos['total_carreta'] ?? 0 ?>
        },
        materiais: {
            labels: ['Bobinas de Cabo', 'Transformadores', 'Postes', 'Chaves', 'Outros'],
            valores: [35, 25, 20, 12, 8]
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
