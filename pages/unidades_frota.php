<?php
$pageTitle = 'Unidades de Frota';
require_once __DIR__ . '/../controllers/UnidadeVeiculoController.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new UnidadeVeiculoController();
$veiculoController = new VeiculoController();
$mensagem = null;
$erro = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->salvar($_POST);
        $mensagem = 'Unidade de frota salva com sucesso.';
    }

    if (isset($_GET['delete'])) {
        $controller->excluir((int) $_GET['delete']);
        $mensagem = 'Unidade de frota removida com sucesso.';
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

$editando = !empty($_GET['edit']) ? $controller->buscar((int) $_GET['edit']) : null;
$unidades = $controller->listar();
$veiculos = $veiculoController->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Unidades de frota</h2>
            <p class="text-muted mb-0">Cadastre as unidades operacionais que podem ser reservadas no planejamento por rota.</p>
        </div>
    </div>

    <?php if ($mensagem): ?><div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0"><?= $editando ? 'Editar unidade' : 'Nova unidade' ?></h5></div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">TIPO DE VEÍCULO</label>
                            <select name="veiculo_id" class="form-select bg-light" required>
                                <option value="">Selecione</option>
                                <?php foreach ($veiculos as $veiculo): ?>
                                    <option value="<?= (int) $veiculo['id'] ?>" <?= (int) ($editando['veiculo_id'] ?? 0) === (int) $veiculo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($veiculo['tipo']) ?> - <?= htmlspecialchars($veiculo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">CÓDIGO DA UNIDADE</label>
                            <input type="text" name="codigo_unidade" class="form-control bg-light" value="<?= htmlspecialchars($editando['codigo_unidade'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">STATUS</label>
                            <select name="status_operacional" class="form-select bg-light">
                                <?php foreach (['disponivel' => 'Disponível', 'manutencao' => 'Manutenção', 'indisponivel' => 'Indisponível'] as $valor => $label): ?>
                                    <option value="<?= $valor ?>" <?= ($editando['status_operacional'] ?? 'disponivel') === $valor ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo" <?= !isset($editando['ativo']) || (int) $editando['ativo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Unidade ativa no planejamento</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">OBSERVAÇÕES</label>
                            <textarea name="observacoes" class="form-control bg-light" rows="3"><?= htmlspecialchars($editando['observacoes'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">Salvar</button>
                            <a href="unidades_frota.php" class="btn btn-outline-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-bold mb-0">Unidades cadastradas</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Código</th>
                                    <th>Veículo</th>
                                    <th>Status</th>
                                    <th>Ativa</th>
                                    <th class="pe-4 text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($unidades)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Nenhuma unidade cadastrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($unidades as $unidade): ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold"><?= htmlspecialchars($unidade['codigo_unidade']) ?></td>
                                            <td><?= htmlspecialchars($unidade['veiculo_tipo']) ?> - <?= htmlspecialchars($unidade['veiculo_nome']) ?></td>
                                            <td><?= htmlspecialchars($unidade['status_operacional']) ?></td>
                                            <td><?= (int) $unidade['ativo'] === 1 ? 'Sim' : 'Não' ?></td>
                                            <td class="pe-4 text-end">
                                                <a href="unidades_frota.php?edit=<?= (int) $unidade['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                                <a href="unidades_frota.php?delete=<?= (int) $unidade['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remover esta unidade?')">Excluir</a>
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
