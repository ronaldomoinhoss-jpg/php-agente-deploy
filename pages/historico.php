<?php
$pageTitle = 'Histórico de Simulações';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

$controller = new SimulacaoController();
$simulacoes = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Histórico de Alocações Realizadas</h2>
            <p class="text-muted mb-0">Consulte, revise ou duplique simulações de carga gravadas anteriormente.</p>
        </div>
        <a href="simulacao.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Nova Simulação
        </a>
    </div>

    <!-- Tabela do Histórico Completo -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código / Data</th>
                            <th>Operador</th>
                            <th>Veículo</th>
                            <th>Peso Total</th>
                            <th>Volume Total</th>
                            <th>Ocupação Peso</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($simulacoes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Nenhuma simulação no histórico.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($simulacoes as $s): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($s['codigo_simulacao']) ?></div>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></small>
                                    </td>
                                    <td><span class="small fw-semibold"><?= htmlspecialchars($s['usuario_nome'] ?? 'Operador') ?></span></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($s['veiculo_tipo']) ?> px-2 py-1 me-1">
                                            <?= htmlspecialchars($s['veiculo_tipo']) ?>
                                        </span>
                                        <small class="fw-bold text-dark"><?= htmlspecialchars($s['veiculo_nome']) ?></small>
                                    </td>
                                    <td><?= number_format($s['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                    <td class="text-purple" style="color: #8b5cf6;"><?= number_format($s['volume_total_m3'], 2, ',', '.') ?> m³</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                <div class="progress-bar bg-<?= $s['ocupacao_peso_pct'] > 95 ? 'danger' : 'primary' ?>" 
                                                     style="width: <?= min(100, $s['ocupacao_peso_pct']) ?>%"></div>
                                            </div>
                                            <small class="fw-bold"><?= $s['ocupacao_peso_pct'] ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'aprovado'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Aprovado</span>
                                        <?php elseif ($s['status'] === 'aprovado_com_alerta'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Atenção</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Reprovado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="resultado_simulacao.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-light border me-1">
                                            <i class="fa-solid fa-eye text-primary"></i> Ver
                                        </a>
                                        <button class="btn btn-sm btn-light border text-danger" onclick="excluirSimulacao(<?= $s['id'] ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
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
async function excluirSimulacao(id) {
    if (!confirm('Deseja excluir este registro de simulação?')) return;
    const formData = new FormData();
    formData.append('id', id);

    try {
        const resp = await fetch('../api/excluir_simulacao.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao excluir simulação.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
