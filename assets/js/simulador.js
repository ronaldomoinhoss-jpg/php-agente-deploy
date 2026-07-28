// Interactive Visual Cargo Simulator & Visualizer Engine

class CargoVisualizer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.currentLayer = 1; // 1 = Lastro 1, 2 = Lastro 2
        this.simData = null;
    }

    render(data) {
        this.simData = data;
        if (!this.container) return;

        const veiculo = data; // Contém dados do veículo e itens
        const itens = data.itens || [];
        const veiculoComp = parseFloat(veiculo.veiculo_comprimento_m || veiculo.comprimento_m || 8.5);
        const veiculoLarg = parseFloat(veiculo.veiculo_largura_m || veiculo.largura_m || 2.45);

        // Filtrar itens do lastro selecionado
        const itensLastro = itens.filter(it => parseInt(it.lastro_posicao) === this.currentLayer && it.status_alocacao === 'alocado');

        let html = `
            <div class="cargo-simulation-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0 text-white"><i class="fa-solid fa-cube text-primary me-2"></i>Visão Superior da Carroceria</h4>
                        <small class="text-white-50">Veículo: ${veiculo.veiculo_nome || veiculo.nome} (${veiculoComp}m x ${veiculoLarg}m)</small>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm ${this.currentLayer === 1 ? 'btn-primary' : 'btn-outline-light'}" onclick="window.cargoVis.switchLayer(1)">
                            <i class="fa-solid fa-layer-group me-1"></i> Lastro 1 (Piso)
                        </button>
                        <button class="btn btn-sm ${this.currentLayer === 2 ? 'btn-primary' : 'btn-outline-light'}" onclick="window.cargoVis.switchLayer(2)">
                            <i class="fa-solid fa-layer-group me-1"></i> Lastro 2 (Superior)
                        </button>
                    </div>
                </div>

                <div class="truck-bed-visual">
                    <div class="truck-bed-header">
                        <span>CABINE (FRENTE DO VEÍCULO)</span>
                        <span>TRASEIRA DA CARROCERIA</span>
                    </div>
                    <div class="truck-cabin">FRENTE</div>
                    <div class="truck-load-area" id="loadArea">
        `;

        // Renderizar cada item no Lastro ativo
        itensLastro.forEach((it, idx) => {
            const posX = parseFloat(it.posicao_x || 0);
            const posY = parseFloat(it.posicao_y || 0);
            const comp = parseFloat(it.comprimento_m || 1.2);
            const larg = parseFloat(it.largura_m || 1.0);

            // Calcular porcentagem de posição na área útil
            const leftPct = (posX / veiculoComp) * 100;
            const topPct = (posY / veiculoLarg) * 100;
            const widthPct = Math.max(8, (comp / veiculoComp) * 100);
            const heightPct = Math.max(12, (larg / veiculoLarg) * 100);

            let blockClass = 'cargo-block-outro';
            if (it.codigo_material && it.codigo_material.includes('BOB')) blockClass = 'cargo-block-bobina';
            else if (it.codigo_material && it.codigo_material.includes('TRF')) blockClass = 'cargo-block-transformador';
            else if (it.codigo_material && it.codigo_material.includes('POS')) blockClass = 'cargo-block-poste';
            else if (it.codigo_material && it.codigo_material.includes('CX')) blockClass = 'cargo-block-caixa';

            html += `
                <div class="cargo-block ${blockClass}" 
                     style="left: ${leftPct}%; top: ${topPct}%; width: ${widthPct}%; height: ${heightPct}%;"
                     data-bs-toggle="tooltip" 
                     title="${it.codigo_material} - ${it.descricao_material} (${it.peso_unitario_kg}kg)">
                    ${it.codigo_material}
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                <!-- Painel de Legendas e Centro de Gravidade -->
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-circle me-1"></i> Bobina (Piramidal)</span>
                            <span class="badge bg-danger"><i class="fa-solid fa-bolt me-1"></i> Transformador</span>
                            <span class="badge bg-secondary"><i class="fa-solid fa-grip-lines me-1"></i> Poste Concreto</span>
                            <span class="badge bg-success"><i class="fa-solid fa-box me-1"></i> Caixa/Palete</span>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-white-50">Centro de Gravidade Longitudinal: <strong>${data.centro_gravidade_x || 50}%</strong></small>
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
        
        // Re-inicializar tooltips
        const tooltipTriggerList = [].slice.call(this.container.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    switchLayer(layer) {
        this.currentLayer = layer;
        if (this.simData) {
            this.render(this.simData);
        }
    }
}

// Global instance helper
document.addEventListener('DOMContentLoaded', () => {
    window.cargoVis = new CargoVisualizer('visualizadorCarga');
});
