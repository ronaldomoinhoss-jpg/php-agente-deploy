<?php
$pageTitle = 'Cadastro de Veículos';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new VeiculoController();
$veiculos = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Frota candidata</h2>
            <p class="text-muted mb-0">Cadastre carreta, truck, munck e outros veículos com acesso de descarga e disponibilidade.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVeiculo" onclick="resetVeiculoForm()"><i class="fa-solid fa-plus me-1"></i>Novo Veículo</button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Tipo</th>
                            <th>Nome</th>
                            <th>Capacidade</th>
                            <th>Envelope útil</th>
                            <th>Acesso</th>
                            <th>Disponível</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($veiculo['tipo']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($veiculo['nome']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($veiculo['observacoes']) ?></small>
                                </td>
                                <td><?= number_format($veiculo['capacidade_kg'], 0, ',', '.') ?> kg / <?= number_format($veiculo['capacidade_m3'], 2, ',', '.') ?> m³</td>
                                <td><?= number_format($veiculo['comprimento_m'], 2, ',', '.') ?> x <?= number_format($veiculo['largura_m'], 2, ',', '.') ?> x <?= number_format($veiculo['altura_m'], 2, ',', '.') ?> m</td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($veiculo['acesso_descarga']) ?></span></td>
                                <td><?= (int) $veiculo['quantidade_disponivel'] ?></td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-light border me-1" onclick='editVeiculo(<?= json_encode($veiculo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><i class="fa-solid fa-pen text-primary"></i></button>
                                    <button class="btn btn-sm btn-light border text-danger" onclick="deleteVeiculo(<?= (int) $veiculo['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVeiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="veiculoModalTitle">Novo veículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="veiculoForm" onsubmit="saveVeiculo(event)">
                <input type="hidden" name="id" id="vei_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Tipo</label><input class="form-control bg-light" name="tipo" id="vei_tipo" required></div>
                        <div class="col-md-9"><label class="form-label">Nome</label><input class="form-control bg-light" name="nome" id="vei_nome" required></div>
                        <div class="col-md-3"><label class="form-label">Capacidade kg</label><input type="number" step="0.01" class="form-control bg-light" name="capacidade_kg" id="vei_capacidade_kg" required></div>
                        <div class="col-md-3"><label class="form-label">Capacidade m³</label><input type="number" step="0.01" class="form-control bg-light" name="capacidade_m3" id="vei_capacidade_m3" required></div>
                        <div class="col-md-2"><label class="form-label">Comp. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="comprimento_m" id="vei_comprimento_m" required></div>
                        <div class="col-md-2"><label class="form-label">Larg. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="largura_m" id="vei_largura_m" required></div>
                        <div class="col-md-2"><label class="form-label">Alt. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="altura_m" id="vei_altura_m" required></div>
                        <div class="col-md-3"><label class="form-label">Máx. lastros</label><select class="form-select bg-light" name="max_lastros" id="vei_max_lastros"><option value="1">1</option><option value="2">2</option></select></div>
                        <div class="col-md-3"><label class="form-label">Acesso descarga</label><select class="form-select bg-light" name="acesso_descarga" id="vei_acesso_descarga"><option value="traseira">Traseira</option><option value="lateral">Lateral</option><option value="misto">Misto</option></select></div>
                        <div class="col-md-3"><label class="form-label">Qtde. disponível</label><input type="number" class="form-control bg-light" name="quantidade_disponivel" id="vei_quantidade_disponivel" min="1" value="1" required></div>
                        <div class="col-md-12"><label class="form-label">Observações</label><textarea class="form-control bg-light" name="observacoes" id="vei_observacoes" rows="2"></textarea></div>
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
function resetVeiculoForm() {
    document.getElementById('veiculoForm').reset();
    document.getElementById('vei_id').value = '';
    document.getElementById('veiculoModalTitle').innerText = 'Novo veículo';
}

function editVeiculo(veiculo) {
    resetVeiculoForm();
    Object.keys(veiculo).forEach((key) => {
        const field = document.getElementById(`vei_${key}`);
        if (field) field.value = veiculo[key];
    });
    document.getElementById('veiculoModalTitle').innerText = 'Editar veículo';
    new bootstrap.Modal(document.getElementById('modalVeiculo')).show();
}

async function saveVeiculo(event) {
    event.preventDefault();
    const response = await fetch('../api/salvar_veiculo.php', { method: 'POST', body: new FormData(document.getElementById('veiculoForm')) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteVeiculo(id) {
    if (!confirm('Excluir este veículo?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch('../api/excluir_veiculo.php', { method: 'POST', body: fd });
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

