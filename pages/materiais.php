<?php
$pageTitle = 'Cadastro de Materiais';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$controller = new MaterialController();
$busca = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$materiais = $controller->listar($busca, $categoria);
$categorias = $controller->listarCategorias();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Catálogo técnico de materiais</h2>
            <p class="text-muted mb-0">Controle peso, cubagem, empilhamento e perfil físico dos materiais usados nas bases.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="importar_materiais.php" class="btn btn-outline-primary"><i class="fa-solid fa-file-import me-1"></i>Importar</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMaterial" onclick="resetMaterialForm()"><i class="fa-solid fa-plus me-1"></i>Novo Material</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form class="row g-2">
                <div class="col-md-7">
                    <input type="text" name="q" class="form-control bg-light" placeholder="Buscar por código ou descrição" value="<?= htmlspecialchars($busca) ?>">
                </div>
                <div class="col-md-3">
                    <select name="categoria" class="form-select bg-light">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $categoria === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Formato</th>
                            <th>Peso</th>
                            <th>Dimensões</th>
                            <th>Empilhamento</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materiais)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Nenhum material encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($materiais as $material): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($material['codigo']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($material['descricao']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($material['observacoes']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($material['categoria']) ?></td>
                                    <td><?= htmlspecialchars($material['formato_fisico']) ?></td>
                                    <td><?= number_format($material['peso_unitario_kg'], 2, ',', '.') ?> kg</td>
                                    <td><?= number_format($material['comprimento_m'], 2, ',', '.') ?> x <?= number_format($material['largura_m'], 2, ',', '.') ?> x <?= number_format($material['altura_m'], 2, ',', '.') ?> m</td>
                                    <td>
                                        <span class="badge bg-<?= $material['empilhavel'] ? 'success' : 'secondary' ?><?= $material['empilhavel'] ? '' : '' ?>">
                                            <?= $material['empilhavel'] ? "Até {$material['max_lastros']} lastros / {$material['perfil_empilhamento']}" : 'Somente lastro 1' ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-sm btn-light border me-1" onclick='editMaterial(<?= json_encode($material, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><i class="fa-solid fa-pen text-primary"></i></button>
                                        <button class="btn btn-sm btn-light border text-danger" onclick="deleteMaterial(<?= (int) $material['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
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

<div class="modal fade" id="modalMaterial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold" id="materialModalTitle">Novo material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="materialForm" onsubmit="saveMaterial(event)">
                <input type="hidden" name="id" id="mat_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Código</label><input class="form-control bg-light" name="codigo" id="mat_codigo" required></div>
                        <div class="col-md-6"><label class="form-label">Descrição</label><input class="form-control bg-light" name="descricao" id="mat_descricao" required></div>
                        <div class="col-md-3"><label class="form-label">Categoria</label><input class="form-control bg-light" name="categoria" id="mat_categoria" required></div>
                        <div class="col-md-3"><label class="form-label">Formato</label><select class="form-select bg-light" name="formato_fisico" id="mat_formato_fisico"><option value="caixa">Caixa</option><option value="bobina">Bobina</option><option value="poste">Poste</option><option value="transformador">Transformador</option><option value="palete">Palete</option><option value="outro">Outro</option></select></div>
                        <div class="col-md-3"><label class="form-label">Peso unitário (kg)</label><input type="number" step="0.01" class="form-control bg-light" name="peso_unitario_kg" id="mat_peso_unitario_kg" required></div>
                        <div class="col-md-2"><label class="form-label">Comp. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="comprimento_m" id="mat_comprimento_m" oninput="calcVolume()" required></div>
                        <div class="col-md-2"><label class="form-label">Larg. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="largura_m" id="mat_largura_m" oninput="calcVolume()" required></div>
                        <div class="col-md-2"><label class="form-label">Alt. (m)</label><input type="number" step="0.01" class="form-control bg-light" name="altura_m" id="mat_altura_m" oninput="calcVolume()" required></div>
                        <div class="col-md-3"><label class="form-label">Volume (m³)</label><input type="number" step="0.0001" class="form-control bg-light" name="volume_unitario_m3" id="mat_volume_unitario_m3" readonly></div>
                        <div class="col-md-3"><label class="form-label">Empilhável</label><select class="form-select bg-light" name="empilhavel" id="mat_empilhavel"><option value="1">Sim</option><option value="0">Não</option></select></div>
                        <div class="col-md-3"><label class="form-label">Máx. lastros</label><select class="form-select bg-light" name="max_lastros" id="mat_max_lastros"><option value="1">1</option><option value="2">2</option></select></div>
                        <div class="col-md-3"><label class="form-label">Perfil empilhamento</label><select class="form-select bg-light" name="perfil_empilhamento" id="mat_perfil_empilhamento"><option value="nenhum">Nenhum</option><option value="reto">Reto</option><option value="piramidal">Piramidal</option></select></div>
                        <div class="col-md-3"><label class="form-label">Fragilidade</label><select class="form-select bg-light" name="fragilidade" id="mat_fragilidade"><option value="baixa">Baixa</option><option value="media">Média</option><option value="alta">Alta</option></select></div>
                        <div class="col-md-3"><label class="form-label">Amarração especial</label><select class="form-select bg-light" name="amarracao_especial" id="mat_amarracao_especial"><option value="0">Não</option><option value="1">Sim</option></select></div>
                        <div class="col-md-12"><label class="form-label">Observações</label><textarea class="form-control bg-light" name="observacoes" id="mat_observacoes" rows="2"></textarea></div>
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
function calcVolume() {
    const c = parseFloat(document.getElementById('mat_comprimento_m').value) || 0;
    const l = parseFloat(document.getElementById('mat_largura_m').value) || 0;
    const a = parseFloat(document.getElementById('mat_altura_m').value) || 0;
    document.getElementById('mat_volume_unitario_m3').value = c && l && a ? (c * l * a).toFixed(4) : '';
}

function resetMaterialForm() {
    document.getElementById('materialForm').reset();
    document.getElementById('mat_id').value = '';
    document.getElementById('materialModalTitle').innerText = 'Novo material';
}

function editMaterial(material) {
    resetMaterialForm();
    Object.keys(material).forEach((key) => {
        const field = document.getElementById(`mat_${key}`);
        if (field) field.value = material[key];
    });
    document.getElementById('materialModalTitle').innerText = 'Editar material';
    calcVolume();
    new bootstrap.Modal(document.getElementById('modalMaterial')).show();
}

async function saveMaterial(event) {
    event.preventDefault();
    const formData = new FormData(document.getElementById('materialForm'));
    const response = await fetch('../api/salvar_material.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}

async function deleteMaterial(id) {
    if (!confirm('Excluir este material?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch('../api/excluir_material.php', { method: 'POST', body: fd });
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

