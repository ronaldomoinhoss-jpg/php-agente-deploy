<?php
$pageTitle = 'Dashboard Logístico';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$stats = [
    'materiais' => (int) $pdo->query('SELECT COUNT(*) FROM materiais')->fetchColumn(),
    'veiculos' => (int) $pdo->query('SELECT COUNT(*) FROM veiculos')->fetchColumn(),
    'bases' => (int) $pdo->query('SELECT COUNT(*) FROM bases_operacionais')->fetchColumn(),
    'pedidos' => (int) $pdo->query('SELECT COUNT(*) FROM pedidos_carga')->fetchColumn(),
    'simulacoes' => (int) $pdo->query('SELECT COUNT(*) FROM simulacoes')->fetchColumn(),
];

$ultimasSimulacoes = $pdo->query(
    'SELECT s.*, p.codigo_pedido
     FROM simulacoes s
     JOIN pedidos_carga p ON p.id = s.pedido_id
     ORDER BY s.id DESC
     LIMIT 5'
)->fetchAll();

$basesResumo = $pdo->query(
    'SELECT b.nome, COUNT(i.id) AS linhas, COALESCE(SUM(i.quantidade), 0) AS unidades
     FROM bases_operacionais b
     LEFT JOIN pedido_itens i ON i.base_id = b.id
     GROUP BY b.id
     ORDER BY b.ordem_padrao ASC'
)->fetchAll();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Cubagem2 para distribuição elétrica</h2>
            <p class="text-muted mb-0">Planeje pedidos multi-base, simule a frota e visualize a cubagem com foco em descarga sem remanejo.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="pedidos.php" class="btn btn-outline-primary"><i class="fa-solid fa-list-check me-1"></i>Novo Pedido</a>
            <a href="simulacao.php" class="btn btn-primary"><i class="fa-solid fa-cube me-1"></i>Simular Agora</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Materiais</small><h3 class="mb-0"><?= $stats['materiais'] ?></h3></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Veículos</small><h3 class="mb-0"><?= $stats['veiculos'] ?></h3></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Bases</small><h3 class="mb-0"><?= $stats['bases'] ?></h3></div></div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Pedidos</small><h3 class="mb-0"><?= $stats['pedidos'] ?></h3></div></div>
        </div>
        <div class="col-xl-4 col-md-8">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body"><small class="text-muted d-block">Simulações realizadas</small><h3 class="mb-1"><?= $stats['simulacoes'] ?></h3><small class="text-muted">Baseado no histórico salvo até <?= date('d/m/Y') ?>.</small></div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Últimas simulações</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Código</th>
                                    <th>Pedido</th>
                                    <th>Veículos</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimasSimulacoes)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma simulação registrada ainda.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ultimasSimulacoes as $sim): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($sim['codigo_simulacao']) ?></td>
                                            <td><?= htmlspecialchars($sim['codigo_pedido']) ?></td>
                                            <td><?= (int) $sim['total_veiculos'] ?></td>
                                            <td><?= number_format($sim['score_total'], 0, ',', '.') ?></td>
                                            <td><span class="badge bg-<?= $sim['status'] === 'reprovado' ? 'danger' : ($sim['status'] === 'aprovado_com_alerta' ? 'warning text-dark' : 'success') ?>"><?= htmlspecialchars($sim['status']) ?></span></td>
                                            <td class="pe-4 text-end"><a href="resultado_simulacao.php?id=<?= $sim['id'] ?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Bases atendidas nos pedidos</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($basesResumo as $base): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($base['nome']) ?></div>
                                <small class="text-muted"><?= (int) $base['linhas'] ?> linhas cadastradas</small>
                            </div>
                            <span class="badge bg-light text-dark border"><?= (int) $base['unidades'] ?> unid.</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

