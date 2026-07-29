<?php
$pageTitle = 'Histórico de Simulações';
require_once __DIR__ . '/../controllers/SimulacaoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new SimulacaoController();
$simulacoes = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="mb-4">
        <h2 class="h3 mb-1 text-dark">Histórico de simulações</h2>
        <p class="text-muted mb-0">Acompanhe os cenários salvos e reabra os relatórios quando necessário.</p>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Pedido</th>
                            <th>Status</th>
                            <th>Veículos</th>
                            <th>Peso</th>
                            <th>Volume</th>
                            <th>Score</th>
                            <th class="pe-4 text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($simulacoes)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma simulação salva.</td></tr>
                        <?php else: ?>
                            <?php foreach ($simulacoes as $sim): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($sim['codigo_simulacao']) ?></td>
                                    <td><?= htmlspecialchars($sim['codigo_pedido']) ?> - <?= htmlspecialchars($sim['pedido_descricao']) ?></td>
                                    <td><?= htmlspecialchars($sim['status']) ?></td>
                                    <td><?= (int) $sim['total_veiculos'] ?></td>
                                    <td><?= number_format($sim['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                    <td><?= number_format($sim['volume_total_m3'], 4, ',', '.') ?> m³</td>
                                    <td><?= number_format($sim['score_total'], 0, ',', '.') ?></td>
                                    <td class="pe-4 text-end"><a href="resultado_simulacao.php?id=<?= (int) $sim['id'] ?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

