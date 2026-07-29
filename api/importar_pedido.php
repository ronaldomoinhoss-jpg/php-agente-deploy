<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/PedidoCargaController.php';
require_once __DIR__ . '/../controllers/MaterialController.php';
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../models/ImportadorPlanilha.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $importador = new ImportadorPlanilha();
    if (!empty($_FILES['arquivo_pedido']['tmp_name'])) {
        $rows = $importador->lerUpload($_FILES['arquivo_pedido']);
    } elseif (!empty($_POST['texto_pedido'])) {
        $rows = $importador->lerTexto($_POST['texto_pedido']);
    } else {
        throw new Exception('Envie um arquivo CSV/XLSX do pedido ou cole os dados.');
    }

    if (empty($rows)) {
        throw new Exception('Nenhuma linha válida do pedido foi encontrada.');
    }

    $materialController = new MaterialController();
    $baseController = new BaseController();
    $pedidoController = new PedidoCargaController();

    $itens = [];
    foreach ($rows as $row) {
        $codigoMaterial = trim((string) ($row['codigo_material'] ?? ''));
        $quantidade = max(1, (int) ($row['quantidade'] ?? 0));
        $baseDestino = trim((string) ($row['base_destino'] ?? ''));
        $ordem = max(1, (int) ($row['ordem_entrega'] ?? 1));

        if ($codigoMaterial === '' || $baseDestino === '') {
            continue;
        }

        $material = $materialController->buscarPorCodigo($codigoMaterial);
        if (!$material) {
            throw new Exception("Material '{$codigoMaterial}' não encontrado no catálogo.");
        }

        $base = $baseController->buscarPorCodigoOuNome($baseDestino);
        if (!$base) {
            $baseId = $baseController->salvar([
                'codigo' => strtoupper(preg_replace('/[^A-Z0-9]+/', '-', $baseDestino)),
                'nome' => $baseDestino,
                'ordem_padrao' => $ordem,
            ]);
            $base = $baseController->buscar($baseId);
        }

        $itens[] = [
            'material_id' => $material['id'],
            'base_id' => $base['id'],
            'quantidade' => $quantidade,
            'ordem_entrega' => $ordem,
            'observacoes_item' => $row['observacoes_item'] ?? '',
        ];
    }

    if (empty($itens)) {
        throw new Exception('Nenhum item válido foi montado para o pedido.');
    }

    $descricao = trim((string) ($_POST['descricao_pedido'] ?? 'Pedido importado em 29/07/2026'));
    $codigo = trim((string) ($_POST['codigo_pedido'] ?? ''));
    $pedidoId = $pedidoController->salvar([
        'codigo_pedido' => $codigo,
        'descricao' => $descricao,
        'status' => 'aberto',
        'observacoes' => $_POST['observacoes'] ?? 'Criado por importação de planilha.',
        'itens' => $itens,
    ]);

    json_response('success', 'Pedido importado com sucesso!', $pedidoController->buscar($pedidoId));
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}

