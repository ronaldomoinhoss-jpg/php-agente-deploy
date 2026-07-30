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
            <h2 class="h3 mb-1 text-dark">Simulação manual e automática</h2>
            <p class="text-muted mb-0">A mesma tela permite autoalocar a carga ou montar manualmente arrastando itens para a carreta.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xxl-3 col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">1. Pedidos da carga</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-label small fw-bold text-secondary mb-0">SELEÇÃO MÚLTIPLA</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllPedidos()">Marcar todos</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearPedidoSelection()">Limpar</button>
                        </div>
                    </div>
                    <div class="small text-muted mb-3">Você pode combinar vários pedidos na mesma carreta, inclusive com vários destinos e várias bases.</div>
                    <div id="pedidoSelectionList" class="pedido-selection-list mb-3">
                        <?php foreach ($pedidos as $pedido): ?>
                            <label class="pedido-selection-item">
                                <input
                                    type="checkbox"
                                    class="form-check-input pedido-selector"
                                    value="<?= $pedido['id'] ?>"
                                    <?= $pedidoPreSelecionado === (int) $pedido['id'] ? 'checked' : '' ?>
                                    onchange="renderPedidoResumo()"
                                >
                                <span class="pedido-selection-content">
                                    <span class="fw-semibold d-block"><?= htmlspecialchars($pedido['codigo_pedido']) ?></span>
                                    <span class="small text-muted d-block"><?= htmlspecialchars($pedido['descricao']) ?></span>
                                    <span class="small text-muted"><?= (int) ($pedido['unidades'] ?? 0) ?> unidade(s) | <?= (int) ($pedido['linhas'] ?? 0) ?> item(ns)</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="pedidoSelecionadoResumo" class="small fw-semibold text-primary mb-3">Nenhum pedido selecionado.</div>
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

                    <div class="d-grid gap-2">
                        <button id="btnExecutarAutomatico" class="btn btn-primary" onclick="executarSimulacaoAutomatica()"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Autoalocar no desenho</button>
                        <button id="btnSalvarManual" class="btn btn-success" onclick="salvarMontagemManual()"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar carga atual</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-9 col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div id="simulacaoHint" class="alert alert-light border mb-0">
                        <div class="fw-semibold mb-1">Fluxo único de montagem</div>
                        <div class="small text-muted">Arraste itens da lista para a carreta, mova volumes dentro do desenho e use o botão de autoalocação quando quiser gerar uma proposta automática no mesmo quadro.</div>
                    </div>
                    <div id="simulacaoLoading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary mb-3"></div>
                        <div class="fw-semibold">Calculando frota, score e posicionamento...</div>
                    </div>
                    <div id="simulacaoAutoStatus" class="alert alert-success d-none mt-3 mb-0"></div>
                </div>
            </div>

            <div id="manualPanel">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Montagem de carga</h5>
                        <small class="text-muted">Arraste itens soltos ou blocos agrupados para os veículos. Itens já desenhados podem ser movidos no próprio quadro ou removidos sem voltar para outra tela.</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-xl-3 col-lg-4">
                                <div class="manual-builder-panel">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <strong>Lista de itens</strong>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="resetManualPlanner()">Resetar</button>
                                    </div>
                                    <div class="small text-muted mb-3">Clique para selecionar, agrupe em um bloco manual ou arraste direto para um veículo.</div>
                                    <div class="alert alert-warning small py-2 px-3 mb-3">
                                        Bobinas de cabo são tratadas deitadas. Quando houver apoio válido, o sistema também aceita pirâmide com 2 embaixo e 1 em cima.
                                    </div>

                                    <div class="card border-0 bg-light rounded-4 mb-3">
                                        <div class="card-body p-3">
                                            <div class="fw-semibold mb-2">Criar bloco manual</div>
                                            <div class="small text-muted mb-2">Selecione itens na lista e informe as dimensões do bloco consolidado.</div>
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

                            <div class="col-xl-9 col-lg-8">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <strong>Desenho da carga</strong>
                                        <div class="small text-muted">Arraste os itens e o sistema detecta automaticamente se o apoio correto e no lastro 1 ou no lastro 2. Você pode montar em 2D ou 3D.</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Modo de montagem manual">
                                            <input type="radio" class="btn-check" name="manual_board_mode" id="manual_board_mode_2d" autocomplete="off" checked onchange="setManualBoardMode('2d')">
                                            <label class="btn btn-outline-primary" for="manual_board_mode_2d">2D</label>
                                            <input type="radio" class="btn-check" name="manual_board_mode" id="manual_board_mode_3d" autocomplete="off" onchange="setManualBoardMode('3d')">
                                            <label class="btn btn-outline-primary" for="manual_board_mode_3d">3D</label>
                                        </div>
                                        <span class="badge bg-light text-dark border" id="manualSummaryBadge">0 itens posicionados</span>
                                    </div>
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

function getTransportDimensions(length, width, height, isBobina) {
    const baseLength = parseFloat(length || 0);
    const baseWidth = parseFloat(width || 0);
    const baseHeight = parseFloat(height || 0);
    if (!isBobina) {
        return {
            comprimento_m: baseLength,
            largura_m: baseWidth,
            altura_m: baseHeight
        };
    }

    const diameter = Math.max(baseLength, baseWidth);
    const spoolWidth = Math.min(baseLength, baseWidth, baseHeight);
    return {
        comprimento_m: diameter,
        largura_m: spoolWidth,
        altura_m: diameter
    };
}

const manualPlanner = {
    initializedPedidoKey: null,
    palette: [],
    placed: [],
    selectedIds: new Set(),
    selectedPlacedId: null,
    active3DDrag: null,
    dragPayload: null,
    slotDefs: [],
    boardMode: '2d',
    renderers: {},
    viewStateBySlot: {},
    previews: {},
    snapConfig: {
        gridStep: 0.05,
        gridTolerance: 0.04,
        edgeTolerance: 0.12,
        neighborTolerance: 0.14
    }
};

function getSelectedPedidoIds() {
    return Array.from(document.querySelectorAll('.pedido-selector:checked'))
        .map(input => parseInt(input.value || '0', 10))
        .filter(id => id > 0);
}

function getSelectedPedidos() {
    return getSelectedPedidoIds()
        .map(id => pedidoMap[id])
        .filter(Boolean);
}

function selectAllPedidos() {
    document.querySelectorAll('.pedido-selector').forEach(input => {
        input.checked = true;
    });
    renderPedidoResumo();
}

function clearPedidoSelection() {
    document.querySelectorAll('.pedido-selector').forEach(input => {
        input.checked = false;
    });
    renderPedidoResumo();
}

function buildSelectedPedidoBundle() {
    const pedidos = getSelectedPedidos();
    if (!pedidos.length) {
        return null;
    }

    if (pedidos.length === 1) {
        const pedido = structuredClone(pedidos[0]);
        pedido.itens = (pedido.itens || []).map(item => ({
            ...item,
            pedido_codigo: pedido.codigo_pedido,
            pedido_descricao: pedido.descricao
        }));
        return pedido;
    }

    const itens = [];
    pedidos.forEach(pedido => {
        (pedido.itens || []).forEach(item => {
            itens.push({
                ...structuredClone(item),
                pedido_codigo: pedido.codigo_pedido,
                pedido_descricao: pedido.descricao,
                pedido_origem_id: pedido.id
            });
        });
    });

    return {
        id: null,
        codigo_pedido: `MULTI-${pedidos.length}`,
        descricao: `${pedidos.length} pedidos combinados`,
        pedidos_origem: pedidos.map(pedido => ({
            id: pedido.id,
            codigo_pedido: pedido.codigo_pedido,
            descricao: pedido.descricao
        })),
        itens
    };
}

