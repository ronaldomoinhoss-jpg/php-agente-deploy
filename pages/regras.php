<?php
$pageTitle = 'Regras de Empilhamento e Restrições';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/RegraController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

$regraController = new RegraController();
$regras = $regraController->listar();

$materialController = new MaterialController();
$materiais = $materialController->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Painel de Regras de Segurança Operacional</h2>
            <p class="text-muted mb-0">Configure restrições de sobreposição, incompatibilidade e obrigatoriedades para cada tipo de material.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRegra" onclick="limparFormularioRegra()">
            <i class="fa-solid fa-shield-plus me-1"></i> Nova Regra de Restrição
        </button>
    </div>

    <!-- Tabela de Regras -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Origem / Categoria</th>
                            <th>Regra Configurada</th>
                            <th>Destino / Alvo</th>
                            <th>Prioridade</th>
                            <th>Justificativa Operacional</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($regras)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Nenhuma regra cadastrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($regras as $r): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-secondary">#<?= $r['id'] ?></td>
                                    <td>
                                        <?php if (!empty($r['origem_codigo'])): ?>
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($r['origem_codigo']) ?></span>
                                        <?php elseif (!empty($r['tipo_material_origem'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= strtoupper($r['tipo_material_origem']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Todos os Materiais</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-dark">
                                            <?php
                                            $tiposNomes = [
                                                'nao_sobrepor' => 'Não ficar em cima de',
                                                'nao_submeter' => 'Não ficar embaixo de',
                                                'nao_lado_a_lado' => 'Não ficar ao lado de',
                                                'obrigatorio_lastro_1' => 'Obrigatório no 1º Lastro (Piso)',
                                                'obrigatorio_ultimo_lastro' => 'Obrigatório no Último Lastro',
                                                'incompativel_transporte' => 'Não transportar junto com',
                                                'proximo_lateral' => 'Ficar próximo da lateral',
                                                'centralizado' => 'Ficar centralizado',
                                                'amarracao_especial' => 'Exige amarração especial',
                                                'separacao_fisica' => 'Exige separação física',
                                                'piramidal_bobinas' => 'Empilhamento Piramidal (Bobinas)'
                                            ];
                                            echo $tiposNomes[$r['tipo_regra']] ?? $r['tipo_regra'];
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['destino_codigo'])): ?>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($r['destino_codigo']) ?></span>
                                        <?php elseif (!empty($r['tipo_material_destino'])): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border"><?= strtoupper($r['tipo_material_destino']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['prioridade'] === 'bloqueante'): ?>
                                            <span class="badge bg-danger text-white px-2 py-1"><i class="fa-solid fa-lock me-1"></i> Bloqueante</span>
                                        <?php elseif ($r['prioridade'] === 'alta'): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Alta</span>
                                        <?php elseif ($r['prioridade'] === 'media'): ?>
                                            <span class="badge bg-info text-white px-2 py-1">Média</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border">Baixa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($r['justificativa'] ?? 'Sem justificativa') ?></small></td>
                                    <td>
                                        <?php if ($r['ativo'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-muted border">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-sm btn-light border text-danger" onclick="excluirRegra(<?= $r['id'] ?>)">
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

<!-- Modal para Cadastrar Regra -->
<div class="modal fade" id="modalRegra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Cadastrar Regra de Restrição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formRegra" onsubmit="salvarRegra(event)">
                <input type="hidden" name="id" id="regra_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">MATERIAL ORIGEM (ESPECÍFICO)</label>
                            <select name="material_origem_id" id="regra_material_origem_id" class="form-select bg-light">
                                <option value="">Nenhum (Usar Categoria)</option>
                                <?php foreach ($materiais as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= $m['codigo'] ?> - <?= $m['descricao'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">OU CATEGORIA ORIGEM</label>
                            <select name="tipo_material_origem" id="regra_tipo_material_origem" class="form-select bg-light">
                                <option value="">Todas as Categorias</option>
                                <option value="bobina_cabo">Bobina de Cabo</option>
                                <option value="transformador">Transformador</option>
                                <option value="poste">Poste</option>
                                <option value="chave">Chave Seccionadora</option>
                                <option value="isolador">Isolador</option>
                                <option value="caixa">Caixa / Palete</option>
                                <option value="ferragem">Ferragem</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">TIPO DA REGRA *</label>
                            <select name="tipo_regra" id="regra_tipo_regra" class="form-select bg-light" required>
                                <option value="nao_sobrepor">Item A não pode ficar em cima do Item B</option>
                                <option value="nao_submeter">Item A não pode ficar embaixo do Item B</option>
                                <option value="nao_lado_a_lado">Item A não pode ficar ao lado do Item B</option>
                                <option value="obrigatorio_lastro_1">Item A deve ficar no 1º Lastro (Piso)</option>
                                <option value="obrigatorio_ultimo_lastro">Item A deve ficar no Último Lastro</option>
                                <option value="incompativel_transporte">Item A não pode ser transportado com Item B</option>
                                <option value="proximo_lateral">Item A deve ficar próximo da lateral</option>
                                <option value="centralizado">Item A deve ficar centralizado</option>
                                <option value="amarracao_especial">Item A exige amarração especial</option>
                                <option value="separacao_fisica">Item A exige separação física</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">PRIORIDADE *</label>
                            <select name="prioridade" id="regra_prioridade" class="form-select bg-light" required>
                                <option value="bloqueante">Bloqueante (Impede simulação)</option>
                                <option value="alta" selected>Alta (Alerta Amarelo)</option>
                                <option value="media">Média</option>
                                <option value="baixa">Baixa</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">MATERIAL DESTINO (SE APLICÁVEL)</label>
                            <select name="material_destino_id" id="regra_material_destino_id" class="form-select bg-light">
                                <option value="">Nenhum (Usar Categoria)</option>
                                <?php foreach ($materiais as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= $m['codigo'] ?> - <?= $m['descricao'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary">OU CATEGORIA DESTINO</label>
                            <select name="tipo_material_destino" id="regra_tipo_material_destino" class="form-select bg-light">
                                <option value="">Nenhuma</option>
                                <option value="bobina_cabo">Bobina de Cabo</option>
                                <option value="transformador">Transformador</option>
                                <option value="poste">Poste</option>
                                <option value="chave">Chave Seccionadora</option>
                                <option value="isolador">Isolador</option>
                                <option value="caixa">Caixa / Palete</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">JUSTIFICATIVA E MOTIVO OPERACIONAL</label>
                        <textarea name="justificativa" id="regra_justificativa" class="form-control bg-light" rows="2" placeholder="Ex: Evitar tombamento ou danos às buchas de porcelana durante o transporte."></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ativo" value="1" id="regra_ativo" checked>
                        <label class="form-check-label fw-bold small" for="regra_ativo">Regra Ativa no Sistema</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-save me-1"></i> Salvar Regra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function limparFormularioRegra() {
    document.getElementById('formRegra').reset();
    document.getElementById('regra_id').value = '';
}

async function salvarRegra(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formRegra'));

    try {
        const resp = await fetch('../api/salvar_regra.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao salvar regra.', 'error');
    }
}

async function excluirRegra(id) {
    if (!confirm('Deseja realmente excluir esta regra?')) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const resp = await fetch('../api/excluir_regra.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao excluir regra.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
