<?php
$pageTitle = 'Comparador de Eficiência de Veículos';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

$veiculoController = new VeiculoController();
$veiculos = $veiculoController->listar();

$materialController = new MaterialController();
$materiais = $materialController->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Comparador de Eficiência de Transporte</h2>
            <p class="text-muted mb-0">Compare lado a lado o desempenho da mesma carga em um Munck, Truck e Carreta Prancha.</p>
        </div>
    </div>

    <!-- Tabela Comparativa de Frota -->
    <div class="row g-4 mb-4">
        <?php foreach ($veiculos as $v): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 text-center">
                        <span class="badge badge-<?= strtolower($v['tipo']) ?> px-3 py-2 fs-6 rounded-pill mb-2">
                            <?= htmlspecialchars($v['tipo']) ?>
                        </span>
                        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($v['nome']) ?></h4>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Capacidade Peso:</span>
                                <strong class="text-dark"><?= number_format($v['capacidade_kg'], 0, ',', '.') ?> kg</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Capacidade Volume:</span>
                                <strong class="text-dark"><?= number_format($v['capacidade_m3'], 2, ',', '.') ?> m³</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Comprimento Útil:</span>
                                <strong><?= $v['comprimento_m'] ?> m</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Largura Útil:</span>
                                <strong><?= $v['largura_m'] ?> m</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Altura Permissão:</span>
                                <strong><?= $v['altura_m'] ?> m</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Lastros Máximos:</span>
                                <strong><?= $v['max_lastros'] ?> Camadas</strong>
                            </li>
                        </ul>

                        <a href="simulacao.php?veiculo_id=<?= $v['id'] ?>" class="btn btn-outline-primary w-100">
                            <i class="fa-solid fa-play me-1"></i> Simular Neste Veículo
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
