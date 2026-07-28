<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método de requisição inválido.");
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("ID da simulação é inválido.");
    }

    $controller = new SimulacaoController();
    $controller->excluir($id);

    json_response('success', 'Simulação excluída com sucesso!');
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