function renderPedidoResumo() {
    const pedidos = getSelectedPedidos();
    const container = document.getElementById('pedidoResumo');
    const badge = document.getElementById('pedidoSelecionadoResumo');

    if (!pedidos.length) {
        badge.textContent = 'Nenhum pedido selecionado.';
        container.innerHTML = 'Selecione pelo menos um pedido para ver os itens, as bases e a ordem de entrega.';
        resetManualPlanner();
        return;
    }

    const totalItens = pedidos.reduce((sum, pedido) => sum + (pedido.itens || []).length, 0);
    const totalUnidades = pedidos.reduce((sum, pedido) => sum + (pedido.itens || []).reduce((inner, item) => inner + parseInt(item.quantidade || 0, 10), 0), 0);
    const totalBases = new Set(
        pedidos.flatMap(pedido => (pedido.itens || []).map(item => `${item.base_id}:${item.base_nome}`))
    ).size;

    badge.textContent = `${pedidos.length} pedido(s) | ${totalBases} base(s) | ${totalUnidades} unidade(s)`;

    container.innerHTML = pedidos.map(pedido => {
        const itens = (pedido.itens || []).map(item => `
            <div class="border rounded-3 p-2 mb-2 bg-light">
                <strong>${item.material_codigo}</strong> - ${item.material_descricao}<br>
                <small class="text-muted">${item.base_nome} | ordem ${item.ordem_entrega} | qtd ${item.quantidade}</small>
            </div>
        `).join('');

        return `
            <div class="border rounded-4 p-3 mb-3 bg-white">
                <div class="fw-semibold">${pedido.codigo_pedido}</div>
                <div class="text-muted small mb-2">${pedido.descricao}</div>
                ${itens}
            </div>
        `;
    }).join('') + `<div class="small text-muted">Resumo consolidado: ${totalItens} linha(s) e ${totalUnidades} unidade(s) nesta proposta de carga.</div>`;

    initializeManualPlanner();
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
                capacidade_m3: parseFloat(veiculo.capacidade_m3),
                max_lastros: parseInt(veiculo.max_lastros || 1, 10)
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
            const subtitleBase = `${item.base_nome} | ordem ${item.ordem_entrega}`;
            const subtitle = item.pedido_codigo ? `${item.pedido_codigo} | ${subtitleBase}` : subtitleBase;
            const isBobina = String(item.formato_fisico || '').toLowerCase() === 'bobina' || String(item.material_codigo || '').toUpperCase().includes('BOB');
            const dims = getTransportDimensions(item.comprimento_m, item.largura_m, item.altura_m, isBobina);
            units.push({
                id: `unit:${unitKey}`,
                tokenType: 'unit',
                unit_key: unitKey,
                label: `${item.material_codigo} #${i}`,
                subtitle,
                material_codigo: item.material_codigo,
                descricao_material: item.material_descricao,
                base_nome: item.base_nome,
                pedido_codigo: item.pedido_codigo || '',
                ordem_entrega: parseInt(item.ordem_entrega, 10),
                comprimento_m: dims.comprimento_m,
                largura_m: dims.largura_m,
                altura_m: dims.altura_m,
                peso_unitario_kg: parseFloat(item.peso_unitario_kg),
                volume_unitario_m3: parseFloat(item.volume_unitario_m3),
                empilhavel: parseInt(item.empilhavel ?? 1, 10),
                max_lastros: parseInt(item.max_lastros ?? 1, 10),
                isBobina,
                perfil_empilhamento: item.perfil_empilhamento || '',
                color: baseColor(item.base_nome),
                members: [{
                    unit_key: unitKey,
                    label: `${item.material_codigo} #${i}`,
                    comprimento_m: dims.comprimento_m,
                    largura_m: dims.largura_m,
                    altura_m: dims.altura_m,
                    peso_unitario_kg: parseFloat(item.peso_unitario_kg),
                    empilhavel: parseInt(item.empilhavel ?? 1, 10),
                    max_lastros: parseInt(item.max_lastros ?? 1, 10),
                    isBobina,
                    perfil_empilhamento: item.perfil_empilhamento || ''
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
    const pedidoIds = getSelectedPedidoIds();
    const pedidoKey = pedidoIds.join(',');
    const pedidoBundle = buildSelectedPedidoBundle();
    if (!pedidoBundle) {
        return;
    }

    const slotDefs = buildSlotDefinitions();
    manualPlanner.slotDefs = slotDefs;

    if (manualPlanner.initializedPedidoKey !== pedidoKey) {
        manualPlanner.initializedPedidoKey = pedidoKey;
        manualPlanner.palette = expandPedidoUnitsForManual(pedidoBundle);
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
    manualPlanner.initializedPedidoKey = null;
    manualPlanner.palette = [];
    manualPlanner.placed = [];
    manualPlanner.selectedIds = new Set();
    manualPlanner.selectedPlacedId = null;
    manualPlanner.active3DDrag = null;
    manualPlanner.slotDefs = [];
    manualPlanner.viewStateBySlot = {};
    manualPlanner.previews = {};
    renderManualPlanner();
}

function refreshManualBoards() {
    initializeManualPlanner();
}

function renderManualPlanner() {
    renderManualPalette();
    renderManualBoards();
    updateManualSummary();
}

function setManualBoardMode(mode) {
    if (!['2d', '3d'].includes(mode)) return;
    manualPlanner.boardMode = mode;
    renderManualBoards();
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
            <div class="manual-token-visual ${token.tokenType === 'group' ? 'group' : ''} ${token.isBobina ? 'bobina' : ''}">
                <div class="manual-token-visual-shape" style="background:${token.color || '#2563eb'};"></div>
                <div class="manual-token-visual-meta">
                    <span>${token.comprimento_m.toFixed(2)} m</span>
                    <span>${token.largura_m.toFixed(2)} m</span>
                    <span>${token.altura_m.toFixed(2)} m</span>
                </div>
            </div>
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
    destroyManual3DScenes();

    if (!manualPlanner.slotDefs.length) {
        container.innerHTML = '<div class="text-center text-muted py-5 border rounded-4 bg-light">Defina pelo menos um veículo com quantidade maior que zero para montar manualmente.</div>';
        return;
    }

    container.innerHTML = manualPlanner.slotDefs.map(slot => {
        const placed = manualPlanner.placed.filter(token => token.vehicle_slot_key === slot.slot_key);
        const preview = manualPlanner.previews[slot.slot_key] || null;
        const selectedToken = placed.find(token => token.id === manualPlanner.selectedPlacedId) || null;
        const selectedReturnAction = selectedToken ? `returnPlacedToken('${selectedToken.id}')` : 'return false;';
        const selectedRemoveAction = selectedToken ? `removePlacedToken('${selectedToken.id}')` : 'return false;';
        const scale = Math.min(820 / slot.comprimento_m, 320 / slot.largura_m);
        const blocks = placed.map(token => {
            const left = token.posicao_x * scale;
            const top = token.posicao_y * scale;
            const width = Math.max(24, token.comprimento_m * scale);
            const height = Math.max(24, token.largura_m * scale);
            return `
                <div class="manual-placed-block ${token.tokenType === 'group' ? 'group' : ''} ${token.isBobina ? 'bobina' : ''} ${manualPlanner.selectedPlacedId === token.id ? 'selected' : ''}"
                     draggable="true"
                     ondragstart="onManualTokenDragStart(event, '${token.id}', 'placed')"
                     onclick="event.stopPropagation(); selectPlacedToken('${token.id}')"
                     ondblclick="event.stopPropagation(); removePlacedToken('${token.id}')"
                     style="left:${left}px;top:${top}px;width:${width}px;height:${height}px;background:${token.color || '#2563eb'};"
                     title="${escapeHtml(token.label)} | lastro ${token.lastro_posicao} | clique para selecionar | duplo clique para remover">
                    <button class="manual-block-remove" type="button" draggable="false" onclick="event.stopPropagation(); removePlacedToken('${token.id}')">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="manual-block-actions">
                        <button class="manual-block-action" type="button" draggable="false" onclick="event.stopPropagation(); returnPlacedToken('${token.id}')">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                        <button class="manual-block-action danger" type="button" draggable="false" onclick="event.stopPropagation(); removePlacedToken('${token.id}')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <span>${escapeHtml(token.label)}</span>
                </div>
            `;
        }).join('');
        const previewGuides = preview ? `
            ${(preview.guidesX || []).map(x => `<div class="manual-snap-guide vertical" style="left:${Math.max(0, x * scale)}px;"></div>`).join('')}
            ${(preview.guidesY || []).map(y => `<div class="manual-snap-guide horizontal" style="top:${Math.max(0, y * scale)}px;"></div>`).join('')}
            <div class="manual-preview-block ${preview.isBobina ? 'bobina' : ''}"
                 style="left:${preview.x * scale}px;top:${preview.y * scale}px;width:${Math.max(24, preview.length * scale)}px;height:${Math.max(24, preview.width * scale)}px;background:${preview.color || '#2563eb'};">
                <span>${escapeHtml(preview.label)}</span>
            </div>
            <div class="manual-snap-badge">${escapeHtml(preview.message)}</div>
        ` : '';

        const list = placed.map(token => `
            <div class="manual-placed-list-item" draggable="true" ondragstart="onManualTokenDragStart(event, '${token.id}', 'placed')">
                <div>
                    <div class="fw-semibold small">${escapeHtml(token.label)}</div>
                    <div class="text-muted xsmall">${token.comprimento_m.toFixed(2)} x ${token.largura_m.toFixed(2)} m</div>
                </div>
                    <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border">Auto L${token.lastro_posicao}</span>
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
                        <span class="badge bg-light text-dark border">Detecção automática de lastro</span>
                    </div>
                </div>
                ${manualPlanner.boardMode === '2d'
                    ? `
                        <div class="manual-board-dropzone"
                             data-slot-key="${slot.slot_key}"
                             data-scale="${scale}"
                             data-length="${slot.comprimento_m}"
                             data-width="${slot.largura_m}"
                             onclick="clearPlacedSelection()"
                             ondragover="onManualBoardDragOver(event)"
                             ondragleave="onManualBoardDragLeave(event)"
                             ondrop="onManualBoardDrop(event)">
                            <div class="manual-board-cabin">Cabine</div>
                            <div class="manual-board-rear">Traseira</div>
                            <div class="manual-board-stage" style="width:${Math.max(540, slot.comprimento_m * scale)}px;height:${Math.max(220, slot.largura_m * scale)}px;">
                                ${blocks}
                                ${previewGuides}
                            </div>
                        </div>
                    `
                    : `
                        <div class="manual-board-dropzone manual-board-dropzone-3d">
                            <div class="manual-3d-overlay" id="${buildManualOverlayId(slot.slot_key)}">Puxe os volumes do patio 3D para dentro da carreta. Se precisar, use autoalocacao e depois ajuste no mesmo ambiente.</div>
                            <div class="manual-3d-toolbar ${selectedToken ? 'active' : ''}">
                                <div class="manual-3d-toolbar-info">
                                    ${selectedToken
                                        ? `<strong>${escapeHtml(selectedToken.label)}</strong><span>Lastro ${selectedToken.lastro_posicao} | clique para manter selecionado</span>`
                                        : '<strong>Patio 3D ativo</strong><span>Arraste volumes de fora para a carreta ou devolva da carreta para o patio.</span>'
                                    }
                                </div>
                                <div class="manual-3d-toolbar-actions">
                                    <button class="btn btn-sm btn-light border" type="button" ${selectedToken ? '' : 'disabled'} onclick="${selectedReturnAction}">
                                        <i class="fa-solid fa-arrow-left me-1"></i>Devolver
                                    </button>
                                    <button class="btn btn-sm btn-danger" type="button" ${selectedToken ? '' : 'disabled'} onclick="${selectedRemoveAction}">
                                        <i class="fa-solid fa-trash me-1"></i>Remover
                                    </button>
                                </div>
                            </div>
                            <div class="manual-3d-stage" id="${buildManualStageId(slot.slot_key)}"></div>
                        </div>
                    `
                }
                <div class="manual-placed-list mt-3">${list || '<div class="text-muted small">Nenhum item posicionado neste veículo.</div>'}</div>
            </div>
        `;
    }).join('');

    if (manualPlanner.boardMode === '3d') {
        requestAnimationFrame(renderManual3DBoards);
    }
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

function selectPlacedToken(tokenId) {
    manualPlanner.selectedPlacedId = manualPlanner.selectedPlacedId === tokenId ? null : tokenId;
    renderManualBoards();
}

function clearPlacedSelection() {
    if (!manualPlanner.selectedPlacedId) return;
    manualPlanner.selectedPlacedId = null;
    renderManualBoards();
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
    const maxLastros = members.reduce((minValue, member) => Math.min(minValue, parseInt(member.max_lastros ?? 1, 10)), 2);
    const empilhavel = members.every(member => parseInt(member.max_lastros ?? 1, 10) >= 2) ? 1 : 0;

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
        empilhavel,
        max_lastros: maxLastros,
        isBobina: hasBobina,
        perfil_empilhamento: hasBobina ? 'piramidal' : ''
    };

    const selectedIds = new Set(selected.map(token => token.id));
    manualPlanner.palette = manualPlanner.palette.filter(token => !selectedIds.has(token.id));
    manualPlanner.palette.push(groupToken);
    manualPlanner.selectedIds = new Set();
    manualPlanner.selectedPlacedId = null;
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
    const payload = extractManualDragPayload(event);
    const board = event.currentTarget;
    if (!payload || !board) return;

    const token = peekManualToken(payload);
    const stage = board.querySelector('.manual-board-stage');
    if (!token || !stage) return;

    const slotKey = board.dataset.slotKey;
    const scale = parseFloat(board.dataset.scale || '1');
    const stageLength = parseFloat(board.dataset.length || '0');
    const stageWidth = parseFloat(board.dataset.width || '0');
    const rect = stage.getBoundingClientRect();
    const x = Math.max(0, event.clientX - rect.left) / scale;
    const y = Math.max(0, event.clientY - rect.top) / scale;
    const placement = determineAutomaticPlacement(token, slotKey, x, y, stageLength, stageWidth, payload.sourceType === 'placed' ? payload.tokenId : null);
    if (!placement) {
        delete manualPlanner.previews[slotKey];
        renderManualBoards();
        return;
    }

    manualPlanner.previews[slotKey] = {
        x: placement.snap.x,
        y: placement.snap.y,
        length: parseFloat(token.comprimento_m || 0),
        width: parseFloat(token.largura_m || 0),
        color: token.color,
        label: token.label,
        isBobina: token.isBobina,
        message: placement.message,
        guidesX: placement.snap.guidesX,
        guidesY: placement.snap.guidesY
    };
    renderManualBoards();
}

function onManualBoardDragLeave(event) {
    const board = event.currentTarget;
    if (!board) return;
    const leavingTo = event.relatedTarget;
    if (leavingTo && board.contains(leavingTo)) return;
    delete manualPlanner.previews[board.dataset.slotKey];
    renderManualBoards();
}

function onManualBoardDrop(event) {
    event.preventDefault();
    const payload = extractManualDragPayload(event);
    if (!payload) return;

    const board = event.currentTarget;
    const slotKey = board.dataset.slotKey;
    const scale = parseFloat(board.dataset.scale);
    const stage = board.querySelector('.manual-board-stage');
    const rect = stage.getBoundingClientRect();
    const dropX = Math.max(0, event.clientX - rect.left);
    const dropY = Math.max(0, event.clientY - rect.top);
    const token = pickManualToken(payload);
    if (!token) return;

    const stageLength = parseFloat(board.dataset.length || '0');
    const stageWidth = parseFloat(board.dataset.width || '0');
    delete manualPlanner.previews[slotKey];
    placeManualToken(token, slotKey, null, dropX / scale, dropY / scale, stageLength, stageWidth);
}

function returnPlacedToken(tokenId) {
    const index = manualPlanner.placed.findIndex(entry => entry.id === tokenId);
    if (index < 0) return;
    manualPlanner.palette.push(stripPlacement(manualPlanner.placed[index]));
    manualPlanner.placed.splice(index, 1);
    if (manualPlanner.selectedPlacedId === tokenId) {
        manualPlanner.selectedPlacedId = null;
    }
    renderManualPlanner();
}

function removePlacedToken(tokenId) {
    returnPlacedToken(tokenId);
}

function extractManualDragPayload(event) {
    const raw = event.dataTransfer.getData('text/plain');
    let payload = manualPlanner.dragPayload;
    if (raw) {
        try { payload = JSON.parse(raw); } catch (_) {}
    }
    return payload;
}

function pickManualToken(payload) {
    if (!payload) return null;
    if (payload.sourceType === 'palette') {
        const index = manualPlanner.palette.findIndex(entry => entry.id === payload.tokenId);
        if (index < 0) return null;
        const token = structuredClone(manualPlanner.palette[index]);
        manualPlanner.palette.splice(index, 1);
        return token;
    }

    const index = manualPlanner.placed.findIndex(entry => entry.id === payload.tokenId);
    if (index < 0) return null;
    const token = structuredClone(manualPlanner.placed[index]);
    manualPlanner.placed.splice(index, 1);
    return token;
}

function peekManualToken(payload) {
    if (!payload) return null;
    if (payload.sourceType === 'palette') {
        const token = manualPlanner.palette.find(entry => entry.id === payload.tokenId);
        return token ? structuredClone(token) : null;
    }

    const token = manualPlanner.placed.find(entry => entry.id === payload.tokenId);
    return token ? structuredClone(token) : null;
}

function restoreManualToken(token, sourceType) {
    if (!token) return;
    if (sourceType === 'placed') {
        manualPlanner.placed.push(token);
        return;
    }
    manualPlanner.palette.push(stripPlacement(token));
}

function placeManualToken(token, slotKey, targetLastro, x, y, stageLength, stageWidth) {
    const placement = determineAutomaticPlacement(token, slotKey, x, y, stageLength, stageWidth, token.id || null, targetLastro);
    if (!placement) {
        restoreManualToken(token, token.vehicle_slot_key ? 'placed' : 'palette');
        showToast('Nao encontrei apoio valido para posicionar o item nesse ponto.', 'warning');
        return;
    }

    const snap = placement.snap;
    token.vehicle_slot_key = slotKey;
    token.lastro_posicao = placement.lastro;
    token.posicao_x = snap.x;
    token.posicao_y = snap.y;
    token.posicao_z = placement.supportZ;
    token.snap_hint = placement.message;
    manualPlanner.placed.push(token);
    manualPlanner.selectedPlacedId = token.id;
    manualPlanner.dragPayload = null;
    delete manualPlanner.previews[slotKey];
    renderManualPlanner();
}

function allocatePaletteTokenToSlot(tokenId, slotKey, targetLastro, x, y, stageLength, stageWidth) {
    const index = manualPlanner.palette.findIndex(entry => entry.id === tokenId);
    if (index < 0) return;
    const token = structuredClone(manualPlanner.palette[index]);
    manualPlanner.palette.splice(index, 1);
    placeManualToken(token, slotKey, targetLastro, x, y, stageLength, stageWidth);
}

function movePlacedTokenToSlot(tokenId, slotKey, targetLastro, x, y, stageLength, stageWidth) {
    const token = manualPlanner.placed.find(entry => entry.id === tokenId && entry.vehicle_slot_key === slotKey);
    if (!token) return;
    const placement = determineAutomaticPlacement(token, slotKey, x, y, stageLength, stageWidth, tokenId, targetLastro);
    if (!placement) {
        showToast('Nao encontrei apoio valido para mover o item para esse ponto.', 'warning');
        return;
    }

    token.lastro_posicao = placement.lastro;
    token.posicao_x = placement.snap.x;
    token.posicao_y = placement.snap.y;
    token.posicao_z = placement.supportZ;
    token.snap_hint = placement.message;
    manualPlanner.selectedPlacedId = tokenId;
    delete manualPlanner.previews[slotKey];
    renderManualPlanner();
}

function overlaps2D(ax, ay, aw, ah, bx, by, bw, bh) {
    return ax < (bx + bw)
        && (ax + aw) > bx
        && ay < (by + bh)
        && (ay + ah) > by;
}

function getLayerOnePlacements(slotKey, movingTokenId = null) {
    return manualPlanner.placed.filter(entry =>
        entry.vehicle_slot_key === slotKey &&
        parseInt(entry.lastro_posicao || 1, 10) === 1 &&
        entry.id !== movingTokenId
    );
}

function isTargetingOccupiedArea(slotKey, token, rawX, rawY, movingTokenId = null) {
    return getLayerOnePlacements(slotKey, movingTokenId).some(entry =>
        overlaps2D(
            rawX,
            rawY,
            parseFloat(token.comprimento_m || 0),
            parseFloat(token.largura_m || 0),
            parseFloat(entry.posicao_x || 0),
            parseFloat(entry.posicao_y || 0),
            parseFloat(entry.comprimento_m || 0),
            parseFloat(entry.largura_m || 0)
        )
    );
}

function canTokenUseSecondLayer(token, slotKey) {
    const slot = manualPlanner.slotDefs.find(entry => entry.slot_key === slotKey);
    const slotMaxLastros = parseInt(slot?.max_lastros ?? 1, 10);
    const tokenMaxLastros = parseInt(token.max_lastros ?? 1, 10);
    const tokenEmpilhavel = parseInt(token.empilhavel ?? 1, 10);
    return slotMaxLastros >= 2 && tokenMaxLastros >= 2 && tokenEmpilhavel === 1;
}

function detectDirectSecondLayerSupport(token, slotKey, rawX, rawY, movingTokenId = null) {
    if (!canTokenUseSecondLayer(token, slotKey)) {
        return null;
    }

    if (token.isBobina && String(token.perfil_empilhamento || '').toLowerCase() === 'piramidal') {
        const pairSupport = findPyramidalPairSupportInBoard(slotKey, token, rawX, rawY, movingTokenId);
        if (pairSupport) {
            return {
                x: pairSupport.x,
                y: pairSupport.y,
                z: pairSupport.z,
                message: 'IMA: apoio piramidal detectado'
            };
        }
    }

    const supports = getLayerOnePlacements(slotKey, movingTokenId)
        .filter(entry =>
            canTokenSupport(token, entry) &&
            overlaps2D(
                rawX,
                rawY,
                parseFloat(token.comprimento_m || 0),
                parseFloat(token.largura_m || 0),
                parseFloat(entry.posicao_x || 0),
                parseFloat(entry.posicao_y || 0),
                parseFloat(entry.comprimento_m || 0),
                parseFloat(entry.largura_m || 0)
            )
        )
        .map(entry => {
            const clampedX = round2(clamp(
                rawX,
                parseFloat(entry.posicao_x || 0),
                parseFloat(entry.posicao_x || 0) + parseFloat(entry.comprimento_m || 0) - parseFloat(token.comprimento_m || 0)
            ));
            const clampedY = round2(clamp(
                rawY,
                parseFloat(entry.posicao_y || 0),
                parseFloat(entry.posicao_y || 0) + parseFloat(entry.largura_m || 0) - parseFloat(token.largura_m || 0)
            ));
            const overlapWidth = Math.max(0, Math.min(rawX + parseFloat(token.comprimento_m || 0), parseFloat(entry.posicao_x || 0) + parseFloat(entry.comprimento_m || 0)) - Math.max(rawX, parseFloat(entry.posicao_x || 0)));
            const overlapHeight = Math.max(0, Math.min(rawY + parseFloat(token.largura_m || 0), parseFloat(entry.posicao_y || 0) + parseFloat(entry.largura_m || 0)) - Math.max(rawY, parseFloat(entry.posicao_y || 0)));
            return {
                entry,
                x: clampedX,
                y: clampedY,
                z: parseFloat(entry.altura_m || 0),
                score: (overlapWidth * overlapHeight)
            };
        })
        .sort((a, b) => b.score - a.score);

    if (!supports.length) {
        return null;
    }

    return {
        x: supports[0].x,
        y: supports[0].y,
        z: supports[0].z,
        message: `IMA: apoio sobre ${supports[0].entry.label || supports[0].entry.material_codigo || supports[0].entry.codigo_material}`
    };
}

function determineAutomaticPlacement(token, slotKey, x, y, stageLength, stageWidth, movingTokenId = null, forcedLastro = null) {
    const rawX = round2(clamp(x, 0, Math.max(0, stageLength - parseFloat(token.comprimento_m || 0))));
    const rawY = round2(clamp(y, 0, Math.max(0, stageWidth - parseFloat(token.largura_m || 0))));
    const targetingOccupied = isTargetingOccupiedArea(slotKey, token, rawX, rawY, movingTokenId);
    const directSupport = detectDirectSecondLayerSupport(token, slotKey, rawX, rawY, movingTokenId);

    if ((!forcedLastro || parseInt(forcedLastro || 0, 10) === 2) && targetingOccupied && directSupport) {
        return {
            lastro: 2,
            snap: {
                x: directSupport.x,
                y: directSupport.y,
                message: directSupport.message,
                guidesX: [],
                guidesY: []
            },
            supportZ: directSupport.z,
            message: `${directSupport.message} | detectado L2`
        };
    }

    const candidates = [];
    const tryLayers = forcedLastro ? [parseInt(forcedLastro || 1, 10)] : [1, 2];

    for (const layer of tryLayers) {
        if (layer === 2 && !canTokenUseSecondLayer(token, slotKey)) {
            continue;
        }

        const snap = computeMagneticPlacement(token, slotKey, layer, rawX, rawY, stageLength, stageWidth, movingTokenId);
        const supportZ = computeManualTokenSupportZ(token, slotKey, layer, snap.x, snap.y, movingTokenId);
        if (layer === 2 && supportZ === null) {
            continue;
        }

        candidates.push({
            lastro: layer,
            snap,
            supportZ: supportZ ?? 0,
            message: `${snap.message} | detectado L${layer}`
        });
    }

    if (!candidates.length) {
        return null;
    }

    const firstLayerCandidate = candidates.find(candidate => candidate.lastro === 1);
    if (firstLayerCandidate) {
        return firstLayerCandidate;
    }

    return candidates.find(candidate => candidate.lastro === 2) || candidates[0];
}

function canTokenSupport(topToken, supportToken) {
    return parseFloat(supportToken.comprimento_m || 0) + 0.05 >= parseFloat(topToken.comprimento_m || 0)
        && parseFloat(supportToken.largura_m || 0) + 0.05 >= parseFloat(topToken.largura_m || 0);
}

function findPyramidalPairSupportInBoard(slotKey, token, posX, posY, movingTokenId = null) {
    const baseMatches = manualPlanner.placed.filter(entry =>
        entry.vehicle_slot_key === slotKey &&
        parseInt(entry.lastro_posicao || 1, 10) === 1 &&
        entry.id !== movingTokenId &&
        entry.isBobina &&
        entry.material_codigo === token.material_codigo &&
        entry.base_nome === token.base_nome &&
        parseInt(entry.ordem_entrega || 0, 10) === parseInt(token.ordem_entrega || 0, 10)
    );

    if (baseMatches.length < 2) {
        return null;
    }

    for (let i = 0; i < baseMatches.length - 1; i++) {
        for (let j = i + 1; j < baseMatches.length; j++) {
            const a = baseMatches[i];
            const b = baseMatches[j];
            const sameTrackY = Math.abs(parseFloat(a.posicao_y) - parseFloat(b.posicao_y)) <= 0.2;
            const adjacentX = Math.abs((parseFloat(a.posicao_x) + parseFloat(a.comprimento_m)) - parseFloat(b.posicao_x)) <= 0.3
                || Math.abs((parseFloat(b.posicao_x) + parseFloat(b.comprimento_m)) - parseFloat(a.posicao_x)) <= 0.3;

            if (!sameTrackY || !adjacentX) {
                continue;
            }

            const centerAX = parseFloat(a.posicao_x) + (parseFloat(a.comprimento_m) / 2);
            const centerBX = parseFloat(b.posicao_x) + (parseFloat(b.comprimento_m) / 2);
            const candidate = {
                x: round2(((centerAX + centerBX) / 2) - (parseFloat(token.comprimento_m) / 2)),
                y: round2((parseFloat(a.posicao_y) + parseFloat(b.posicao_y)) / 2),
                z: round2(Math.max(parseFloat(a.altura_m), parseFloat(b.altura_m)) * 0.73)
            };

            if (
                Math.abs(candidate.x - posX) <= 0.3 && Math.abs(candidate.y - posY) <= 0.3
                || overlaps2D(
                    posX,
                    posY,
                    parseFloat(token.comprimento_m || 0),
                    parseFloat(token.largura_m || 0),
                    candidate.x,
                    candidate.y,
                    parseFloat(token.comprimento_m || 0),
                    parseFloat(token.largura_m || 0)
                )
            ) {
                return candidate;
            }
        }
    }

    return null;
}

function computeManualTokenSupportZ(token, slotKey, targetLastro, posX, posY, movingTokenId = null) {
    if (parseInt(targetLastro || 1, 10) !== 2) {
        return 0;
    }

    if (token.isBobina && String(token.perfil_empilhamento || '').toLowerCase() === 'piramidal') {
        const pairSupport = findPyramidalPairSupportInBoard(slotKey, token, posX, posY, movingTokenId);
        if (pairSupport) {
            return pairSupport.z;
        }
    }

    const support = manualPlanner.placed.find(entry =>
        entry.vehicle_slot_key === slotKey &&
        parseInt(entry.lastro_posicao || 1, 10) === 1 &&
        entry.id !== movingTokenId &&
        canTokenSupport(token, entry) &&
        posX >= parseFloat(entry.posicao_x) - 0.001 &&
        posY >= parseFloat(entry.posicao_y) - 0.001 &&
        (posX + parseFloat(token.comprimento_m)) <= (parseFloat(entry.posicao_x) + parseFloat(entry.comprimento_m) + 0.001) &&
        (posY + parseFloat(token.largura_m)) <= (parseFloat(entry.posicao_y) + parseFloat(entry.largura_m) + 0.001)
    );

    return support ? parseFloat(support.altura_m || 0) : null;
}

function hideAutoStatus() {
    const box = document.getElementById('simulacaoAutoStatus');
    if (!box) return;
    box.classList.add('d-none');
    box.textContent = '';
}

function showAutoStatus(message) {
    const box = document.getElementById('simulacaoAutoStatus');
    if (!box) return;
    box.textContent = message;
    box.classList.remove('d-none');
}

function createPlacementTokenFromAuto(item, fallbackUnitKey, fallbackLabel) {
    const isBobina = String(item.formato_fisico || '').toLowerCase() === 'bobina' || String(item.codigo_material || '').toUpperCase().includes('BOB');
    const dims = getTransportDimensions(item.comprimento_m, item.largura_m, item.altura_m, isBobina);
    return {
        id: `auto:${fallbackUnitKey}`,
        tokenType: 'unit',
        unit_key: fallbackUnitKey,
        label: fallbackLabel || item.codigo_material,
        subtitle: `${item.base_nome} | ordem ${item.ordem_entrega}`,
        material_codigo: item.codigo_material,
        descricao_material: item.descricao_material,
        base_nome: item.base_nome,
        ordem_entrega: parseInt(item.ordem_entrega, 10),
        comprimento_m: dims.comprimento_m,
        largura_m: dims.largura_m,
        altura_m: dims.altura_m,
        peso_unitario_kg: parseFloat(item.peso_unitario_kg),
        volume_unitario_m3: parseFloat(item.volume_unitario_m3),
        isBobina,
        color: item.cor_hex || baseColor(item.base_nome),
        vehicle_slot_key: null,
        lastro_posicao: parseInt(item.lastro_posicao || 1, 10),
        posicao_x: parseFloat(item.posicao_x || 0),
        posicao_y: parseFloat(item.posicao_y || 0),
        posicao_z: parseFloat(item.posicao_z || 0),
        perfil_empilhamento: item.perfil_empilhamento || '',
        members: [{
            unit_key: fallbackUnitKey,
            label: fallbackLabel || item.codigo_material,
            comprimento_m: dims.comprimento_m,
            largura_m: dims.largura_m,
            altura_m: dims.altura_m,
            peso_unitario_kg: parseFloat(item.peso_unitario_kg),
            isBobina,
            perfil_empilhamento: item.perfil_empilhamento || ''
        }]
    };
}

function applyAutomaticSimulationToBoard(simulation) {
    const pedidoBundle = buildSelectedPedidoBundle();
    if (!pedidoBundle) return;

    initializeManualPlanner();
    const slotDefs = buildSlotDefinitions();
    manualPlanner.slotDefs = slotDefs;

    const originalUnits = expandPedidoUnitsForManual(pedidoBundle);
    const pool = new Map();
    originalUnits.forEach(unit => {
        const key = String(unit.unit_key).split(':').slice(0, 4).join(':');
        if (!pool.has(key)) pool.set(key, []);
        pool.get(key).push(structuredClone(unit));
    });

    manualPlanner.placed = [];

    (simulation.veiculos || []).forEach(vehicle => {
        const slot = slotDefs.find(entry =>
            parseInt(entry.veiculo_id, 10) === parseInt(vehicle.veiculo_id, 10) &&
            String(entry.nome) === String(vehicle.veiculo_nome) &&
            String(entry.slot_key).endsWith(`:${String(vehicle.slot_codigo).split('#').pop().trim()}`)
        ) || slotDefs.find(entry => parseInt(entry.veiculo_id, 10) === parseInt(vehicle.veiculo_id, 10));

        if (!slot) return;

        (vehicle.itens || []).forEach(item => {
            const groupKey = `${item.pedido_item_id}:${item.material_id}:${item.base_id}:${item.ordem_entrega}`;
            const available = pool.get(groupKey) || [];
            const unit = available.shift();
            pool.set(groupKey, available);
            const unitKey = unit?.unit_key || `${groupKey}:auto`;
            const label = unit?.label || `${item.codigo_material}`;
            const token = createPlacementTokenFromAuto(item, unitKey, label);
            token.id = unit?.id || `auto:${unitKey}`;
            token.vehicle_slot_key = slot.slot_key;
            token.lastro_posicao = parseInt(item.lastro_posicao || 1, 10);
            token.posicao_x = parseFloat(item.posicao_x || 0);
            token.posicao_y = parseFloat(item.posicao_y || 0);
            manualPlanner.placed.push(token);
        });
    });

    manualPlanner.palette = [];
    pool.forEach(units => {
        units.forEach(unit => manualPlanner.palette.push(unit));
    });
    manualPlanner.selectedIds = new Set();
    manualPlanner.selectedPlacedId = null;
    manualPlanner.previews = {};
    renderManualPlanner();
}

function computeMagneticPlacement(token, slotKey, targetLastro, x, y, stageLength, stageWidth, movingTokenId = null) {
    const length = parseFloat(token.comprimento_m || 0);
    const width = parseFloat(token.largura_m || 0);
    const maxX = Math.max(0, stageLength - length);
    const maxY = Math.max(0, stageWidth - width);
    let snappedX = round2(Math.min(maxX, Math.max(0, x)));
    let snappedY = round2(Math.min(maxY, Math.max(0, y)));

    const sameLayerTokens = manualPlanner.placed.filter(entry =>
        entry.vehicle_slot_key === slotKey &&
        parseInt(entry.lastro_posicao || 1, 10) === parseInt(targetLastro || 1, 10) &&
        (!movingTokenId || entry.id !== movingTokenId)
    );

    const snapX = findBestSnapOnAxis(snappedX, length, stageLength, sameLayerTokens, 'x');
    const snapY = findBestSnapOnAxis(snappedY, width, stageWidth, sameLayerTokens, 'y');

    snappedX = snapX.value;
    snappedY = snapY.value;

    const messages = [snapX.reason, snapY.reason].filter(Boolean);
    return {
        x: snappedX,
        y: snappedY,
        message: messages.length ? `IMA: ${messages.join(' + ')}` : 'IMA: grade fina',
        guidesX: snapX.guide !== null ? [snapX.guide] : [],
        guidesY: snapY.guide !== null ? [snapY.guide] : []
    };
}

function findBestSnapOnAxis(value, itemSize, stageSize, neighbors, axis) {
    const cfg = manualPlanner.snapConfig;
    const max = Math.max(0, stageSize - itemSize);
    const candidates = [
        { value: 0, score: cfg.edgeTolerance, reason: axis === 'x' ? 'parede frontal' : 'lateral esquerda', guide: 0 },
        { value: max, score: cfg.edgeTolerance, reason: axis === 'x' ? 'traseira' : 'lateral direita', guide: stageSize }
    ];

    const gridValue = round2(Math.round(value / cfg.gridStep) * cfg.gridStep);
    if (Math.abs(gridValue - value) <= cfg.gridTolerance) {
        candidates.push({ value: clamp(gridValue, 0, max), score: cfg.gridTolerance, reason: 'grade', guide: gridValue });
    }

    neighbors.forEach(neighbor => {
        const start = parseFloat(axis === 'x' ? neighbor.posicao_x : neighbor.posicao_y);
        const size = parseFloat(axis === 'x' ? neighbor.comprimento_m : neighbor.largura_m);
        const end = start + size;
        candidates.push({ value: start, score: cfg.neighborTolerance, reason: `alinhado com ${neighbor.label || neighbor.material_codigo || 'volume'}`, guide: start });
        candidates.push({ value: end, score: cfg.neighborTolerance, reason: `encostado em ${neighbor.label || neighbor.material_codigo || 'volume'}`, guide: end });
        candidates.push({ value: start - itemSize, score: cfg.neighborTolerance, reason: `encaixe antes de ${neighbor.label || neighbor.material_codigo || 'volume'}`, guide: start });
        candidates.push({ value: end - itemSize, score: cfg.neighborTolerance, reason: `alinhado pela traseira de ${neighbor.label || neighbor.material_codigo || 'volume'}`, guide: end });
    });

    let best = { value: round2(clamp(value, 0, max)), reason: '', guide: null, distance: Number.POSITIVE_INFINITY };
    candidates.forEach(candidate => {
        const normalized = round2(clamp(candidate.value, 0, max));
        const distance = Math.abs(normalized - value);
        if (distance > candidate.score + 0.0001) return;
        if (distance < best.distance) {
            best = { value: normalized, reason: candidate.reason, guide: candidate.guide, distance };
        }
    });

    return best;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function buildManualStageId(slotKey) {
    return `manual3d-stage-${sanitizeDomId(slotKey)}`;
}

function buildManualOverlayId(slotKey) {
    return `manual3d-overlay-${sanitizeDomId(slotKey)}`;
}

function sanitizeDomId(value) {
    return String(value).replaceAll(':', '-').replaceAll(' ', '-');
}

function destroyManual3DScenes() {
    Object.values(manualPlanner.renderers).forEach(state => {
        if (state?.slotKey && state?.camera && state?.controls) {
            storeManual3DViewState(state.slotKey, state.camera, state.controls);
        }
        if (state.animationFrame) cancelAnimationFrame(state.animationFrame);
        if (state.controls) state.controls.dispose();
        if (state.renderer) state.renderer.dispose();
    });
    manualPlanner.renderers = {};
}

function renderManual3DBoards() {
    if (!window.THREE) {
        showToast('Three.js não está disponível para a montagem 3D.', 'error');
        return;
    }

    manualPlanner.slotDefs.forEach(slot => {
        const stage = document.getElementById(buildManualStageId(slot.slot_key));
        const overlay = document.getElementById(buildManualOverlayId(slot.slot_key));
        if (!stage || !overlay) return;

        const tokens = manualPlanner.placed.filter(token => token.vehicle_slot_key === slot.slot_key);
        const rendererState = createManual3DScene(stage, overlay, slot, tokens);
        manualPlanner.renderers[slot.slot_key] = rendererState;
    });
}

function createManual3DScene(stage, overlay, slot, tokens) {
    const widthPx = Math.max(520, stage.clientWidth || 520);
    const heightPx = 380;
    stage.innerHTML = '';
    manualPlanner.active3DDrag = null;

    const paletteLayout = build3DPaletteLayout(slot);
    const defaultTargetX = round2(paletteLayout.centerX * 0.42);

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xe2e8f0);

    const camera = new THREE.PerspectiveCamera(42, widthPx / heightPx, 0.1, 1000);
    const savedView = manualPlanner.viewStateBySlot[slot.slot_key] || null;
    if (savedView) {
        camera.position.set(savedView.position.x, savedView.position.y, savedView.position.z);
    } else {
        camera.position.set(slot.comprimento_m * 1.45 + (paletteLayout.stageLength * 0.25), slot.altura_m * 2.75, slot.largura_m * 4.25);
    }

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(window.devicePixelRatio || 1);
    renderer.setSize(widthPx, heightPx);
    renderer.shadowMap.enabled = true;
    stage.appendChild(renderer.domElement);

    const controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    if (savedView) {
        controls.target.set(savedView.target.x, savedView.target.y, savedView.target.z);
    } else {
        controls.target.set(defaultTargetX, slot.altura_m * 0.45, 0);
    }
    controls.update();
    controls.addEventListener('change', () => {
        storeManual3DViewState(slot.slot_key, camera, controls);
    });

    scene.add(new THREE.AmbientLight(0xffffff, 0.92));
    const sun = new THREE.DirectionalLight(0xffffff, 0.95);
    sun.position.set(slot.comprimento_m, slot.altura_m * 4, slot.largura_m * 2);
    sun.castShadow = true;
    scene.add(sun);

    const floorSpanX = Math.max(slot.comprimento_m + paletteLayout.stageLength + 6, 18);
    const floor = new THREE.Mesh(
        new THREE.PlaneGeometry(floorSpanX, Math.max(paletteLayout.stageWidth + 4, slot.largura_m * 2.2)),
        new THREE.MeshStandardMaterial({ color: 0xcbd5e1, roughness: 0.95, metalness: 0.02 })
    );
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(round2((paletteLayout.centerX - (slot.comprimento_m / 2)) / 2), 0, 0);
    floor.receiveShadow = true;
    scene.add(floor);

    const bedTopY = 0.18;
    const bed = new THREE.Mesh(
        new THREE.BoxGeometry(slot.comprimento_m, 0.18, slot.largura_m),
        new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.7, metalness: 0.22 })
    );
    bed.position.set(0, bedTopY / 2, 0);
    bed.castShadow = true;
    bed.receiveShadow = true;
    scene.add(bed);

    const walls = new THREE.LineSegments(
        new THREE.EdgesGeometry(new THREE.BoxGeometry(slot.comprimento_m, slot.altura_m, slot.largura_m)),
        new THREE.LineBasicMaterial({ color: 0x64748b })
    );
    walls.position.set(0, slot.altura_m / 2 + bedTopY, 0);
    scene.add(walls);

    const cabin = new THREE.Mesh(
        new THREE.BoxGeometry(1.6, slot.altura_m + 0.7, slot.largura_m * 0.94),
        new THREE.MeshStandardMaterial({ color: 0x2563eb, roughness: 0.4, metalness: 0.18 })
    );
    cabin.position.set(-(slot.comprimento_m / 2) - 0.85, (slot.altura_m + 0.7) / 2, 0);
    cabin.castShadow = true;
    scene.add(cabin);

    const stagingPlatform = new THREE.Mesh(
        new THREE.BoxGeometry(paletteLayout.stageLength, 0.16, paletteLayout.stageWidth),
        new THREE.MeshStandardMaterial({ color: 0xe5e7eb, roughness: 0.72, metalness: 0.05 })
    );
    stagingPlatform.position.set(paletteLayout.centerX, 0.08, 0);
    stagingPlatform.castShadow = true;
    stagingPlatform.receiveShadow = true;
    scene.add(stagingPlatform);

    const stagingOutline = new THREE.LineSegments(
        new THREE.EdgesGeometry(new THREE.BoxGeometry(paletteLayout.stageLength, 0.16, paletteLayout.stageWidth)),
        new THREE.LineBasicMaterial({ color: 0x94a3b8 })
    );
    stagingOutline.position.set(paletteLayout.centerX, 0.08, 0);
    scene.add(stagingOutline);

    const truckDropPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(slot.comprimento_m, slot.largura_m),
        new THREE.MeshBasicMaterial({ visible: false })
    );
    truckDropPlane.rotation.x = -Math.PI / 2;
    truckDropPlane.position.set(0, bedTopY + 0.02, 0);
    scene.add(truckDropPlane);

    const yardDropPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(paletteLayout.stageLength, paletteLayout.stageWidth),
        new THREE.MeshBasicMaterial({ visible: false })
    );
    yardDropPlane.rotation.x = -Math.PI / 2;
    yardDropPlane.position.set(paletteLayout.centerX, 0.18, 0);
    scene.add(yardDropPlane);

    let previewMesh = null;
    let previewTokenSignature = '';
    const interactiveMeshes = [];
    tokens.forEach(token => {
        const mesh = createManualMeshForToken(token);
        const x = -(slot.comprimento_m / 2) + parseFloat(token.posicao_x) + (parseFloat(token.comprimento_m) / 2);
        const z = -(slot.largura_m / 2) + parseFloat(token.posicao_y) + (parseFloat(token.largura_m) / 2);
        const y = bedTopY + parseFloat(token.posicao_z || 0) + (parseFloat(token.altura_m) / 2) + 0.02;
        mesh.position.set(x, y, z);
        mesh.userData = {
            tokenId: token.id,
            sourceType: 'placed',
            slotKey: slot.slot_key,
            token
        };
        if (manualPlanner.selectedPlacedId === token.id) {
            mesh.material.emissive = new THREE.Color(0xffffff);
            mesh.material.emissiveIntensity = 0.2;
        }
        interactiveMeshes.push(mesh);
        scene.add(mesh);
    });

    paletteLayout.items.forEach(item => {
        const mesh = createManualMeshForToken(item.token, item.dims);
        mesh.position.set(item.x, item.y, item.z);
        mesh.userData = {
            tokenId: item.token.id,
            sourceType: 'palette',
            slotKey: slot.slot_key,
            token: item.token,
            dims: item.dims
        };
        interactiveMeshes.push(mesh);
        scene.add(mesh);
    });

    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const DRAG_THRESHOLD = 5;

    function updatePointer(event) {
        const rect = renderer.domElement.getBoundingClientRect();
        pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    }

    function getIntersectedInteractive(event) {
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);
        return raycaster.intersectObjects(interactiveMeshes, false)[0] || null;
    }

    function resolve3DDragTarget(event, token, sourceType, tokenId) {
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);

        const truckHit = raycaster.intersectObject(truckDropPlane, false)[0];
        if (truckHit) {
            const positionX = truckHit.point.x + (slot.comprimento_m / 2) - (parseFloat(token.comprimento_m || 0) / 2);
            const positionY = truckHit.point.z + (slot.largura_m / 2) - (parseFloat(token.largura_m || 0) / 2);
            const placement = determineAutomaticPlacement(
                token,
                slot.slot_key,
                positionX,
                positionY,
                slot.comprimento_m,
                slot.largura_m,
                sourceType === 'placed' ? tokenId : null
            );
            if (!placement) {
                return null;
            }

            return {
                zone: 'truck',
                lastro: placement.lastro,
                snap: placement.snap,
                supportZ: placement.supportZ,
                dims: {
                    comprimento_m: parseFloat(token.comprimento_m),
                    largura_m: parseFloat(token.largura_m),
                    altura_m: parseFloat(token.altura_m)
                },
                meshPosition: {
                    x: round2(-(slot.comprimento_m / 2) + placement.snap.x + (parseFloat(token.comprimento_m) / 2)),
                    y: round2(bedTopY + placement.supportZ + (parseFloat(token.altura_m) / 2) + 0.02),
                    z: round2(-(slot.largura_m / 2) + placement.snap.y + (parseFloat(token.largura_m) / 2))
                },
                message: `${token.label} | ${placement.message}`
            };
        }

        const yardHit = raycaster.intersectObject(yardDropPlane, false)[0];
        if (yardHit) {
            const dims = get3DPaletteDisplayDimensions(token);
            const minX = paletteLayout.centerX - (paletteLayout.stageLength / 2) + (dims.comprimento_m / 2) + 0.2;
            const maxX = paletteLayout.centerX + (paletteLayout.stageLength / 2) - (dims.comprimento_m / 2) - 0.2;
            const minZ = -(paletteLayout.stageWidth / 2) + (dims.largura_m / 2) + 0.2;
            const maxZ = (paletteLayout.stageWidth / 2) - (dims.largura_m / 2) - 0.2;
            return {
                zone: 'yard',
                dims,
                meshPosition: {
                    x: round2(clamp(yardHit.point.x, minX, maxX)),
                    y: round2((dims.altura_m / 2) + 0.1),
                    z: round2(clamp(yardHit.point.z, minZ, maxZ))
                },
                message: sourceType === 'placed'
                    ? `${token.label} | solte no patio para retirar da carreta`
                    : `${token.label} | material aguardando fora da carreta`
            };
        }

        return null;
    }

    function showManual3DPreview(token, target) {
        const signature = `${target.zone}:${token.isBobina ? 'bobina' : 'box'}:${target.dims.comprimento_m}:${target.dims.largura_m}:${target.dims.altura_m}:${token.color}`;
        if (!previewMesh || previewTokenSignature !== signature) {
            if (previewMesh) {
                scene.remove(previewMesh);
            }
            previewMesh = createManualPreviewMesh(token, target.dims);
            previewTokenSignature = signature;
            scene.add(previewMesh);
        }

        previewMesh.position.set(target.meshPosition.x, target.meshPosition.y, target.meshPosition.z);
        previewMesh.visible = true;
    }

    function hideManual3DPreview() {
        if (previewMesh) {
            previewMesh.visible = false;
        }
    }

    renderer.domElement.addEventListener('pointermove', (event) => {
        const drag = manualPlanner.active3DDrag;
        if (drag) {
            const dragDistance = Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY);
            if (!drag.moved && dragDistance >= DRAG_THRESHOLD) {
                drag.moved = true;
                if (drag.mesh) {
                    drag.mesh.visible = false;
                }
            }

            if (!drag.moved) {
                return;
            }

            const token = peekManualToken({ tokenId: drag.tokenId, sourceType: drag.sourceType });
            if (!token) {
                return;
            }

            const target = resolve3DDragTarget(event, token, drag.sourceType, drag.tokenId);
            if (!target) {
                hideManual3DPreview();
                overlay.textContent = 'Arraste o volume entre o patio e a carreta 3D.';
                return;
            }

            showManual3DPreview(token, target);
            overlay.textContent = target.message;
            return;
        }

        const hit = getIntersectedInteractive(event);
        if (!hit) {
            overlay.textContent = 'Mouse para rotacionar, scroll para zoom. Arraste os volumes do patio 3D para a carreta.';
            return;
        }

        const token = hit.object.userData.token;
        const hint = hit.object.userData.sourceType === 'palette'
            ? 'fora da carreta'
            : `lastro ${token.lastro_posicao}`;
        overlay.textContent = `${token.label} | ${token.comprimento_m.toFixed(2)} x ${token.largura_m.toFixed(2)} m | ${hint}`;
    });

    renderer.domElement.addEventListener('pointerdown', (event) => {
        const hit = getIntersectedInteractive(event);
        if (!hit) {
            return;
        }

        const meta = hit.object.userData;
        manualPlanner.active3DDrag = {
            tokenId: meta.tokenId,
            sourceType: meta.sourceType,
            slotKey: slot.slot_key,
            startX: event.clientX,
            startY: event.clientY,
            moved: false,
            mesh: hit.object
        };

        if (meta.sourceType === 'placed') {
            manualPlanner.selectedPlacedId = meta.tokenId;
        }

        controls.enabled = false;
    });

    function finishManual3DDrag(event) {
        const drag = manualPlanner.active3DDrag;
        if (!drag) return;

        const token = peekManualToken({ tokenId: drag.tokenId, sourceType: drag.sourceType });
        if (drag.mesh) {
            drag.mesh.visible = true;
        }
        controls.enabled = true;

        if (!token) {
            manualPlanner.active3DDrag = null;
            hideManual3DPreview();
            return;
        }

        if (!drag.moved) {
            if (drag.sourceType === 'placed') {
                selectPlacedToken(drag.tokenId);
            }
            manualPlanner.active3DDrag = null;
            hideManual3DPreview();
            return;
        }

        const target = resolve3DDragTarget(event, token, drag.sourceType, drag.tokenId);
        hideManual3DPreview();
        manualPlanner.active3DDrag = null;

        if (!target) {
            overlay.textContent = 'Movimento cancelado. Arraste entre o patio e a carreta para alocar visualmente.';
            return;
        }

        if (target.zone === 'truck') {
            if (drag.sourceType === 'palette') {
                allocatePaletteTokenToSlot(drag.tokenId, slot.slot_key, target.lastro, target.snap.x, target.snap.y, slot.comprimento_m, slot.largura_m);
                return;
            }
            movePlacedTokenToSlot(drag.tokenId, slot.slot_key, target.lastro, target.snap.x, target.snap.y, slot.comprimento_m, slot.largura_m);
            return;
        }

        if (target.zone === 'yard' && drag.sourceType === 'placed') {
            returnPlacedToken(drag.tokenId);
            return;
        }

        renderManualBoards();
    }

    renderer.domElement.addEventListener('pointerup', finishManual3DDrag);
    renderer.domElement.addEventListener('pointerleave', (event) => {
        if (manualPlanner.active3DDrag) {
            finishManual3DDrag(event);
            return;
        }
        hideManual3DPreview();
        overlay.textContent = 'Mouse para rotacionar, scroll para zoom. Arraste os volumes do patio 3D para a carreta.';
    });

    const animate = () => {
        rendererState.animationFrame = requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    };
    const rendererState = { renderer, controls, camera, slotKey: slot.slot_key, animationFrame: null };
    animate();

    return rendererState;
}

