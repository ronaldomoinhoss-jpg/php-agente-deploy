<?php
$pageTitle = 'Relatório Semanal da Frota';
require_once __DIR__ . '/../controllers/PlanejamentoRotaController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new PlanejamentoRotaController();
$dataReferencia = $_GET['data_referencia'] ?? date('Y-m-d');
$relatorio = $controller->listarSemana($dataReferencia);

$agrupado = [];
foreach ($relatorio['linhas'] as $linha) {
    $chave = ($linha['codigo_unidade'] ?: 'SEM-UNIDADE') . '|' . $linha['data_operacao'];
    if (!isset($agrupado[$chave])) {
        $agrupado[$chave] = [
            'data_operacao' => $linha['data_operacao'],
            'codigo_unidade' => $linha['codigo_unidade'] ?: 'Sem unidade',
            'veiculo_nome' => $linha['veiculo_nome'],
            'cargas' => [],
            'peso_total_kg' => 0.0,
            'volume_total_m3' => 0.0,
        ];
    }
    $agrupado[$chave]['cargas'][] = $linha;
    $agrupado[$chave]['peso_total_kg'] += (float) $linha['peso_total_kg'];
    $agrupado[$chave]['volume_total_m3'] += (float) $linha['volume_total_m3'];
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Relatório semanal da frota</h2>
            <p class="text-muted mb-0">Janela de <?= date('d/m/Y', strtotime($relatorio['inicio'])) ?> até <?= date('d/m/Y', strtotime($relatorio['fim'])) ?>.</p>
        </div>
        <form method="get" class="d-flex gap-2">
            <input type="date" name="data_referencia" class="form-control" value="<?= htmlspecialchars($dataReferencia) ?>">
            <button type="submit" class="btn btn-primary">Atualizar</button>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Programação consolidada</h5></div>
        <div class="card-body p-4">
            <?php if (empty($agrupado)): ?>
                <div class="text-center text-muted py-5">Nenhuma carga planejada na semana selecionada.</div>
            <?php else: ?>
                <?php foreach ($agrupado as $bloco): ?>
                    <div class="border rounded-4 p-3 mb-4 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($bloco['codigo_unidade']) ?> - <?= htmlspecialchars($bloco['veiculo_nome']) ?></div>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($bloco['data_operacao'])) ?></small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Peso consolidado</small>
                                <strong><?= number_format($bloco['peso_total_kg'], 2, ',', '.') ?> kg</strong>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Carga</th>
                                        <th>Planejamento</th>
                                        <th>Rota</th>
                                        <th>Bases</th>
                                        <th>Peso</th>
                                        <th>Volume</th>
                                        <th>Ocupação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bloco['cargas'] as $carga): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($carga['codigo_carga']) ?></td>
                                            <td><?= htmlspecialchars($carga['codigo_planejamento']) ?></td>
                                            <td><?= htmlspecialchars($carga['rota_codigo']) ?> - <?= htmlspecialchars($carga['rota_descricao']) ?></td>
                                            <td><?= htmlspecialchars($carga['bases_atendidas']) ?></td>
                                            <td><?= number_format($carga['peso_total_kg'], 2, ',', '.') ?> kg</td>
                                            <td><?= number_format($carga['volume_total_m3'], 4, ',', '.') ?> m³</td>
                                            <td><?= number_format($carga['ocupacao_peso_pct'], 2, ',', '.') ?>% / <?= number_format($carga['ocupacao_volume_pct'], 2, ',', '.') ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
