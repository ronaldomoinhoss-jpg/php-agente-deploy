<?php
$pageTitle = 'Importar Lista de Materiais';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 text-dark">Importação em Lote de Materiais</h2>
            <p class="text-muted mb-0">Envie um arquivo CSV/Excel ou cole o texto dos materiais para validar e simular a cubagem.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Método 1: Upload de Arquivo CSV -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="fa-solid fa-file-arrow-up me-2"></i>Upload de Arquivo CSV / Excel
                    </h5>
                    <small class="text-muted">Selecione o arquivo formatado com separador ponto e vírgula (;)</small>
                </div>
                <div class="card-body p-4">
                    <form id="formUploadCsv" onsubmit="enviarCsv(event)">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">ARQUIVO CSV</label>
                            <input type="file" name="arquivo_csv" id="arquivo_csv" class="form-control bg-light" accept=".csv, .txt" required>
                        </div>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fa-solid fa-info-circle me-1"></i> Formato esperado: <code>codigo;descricao;quantidade;peso;comprimento;largura;altura;tipo</code>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-upload me-1"></i> Enviar e Processar Arquivo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Método 2: Colar Texto CSV -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-success">
                        <i class="fa-solid fa-paste me-2"></i>Colar Conteúdo Manual
                    </h5>
                    <small class="text-muted">Copie do Excel e cole diretamente no campo abaixo</small>
                </div>
                <div class="card-body p-4">
                    <form id="formTextoCsv" onsubmit="enviarTextoCsv(event)">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">CONTEÚDO CSV / TABULADO</label>
                            <textarea name="csv_texto" id="csv_texto" class="form-control bg-light font-monospace small" rows="4" placeholder="BOB-CAB-120;Bobina de Cabo 120mm²;4;850;1.40;1.40;1.10;bobina_cabo&#10;TRF-TRI-75KVA;Transformador Trifásico 75kVA;2;680;1.10;0.90;1.20;transformador"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fa-solid fa-check-double me-1"></i> Validar e Importar Texto
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Prévia da Carga Importada -->
    <div class="card border-0 shadow-sm rounded-4 d-none" id="cardPreviaImportacao">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-list-check text-primary me-2"></i>Prévia dos Materiais Processados
                </h5>
                <small class="text-muted">Ajuste as quantidades se necessário antes de enviar para o simulador</small>
            </div>
            <button class="btn btn-success" onclick="enviarParaSimulador()">
                <i class="fa-solid fa-truck-fast me-1"></i> Ir Para Simulação de Carga
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabelaPrevia">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th style="width: 120px;">Quantidade</th>
                            <th>Peso Unit (kg)</th>
                            <th>Peso Total (kg)</th>
                            <th>Vol Unit (m³)</th>
                            <th>Vol Total (m³)</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPrevia"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let materiaisImportadosCache = [];

async function enviarCsv(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formUploadCsv'));
    await processarImportacao(formData);
}

async function enviarTextoCsv(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formTextoCsv'));
    await processarImportacao(formData);
}

async function processarImportacao(formData) {
    try {
        const resp = await fetch('../api/importar_csv.php', { method: 'POST', body: formData });
        const res = await resp.json();

        if (res.status === 'success') {
            showToast(res.message, 'success');
            materiaisImportadosCache = res.data;
            exibirPrevia(res.data);
        } else {
            showToast(res.message, 'error');
        }
    } catch (err) {
        showToast('Erro ao importar materiais.', 'error');
    }
}

function exibirPrevia(itens) {
    const card = document.getElementById('cardPreviaImportacao');
    const tbody = document.getElementById('tbodyPrevia');
    card.classList.remove('d-none');
    tbody.innerHTML = '';

    itens.forEach((it, idx) => {
        tbody.innerHTML += `
            <tr>
                <td class="ps-4 fw-bold text-primary">${it.codigo}</td>
                <td><span class="fw-semibold">${it.descricao}</span></td>
                <td><span class="badge bg-light text-dark border">${it.tipo.toUpperCase()}</span></td>
                <td>
                    <input type="number" min="1" class="form-control form-control-sm text-center fw-bold" 
                           value="${it.quantidade}" onchange="atualizarQuantidadeCache(${idx}, this.value)">
                </td>
                <td>${it.peso_unitario_kg} kg</td>
                <td class="fw-bold" id="pesoTotal_${idx}">${(it.peso_unitario_kg * it.quantidade).toFixed(2)} kg</td>
                <td>${it.volume_unitario_m3} m³</td>
                <td class="fw-bold text-purple" id="volTotal_${idx}">${(it.volume_unitario_m3 * it.quantidade).toFixed(4)} m³</td>
            </tr>
        `;
    });
}

function atualizarQuantidadeCache(idx, novaQtd) {
    const qtd = parseInt(novaQtd) || 1;
    materiaisImportadosCache[idx].quantidade = qtd;

    const pesoTot = (materiaisImportadosCache[idx].peso_unitario_kg * qtd).toFixed(2);
    const volTot = (materiaisImportadosCache[idx].volume_unitario_m3 * qtd).toFixed(4);

    document.getElementById(`pesoTotal_${idx}`).innerText = `${pesoTot} kg`;
    document.getElementById(`volTotal_${idx}`).innerText = `${volTot} m³`;
}

function enviarParaSimulador() {
    if (!materiaisImportadosCache.length) return;
    sessionStorage.setItem('materiaisImportadosSimulacao', JSON.stringify(materiaisImportadosCache));
    window.location.href = 'simulacao.php?origem=importacao';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
