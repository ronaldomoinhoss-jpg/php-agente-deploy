<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/BaseController.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('ID da base inválido.');
    }

    $controller = new BaseController();
    $controller->excluir($id);
    json_response('success', 'Base operacional excluída com sucesso!');
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}

