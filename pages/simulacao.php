<?php
$pageTitle = 'Simulador Multi-Veículo';
require_once __DIR__ . '/../controllers/PedidoCargaController.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

$pedidoController = new PedidoCargaController();
$veiculoController = new VeiculoController();
$pedidos = $pedidoController->listar();
$veiculos = $veiculoController->listar();
$pedidoMap = [];
foreach ($pedidos as $pedido) {
    $pedidoMap[$pedido['id']] = $pedidoController->buscar((int) $pedido['id']);
}
$pedidoPreSelecionado = (int) ($_GET['pedido_id'] ?? 0);
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Simulação 3D com modo manual</h2>
            <p class="text-muted mb-0">Você pode enviar tudo automaticamente ou montar manualmente a carga arrastando itens e blocos para cada veículo.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">1. Pedido e modo</h5>
                </div>
                <div class="card-body p-4">
                    <label class="form-label small fw-bold text-secondary">PEDIDO</label>
                    <select id="pedido_id" class="form-select bg-light mb-3" onchange="renderPedidoResumo()" data-selected="<?= $pedidoPreSelecionado ?>">
                        <option value="">Selecione</option>
                        <?php foreach ($pedidos as $pedido): ?>
                            <option value="<?= $pedido['id'] ?>" <?= $pedidoPreSelecionado === (int) $pedido['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pedido['codigo_pedido']) ?> - <?= htmlspecialchars($pedido['descricao']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="form-label small fw-bold text-secondary">MODO DE EXECUÇÃO</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="sim_mode" id="mode_automatico" value="automatico" checked onchange="toggleSimulationMode()">
                        <label class="btn btn-outline-primary" for="mode_automatico">Automático</label>
                        <input type="radio" class="btn-check" name="sim_mode" id="mode_manual" value="manual" onchange="toggleSimulationMode()">
                        <label class="btn btn-outline-primary" for="mode_manual">Manual</label>
                    </div>

                    <div id="pedidoResumo" class="small text-muted">Selecione um pedido para ver os itens e a ordem de entrega.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">2. Frota candidata</h5>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($veiculos as $veiculo): ?>
                        <div class="border rounded-3 p-3 mb-3 bg-light vehicle-card" data-json='<?= json_encode($veiculo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>'>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><?= htmlspecialchars($veiculo['nome']) ?></strong>
                                <span class="badge bg-white text-dark border"><?= htmlspecialchars($veiculo['acesso_descarga']) ?></span>
                            </div>
                            <small class="text-muted d-block mb-2"><?= htmlspecialchars($veiculo['tipo']) ?> | <?= number_format($veiculo['capacidade_kg'], 0, ',', '.') ?> kg | <?= number_format($veiculo['capacidade_m3'], 2, ',', '.') ?> m³</small>
                            <label class="form-label small">Quantidade a considerar</label>
                            <input type="number" min="0" max="<?= (int) $veiculo['quantidade_disponivel'] ?>" value="<?= min(1, (int) $veiculo['quantidade_disponivel']) ?>" class="form-control bg-white frota-qtd" data-veiculo-id="<?= $veiculo['id'] ?>" onchange="refreshManualBoards()">
                        </div>
                    <?php endforeach; ?>

                    <label class="form-label small fw-bold text-secondary">OBSERVAÇÕES</label>
                    <textarea id="sim_observacoes" class="form-control bg-light mb-3" rows="3" placeholder="Ex.: agrupar cabos e ferragens em blocos manuais para otimizar o embarque."></textarea>

                    <button id="btnExecutarAutomatico" class="btn btn-primary w-100" onclick="executarSimulacaoAutomatica()"><i class="fa-solid fa-play me-1"></i>Executar Simulação Automática</button>
                    <button id="btnSalvarManual" class="btn btn-success w-100 d-none" onclick="salvarMontagemManual()"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar Simulação Manual</button>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div id="automaticPanel" class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div id="simulacaoHint" class="text-center py-5">
                        <i class="fa-solid fa-cubes fa-4x text-primary mb-3"></i>
                        <h4>Pronto para simular</h4>
                        <p class="text-muted mb-0">No modo automático o sistema escolhe a melhor composição de veículos e acomoda os itens sozinho.</p>
                    </div>
                    <div id="simulacaoLoading" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary mb-3"></div>
                        <div class="fw-semibold">Calculando frota, score e posicionamento 3D...</div>
                    </div>
                </div>
            </div>

            <div id="manualPanel" class="d-none">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Montagem manual por arrastar e soltar</h5>
                        <small class="text-muted">Arraste itens soltos ou blocos agrupados para os veículos. Depois você ainda pode editar no resultado.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="manual-builder-panel">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong>Paleta de itens</strong>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="resetManualPlanner()">Resetar</button>
                                    </div>
                                    <div class="small text-muted mb-3">Clique para selecionar, agrupe em um bloco manual ou arraste direto para um veículo.</div>
                                    <div class="alert alert-warning small py-2 px-3 mb-3">
                                        Bobinas sempre permanecem em pé. O modo manual não permite alocar bobina deitada de lado.
                                    </div>

                                    <div class="card border-0 bg-light rounded-4 mb-3">
                                        <div class="card-body p-3">
                                            <div class="fw-semibold mb-2">Criar bloco manual</div>
                                            <div class="small text-muted mb-2">Selecione itens na paleta e informe as dimensões do bloco consolidado.</div>
                                            <input id="group_label" class="form-control form-control-sm mb-2" placeholder="Ex.: Kit Base Norte A">
                                            <div class="row g-2">
                                                <div class="col-4"><input id="group_length" type="number" step="0.01" class="form-control form-control-sm" placeholder="Comp."></div>
                                                <div class="col-4"><input id="group_width" type="number" step="0.01" class="form-control form-control-sm" placeholder="Larg."></div>
                                                <div class="col-4"><input id="group_height" type="number" step="0.01" class="form-control form-control-sm" placeholder="Alt."></div>
                                            </div>
                                            <button class="btn btn-sm btn-primary w-100 mt-2" type="button" onclick="createManualGroup()">Criar bloco com selecionados</button>
                                        </div>
                                    </div>

                                    <div id="manualPalette" class="manual-palette"></div>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong>Veículos para drop</strong>
                                        <div class="small text-muted">Escolha o lastro de destino em cada veículo antes de soltar o item.</div>
                                    </div>
                                    <span class="badge bg-light text-dark border" id="manualSummaryBadge">0 itens posicionados</span>
                                </div>
                                <div id="manualBoards" class="manual-boards"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const pedidoMap = <?= json_encode($pedidoMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const vehicleCatalog = <?= json_encode($veiculos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const manualPlanner = {
    initializedPedidoId: null,
    palette: [],
    placed: [],
    selectedIds: new Set(),
    dragPayload: null,
    slotDefs: []
};

function getCurrentMode() {
    return document.querySelector('input[name="sim_mode"]:checked')?.value || 'automatico';
}

function renderPedidoResumo() {
    const pedidoId = document.getElementById('pedido_id').value;
    const container = document.getElementById('pedidoResumo');
    if (!pedidoId || !pedidoMap[pedidoId]) {
        container.innerHTML = 'Selecione um pedido para ver os itens e a ordem de entrega.';
        resetManualPlanner();
        return;
    }

    const pedido = pedidoMap[pedidoId];
    const itens = (pedido.itens || []).map(item => `
        <div class="border rounded-3 p-2 mb-2 bg-light">
            <strong>${item.material_codigo}</strong> - ${item.material_descricao}<br>
            <small class="text-muted">${item.base_nome} | ordem ${item.ordem_entrega} | qtd ${item.quantidade}</small>
        </div>
    `).join('');
    container.innerHTML = `<div class="fw-semibold mb-2">${pedido.descricao}</div>${itens}`;

    if (getCurrentMode() === 'manual') {
        initializeManualPlanner();
    }
}

function toggleSimulationMode() {
    const manual = getCurrentMode() === 'manual';
    document.getElementById('automaticPanel').classList.toggle('d-none', manual);
    document.getElementById('manualPanel').classList.toggle('d-none', !manual);
    document.getElementById('btnExecutarAutomatico').classList.toggle('d-none', manual);
    document.getElementById('btnSalvarManual').classList.toggle('d-none', !manual);

    if (manual) {
        initializeManualPlanner();
    }
}

function getSelectedFrota() {
    return Array.from(document.querySelectorAll('.frota-qtd'))
        .map(input => ({ veiculo_id: parseInt(input.dataset.veiculoId, 10), quantidade: parseInt(input.value || '0', 10) }))
        .filter(item => item.quantidade > 0);
}

function buildSlotDefinitions() {
    const frota = getSelectedFrota();
    const slots = [];
    frota.forEach(entry => {
        const veiculo = vehicleCatalog.find(item => parseInt(item.id, 10) === entry.veiculo_id);
        if (!veiculo) return;
        const limit = Math.min(entry.quantidade, parseInt(veiculo.quantidade_disponivel || entry.quantidade, 10));
        for (let ordinal = 1; ordinal <= limit; ordinal++) {
            slots.push({
                slot_key: `${veiculo.id}:${ordinal}`,
                veiculo_id: parseInt(veiculo.id, 10),
                ordinal,
                nome: veiculo.nome,
                tipo: veiculo.tipo,
                acesso_descarga: veiculo.acesso_descarga,
                comprimento_m: parseFloat(veiculo.comprimento_m),
                largura_m: parseFloat(veiculo.largura_m),
                altura_m: parseFloat(veiculo.altura_m),
                capacidade_kg: parseFloat(veiculo.capacidade_kg),
                capacidade_m3: parseFloat(veiculo.capacidade_m3)
            });
        }
    });
    return slots;
}

function expandPedidoUnitsForManual(pedido) {
    const units = [];
    (pedido.itens || []).forEach(item => {
        for (let i = 1; i <= parseInt(item.quantidade || 0, 10); i++) {
            const unitKey = `${item.id}:${item.material_id}:${item.base_id}:${item.ordem_entrega}:${i}`;
            units.push({
                id: `unit:${unitKey}`,
                tokenType: 'unit',
                unit_key: unitKey,
                label: `${item.material_codigo} #${i}`,
                subtitle: `${item.base_nome} | ordem ${item.ordem_entrega}`,
                material_codigo: item.material_codigo,
                descricao_material: item.material_descricao,
                base_nome: item.base_nome,
                ordem_entrega: parseInt(item.ordem_entrega, 10),
                comprimento_m: parseFloat(item.comprimento_m),
                largura_m: parseFloat(item.largura_m),
                altura_m: parseFloat(item.altura_m),
                peso_unitario_kg: parseFloat(item.peso_unitario_kg),
                volume_unitario_m3: parseFloat(item.volume_unitario_m3),
                isBobina: String(item.formato_fisico || '').toLowerCase() === 'bobina' || String(item.material_codigo || '').toUpperCase().includes('BOB'),
                color: baseColor(item.base_nome),
                members: [{
                    unit_key: unitKey,
                    label: `${item.material_codigo} #${i}`,
                    comprimento_m: parseFloat(item.comprimento_m),
                    largura_m: parseFloat(item.largura_m),
                    altura_m: parseFloat(item.altura_m),
                    peso_unitario_kg: parseFloat(item.peso_unitario_kg),
                    isBobina: String(item.formato_fisico || '').toLowerCase() === 'bobina' || String(item.material_codigo || '').toUpperCase().includes('BOB')
                }]
            });
        }
    });
    return units;
}

function baseColor(baseName) {
    const palette = ['#2563eb', '#16a34a', '#ea580c', '#dc2626', '#7c3aed', '#0891b2'];
    let total = 0;
    String(baseName || '').split('').forEach(char => { total += char.charCodeAt(0); });
    return palette[total % palette.length];
}

function initializeManualPlanner() {
    const pedidoId = document.getElementById('pedido_id').value;
    if (!pedidoId || !pedidoMap[pedidoId]) {
        return;
    }

    const slotDefs = buildSlotDefinitions();
    manualPlanner.slotDefs = slotDefs;

    if (manualPlanner.initializedPedidoId !== pedidoId) {
        manualPlanner.initializedPedidoId = pedidoId;
        manualPlanner.palette = expandPedidoUnitsForManual(pedidoMap[pedidoId]);
        manualPlanner.placed = [];
        manualPlanner.selectedIds = new Set();
    } else {
        syncPlacedTokensWithSlots();
    }

    renderManualPlanner();
}

function syncPlacedTokensWithSlots() {
    const validSlotKeys = new Set(manualPlanner.slotDefs.map(slot => slot.slot_key));
    const returning = [];
    manualPlanner.placed = manualPlanner.placed.filter(token => {
        if (validSlotKeys.has(token.vehicle_slot_key)) return true;
        returning.push(stripPlacement(token));
        return false;
    });
    manualPlanner.palette.push(...returning);
}

function stripPlacement(token) {
    const clone = structuredClone(token);
    delete clone.vehicle_slot_key;
    delete clone.posicao_x;
    delete clone.posicao_y;
    delete clone.lastro_posicao;
    return clone;
}

function resetManualPlanner() {
    manualPlanner.initializedPedidoId = null;
    manualPlanner.palette = [];
    manualPlanner.placed = [];
    manualPlanner.selectedIds = new Set();
    manualPlanner.slotDefs = [];
    renderManualPlanner();
}

function refreshManualBoards() {
    if (getCurrentMode() !== 'manual') return;
    initializeManualPlanner();
}

function renderManualPlanner() {
    renderManualPalette();
    renderManualBoards();
    updateManualSummary();
}

function renderManualPalette() {
    const container = document.getElementById('manualPalette');
    if (!container) return;

    if (!manualPlanner.palette.length) {
        container.innerHTML = '<div class="text-center text-muted py-4 border rounded-4 bg-light">Nenhum item disponível na paleta.</div>';
        return;
    }

    container.innerHTML = manualPlanner.palette.map(token => `
        <div class="manual-token-card ${manualPlanner.selectedIds.has(token.id) ? 'selected' : ''}" draggable="true"
             ondragstart="onManualTokenDragStart(event, '${token.id}', 'palette')"
             onclick="toggleTokenSelection('${token.id}')">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-semibold">${escapeHtml(token.label)}</div>
                    <div class="small text-muted">${escapeHtml(token.subtitle || '')}</div>
                </div>
                <span class="badge ${token.tokenType === 'group' ? 'bg-dark' : 'bg-light text-dark border'}">${token.tokenType === 'group' ? 'Bloco' : 'Item'}</span>
            </div>
            <div class="small mt-2">
                ${token.comprimento_m.toFixed(2)} x ${token.largura_m.toFixed(2)} x ${token.altura_m.toFixed(2)} m<br>
                ${token.peso_unitario_kg.toFixed(2)} kg
                ${token.tokenType === 'group' ? `<br>${token.members.length} unidade(s)` : ''}
            </div>
        </div>
    `).join('');
}

function renderManualBoards() {
    const container = document.getElementById('manualBoards');
    if (!container) return;

    if (!manualPlanner.slotDefs.length) {
        container.innerHTML = '<div class="text-center text-muted py-5 border rounded-4 bg-light">Defina pelo menos um veículo com quantidade maior que zero para montar manualmente.</div>';
        return;
    }

    container.innerHTML = manualPlanner.slotDefs.map(slot => {
        const placed = manualPlanner.placed.filter(token => token.vehicle_slot_key === slot.slot_key);
        const scale = Math.min(500 / slot.comprimento_m, 220 / slot.largura_m);
        const blocks = placed.map(token => {
            const left = token.posicao_x * scale;
            const top = token.posicao_y * scale;
            const width = Math.max(24, token.comprimento_m * scale);
            const height = Math.max(24, token.largura_m * scale);
            return `
                <div class="manual-placed-block ${token.tokenType === 'group' ? 'group' : ''} ${token.isBobina ? 'bobina' : ''}"
                     draggable="true"
                     ondragstart="onManualTokenDragStart(event, '${token.id}', 'placed')"
                     style="left:${left}px;top:${top}px;width:${width}px;height:${height}px;background:${token.color || '#2563eb'};"
                     title="${escapeHtml(token.label)} | lastro ${token.lastro_posicao}">
                    <span>${escapeHtml(token.label)}</span>
                </div>
            `;
        }).join('');

        const list = placed.map(token => `
            <div class="manual-placed-list-item">
                <div>
                    <div class="fw-semibold small">${escapeHtml(token.label)}</div>
                    <div class="text-muted xsmall">${token.comprimento_m.toFixed(2)} x ${token.largura_m.toFixed(2)} m</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" onchange="updatePlacedLastro('${token.id}', this.value)">
                        <option value="1" ${token.lastro_posicao === 1 ? 'selected' : ''}>L1</option>
                        <option value="2" ${token.lastro_posicao === 2 ? 'selected' : ''}>L2</option>
                    </select>
                    <button class="btn btn-sm btn-light border" type="button" onclick="returnPlacedToken('${token.id}')"><i class="fa-solid fa-rotate-left"></i></button>
                </div>
            </div>
        `).join('');

        return `
            <div class="manual-board-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">${escapeHtml(slot.nome)} #${slot.ordinal}</div>
                        <div class="small text-muted">${escapeHtml(slot.tipo)} | acesso ${escapeHtml(slot.acesso_descarga)} | ${slot.comprimento_m.toFixed(2)} x ${slot.largura_m.toFixed(2)} m</div>
                    </div>
                    <div class="manual-board-actions">
                        <label class="small text-muted d-block">Lastro alvo</label>
                        <select class="form-select form-select-sm manual-board-layer" data-slot-key="${slot.slot_key}">
                            <option value="1">Lastro 1</option>
                            <option value="2">Lastro 2</option>
                        </select>
                    </div>
                </div>
                <div class="manual-board-dropzone"
                     data-slot-key="${slot.slot_key}"
                     data-scale="${scale}"
                     data-length="${slot.comprimento_m}"
                     data-width="${slot.largura_m}"
                     ondragover="onManualBoardDragOver(event)"
                     ondrop="onManualBoardDrop(event)">
                    <div class="manual-board-cabin">Cabine</div>
                    <div class="manual-board-rear">Traseira</div>
                    <div class="manual-board-stage" style="width:${Math.max(280, slot.comprimento_m * scale)}px;height:${Math.max(120, slot.largura_m * scale)}px;">
                        ${blocks}
                    </div>
                </div>
                <div class="manual-placed-list mt-3">${list || '<div class="text-muted small">Nenhum item posicionado neste veículo.</div>'}</div>
            </div>
        `;
    }).join('');
}

function updateManualSummary() {
    const badge = document.getElementById('manualSummaryBadge');
    if (!badge) return;
    const placedUnits = manualPlanner.placed.reduce((sum, token) => sum + (token.tokenType === 'group' ? (token.members?.length || 0) : 1), 0);
    badge.textContent = `${manualPlanner.placed.length} bloco(s) | ${placedUnits} unidade(s) posicionada(s)`;
}

function toggleTokenSelection(tokenId) {
    if (manualPlanner.selectedIds.has(tokenId)) manualPlanner.selectedIds.delete(tokenId);
    else manualPlanner.selectedIds.add(tokenId);
    renderManualPalette();
}

function createManualGroup() {
    const selected = manualPlanner.palette.filter(token => manualPlanner.selectedIds.has(token.id));
    if (selected.length < 2) {
        showToast('Selecione pelo menos 2 itens da paleta para criar um bloco manual.', 'warning');
        return;
    }

    const label = (document.getElementById('group_label').value || '').trim() || `Bloco manual ${Date.now().toString().slice(-4)}`;
    const length = parseFloat(document.getElementById('group_length').value || '0');
    const width = parseFloat(document.getElementById('group_width').value || '0');
    const height = parseFloat(document.getElementById('group_height').value || '0');

    if (length <= 0 || width <= 0 || height <= 0) {
        showToast('Informe comprimento, largura e altura do bloco manual.', 'warning');
        return;
    }

    const members = selected.flatMap(token => token.members || []);
    const totalWeight = members.reduce((sum, member) => sum + parseFloat(member.peso_unitario_kg || 0), 0);
    const hasBobina = members.some(member => member.isBobina);
    const baseName = selected.map(token => token.base_nome || token.subtitle || '').join(' / ');
    const color = selected[0]?.color || '#2563eb';

    const groupToken = {
        id: `group:${Date.now()}:${Math.random().toString(36).slice(2, 8)}`,
        tokenType: 'group',
        label,
        subtitle: baseName,
        comprimento_m: length,
        largura_m: width,
        altura_m: height,
        peso_unitario_kg: totalWeight,
        volume_unitario_m3: length * width * height,
        members,
        color,
        isBobina: hasBobina
    };

    const selectedIds = new Set(selected.map(token => token.id));
    manualPlanner.palette = manualPlanner.palette.filter(token => !selectedIds.has(token.id));
    manualPlanner.palette.push(groupToken);
    manualPlanner.selectedIds = new Set();
    document.getElementById('group_label').value = '';
    document.getElementById('group_length').value = '';
    document.getElementById('group_width').value = '';
    document.getElementById('group_height').value = '';
    renderManualPlanner();
}

function onManualTokenDragStart(event, tokenId, sourceType) {
    manualPlanner.dragPayload = { tokenId, sourceType };
    event.dataTransfer.setData('text/plain', JSON.stringify(manualPlanner.dragPayload));
    event.dataTransfer.effectAllowed = 'move';
}

function onManualBoardDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
}

function onManualBoardDrop(event) {
    event.preventDefault();
    const raw = event.dataTransfer.getData('text/plain');
    let payload = manualPlanner.dragPayload;
    if (raw) {
        try { payload = JSON.parse(raw); } catch (_) {}
    }
    if (!payload) return;

    const board = event.currentTarget;
    const slotKey = board.dataset.slotKey;
    const scale = parseFloat(board.dataset.scale);
    const stage = board.querySelector('.manual-board-stage');
    const rect = stage.getBoundingClientRect();
    const dropX = Math.max(0, event.clientX - rect.left);
    const dropY = Math.max(0, event.clientY - rect.top);
    const targetLastro = parseInt(document.querySelector(`.manual-board-layer[data-slot-key="${slotKey}"]`)?.value || '1', 10);

    let token = null;
    if (payload.sourceType === 'palette') {
        const index = manualPlanner.palette.findIndex(entry => entry.id === payload.tokenId);
        if (index < 0) return;
        token = structuredClone(manualPlanner.palette[index]);
        manualPlanner.palette.splice(index, 1);
    } else {
        const index = manualPlanner.placed.findIndex(entry => entry.id === payload.tokenId);
        if (index < 0) return;
        token = manualPlanner.placed[index];
        manualPlanner.placed.splice(index, 1);
    }

    const stageLength = parseFloat(board.dataset.length || '0');
    const stageWidth = parseFloat(board.dataset.width || '0');
    const maxX = Math.max(0, stageLength - parseFloat(token.comprimento_m || 0));
    const maxY = Math.max(0, stageWidth - parseFloat(token.largura_m || 0));

    token.vehicle_slot_key = slotKey;
    token.lastro_posicao = targetLastro;
    token.posicao_x = round2(Math.min(maxX, Math.max(0, dropX / scale)));
    token.posicao_y = round2(Math.min(maxY, Math.max(0, dropY / scale)));
    manualPlanner.placed.push(token);
    manualPlanner.dragPayload = null;
    renderManualPlanner();
}

function updatePlacedLastro(tokenId, value) {
    const token = manualPlanner.placed.find(entry => entry.id === tokenId);
    if (!token) return;
    token.lastro_posicao = parseInt(value || '1', 10);
    renderManualBoards();
}

function returnPlacedToken(tokenId) {
    const index = manualPlanner.placed.findIndex(entry => entry.id === tokenId);
    if (index < 0) return;
    manualPlanner.palette.push(stripPlacement(manualPlanner.placed[index]));
    manualPlanner.placed.splice(index, 1);
    renderManualPlanner();
}

function buildManualPlacementsPayload() {
    const placements = [];
    for (const token of manualPlanner.placed) {
        if (token.tokenType === 'unit') {
            placements.push({
                unit_key: token.unit_key,
                vehicle_slot_key: token.vehicle_slot_key,
                lastro_posicao: token.lastro_posicao || 1,
                posicao_x: token.posicao_x || 0,
                posicao_y: token.posicao_y || 0,
                comprimento_m: token.comprimento_m,
                largura_m: token.largura_m,
                orientacao_manual: token.isBobina ? 'em_pe' : 'base_0',
                grupo_label: token.label
            });
            continue;
        }

        const exploded = explodeManualGroup(token);
        placements.push(...exploded);
    }
    return placements;
}

function explodeManualGroup(groupToken) {
    const members = [...(groupToken.members || [])].sort((a, b) => ((b.comprimento_m * b.largura_m) - (a.comprimento_m * a.largura_m)));
    const gap = 0.02;
    let cursorX = 0;
    let cursorY = 0;
    let rowHeight = 0;
    const placements = [];

    for (const member of members) {
        let options = [{
            comprimento_m: member.comprimento_m,
            largura_m: member.largura_m,
            orientacao_manual: member.isBobina ? 'em_pe' : 'base_0'
        }];
        if (!member.isBobina && Math.abs(member.comprimento_m - member.largura_m) > 0.001) {
            options.push({
                comprimento_m: member.largura_m,
                largura_m: member.comprimento_m,
                orientacao_manual: 'base_90'
            });
        }

        let chosen = null;
        for (const option of options) {
            if (cursorX + option.comprimento_m <= groupToken.comprimento_m + 0.0001 && cursorY + option.largura_m <= groupToken.largura_m + 0.0001) {
                chosen = option;
                break;
            }
        }

        if (!chosen) {
            cursorX = 0;
            cursorY = round2(cursorY + rowHeight + gap);
            rowHeight = 0;
            for (const option of options) {
                if (cursorX + option.comprimento_m <= groupToken.comprimento_m + 0.0001 && cursorY + option.largura_m <= groupToken.largura_m + 0.0001) {
                    chosen = option;
                    break;
                }
            }
        }

        if (!chosen) {
            throw new Error(`O bloco "${groupToken.label}" não comporta todos os itens nas dimensões informadas.`);
        }

        placements.push({
            unit_key: member.unit_key,
            vehicle_slot_key: groupToken.vehicle_slot_key,
            lastro_posicao: groupToken.lastro_posicao || 1,
            posicao_x: round2((groupToken.posicao_x || 0) + cursorX),
            posicao_y: round2((groupToken.posicao_y || 0) + cursorY),
            comprimento_m: chosen.comprimento_m,
            largura_m: chosen.largura_m,
            orientacao_manual: chosen.orientacao_manual,
            grupo_label: groupToken.label
        });

        cursorX = round2(cursorX + chosen.comprimento_m + gap);
        rowHeight = Math.max(rowHeight, chosen.largura_m);
    }

    return placements;
}

async function executarSimulacaoAutomatica() {
    const pedidoId = parseInt(document.getElementById('pedido_id').value || '0', 10);
    if (!pedidoId) {
        showToast('Selecione um pedido.', 'warning');
        return;
    }

    const frota = getSelectedFrota();
    if (!frota.length) {
        showToast('Informe pelo menos um veículo com quantidade maior que zero.', 'warning');
        return;
    }

    document.getElementById('simulacaoHint').classList.add('d-none');
    document.getElementById('simulacaoLoading').classList.remove('d-none');

    const response = await fetch('../api/executar_simulacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            modo: 'automatico',
            pedido_id: pedidoId,
            frota,
            observacoes: document.getElementById('sim_observacoes').value
        })
    });
    const result = await response.json();
    document.getElementById('simulacaoLoading').classList.add('d-none');
    document.getElementById('simulacaoHint').classList.remove('d-none');

    if (result.status === 'success') {
        showToast(result.message, 'success');
        window.location.href = `resultado_simulacao.php?id=${result.data.id}`;
    } else {
        showToast(result.message, 'error');
    }
}

