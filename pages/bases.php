<?php
$pageTitle = 'Bases Operacionais';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new BaseController();
$bases = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Bases de atendimento</h2>
            <p class="text-muted mb-0">Cadastre as bases e a prioridade padrão de entrega para o planejamento operacional.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBase" onclick="resetBaseForm()"><i class="fa-solid fa-plus me-1"></i>Nova Base</button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Nome</th>
                            <th>Endereço</th>
                            <th>Ordem padrão</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bases as $base): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($base['codigo']) ?></td>
                                <td><?= htmlspecialchars($base['nome']) ?></td>
                                <td><?= htmlspecialchars($base['endereco']) ?></td>
                                <td><?= (int) $base['ordem_padrao'] ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border me-1" onclick='editBase(<?= json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><i class="fa-solid fa-pen text-primary"></i></button>
                                    <button class="btn btn-sm btn-light border text-danger" onclick="deleteBase(<?= (int) $base['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="baseModalTitle">Nova base</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="baseForm" onsubmit="saveBase(event)">
                <input type="hidden" name="id" id="base_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5"><label class="form-label">Código</label><input class="form-control bg-light" name="codigo" id="base_codigo" required></div>
                        <div class="col-md-7"><label class="form-label">Nome</label><input class="form-control bg-light" name="nome" id="base_nome" required></div>
                        <div class="col-md-8"><label class="form-label">Endereço</label><input class="form-control bg-light" name="endereco" id="base_endereco"></div>
                        <div class="col-md-4"><label class="form-label">Ordem padrão</label><input type="number" min="1" class="form-control bg-light" name="ordem_padrao" id="base_ordem_padrao" value="1" required></div>
                        <div class="col-md-12"><label class="form-label">Observações</label><textarea class="form-control bg-light" name="observacoes" id="base_observacoes" rows="2"></textarea></div>
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
function resetBaseForm() {
    document.getElementById('baseForm').reset();
    document.getElementById('base_id').value = '';
    document.getElementById('baseModalTitle').innerText = 'Nova base';
}

function editBase(base) {
    resetBaseForm();
    Object.keys(base).forEach((key) => {
        const field = document.getElementById(`base_${key}`);
        if (field) field.value = base[key];
    });
    document.getElementById('baseModalTitle').innerText = 'Editar base';
    new bootstrap.Modal(document.getElementById('modalBase')).show();
}

async function saveBase(event) {
    event.preventDefault();
    const response = await fetch('../api/salvar_base.php', { method: 'POST', body: new FormData(document.getElementById('baseForm')) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteBase(id) {
    if (!confirm('Excluir esta base?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch('../api/excluir_base.php', { method: 'POST', body: fd });
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

