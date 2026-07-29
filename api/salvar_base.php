<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/BaseController.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $controller = new BaseController();
    $id = $controller->salvar($_POST);
    json_response('success', 'Base operacional salva com sucesso!', ['id' => $id]);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}

