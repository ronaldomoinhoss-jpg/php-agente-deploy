<?php
$pageTitle = 'Pedidos de Carga';
require_once __DIR__ . '/../controllers/PedidoCargaController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$pedidoController = new PedidoCargaController();
$materialController = new MaterialController();
$baseController = new BaseController();

$pedidos = $pedidoController->listar();
$materiais = $materialController->listar();
$bases = $baseController->listar();
$pedidosDetalhados = [];
foreach ($pedidos as $pedidoResumo) {
    $pedidosDetalhados[$pedidoResumo['id']] = $pedidoController->buscar((int) $pedidoResumo['id']);
}
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Pedidos de carga multi-base</h2>
            <p class="text-muted mb-0">Monte o pedido por base, defina a ordem de entrega e use a lista na simulação da frota.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="importar_materiais.php" class="btn btn-outline-primary"><i class="fa-solid fa-file-import me-1"></i>Importar Pedido</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPedido" onclick="resetPedidoForm()"><i class="fa-solid fa-plus me-1"></i>Novo Pedido</button>
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
                            <th>Status</th>
                            <th>Linhas</th>
                            <th>Unidades</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum pedido cadastrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($pedido['codigo_pedido']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($pedido['descricao']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($pedido['observacoes']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($pedido['status']) ?></td>
                                    <td><?= (int) $pedido['linhas'] ?></td>
                                    <td><?= (int) $pedido['unidades'] ?></td>
                                    <td class="pe-4 text-end">
                                        <button class="btn btn-sm btn-light border me-1" onclick='editPedido(<?= json_encode($pedidosDetalhados[$pedido["id"]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'><i class="fa-solid fa-pen text-primary"></i></button>
                                        <a href="simulacao.php?pedido_id=<?= (int) $pedido['id'] ?>" class="btn btn-sm btn-outline-success me-1"><i class="fa-solid fa-cube"></i></a>
                                        <button class="btn btn-sm btn-light border text-danger" onclick="deletePedido(<?= (int) $pedido['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
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

<div class="modal fade" id="modalPedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold" id="pedidoModalTitle">Novo pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pedidoForm" onsubmit="savePedido(event)">
                <input type="hidden" name="id" id="pedido_id">
                <input type="hidden" name="itens" id="pedido_itens_json">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><label class="form-label">Código</label><input class="form-control bg-light" name="codigo_pedido" id="pedido_codigo_pedido" placeholder="Auto"></div>
                        <div class="col-md-6"><label class="form-label">Descrição</label><input class="form-control bg-light" name="descricao" id="pedido_descricao" required></div>
                        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select bg-light" name="status" id="pedido_status"><option value="rascunho">Rascunho</option><option value="aberto">Aberto</option><option value="fechado">Fechado</option></select></div>
                        <div class="col-md-12"><label class="form-label">Observações</label><textarea class="form-control bg-light" name="observacoes" id="pedido_observacoes" rows="2"></textarea></div>
                    </div>

                    <div class="card border-0 bg-light rounded-4 mb-3">
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4"><label class="form-label small">Material</label><select id="item_material_id" class="form-select bg-white"><option value="">Selecione</option><?php foreach ($materiais as $m): ?><option value="<?= $m['id'] ?>" data-json='<?= json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>'><?= htmlspecialchars($m['codigo']) ?> - <?= htmlspecialchars($m['descricao']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-3"><label class="form-label small">Base destino</label><select id="item_base_id" class="form-select bg-white"><option value="">Selecione</option><?php foreach ($bases as $b): ?><option value="<?= $b['id'] ?>" data-json='<?= json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>'><?= htmlspecialchars($b['codigo']) ?> - <?= htmlspecialchars($b['nome']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-2"><label class="form-label small">Quantidade</label><input type="number" min="1" id="item_quantidade" class="form-control bg-white" value="1"></div>
                                <div class="col-md-2"><label class="form-label small">Ordem entrega</label><input type="number" min="1" id="item_ordem_entrega" class="form-control bg-white" value="1"></div>
                                <div class="col-md-1"><button type="button" class="btn btn-primary w-100" onclick="addPedidoItem()"><i class="fa-solid fa-plus"></i></button></div>
                                <div class="col-md-12"><label class="form-label small">Observação do item</label><input id="item_observacoes_item" class="form-control bg-white"></div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Base</th>
                                    <th>Qtd</th>
                                    <th>Ordem</th>
                                    <th>Obs.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="pedidoItensBody">
                                <tr><td colspan="6" class="text-center text-muted py-3">Nenhum item adicionado.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar pedido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let pedidoItens = [];

function resetPedidoForm() {
    document.getElementById('pedidoForm').reset();
    document.getElementById('pedido_id').value = '';
    document.getElementById('pedidoModalTitle').innerText = 'Novo pedido';
    pedidoItens = [];
    renderPedidoItens();
}

function addPedidoItem() {
    const materialSelect = document.getElementById('item_material_id');
    const baseSelect = document.getElementById('item_base_id');
    if (!materialSelect.value || !baseSelect.value) {
        showToast('Selecione material e base.', 'warning');
        return;
    }

    const material = JSON.parse(materialSelect.options[materialSelect.selectedIndex].dataset.json);
    const base = JSON.parse(baseSelect.options[baseSelect.selectedIndex].dataset.json);
    pedidoItens.push({
        material_id: material.id,
        material_label: `${material.codigo} - ${material.descricao}`,
        base_id: base.id,
        base_label: `${base.codigo} - ${base.nome}`,
        quantidade: parseInt(document.getElementById('item_quantidade').value || '1', 10),
        ordem_entrega: parseInt(document.getElementById('item_ordem_entrega').value || '1', 10),
        observacoes_item: document.getElementById('item_observacoes_item').value || ''
    });
    renderPedidoItens();
}

function renderPedidoItens() {
    const tbody = document.getElementById('pedidoItensBody');
    if (!pedidoItens.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Nenhum item adicionado.</td></tr>';
        return;
    }

    tbody.innerHTML = pedidoItens.map((item, index) => `
        <tr>
            <td>${item.material_label}</td>
            <td>${item.base_label}</td>
            <td><input type="number" min="1" value="${item.quantidade}" class="form-control form-control-sm" onchange="pedidoItens[${index}].quantidade=parseInt(this.value||'1',10)"></td>
            <td><input type="number" min="1" value="${item.ordem_entrega}" class="form-control form-control-sm" onchange="pedidoItens[${index}].ordem_entrega=parseInt(this.value||'1',10)"></td>
            <td><input value="${item.observacoes_item ?? ''}" class="form-control form-control-sm" onchange="pedidoItens[${index}].observacoes_item=this.value"></td>
            <td><button type="button" class="btn btn-sm btn-light border text-danger" onclick="pedidoItens.splice(${index},1); renderPedidoItens();"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `).join('');
}

function editPedido(pedido) {
    resetPedidoForm();
    document.getElementById('pedidoModalTitle').innerText = 'Editar pedido';
    document.getElementById('pedido_id').value = pedido.id;
    document.getElementById('pedido_codigo_pedido').value = pedido.codigo_pedido;
    document.getElementById('pedido_descricao').value = pedido.descricao;
    document.getElementById('pedido_status').value = pedido.status;
    document.getElementById('pedido_observacoes').value = pedido.observacoes || '';
    pedidoItens = (pedido.itens || []).map((item) => ({
        material_id: item.material_id,
        material_label: `${item.material_codigo} - ${item.material_descricao}`,
        base_id: item.base_id,
        base_label: `${item.base_codigo} - ${item.base_nome}`,
        quantidade: item.quantidade,
        ordem_entrega: item.ordem_entrega,
        observacoes_item: item.observacoes_item || ''
    }));
    renderPedidoItens();
    new bootstrap.Modal(document.getElementById('modalPedido')).show();
}

async function savePedido(event) {
    event.preventDefault();
    if (!pedidoItens.length) {
        showToast('Adicione pelo menos um item ao pedido.', 'warning');
        return;
    }
    document.getElementById('pedido_itens_json').value = JSON.stringify(pedidoItens);
    const response = await fetch('../api/salvar_pedido.php', { method: 'POST', body: new FormData(document.getElementById('pedidoForm')) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        showToast(result.message, 'error');
    }
}

async function deletePedido(id) {
    if (!confirm('Excluir este pedido?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const response = await fetch('../api/excluir_pedido.php', { method: 'POST', body: fd });
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

