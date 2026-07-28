<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método de requisição inválido.");
    }

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);

    if (empty($inputData)) {
        $inputData = $_POST;
    }

    $veiculo_id = (int)($inputData['veiculo_id'] ?? 0);
    $max_lastros = (int)($inputData['max_lastros_permitido'] ?? 2);
    $observacoes = sanitize_input($inputData['observacoes_operacionais'] ?? '');
    $materiaisReq = $inputData['materiais'] ?? [];

    if ($veiculo_id <= 0) {
        throw new Exception("Selecione um veículo válido para a simulação.");
    }

    if (empty($materiaisReq) || !is_array($materiaisReq)) {
        throw new Exception("A lista de materiais para alocação não pode estar vazia.");
    }

    $controller = new SimulacaoController();
    $resultado = $controller->executar($veiculo_id, $materiaisReq, $max_lastros, $observacoes);

    json_response('success', 'Simulação executada com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
