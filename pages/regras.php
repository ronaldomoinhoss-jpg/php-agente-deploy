<?php
$pageTitle = 'Regras Operacionais';
require_once __DIR__ . '/../controllers/RegraController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$regraController = new RegraController();
$materialController = new MaterialController();
$regras = $regraController->listar();
$materiais = $materialController->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Regras operacionais e de empilhamento</h2>
            <p class="text-muted mb-0">Controle restrições bloqueantes e alertas para base, lastro, separação e amarração.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegra" onclick="resetRegraForm()"><i class="fa-solid fa-plus me-1"></i>Nova Regra</button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Origem</th>
                            <th>Tipo regra</th>
                            <th>Destino</th>
                            <th>Severidade</th>
                            <th>Justificativa</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regras as $regra): ?>
                            <tr>
                                <td class="ps-4"><?= htmlspecialchars($regra['origem_codigo'] ?: ($regra['categoria_origem'] ?: 'Todos')) ?></td>
                                <td><span class="fw-semibold"><?= htmlspecialchars($regra['tipo_regra']) ?></span></td>
                                <td><?= htmlspecialchars($regra['destino_codigo'] ?: ($regra['categoria_destino'] ?: '-')) ?></td>
                                <td><span class="badge bg-<?= $regra['severidade'] === 'bloqueante' ? 'danger' : 'warning text-dark' ?>"><?= htmlspecialchars($regra['severidade']) ?></span></td>
                                <td><small class="text-muted"><?= htmlspecialchars($regra['justificativa']) ?></small></td>
                                <td><?= $regra['ativo'] ? 'Ativa' : 'Inativa' ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border me-1" onclick='editRegra(<?= json_encode($regra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><i class="fa-solid fa-pen text-primary"></i></button>
                                    <button class="btn btn-sm btn-light border text-danger" onclick="deleteRegra(<?= (int) $regra['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRegra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Regra operacional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="regraForm" onsubmit="saveRegra(event)">
                <input type="hidden" name="id" id="regra_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Material origem</label><select class="form-select bg-light" name="material_origem_id" id="regra_material_origem_id"><option value="">Nenhum</option><?php foreach ($materiais as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['codigo']) ?> - <?= htmlspecialchars($m['descricao']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Categoria origem</label><input class="form-control bg-light" name="categoria_origem" id="regra_categoria_origem"></div>
                        <div class="col-md-6"><label class="form-label">Material destino</label><select class="form-select bg-light" name="material_destino_id" id="regra_material_destino_id"><option value="">Nenhum</option><?php foreach ($materiais as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['codigo']) ?> - <?= htmlspecialchars($m['descricao']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Categoria destino</label><input class="form-control bg-light" name="categoria_destino" id="regra_categoria_destino"></div>
                        <div class="col-md-6"><label class="form-label">Tipo da regra</label><select class="form-select bg-light" name="tipo_regra" id="regra_tipo_regra"><option value="obrigatorio_lastro_1">Obrigatório no lastro 1</option><option value="sem_carga_superior">Sem carga superior</option><option value="nao_sobrepor">Não sobrepor</option><option value="separacao_fisica">Separação física</option><option value="preferir_lastro_1">Preferir lastro 1</option><option value="amarracao_especial">Amarração especial</option><option value="piramidal_bobinas">Piramidal para bobinas</option></select></div>
                        <div class="col-md-6"><label class="form-label">Severidade</label><select class="form-select bg-light" name="severidade" id="regra_severidade"><option value="alerta">Alerta</option><option value="bloqueante">Bloqueante</option></select></div>
                        <div class="col-md-12"><label class="form-label">Justificativa</label><textarea class="form-control bg-light" name="justificativa" id="regra_justificativa" rows="2"></textarea></div>
                        <div class="col-md-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ativo" value="1" id="regra_ativo" checked><label class="form-check-label" for="regra_ativo">Regra ativa</label></div></div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetRegraForm() {
    document.getElementById('regraForm').reset();
    document.getElementById('regra_id').value = '';
}

function editRegra(regra) {
    resetRegraForm();
    Object.keys(regra).forEach((key) => {
        const field = document.getElementById(`regra_${key}`);
        if (!field) return;
        if (field.type === 'checkbox') field.checked = regra[key] == 1;
        else field.value = regra[key] ?? '';
    });
    new bootstrap.Modal(document.getElementById('modalRegra')).show();
}

async function saveRegra(event) {
    event.preventDefault();
    const response = await fetch('../api/salvar_regra.php', { method: 'POST', body: new FormData(document.getElementById('regraForm')) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteRegra(id) {
    if (!confirm('Excluir esta regra?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch('../api/excluir_regra.php', { method: 'POST', body: fd });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

