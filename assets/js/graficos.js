// System Dashboard Graphics using Chart.js

function renderDashboardGraficos(dados) {
    if (typeof Chart === 'undefined') return;

    // 1. Gráfico de Distribuição por Tipo de Veículo
    const ctxVeiculos = document.getElementById('chartVeiculos');
    if (ctxVeiculos && dados.veiculos) {
        new Chart(ctxVeiculos, {
            type: 'bar',
            data: {
                labels: ['Caminhão Munck', 'Caminhão Truck', 'Carreta Prancha'],
                datasets: [{
                    label: 'Veículos Cadastrados',
                    data: [
                        dados.veiculos.total_munck || 1,
                        dados.veiculos.total_truck || 1,
                        dados.veiculos.total_carreta || 1
                    ],
                    backgroundColor: ['#d97706', '#2563eb', '#059669'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }

    // 2. Gráfico de Materiais Mais Utilizados em Simulações
    const ctxMateriais = document.getElementById('chartMateriais');
    if (ctxMateriais && dados.materiais) {
        new Chart(ctxMateriais, {
            type: 'doughnut',
            data: {
                labels: dados.materiais.labels || ['Bobinas de Cabo', 'Transformadores', 'Postes Concreto', 'Chaves 15kV', 'Outros'],
                datasets: [{
                    data: dados.materiais.valores || [40, 25, 15, 12, 8],
                    backgroundColor: ['#f59e0b', '#ef4444', '#64748b', '#3b82f6', '#10b981']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
}
