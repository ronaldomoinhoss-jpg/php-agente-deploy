<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/PedidoCargaController.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $payload = $_POST;
    if (empty($payload['itens'])) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $payload = $json;
        }
    }

    $controller = new PedidoCargaController();
    $id = $controller->salvar($payload);
    json_response('success', 'Pedido de carga salvo com sucesso!', ['id' => $id]);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}

