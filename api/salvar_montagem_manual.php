<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload)) {
        $payload = $_POST;
    }

    $simulacaoId = (int) ($payload['simulacao_id'] ?? 0);
    $simulacaoVeiculoId = (int) ($payload['simulacao_veiculo_id'] ?? 0);
    $itens = $payload['itens'] ?? [];

    if ($simulacaoId <= 0 || $simulacaoVeiculoId <= 0) {
        throw new Exception('Simulação ou veículo da simulação inválido.');
    }

    if (!is_array($itens) || empty($itens)) {
        throw new Exception('Nenhum item foi enviado para a montagem manual.');
    }

    $controller = new SimulacaoController();
    $resultado = $controller->atualizarMontagemManual($simulacaoId, $simulacaoVeiculoId, $itens);
    json_response('success', 'Montagem manual salva com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
