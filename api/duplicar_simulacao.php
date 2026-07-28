<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/SimulacaoController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método de requisição inválido.");
    }

    $simulacao_id = (int)($_POST['id'] ?? 0);
    if ($simulacao_id <= 0) {
        throw new Exception("ID da simulação é obrigatório.");
    }

    $controller = new SimulacaoController();
    $simulacaoOriginal = $controller->buscar($simulacao_id);

    if (!$simulacaoOriginal) {
        throw new Exception("Simulação não encontrada.");
    }

    $itensReq = [];
    foreach ($simulacaoOriginal['itens'] as $it) {
        $itensReq[] = [
            'material_id' => $it['material_id'],
            'quantidade' => $it['quantidade']
        ];
    }

    $obs = "Cópia duplicada da simulação " . $simulacaoOriginal['codigo_simulacao'];
    $novaSimulacao = $controller->executar($simulacaoOriginal['veiculo_id'], $itensReq, $simulacaoOriginal['max_lastros_permitido'], $obs);

    json_response('success', 'Simulação duplicada com sucesso!', ['id' => $novaSimulacao['id']]);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
