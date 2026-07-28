<?php
$pageTitle = 'Cadastro de Veículos';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';

$controller = new VeiculoController();
$veiculos = $controller->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Frota de Veículos de Carga</h2>
            <p class="text-muted mb-0">Gerencie caminhões Munck, Truck e Carretas Prancha com suas dimensões e capacidades.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalVeiculo" onclick="limparFormulario()">
            <i class="fa-solid fa-plus me-1"></i> Cadastrar Veículo
        </button>
    </div>

    <!-- Cards de Veículos em Grade -->
    <div class="row g-4 mb-4">
        <?php foreach ($veiculos as $v): ?>
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <span class="badge badge-<?= strtolower($v['tipo']) ?> px-3 py-2 fs-6 rounded-pill">
                            <i class="fa-solid fa-truck-front me-1"></i> <?= htmlspecialchars($v['tipo']) ?>
                        </span>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li>
                                    <button class="dropdown-menu-item dropdown-item" onclick='editarVeiculo(<?= json_encode($v) ?>)'>
                                        <i class="fa-solid fa-pen text-primary me-2"></i> Editar
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-menu-item dropdown-item text-danger" onclick="excluirVeiculo(<?= $v['id'] ?>)">
                                        <i class="fa-solid fa-trash me-2"></i> Excluir
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body px-4 pt-2">
                        <h5 class="fw-bold mb-3"><?= htmlspecialchars($v['nome']) ?></h5>

                        <div class="row g-2 mb-3 bg-light p-3 rounded-3">
                            <div class="col-6">
                                <small class="text-muted d-block">CAPACIDADE PESO</small>
                                <strong class="fs-6 text-dark"><?= number_format($v['capacidade_kg'], 0, ',', '.') ?> kg</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">CAPACIDADE VOLUME</small>
                                <strong class="fs-6 text-dark"><?= number_format($v['capacidade_m3'], 2, ',', '.') ?> m³</strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2 small text-secondary">
                            <span><i class="fa-solid fa-arrows-left-right me-1 text-primary"></i> Comprimento: <strong><?= $v['comprimento_m'] ?>m</strong></span>
                            <span><i class="fa-solid fa-arrows-left-right-to-line me-1 text-info"></i> Largura: <strong><?= $v['largura_m'] ?>m</strong></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 small text-secondary">
                            <span><i class="fa-solid fa-arrows-up-down me-1 text-warning"></i> Altura Útil: <strong><?= $v['altura_m'] ?>m</strong></span>
                            <span><i class="fa-solid fa-layer-group me-1 text-success"></i> Max Lastros: <strong><?= $v['max_lastros'] ?></strong></span>
                        </div>

                        <?php if (!empty($v['observacoes'])): ?>
                            <p class="small text-muted mb-0 bg-white p-2 border rounded">
                                <i class="fa-solid fa-circle-info text-info me-1"></i> <?= htmlspecialchars($v['observacoes']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal para Cadastrar / Editar Veículo -->
<div class="modal fade" id="modalVeiculo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="modalVeiculoTitulo">Cadastrar Veículo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formVeiculo" onsubmit="salvarVeiculo(event)">
                <input type="hidden" name="id" id="veiculo_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">TIPO DE VEÍCULO *</label>
                            <select name="tipo" id="veiculo_tipo" class="form-select bg-light" required>
                                <option value="Munck">Munck</option>
                                <option value="Truck">Truck</option>
                                <option value="Carreta">Carreta</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-secondary">NOME OU IDENTIFICAÇÃO *</label>
                            <input type="text" name="nome" id="veiculo_nome" class="form-control bg-light" placeholder="Ex: Caminhão Munck Operational 12T" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">CAPACIDADE MÁXIMA (KG) *</label>
                            <input type="number" step="0.01" name="capacidade_kg" id="veiculo_capacidade_kg" class="form-control bg-light" placeholder="Ex: 12000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">CAPACIDADE MÁXIMA (M³) *</label>
                            <input type="number" step="0.01" name="capacidade_m3" id="veiculo_capacidade_m3" class="form-control bg-light" placeholder="Ex: 24.50" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">COMPRIMENTO (M) *</label>
                            <input type="number" step="0.01" name="comprimento_m" id="veiculo_comprimento_m" class="form-control bg-light" placeholder="Ex: 6.20" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">LARGURA (M) *</label>
                            <input type="number" step="0.01" name="largura_m" id="veiculo_largura_m" class="form-control bg-light" placeholder="Ex: 2.45" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">ALTURA ÚTIL (M) *</label>
                            <input type="number" step="0.01" name="altura_m" id="veiculo_altura_m" class="form-control bg-light" placeholder="Ex: 1.60" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">MAX LASTROS *</label>
                            <select name="max_lastros" id="veiculo_max_lastros" class="form-select bg-light" required>
                                <option value="1">1 Lastro</option>
                                <option value="2" selected>2 Lastros (Máximo)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">OBSERVAÇÕES OPERACIONAIS</label>
                        <textarea name="observacoes" id="veiculo_observacoes" class="form-control bg-light" rows="2" placeholder="Observações operacionais ou especificações sobre guindaste/lança."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save me-1"></i> Salvar Veículo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function limparFormulario() {
    document.getElementById('formVeiculo').reset();
    document.getElementById('veiculo_id').value = '';
    document.getElementById('modalVeiculoTitulo').innerText = 'Cadastrar Veículo';
}

function editarVeiculo(v) {
    document.getElementById('veiculo_id').value = v.id;
    document.getElementById('veiculo_tipo').value = v.tipo;
    document.getElementById('veiculo_nome').value = v.nome;
    document.getElementById('veiculo_capacidade_kg').value = v.capacidade_kg;
    document.getElementById('veiculo_capacidade_m3').value = v.capacidade_m3;
    document.getElementById('veiculo_comprimento_m').value = v.comprimento_m;
    document.getElementById('veiculo_largura_m').value = v.largura_m;
    document.getElementById('veiculo_altura_m').value = v.altura_m;
    document.getElementById('veiculo_max_lastros').value = v.max_lastros;
    document.getElementById('veiculo_observacoes').value = v.observacoes || '';
    
    document.getElementById('modalVeiculoTitulo').innerText = 'Editar Veículo';
    const modal = new bootstrap.Modal(document.getElementById('modalVeiculo'));
    modal.show();
}

async function salvarVeiculo(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formVeiculo'));

    try {
        const resp = await fetch('../api/salvar_veiculo.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro de comunicação com o servidor.', 'error');
    }
}

async function excluirVeiculo(id) {
    if (!confirm('Deseja realmente excluir este veículo?')) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const resp = await fetch('../api/excluir_veiculo.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao excluir veículo.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
