<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/RegraController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método de requisição inválido.");
    }

    $controller = new RegraController();
    $id = $controller->salvar($_POST);

    json_response('success', 'Regra de empilhamento/restrição salva com sucesso!', ['id' => $id]);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
