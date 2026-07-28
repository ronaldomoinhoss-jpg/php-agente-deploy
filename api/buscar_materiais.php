<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $busca = $_GET['q'] ?? '';
    $tipo = $_GET['tipo'] ?? '';

    $controller = new MaterialController();
    $materiais = $controller->listar($busca, $tipo);

    json_response('success', 'Materiais localizados', $materiais);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
