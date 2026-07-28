<?php
$pageTitle = 'Simulador Visual de Carga';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
require_once __DIR__ . '/../controllers/VeiculoController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

$veiculoController = new VeiculoController();
$veiculos = $veiculoController->listar();

$materialController = new MaterialController();
$materiais = $materialController->listar();
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Simulador e Otimizador de Carga 3D</h2>
            <p class="text-muted mb-0">Selecione o veículo, adicione os materiais e simule a alocação gráfica com regras de empilhamento.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="importar_materiais.php" class="btn btn-outline-primary">
                <i class="fa-solid fa-file-csv me-1"></i> Importar Lista CSV
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Formulário de Seleção de Carga e Veículo -->
        <div class="col-xl-5 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>1. Seleção do Veículo & Configuração
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">VEÍCULO DE TRANSPORTE *</label>
                        <select id="sim_veiculo_id" class="form-select bg-light" onchange="atualizarInfoVeiculo(this.value)">
                            <option value="">-- Selecione o Veículo --</option>
                            <?php foreach ($veiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" data-json='<?= json_encode($v) ?>'>
                                    [<?= $v['tipo'] ?>] <?= $v['nome'] ?> (<?= number_format($v['capacidade_kg'], 0, ',', '.') ?> kg / <?= $v['capacidade_m3'] ?> m³)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Card Informativo do Veículo Selecionado -->
                    <div class="p-3 bg-light rounded-3 mb-3 d-none" id="infoVeiculoCard">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary px-2 py-1" id="vTipo">Munck</span>
                            <small class="text-muted" id="vDimen">6.2m x 2.45m x 1.6m</small>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">CAPACIDADE KG</small>
                                <strong id="vKg">12.000 kg</strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">CAPACIDADE M³</small>
                                <strong id="vM3">24,5 m³</strong>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">LIMITE MÁXIMO DE LASTROS (CAMADAS)</label>
                        <select id="sim_max_lastros" class="form-select bg-light">
                            <option value="1">1 Lastro (Apenas Piso)</option>
                            <option value="2" selected>2 Lastros (Piso + Camada Superior Piramidal)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Adicionar Materiais para Alocação -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-boxes-stacked text-success me-2"></i>2. Lista de Materiais da Carga
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <select id="add_material_id" class="form-select bg-light">
                                <option value="">-- Escolher Material --</option>
                                <?php foreach ($materiais as $m): ?>
                                    <option value="<?= $m['id'] ?>" data-json='<?= json_encode($m) ?>'>
                                        <?= $m['codigo'] ?> - <?= $m['descricao'] ?> (<?= $m['peso_unitario_kg'] ?>kg)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <button class="btn btn-success w-100" onclick="adicionarMaterialLista()">
                                <i class="fa-solid fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>

                    <!-- Tabela de Itens Selecionados -->
                    <div class="table-responsive mb-3" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Material</th>
                                    <th style="width: 80px;">Qtd</th>
                                    <th>Peso Tot.</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="tbodySimItens">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Nenhum material adicionado ainda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">OBSERVAÇÕES OPERACIONAIS</label>
                        <textarea id="sim_observacoes" class="form-control bg-light" rows="2" placeholder="Ex: Carga para a subestação Centro. Entregar às 08h."></textarea>
                    </div>

                    <button class="btn btn-primary w-100 py-2 fs-6 fw-bold" onclick="executarSimulacaoLive()">
                        <i class="fa-solid fa-play me-2"></i> Executar Simulação Visual 3D
                    </button>
                </div>
            </div>
        </div>

        <!-- Renderização Visual 3D & Resultados Live -->
        <div class="col-xl-7 col-lg-6">
            <div id="visualizadorCarga">
                <div class="cargo-simulation-container text-center py-5">
                    <i class="fa-solid fa-cube fa-4x text-primary mb-3"></i>
                    <h4 class="text-white">Aguardando Seleção de Carga</h4>
                    <p class="text-white-50">Selecione o veículo e adicione os materiais ao lado para visualizar a alocação em tempo real.</p>
                </div>
            </div>

            <!-- Resumo e Indicadores Live -->
            <div id="painelResumoLive" class="mt-4 d-none">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-line text-primary me-2"></i>Indicadores da Alocação</h5>
                        
                        <div class="row g-3 text-center mb-3">
                            <div class="col-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">OCUPAÇÃO PESO</small>
                                    <strong class="fs-4 text-primary" id="resPesoPct">0%</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">OCUPAÇÃO VOLUME</small>
                                    <strong class="fs-4 text-info" id="resVolPct">0%</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block">CUBAGEM TOTAL</small>
                                    <strong class="fs-4 text-purple" style="color: #8b5cf6;" id="resCubagem">0 m³</strong>
                                </div>
                            </div>
                        </div>

                        <div id="containerAlertasLive"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let listaMateriaisSimulacao = [];

document.addEventListener('DOMContentLoaded', function() {
    // Verificar se veio dados de importação prévia
    const importados = sessionStorage.getItem('materiaisImportadosSimulacao');
    if (importados && window.location.search.includes('origem=importacao')) {
        const itens = JSON.parse(importados);
        itens.forEach(it => {
            listaMateriaisSimulacao.push({
                material_id: it.material_id,
                codigo: it.codigo,
                descricao: it.descricao,
                quantidade: it.quantidade,
                peso_unitario_kg: it.peso_unitario_kg
            });
        });
        renderTabelaSimItens();
        sessionStorage.removeItem('materiaisImportadosSimulacao');
        showToast('Materiais importados carregados com sucesso!', 'success');
    }
});

function atualizarInfoVeiculo(veiculoId) {
    const infoCard = document.getElementById('infoVeiculoCard');
    if (!veiculoId) {
        infoCard.classList.add('d-none');
        return;
    }

    const select = document.getElementById('sim_veiculo_id');
    const option = select.options[select.selectedIndex];
    const v = JSON.parse(option.getAttribute('data-json'));

    document.getElementById('vTipo').innerText = v.tipo;
    document.getElementById('vDimen').innerText = `${v.comprimento_m}m x ${v.largura_m}m x ${v.altura_m}m`;
    document.getElementById('vKg').innerText = `${parseFloat(v.capacidade_kg).toLocaleString('pt-BR')} kg`;
    document.getElementById('vM3').innerText = `${v.capacidade_m3} m³`;
    infoCard.classList.remove('d-none');
}

function adicionarMaterialLista() {
    const select = document.getElementById('add_material_id');
    const matId = select.value;
    if (!matId) return;

    const option = select.options[select.selectedIndex];
    const m = JSON.parse(option.getAttribute('data-json'));

    const existente = listaMateriaisSimulacao.find(it => it.material_id == matId);
    if (existente) {
        existente.quantidade++;
    } else {
        listaMateriaisSimulacao.push({
            material_id: m.id,
            codigo: m.codigo,
            descricao: m.descricao,
            quantidade: 1,
            peso_unitario_kg: parseFloat(m.peso_unitario_kg)
        });
    }

    renderTabelaSimItens();
}

function renderTabelaSimItens() {
    const tbody = document.getElementById('tbodySimItens');
    if (listaMateriaisSimulacao.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Nenhum material adicionado ainda.</td></tr>';
        return;
    }

    tbody.innerHTML = '';
    listaMateriaisSimulacao.forEach((it, idx) => {
        tbody.innerHTML += `
            <tr>
                <td>
                    <div class="fw-bold small text-dark">${it.codigo}</div>
                    <small class="text-muted d-block text-truncate" style="max-width: 120px;">${it.descricao}</small>
                </td>
                <td>
                    <input type="number" min="1" class="form-control form-control-sm text-center fw-bold" 
                           value="${it.quantidade}" onchange="alterarQtdSimItem(${idx}, this.value)">
                </td>
                <td class="small fw-bold">${(it.peso_unitario_kg * it.quantidade).toLocaleString('pt-BR')} kg</td>
                <td>
                    <button class="btn btn-sm text-danger" onclick="removerSimItem(${idx})"><i class="fa-solid fa-times"></i></button>
                </td>
            </tr>
        `;
    });
}

function alterarQtdSimItem(idx, novaQtd) {
    listaMateriaisSimulacao[idx].quantidade = Math.max(1, parseInt(novaQtd) || 1);
    renderTabelaSimItens();
}

function removerSimItem(idx) {
    listaMateriaisSimulacao.splice(idx, 1);
    renderTabelaSimItens();
}

async function executarSimulacaoLive() {
    const veiculoId = document.getElementById('sim_veiculo_id').value;
    const maxLastros = document.getElementById('sim_max_lastros').value;
    const obs = document.getElementById('sim_observacoes').value;

    if (!veiculoId) {
        showToast('Selecione um veículo para a simulação.', 'warning');
        return;
    }

    if (listaMateriaisSimulacao.length === 0) {
        showToast('Adicione ao menos um material para simular a carga.', 'warning');
        return;
    }

    const payload = {
        veiculo_id: veiculoId,
        max_lastros_permitido: maxLastros,
        observacoes_operacionais: obs,
        materiais: listaMateriaisSimulacao
    };

    try {
        const resp = await fetch('../api/executar_simulacao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast('Simulação executada!', 'success');
            const data = res.data;

            // Renderizar Visualizador 3D Canvas
            window.cargoVis.render(data);

            // Atualizar Painel de Indicadores
            document.getElementById('resPesoPct').innerText = `${data.ocupacao_peso_pct}%`;
            document.getElementById('resVolPct').innerText = `${data.ocupacao_volume_pct}%`;
            document.getElementById('resCubagem').innerText = `${data.cubagem_total_m3} m³`;
            document.getElementById('painelResumoLive').classList.remove('d-none');

            // Renderizar Alertas
            const containerAl = document.getElementById('containerAlertasLive');
            containerAl.innerHTML = '';

            (data.alertas || []).forEach(al => {
                containerAl.innerHTML += `
                    <div class="alert alert-${al.severidade === 'danger' ? 'danger' : (al.severidade === 'warning' ? 'warning' : 'info')} py-2 mb-2 small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> ${al.mensagem}
                    </div>
                `;
            });

        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao processar simulação.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
