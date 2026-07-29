<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../models/ImportadorPlanilha.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $importador = new ImportadorPlanilha();
    if (!empty($_FILES['arquivo_planilha']['tmp_name'])) {
        $rows = $importador->lerUpload($_FILES['arquivo_planilha']);
    } elseif (!empty($_POST['texto_planilha'])) {
        $rows = $importador->lerTexto($_POST['texto_planilha']);
    } else {
        throw new Exception('Envie um arquivo CSV/XLSX ou cole os dados da planilha.');
    }

    if (empty($rows)) {
        throw new Exception('Nenhuma linha válida encontrada na planilha.');
    }

    $controller = new MaterialController();
    $resultado = [];
    foreach ($rows as $row) {
        $payload = [
            'codigo' => $row['codigo'] ?? '',
            'descricao' => $row['descricao'] ?? '',
            'categoria' => $row['categoria'] ?? 'outro',
            'formato_fisico' => $row['formato_fisico'] ?? 'caixa',
            'peso_unitario_kg' => $row['peso_unitario_kg'] ?? 0,
            'comprimento_m' => $row['comprimento_m'] ?? 0,
            'largura_m' => $row['largura_m'] ?? 0,
            'altura_m' => $row['altura_m'] ?? 0,
            'empilhavel' => $row['empilhavel'] ?? 1,
            'max_lastros' => $row['max_lastros'] ?? 2,
            'perfil_empilhamento' => $row['perfil_empilhamento'] ?? 'reto',
            'fragilidade' => $row['fragilidade'] ?? 'baixa',
            'observacoes' => $row['observacoes'] ?? '',
        ];
        $id = $controller->salvar($payload);
        $material = $controller->buscar($id);
        $resultado[] = $material;
    }

    json_response('success', 'Catálogo importado com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}

