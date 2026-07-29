<?php
$pageTitle = 'Importações';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>

<div class="container-fluid p-0">
    <div class="mb-4">
        <h2 class="h3 mb-1 text-dark">Importações de catálogo e pedido</h2>
        <p class="text-muted mb-0">Aceita CSV e XLSX para alimentar o catálogo de materiais e a montagem dos pedidos de carga.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Importar catálogo de materiais</h5>
                    <small class="text-muted">Colunas esperadas: `codigo`, `descricao`, `categoria`, `formato_fisico`, `peso_unitario_kg`, `comprimento_m`, `largura_m`, `altura_m`, `empilhavel`, `max_lastros`, `perfil_empilhamento`, `fragilidade`, `observacoes`.</small>
                </div>
                <div class="card-body p-4">
                    <form id="catalogoFormArquivo" onsubmit="importarCatalogo(event, true)">
                        <label class="form-label">Arquivo CSV/XLSX</label>
                        <input type="file" class="form-control bg-light mb-3" name="arquivo_planilha" accept=".csv,.txt,.xlsx" required>
                        <button class="btn btn-primary w-100">Importar arquivo</button>
                    </form>
                    <hr>
                    <form id="catalogoFormTexto" onsubmit="importarCatalogo(event, false)">
                        <label class="form-label">Ou cole o conteúdo</label>
                        <textarea class="form-control bg-light font-monospace small mb-3" name="texto_planilha" rows="6" placeholder="codigo;descricao;categoria;formato_fisico;peso_unitario_kg;comprimento_m;largura_m;altura_m;empilhavel;max_lastros;perfil_empilhamento;fragilidade;observacoes"></textarea>
                        <button class="btn btn-outline-primary w-100">Importar texto</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">Importar pedido de carga</h5>
                    <small class="text-muted">Colunas esperadas: `codigo_material`, `quantidade`, `base_destino`, `ordem_entrega`, `observacoes_item`.</small>
                </div>
                <div class="card-body p-4">
                    <form id="pedidoFormArquivo" onsubmit="importarPedido(event, true)">
                        <input type="text" class="form-control bg-light mb-2" name="descricao_pedido" placeholder="Descrição do pedido">
                        <label class="form-label">Arquivo CSV/XLSX</label>
                        <input type="file" class="form-control bg-light mb-3" name="arquivo_pedido" accept=".csv,.txt,.xlsx" required>
                        <button class="btn btn-success w-100">Importar arquivo</button>
                    </form>
                    <hr>
                    <form id="pedidoFormTexto" onsubmit="importarPedido(event, false)">
                        <input type="text" class="form-control bg-light mb-2" name="descricao_pedido" placeholder="Descrição do pedido">
                        <label class="form-label">Ou cole o conteúdo</label>
                        <textarea class="form-control bg-light font-monospace small mb-3" name="texto_pedido" rows="6" placeholder="codigo_material;quantidade;base_destino;ordem_entrega;observacoes_item"></textarea>
                        <button class="btn btn-outline-success w-100">Importar texto</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mt-4 d-none" id="resultadoImportacao">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold mb-0">Resultado da importação</h5>
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded-3 small mb-0" id="resultadoImportacaoTexto"></pre>
        </div>
    </div>
</div>

<script>
function exibirResultado(payload) {
    document.getElementById('resultadoImportacao').classList.remove('d-none');
    document.getElementById('resultadoImportacaoTexto').textContent = JSON.stringify(payload, null, 2);
}

async function importarCatalogo(event, arquivo) {
    event.preventDefault();
    const form = document.getElementById(arquivo ? 'catalogoFormArquivo' : 'catalogoFormTexto');
    const response = await fetch('../api/importar_catalogo.php', { method: 'POST', body: new FormData(form) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        exibirResultado(result.data);
    } else {
        showToast(result.message, 'error');
    }
}

async function importarPedido(event, arquivo) {
    event.preventDefault();
    const form = document.getElementById(arquivo ? 'pedidoFormArquivo' : 'pedidoFormTexto');
    const response = await fetch('../api/importar_pedido.php', { method: 'POST', body: new FormData(form) });
    const result = await response.json();
    if (result.status === 'success') {
        showToast(result.message, 'success');
        exibirResultado(result.data);
    } else {
        showToast(result.message, 'error');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

