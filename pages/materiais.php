<?php
$pageTitle = 'Cadastro de Materiais';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

$busca = $_GET['q'] ?? '';
$tipoFiltro = $_GET['tipo'] ?? '';

$controller = new MaterialController();
$materiais = $controller->listar($busca, $tipoFiltro);
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Catálogo de Materiais de Distribuição</h2>
            <p class="text-muted mb-0">Cadastre e gerencie condutores, bobinas, transformadores, postes, isoladores e ferragens.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="importar_materiais.php" class="btn btn-outline-primary">
                <i class="fa-solid fa-file-csv me-1"></i> Importar CSV
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMaterial" onclick="limparFormularioMaterial()">
                <i class="fa-solid fa-plus me-1"></i> Cadastrar Material
            </button>
        </div>
    </div>

    <!-- Barra de Pesquisa e Filtros -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="materiais.php" class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                        <input type="text" name="q" class="form-control bg-light border-start-0" placeholder="Buscar por código ou descrição..." value="<?= htmlspecialchars($busca) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="tipo" class="form-select bg-light" onchange="this.form.submit()">
                        <option value="">Todas as Categorias</option>
                        <option value="bobina_cabo" <?= $tipoFiltro == 'bobina_cabo' ? 'selected' : '' ?>>Bobina de Cabo</option>
                        <option value="transformador" <?= $tipoFiltro == 'transformador' ? 'selected' : '' ?>>Transformador</option>
                        <option value="poste" <?= $tipoFiltro == 'poste' ? 'selected' : '' ?>>Poste de Concreto/Madeira</option>
                        <option value="chave" <?= $tipoFiltro == 'chave' ? 'selected' : '' ?>>Chave Seccionadora / Fusível</option>
                        <option value="isolador" <?= $tipoFiltro == 'isolador' ? 'selected' : '' ?>>Isolador Polimérico / Porcelana</option>
                        <option value="caixa" <?= $tipoFiltro == 'caixa' ? 'selected' : '' ?>>Caixa de Medição / Palete</option>
                        <option value="ferragem" <?= $tipoFiltro == 'ferragem' ? 'selected' : '' ?>>Ferragens & Armações</option>
                        <option value="outro" <?= $tipoFiltro == 'outro' ? 'selected' : '' ?>>Outros / Peças Avulsas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Materiais -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Peso Unit. (kg)</th>
                            <th>Dimensões (C x L x A)</th>
                            <th>Vol. Unit. (m³)</th>
                            <th>Empilhavel</th>
                            <th>Fragilidade</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materiais)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    Nenhum material encontrado com os filtros selecionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materiais as $m): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($m['codigo']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($m['descricao']) ?></div>
                                        <?php if (!empty($m['observacoes'])): ?>
                                            <small class="text-muted"><i class="fa-solid fa-info-circle me-1"></i><?= htmlspecialchars($m['observacoes']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <?= str_replace('_', ' ', strtoupper($m['tipo'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold"><?= number_format($m['peso_unitario_kg'], 2, ',', '.') ?> kg</td>
                                    <td class="small text-secondary">
                                        <?= number_format($m['comprimento_m'], 2, ',', '.') ?>m x <?= number_format($m['largura_m'], 2, ',', '.') ?>m x <?= number_format($m['altura_m'], 2, ',', '.') ?>m
                                    </td>
                                    <td class="fw-bold text-purple" style="color: #8b5cf6;"><?= number_format($m['volume_unitario_m3'], 4, ',', '.') ?> m³</td>
                                    <td>
                                        <?php if ($m['permite_empilhamento'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-check me-1"></i> Sim (Max <?= $m['max_lastros'] ?>L)</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-ban me-1"></i> Não (1º Lastro)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['fragilidade'] === 'alta'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Alta</span>
                                        <?php elseif ($m['fragilidade'] === 'media'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Média</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Baixa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-sm btn-light border me-1" onclick='editarMaterial(<?= json_encode($m) ?>)'>
                                            <i class="fa-solid fa-pen text-primary"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light border text-danger" onclick="excluirMaterial(<?= $m['id'] ?>)">
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

<!-- Modal para Cadastrar / Editar Material -->
<div class="modal fade" id="modalMaterial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="modalMaterialTitulo">Cadastrar Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formMaterial" onsubmit="salvarMaterial(event)">
                <input type="hidden" name="id" id="mat_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">CÓDIGO DO MATERIAL *</label>
                            <input type="text" name="codigo" id="mat_codigo" class="form-control bg-light" placeholder="Ex: BOB-CAB-120" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-secondary">DESCRIÇÃO DETALHADA *</label>
                            <input type="text" name="descricao" id="mat_descricao" class="form-control bg-light" placeholder="Ex: Bobina de Cabo Alumínio 120mm²" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">CATEGORIA *</label>
                            <select name="tipo" id="mat_tipo" class="form-select bg-light" required>
                                <option value="bobina_cabo">Bobina de Cabo</option>
                                <option value="transformador">Transformador</option>
                                <option value="poste">Poste</option>
                                <option value="chave">Chave Seccionadora</option>
                                <option value="isolador">Isolador</option>
                                <option value="caixa">Caixa / Palete</option>
                                <option value="ferragem">Ferragem</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">PESO UNIT. (KG) *</label>
                            <input type="number" step="0.01" name="peso_unitario_kg" id="mat_peso_unitario_kg" class="form-control bg-light" placeholder="Ex: 850.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">COMPRIMENTO (M) *</label>
                            <input type="number" step="0.01" name="comprimento_m" id="mat_comprimento_m" class="form-control bg-light" placeholder="Ex: 1.40" oninput="calcularVolumeAuto()" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">LARGURA (M) *</label>
                            <input type="number" step="0.01" name="largura_m" id="mat_largura_m" class="form-control bg-light" placeholder="Ex: 1.40" oninput="calcularVolumeAuto()" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-secondary">ALTURA (M) *</label>
                            <input type="number" step="0.01" name="altura_m" id="mat_altura_m" class="form-control bg-light" placeholder="Ex: 1.10" oninput="calcularVolumeAuto()" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">VOLUME UNITÁRIO (M³) (AUTO)</label>
                            <input type="number" step="0.0001" name="volume_unitario_m3" id="mat_volume_unitario_m3" class="form-control bg-light fw-bold text-purple" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">PERMITE EMPILHAMENTO? *</label>
                            <select name="permite_empilhamento" id="mat_permite_empilhamento" class="form-select bg-light" required>
                                <option value="1">Sim (Até 2 Lastros)</option>
                                <option value="0">Não (Apenas Piso/Lastro 1)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-secondary">FRAGILIDADE *</label>
                            <select name="fragilidade" id="mat_fragilidade" class="form-select bg-light" required>
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta (Sensível)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">OBSERVAÇÕES E REGRAS ESPECÍFICAS</label>
                        <textarea name="observacoes" id="mat_observacoes" class="form-control bg-light" rows="2" placeholder="Instruções de amarração, travas ou empilhamento piramidal."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save me-1"></i> Salvar Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calcularVolumeAuto() {
    const c = parseFloat(document.getElementById('mat_comprimento_m').value) || 0;
    const l = parseFloat(document.getElementById('mat_largura_m').value) || 0;
    const a = parseFloat(document.getElementById('mat_altura_m').value) || 0;
    if (c > 0 && l > 0 && a > 0) {
        document.getElementById('mat_volume_unitario_m3').value = (c * l * a).toFixed(4);
    }
}

function limparFormularioMaterial() {
    document.getElementById('formMaterial').reset();
    document.getElementById('mat_id').value = '';
    document.getElementById('modalMaterialTitulo').innerText = 'Cadastrar Material';
}

function editarMaterial(m) {
    document.getElementById('mat_id').value = m.id;
    document.getElementById('mat_codigo').value = m.codigo;
    document.getElementById('mat_descricao').value = m.descricao;
    document.getElementById('mat_tipo').value = m.tipo;
    document.getElementById('mat_peso_unitario_kg').value = m.peso_unitario_kg;
    document.getElementById('mat_comprimento_m').value = m.comprimento_m;
    document.getElementById('mat_largura_m').value = m.largura_m;
    document.getElementById('mat_altura_m').value = m.altura_m;
    document.getElementById('mat_volume_unitario_m3').value = m.volume_unitario_m3;
    document.getElementById('mat_permite_empilhamento').value = m.permite_empilhamento;
    document.getElementById('mat_fragilidade').value = m.fragilidade;
    document.getElementById('mat_observacoes').value = m.observacoes || '';

    document.getElementById('modalMaterialTitulo').innerText = 'Editar Material';
    const modal = new bootstrap.Modal(document.getElementById('modalMaterial'));
    modal.show();
}

async function salvarMaterial(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formMaterial'));

    try {
        const resp = await fetch('../api/salvar_material.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao salvar material.', 'error');
    }
}

async function excluirMaterial(id) {
    if (!confirm('Deseja realmente excluir este material?')) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const resp = await fetch('../api/excluir_material.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao excluir material.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