function storeManual3DViewState(slotKey, camera, controls) {
    if (!slotKey || !camera || !controls) return;
    manualPlanner.viewStateBySlot[slotKey] = {
        position: {
            x: round2(camera.position.x),
            y: round2(camera.position.y),
            z: round2(camera.position.z)
        },
        target: {
            x: round2(controls.target.x),
            y: round2(controls.target.y),
            z: round2(controls.target.z)
        }
    };
}

function get3DPaletteDisplayDimensions(token) {
    return {
        comprimento_m: round2(clamp(parseFloat(token.comprimento_m || 0.8) * 0.72, 0.75, 2.2)),
        largura_m: round2(clamp(parseFloat(token.largura_m || 0.6) * 0.9, 0.55, 1.45)),
        altura_m: round2(clamp(parseFloat(token.altura_m || 0.5) * 0.9, 0.45, 1.45))
    };
}

function build3DPaletteLayout(slot) {
    const gap = 0.35;
    const usableLength = Math.max(slot.comprimento_m * 0.82, 7.2);
    let cursorX = 0;
    let cursorZ = 0;
    let rowDepth = 0;

    const items = manualPlanner.palette.map(token => {
        const dims = get3DPaletteDisplayDimensions(token);
        if (cursorX > 0 && cursorX + dims.comprimento_m > usableLength) {
            cursorX = 0;
            cursorZ = round2(cursorZ + rowDepth + gap);
            rowDepth = 0;
        }

        const layoutItem = {
            token,
            dims,
            localX: round2(cursorX + (dims.comprimento_m / 2)),
            localZ: round2(cursorZ + (dims.largura_m / 2))
        };

        cursorX = round2(cursorX + dims.comprimento_m + gap);
        rowDepth = Math.max(rowDepth, dims.largura_m);
        return layoutItem;
    });

    const usedWidth = rowDepth > 0 ? cursorZ + rowDepth : slot.largura_m * 1.6;
    const stageLength = Math.max(usableLength + 1.0, 6.8);
    const stageWidth = Math.max(usedWidth + 1.0, slot.largura_m * 2.2, 4.8);
    const centerX = round2((slot.comprimento_m / 2) + 2.6 + (stageLength / 2));
    const startX = round2(centerX - (stageLength / 2) + 0.5);
    const startZ = round2(-(stageWidth / 2) + 0.5);

    return {
        centerX,
        centerZ: 0,
        stageLength,
        stageWidth,
        items: items.map(item => ({
            token: item.token,
            dims: item.dims,
            x: round2(startX + item.localX),
            y: round2((item.dims.altura_m / 2) + 0.08),
            z: round2(startZ + item.localZ)
        }))
    };
}

