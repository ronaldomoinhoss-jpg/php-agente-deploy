<?php
require_once __DIR__ . '/../config/conexao.php';

class ImportadorPlanilha {
    public function lerUpload(array $file): array {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $tmp = $file['tmp_name'] ?? '';

        if ($tmp === '' || !is_file($tmp)) {
            throw new Exception('Arquivo inválido para importação.');
        }

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseCsvFile($tmp);
        }

        if ($ext === 'xlsx') {
            return $this->parseXlsxFile($tmp);
        }

        throw new Exception('Formato não suportado. Use CSV, TXT ou XLSX.');
    }

    public function lerTexto(string $texto): array {
        $linhas = preg_split('/\r\n|\r|\n/', trim($texto));
        if (!$linhas) {
            return [];
        }

        $rows = [];
        foreach ($linhas as $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $rows[] = str_contains($linha, ';')
                ? array_map('trim', explode(';', $linha))
                : array_map('trim', explode(',', $linha));
        }
        return $this->rowsToAssoc($rows);
    }

    private function parseCsvFile(string $file): array {
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new Exception('Não foi possível ler o arquivo CSV.');
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) <= 1) {
                $data = str_getcsv($data[0] ?? '', ',');
            }
            $rows[] = array_map('trim', $data);
        }
        fclose($handle);

        return $this->rowsToAssoc($rows);
    }

    private function parseXlsxFile(string $file): array {
        if (!class_exists('ZipArchive')) {
            throw new Exception('A extensão ZipArchive não está disponível para ler XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new Exception('Não foi possível abrir a planilha XLSX.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new Exception('A primeira aba da planilha não foi encontrada.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData)) {
            throw new Exception('Estrutura XLSX inválida.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $assoc = [];
            $lastIndex = 0;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)/', $ref, $match);
                $colLetters = $match[1] ?? 'A';
                $index = $this->columnToIndex($colLetters);
                while ($lastIndex < $index) {
                    $assoc[] = '';
                    $lastIndex++;
                }

                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $assoc[] = trim($value);
                $lastIndex++;
            }
            $rows[] = $assoc;
        }

        return $this->rowsToAssoc($rows);
    }

    private function rowsToAssoc(array $rows): array {
        $rows = array_values(array_filter($rows, static fn($row) => array_filter($row, static fn($v) => trim((string) $v) !== '')));
        if (empty($rows)) {
            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));
        $result = [];

        foreach ($rows as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$index] ?? '';
            }
            if (!empty($assoc)) {
                $result[] = $assoc;
            }
        }

        return $result;
    }

    private function normalizeHeader(string $header): string {
        $map = [
            'código' => 'codigo',
            'codigo' => 'codigo',
            'descricao' => 'descricao',
            'descrição' => 'descricao',
            'categoria' => 'categoria',
            'formato' => 'formato_fisico',
            'formato_fisico' => 'formato_fisico',
            'peso' => 'peso_unitario_kg',
            'peso_unitario_kg' => 'peso_unitario_kg',
            'comprimento' => 'comprimento_m',
            'comprimento_m' => 'comprimento_m',
            'largura' => 'largura_m',
            'largura_m' => 'largura_m',
            'altura' => 'altura_m',
            'altura_m' => 'altura_m',
            'empilhavel' => 'empilhavel',
            'max_lastros' => 'max_lastros',
            'perfil_empilhamento' => 'perfil_empilhamento',
            'fragilidade' => 'fragilidade',
            'observacoes' => 'observacoes',
            'observações' => 'observacoes',
            'codigo_material' => 'codigo_material',
            'quantidade' => 'quantidade',
            'base_destino' => 'base_destino',
            'ordem_entrega' => 'ordem_entrega',
            'observacoes_item' => 'observacoes_item',
        ];

        $header = strtolower(trim($header));
        return $map[$header] ?? preg_replace('/[^a-z0-9_]+/', '_', $header);
    }

    private function columnToIndex(string $letters): int {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }
}

