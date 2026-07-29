<?php
require_once __DIR__ . '/../config/conexao.php';

class Material {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos(string $busca = '', string $categoria = ''): array {
        $sql = 'SELECT * FROM materiais WHERE 1=1';
        $params = [];

        if ($busca !== '') {
            $sql .= ' AND (codigo LIKE ? OR descricao LIKE ?)';
            $params[] = '%' . $busca . '%';
            $params[] = '%' . $busca . '%';
        }

        if ($categoria !== '') {
            $sql .= ' AND categoria = ?';
            $params[] = $categoria;
        }

        $sql .= ' ORDER BY codigo ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listarCategorias(): array {
        $stmt = $this->pdo->query('SELECT DISTINCT categoria FROM materiais ORDER BY categoria ASC');
        return array_map(static fn($row) => $row['categoria'], $stmt->fetchAll());
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM materiais WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorCodigo(string $codigo): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM materiais WHERE codigo = ?');
        $stmt->execute([$codigo]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $codigo = sanitize_input($data['codigo'] ?? '');
        $descricao = sanitize_input($data['descricao'] ?? '');
        $categoria = sanitize_input($data['categoria'] ?? '');
        $formato = sanitize_input($data['formato_fisico'] ?? 'caixa');
        $peso = (float) ($data['peso_unitario_kg'] ?? 0);
        $comprimento = (float) ($data['comprimento_m'] ?? 0);
        $largura = (float) ($data['largura_m'] ?? 0);
        $altura = (float) ($data['altura_m'] ?? 0);
        $volume = isset($data['volume_unitario_m3']) && (float) $data['volume_unitario_m3'] > 0
            ? (float) $data['volume_unitario_m3']
            : round($comprimento * $largura * $altura, 4);
        $empilhavel = parse_bool_flag($data['empilhavel'] ?? $data['permite_empilhamento'] ?? 1) ? 1 : 0;
        $max_lastros = min(2, max(1, (int) ($data['max_lastros'] ?? 2)));
        if ($empilhavel === 0) {
            $max_lastros = 1;
        }

        $perfil = sanitize_input($data['perfil_empilhamento'] ?? ($empilhavel ? 'reto' : 'nenhum'));
        $fragilidade = sanitize_input($data['fragilidade'] ?? 'baixa');
        $amarracao = parse_bool_flag($data['amarracao_especial'] ?? 0) ? 1 : 0;
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if ($codigo === '' || $descricao === '' || $categoria === '' || $peso <= 0 || $comprimento <= 0 || $largura <= 0 || $altura <= 0) {
            throw new Exception('Preencha código, descrição, categoria, peso e dimensões válidas.');
        }

        $existente = $this->buscarPorCodigo($codigo);
        if ($existente && ($id === null || (int) $existente['id'] !== $id)) {
            throw new Exception("Já existe um material com o código '{$codigo}'.");
        }

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE materiais SET
                    codigo = ?, descricao = ?, categoria = ?, formato_fisico = ?, peso_unitario_kg = ?,
                    comprimento_m = ?, largura_m = ?, altura_m = ?, volume_unitario_m3 = ?, empilhavel = ?,
                    max_lastros = ?, perfil_empilhamento = ?, fragilidade = ?, amarracao_especial = ?, observacoes = ?,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([
                $codigo,
                $descricao,
                $categoria,
                $formato,
                $peso,
                $comprimento,
                $largura,
                $altura,
                $volume,
                $empilhavel,
                $max_lastros,
                $perfil,
                $fragilidade,
                $amarracao,
                $observacoes,
                $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO materiais
                (codigo, descricao, categoria, formato_fisico, peso_unitario_kg, comprimento_m, largura_m, altura_m,
                 volume_unitario_m3, empilhavel, max_lastros, perfil_empilhamento, fragilidade, amarracao_especial, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $codigo,
            $descricao,
            $categoria,
            $formato,
            $peso,
            $comprimento,
            $largura,
            $altura,
            $volume,
            $empilhavel,
            $max_lastros,
            $perfil,
            $fragilidade,
            $amarracao,
            $observacoes,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM materiais WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

