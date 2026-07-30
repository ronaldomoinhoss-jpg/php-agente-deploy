<?php
$pageTitle = 'Rotas Operacionais';
require_once __DIR__ . '/../controllers/RotaController.php';
require_once __DIR__ . '/../models/BaseOperacional.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new RotaController();
$baseModel = new BaseOperacional();
$mensagem = null;
$erro = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $basesSequenciadas = [];
        $baseSeq = $_POST['base_seq'] ?? [];
        foreach ($baseSeq as $baseId => $seq) {
            $baseId = (int) $baseId;
            $seq = (int) $seq;
            if ($baseId > 0 && $seq > 0) {
                $basesSequenciadas[$seq] = $baseId;
            }
        }
        ksort($basesSequenciadas);
        $_POST['bases'] = array_values($basesSequenciadas);

        $controller->salvar($_POST);
        $mensagem = 'Rota salva com sucesso.';
    }

    if (isset($_GET['delete'])) {
        $controller->excluir((int) $_GET['delete']);
        $mensagem = 'Rota removida com sucesso.';
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$editando = !empty($_GET['edit']) ? $controller->buscar((int) $_GET['edit']) : null;
$rotas = $controller->listar();
$bases = $baseModel->listarTodas();
$baseMap = [];
foreach ($bases as $base) {
    $baseMap[(int) $base['id']] = $base;
}
$basesSelecionadas = [];
if ($editando && !empty($editando['bases'])) {
    foreach ($editando['bases'] as $item) {
        $basesSelecionadas[(int) $item['base_id']] = (int) $item['sequencia'];
    }
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Rotas operacionais</h2>
            <p class="text-muted mb-0">Defina a sequência das bases que compõem cada rota de abastecimento.</p>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0"><?= $editando ? 'Editar rota' : 'Nova rota' ?></h5></div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">CÓDIGO</label>
                                <input type="text" name="codigo" class="form-control bg-light" value="<?= htmlspecialchars($editando['codigo'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">DATA PLANEJADA</label>
                                <input type="date" name="data_planejada" class="form-control bg-light" value="<?= htmlspecialchars($editando['data_planejada'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">DESCRIÇÃO</label>
                                <input type="text" name="descricao" class="form-control bg-light" value="<?= htmlspecialchars($editando['descricao'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">BASE DE ORIGEM</label>
                                <select name="origem_base_id" class="form-select bg-light">
                                    <option value="">Selecione</option>
                                    <?php foreach ($bases as $base): ?>
                                        <option value="<?= (int) $base['id'] ?>" <?= (int) ($editando['origem_base_id'] ?? 0) === (int) $base['id'] ? 'selected' : '' ?>><?= htmlspecialchars($base['codigo']) ?> - <?= htmlspecialchars($base['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">STATUS</label>
                                <select name="status" class="form-select bg-light">
                                    <?php foreach (['planejada' => 'Planejada', 'ativa' => 'Ativa', 'encerrada' => 'Encerrada', 'cancelada' => 'Cancelada'] as $valor => $label): ?>
                                        <option value="<?= $valor ?>" <?= ($editando['status'] ?? 'planejada') === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">SEQUÊNCIA DE BASES</label>
                                <div class="border rounded-3 p-3 bg-light">
                                    <?php foreach ($bases as $base): ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <div class="flex-grow-1"><?= htmlspecialchars($base['codigo']) ?> - <?= htmlspecialchars($base['nome']) ?></div>
                                            <input type="number" min="0" class="form-control bg-white" name="base_seq[<?= (int) $base['id'] ?>]" value="<?= htmlspecialchars((string) ($basesSelecionadas[(int) $base['id']] ?? '')) ?>" placeholder="Seq." style="max-width: 100px;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Preencha apenas as bases que pertencem à rota, usando sequência 1, 2, 3...</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">OBSERVAÇÕES</label>
                                <textarea name="observacoes" class="form-control bg-light" rows="3"><?= htmlspecialchars($editando['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary flex-fill">Salvar</button>
                            <a href="rotas.php" class="btn btn-outline-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Rotas cadastradas</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Código</th>
                                    <th>Descrição</th>
                                    <th>Data</th>
                                    <th>Bases</th>
                                    <th class="pe-4 text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rotas)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Nenhuma rota cadastrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rotas as $rota): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($rota['codigo']) ?></td>
                                            <td><?= htmlspecialchars($rota['descricao']) ?></td>
                                            <td><?= $rota['data_planejada'] ? date('d/m/Y', strtotime($rota['data_planejada'])) : '-' ?></td>
                                            <td><?= (int) $rota['total_bases'] ?></td>
                                            <td class="pe-4 text-end">
                                                <a href="rotas.php?edit=<?= (int) $rota['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                <a href="rotas.php?delete=<?= (int) $rota['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover esta rota?')">Excluir</a>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
