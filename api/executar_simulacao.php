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
    $pedidoIds = array_values(array_filter(array_map('intval', $payload['pedido_ids'] ?? []), static fn($id) => $id > 0));
    $frota = $payload['frota'] ?? [];
    $observacoes = sanitize_input($payload['observacoes'] ?? '');

    if ($pedidoId > 0 && !in_array($pedidoId, $pedidoIds, true)) {
        $pedidoIds[] = $pedidoId;
    }

    if (empty($pedidoIds) && $pedidoId <= 0) {
        throw new Exception('Selecione pelo menos um pedido válido para simular.');
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
        if (count($pedidoIds) > 1) {
            $resultado = $controller->executarManualPedidos($pedidoIds, $frota, $placements, $observacoes);
        } else {
            $resultado = $controller->executarManual($pedidoIds[0] ?? $pedidoId, $frota, $placements, $observacoes);
        }
    } else {
        if (count($pedidoIds) > 1) {
            $resultado = $controller->executarPedidos($pedidoIds, $frota, $observacoes);
        } else {
            $resultado = $controller->executar($pedidoIds[0] ?? $pedidoId, $frota, $observacoes);
        }
    }
    json_response('success', 'Simulação executada com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
