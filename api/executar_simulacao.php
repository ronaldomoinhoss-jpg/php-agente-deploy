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

    $modo = sanitize_input($payload['modo'] ?? 'automatico');
    $pedidoId = (int) ($payload['pedido_id'] ?? 0);
    $frota = $payload['frota'] ?? [];
    $observacoes = sanitize_input($payload['observacoes'] ?? '');

    if ($pedidoId <= 0) {
        throw new Exception('Selecione um pedido válido para simular.');
    }

    if (empty($frota) || !is_array($frota)) {
        throw new Exception('Informe pelo menos um veículo candidato com quantidade maior que zero.');
    }

    $controller = new SimulacaoController();
    if ($modo === 'manual') {
        $placements = $payload['placements'] ?? [];
        if (empty($placements) || !is_array($placements)) {
            throw new Exception('A montagem manual precisa ter itens posicionados para salvar.');
        }
        $resultado = $controller->executarManual($pedidoId, $frota, $placements, $observacoes);
    } else {
        $resultado = $controller->executar($pedidoId, $frota, $observacoes);
    }
    json_response('success', 'Simulação executada com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
