<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

try {
    $simulacao_id = (int)($_GET['id'] ?? 0);
    $formato = strtolower($_GET['formato'] ?? 'pdf');

    if ($simulacao_id <= 0) {
        die("ID da simulação é obrigatório.");
    }

    $controller = new SimulacaoController();
    $simulacao = $controller->buscar($simulacao_id);

    if (!$simulacao) {
        die("Simulação não encontrada.");
    }

    if ($formato === 'excel' || $formato === 'csv') {
        $filename = "relatorio_simulacao_{$simulacao['codigo_simulacao']}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        // BOM para Excel reconhecer acentuação UTF-8
        fputs($output, "\xEF\xBB\xBF");

        // Cabeçalho da Simulação
        fputcsv($output, ['RELATÓRIO DE CUBAGEM E ALOCAÇÃO DE CARGA'], ';');
        fputcsv($output, ['Código Simulação:', $simulacao['codigo_simulacao']], ';');
        fputcsv($output, ['Data/Hora:', $simulacao['created_at']], ';');
        fputcsv($output, ['Veículo:', $simulacao['veiculo_nome'] . " ({$simulacao['veiculo_tipo']})"], ';');
        fputcsv($output, ['Capacidade Peso (kg):', $simulacao['veiculo_capacidade_kg']], ';');
        fputcsv($output, ['Capacidade Volume (m³):', $simulacao['veiculo_capacidade_m3']], ';');
        fputcsv($output, ['Peso Carga Total (kg):', $simulacao['peso_total_kg']], ';');
        fputcsv($output, ['Volume Carga Total (m³):', $simulacao['volume_total_m3']], ';');
        fputcsv($output, ['Ocupação Peso (%):', $simulacao['ocupacao_peso_pct'] . '%'], ';');
        fputcsv($output, ['Ocupação Volume (%):', $simulacao['ocupacao_volume_pct'] . '%'], ';');
        fputcsv($output, ['Status:', strtoupper($simulacao['status'])], ';');
        fputcsv($output, [], ';');

        // Itens
        fputcsv($output, ['Código', 'Descrição', 'Qtd', 'Peso Unit (kg)', 'Peso Total (kg)', 'Vol Unit (m³)', 'Vol Total (m³)', 'Lastro Posicionado', 'Status Alocação', 'Observações'], ';');
        foreach ($simulacao['itens'] as $it) {
            fputcsv($output, [
                $it['codigo_material'],
                $it['descricao_material'],
                $it['quantidade'],
                $it['peso_unitario_kg'],
                $it['peso_total_kg'],
                $it['volume_unitario_m3'],
                $it['volume_total_m3'],
                $it['lastro_posicao'] > 0 ? "Lastro {$it['lastro_posicao']}" : 'Não Alocado',
                $it['status_alocacao'],
                $it['observacoes_restricao']
            ], ';');
        }
        fclose($output);
        exit;
    } else {
        // Redireciona para visualização de impressão/PDF HTML
        header("Location: ../pages/resultado_simulacao.php?id={$simulacao_id}&print=1");
        exit;
    }

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}