async function salvarMontagemManual() {
    const pedidoId = parseInt(document.getElementById('pedido_id').value || '0', 10);
    if (!pedidoId) {
        showToast('Selecione um pedido.', 'warning');
        return;
    }

    const frota = getSelectedFrota();
    if (!frota.length) {
        showToast('Informe a frota candidata para a montagem manual.', 'warning');
        return;
    }

    if (!manualPlanner.placed.length) {
        showToast('Arraste pelo menos um item ou bloco para um veículo antes de salvar.', 'warning');
        return;
    }

    let placements = [];
    try {
        placements = buildManualPlacementsPayload();
    } catch (error) {
        showToast(error.message || 'Não foi possível expandir os blocos manuais.', 'error');
        return;
    }

    const response = await fetch('../api/executar_simulacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            modo: 'manual',
            pedido_id: pedidoId,
            frota,
            placements,
            observacoes: document.getElementById('sim_observacoes').value
        })
    });
    const result = await response.json();
    if (result.status === 'success') {
        showToast('Montagem manual salva com sucesso!', 'success');
        window.location.href = `resultado_simulacao.php?id=${result.data.id}`;
    } else {
        showToast(result.message, 'error');
    }
}

function round2(value) {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

function escapeHtml(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

document.addEventListener('DOMContentLoaded', () => {
    renderPedidoResumo();
    toggleSimulationMode();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