function createManualMeshForToken(token, dimensions = null) {
    const dims = dimensions || {
        comprimento_m: parseFloat(token.comprimento_m),
        largura_m: parseFloat(token.largura_m),
        altura_m: parseFloat(token.altura_m)
    };
    const color = new THREE.Color(token.color || '#2563eb');
    const material = new THREE.MeshStandardMaterial({ color, roughness: 0.48, metalness: 0.16 });
    let mesh;
    if (token.isBobina) {
        mesh = new THREE.Mesh(
            new THREE.CylinderGeometry(parseFloat(dims.altura_m) / 2, parseFloat(dims.altura_m) / 2, parseFloat(dims.largura_m), 28),
            material
        );
        mesh.rotation.x = Math.PI / 2;
    } else {
        mesh = new THREE.Mesh(
            new THREE.BoxGeometry(parseFloat(dims.comprimento_m), parseFloat(dims.altura_m), parseFloat(dims.largura_m)),
            material
        );
    }
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    return mesh;
}

function createManualPreviewMesh(token, dimensions = null) {
    const dims = dimensions || {
        comprimento_m: parseFloat(token.comprimento_m),
        largura_m: parseFloat(token.largura_m),
        altura_m: parseFloat(token.altura_m)
    };
    const color = new THREE.Color(token.color || '#2563eb');
    const material = new THREE.MeshStandardMaterial({
        color,
        transparent: true,
        opacity: 0.42,
        roughness: 0.35,
        metalness: 0.12,
        emissive: color,
        emissiveIntensity: 0.18
    });
    let mesh;
    if (token.isBobina) {
        mesh = new THREE.Mesh(
            new THREE.CylinderGeometry(parseFloat(dims.altura_m) / 2, parseFloat(dims.altura_m) / 2, parseFloat(dims.largura_m), 28),
            material
        );
        mesh.rotation.x = Math.PI / 2;
    } else {
        mesh = new THREE.Mesh(
            new THREE.BoxGeometry(parseFloat(dims.comprimento_m), parseFloat(dims.altura_m), parseFloat(dims.largura_m)),
            material
        );
    }
    mesh.castShadow = false;
    mesh.receiveShadow = false;
    return mesh;
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
                orientacao_manual: token.isBobina ? 'deitado_axial' : 'base_0',
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
            orientacao_manual: member.isBobina ? 'deitado_axial' : 'base_0'
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
    const pedidoIds = getSelectedPedidoIds();
    if (!pedidoIds.length) {
        showToast('Selecione pelo menos um pedido.', 'warning');
        return;
    }

    const frota = getSelectedFrota();
    if (!frota.length) {
        showToast('Informe pelo menos um veículo com quantidade maior que zero.', 'warning');
        return;
    }

    hideAutoStatus();
    document.getElementById('simulacaoHint').classList.add('d-none');
    document.getElementById('simulacaoLoading').classList.remove('d-none');

    const response = await fetch('../api/executar_simulacao.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            modo: 'automatico',
            pedido_id: pedidoIds[0],
            pedido_ids: pedidoIds,
            frota,
            observacoes: document.getElementById('sim_observacoes').value
        })
    });
    const result = await response.json();
    document.getElementById('simulacaoLoading').classList.add('d-none');
    document.getElementById('simulacaoHint').classList.remove('d-none');

    if (result.status === 'success') {
        applyAutomaticSimulationToBoard(result.data);
        const statusText = `Autoalocação carregada no desenho. Simulação ${result.data.codigo_simulacao} | status ${result.data.status} | score ${result.data.score_total}.`;
        showAutoStatus(statusText);
        showToast('Autoalocação aplicada no desenho.', 'success');
    } else {
        showToast(result.message, 'error');
    }
}

async function salvarMontagemManual() {
    const pedidoIds = getSelectedPedidoIds();
    if (!pedidoIds.length) {
        showToast('Selecione pelo menos um pedido.', 'warning');
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
            pedido_id: pedidoIds[0],
            pedido_ids: pedidoIds,
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
