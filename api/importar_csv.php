<?php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../controllers/MaterialController.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método de requisição inválido.");
    }

    $materialController = new MaterialController();
    $itensImportados = [];

    // Verificação se foi enviado arquivo ou texto CSV em raw POST
    if (isset($_FILES['arquivo_csv']) && $_FILES['arquivo_csv']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['arquivo_csv']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['arquivo_csv']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'])) {
            throw new Exception("Formato de arquivo inválido. Permita apenas arquivos CSV ou TXT.");
        }

        $handle = fopen($tmpName, 'r');
        $headers = fgetcsv($handle, 1000, ';'); // Tenta ponto-e-vírgula
        if (count($headers) <= 1) {
            rewind($handle);
            $headers = fgetcsv($handle, 1000, ','); // Tenta vírgula
        }

        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (count($data) <= 1) {
                // Tenta dividir por vírgula se linha veio inteira
                $data = explode(',', $data[0]);
            }
            if (count($data) >= 8) {
                $itensImportados[] = [
                    'codigo' => trim($data[0]),
                    'descricao' => trim($data[1]),
                    'quantidade' => (int)trim($data[2]),
                    'peso_unitario_kg' => (float)trim($data[3]),
                    'comprimento_m' => (float)trim($data[4]),
                    'largura_m' => (float)trim($data[5]),
                    'altura_m' => (float)trim($data[6]),
                    'tipo' => trim($data[7]),
                    'permite_empilhamento' => isset($data[8]) ? (int)trim($data[8]) : 1,
                    'observacoes' => isset($data[9]) ? trim($data[9]) : ''
                ];
            }
        }
        fclose($handle);
    } else if (!empty($_POST['csv_texto'])) {
        $linhas = explode("\n", trim($_POST['csv_texto']));
        foreach ($linhas as $idx => $linha) {
            if ($idx === 0 && (stristr($linha, 'codigo') || stristr($linha, 'código'))) continue; // Pular cabeçalho
            $data = str_contains($linha, ';') ? explode(';', $linha) : explode(',', $linha);
            if (count($data) >= 7) {
                $itensImportados[] = [
                    'codigo' => trim($data[0]),
                    'descricao' => trim($data[1]),
                    'quantidade' => (int)trim($data[2]),
                    'peso_unitario_kg' => (float)trim($data[3]),
                    'comprimento_m' => (float)trim($data[4]),
                    'largura_m' => (float)trim($data[5]),
                    'altura_m' => (float)trim($data[6]),
                    'tipo' => isset($data[7]) ? trim($data[7]) : 'outro',
                    'permite_empilhamento' => 1,
                    'observacoes' => ''
                ];
            }
        }
    } else {
        throw new Exception("Envie um arquivo CSV válido ou insira os dados no campo de texto.");
    }

    if (empty($itensImportados)) {
        throw new Exception("Nenhum item válido encontrado no arquivo/texto informado.");
    }

    // Processamento e Salvamento/Enriquecimento no Banco
    $resultado = [];
    foreach ($itensImportados as $it) {
        $existente = $materialController->buscar($it['codigo']);
        if (!$existente) {
            // Salva automaticamente no banco de materiais
            $idSalvo = $materialController->salvar([
                'codigo' => $it['codigo'],
                'descricao' => $it['descricao'],
                'tipo' => $it['tipo'],
                'peso_unitario_kg' => $it['peso_unitario_kg'],
                'comprimento_m' => $it['comprimento_m'],
                'largura_m' => $it['largura_m'],
                'altura_m' => $it['altura_m'],
                'quantidade_padrao' => $it['quantidade'],
                'permite_empilhamento' => $it['permite_empilhamento'],
                'max_lastros' => 2,
                'fragilidade' => 'baixa',
                'observacoes' => $it['observacoes']
            ]);
            $material = $materialController->buscar($idSalvo);
        } else {
            $material = $existente;
        }

        $volUnit = (float)$material['volume_unitario_m3'];
        $pesoUnit = (float)$material['peso_unitario_kg'];
        $qtd = (int)$it['quantidade'];

        $resultado[] = [
            'material_id' => $material['id'],
            'codigo' => $material['codigo'],
            'descricao' => $material['descricao'],
            'tipo' => $material['tipo'],
            'quantidade' => $qtd,
            'peso_unitario_kg' => $pesoUnit,
            'peso_total_kg' => round($pesoUnit * $qtd, 2),
            'volume_unitario_m3' => $volUnit,
            'volume_total_m3' => round($volUnit * $qtd, 4),
            'comprimento_m' => $material['comprimento_m'],
            'largura_m' => $material['largura_m'],
            'altura_m' => $material['altura_m']
        ];
    }

    json_response('success', 'Lista de materiais importada e validada com sucesso!', $resultado);
} catch (Exception $e) {
    json_response('error', $e->getMessage(), [], 400);
}
