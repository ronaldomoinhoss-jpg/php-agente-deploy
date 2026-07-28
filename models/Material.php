<?php
require_once __DIR__ . '/../config/conexao.php';

class Material {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos($busca = '', $tipo = '') {
        $sql = "SELECT * FROM materiais WHERE 1=1";
        $params = [];

        if (!empty($busca)) {
            $sql .= " AND (codigo LIKE ? OR descricao LIKE ?)";
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }

        if (!empty($tipo)) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }

        $sql .= " ORDER BY codigo ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buscarPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM materiais WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function buscarPorCodigo($codigo) {
        $stmt = $this->pdo->prepare("SELECT * FROM materiais WHERE codigo = ?");
        $stmt->execute([$codigo]);
        return $stmt->fetch();
    }

    public function salvar($data) {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $codigo = sanitize_input($data['codigo']);
        $descricao = sanitize_input($data['descricao']);
        $tipo = sanitize_input($data['tipo']);
        $peso_unitario_kg = (float)$data['peso_unitario_kg'];
        $comprimento_m = (float)$data['comprimento_m'];
        $largura_m = (float)$data['largura_m'];
        $altura_m = (float)$data['altura_m'];
        
        // Cálculo automático do volume se não informado
        $volume_unitario_m3 = isset($data['volume_unitario_m3']) && (float)$data['volume_unitario_m3'] > 0
            ? (float)$data['volume_unitario_m3']
            : round($comprimento_m * $largura_m * $altura_m, 4);

        $quantidade_padrao = isset($data['quantidade_padrao']) ? max(1, (int)$data['quantidade_padrao']) : 1;
        $permite_empilhamento = isset($data['permite_empilhamento']) && $data['permite_empilhamento'] == '1' ? 1 : 0;
        
        // Limite máximo universal de lastros = 2
        $max_lastros = isset($data['max_lastros']) ? min(2, max(1, (int)$data['max_lastros'])) : 2;
        if ($permite_empilhamento == 0) {
            $max_lastros = 1;
        }

        $fragilidade = in_array($data['fragilidade'] ?? '', ['baixa', 'media', 'alta']) ? $data['fragilidade'] : 'baixa';
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if (empty($codigo) || empty($descricao) || empty($tipo) || $peso_unitario_kg <= 0 || $comprimento_m <= 0 || $largura_m <= 0 || $altura_m <= 0) {
            throw new Exception("Preencha código, descrição, tipo e dimensões válidas maiores que zero.");
        }

        // Verificar código duplicado
        $existente = $this->buscarPorCodigo($codigo);
        if ($existente && ($id === null || $existente['id'] != $id)) {
            throw new Exception("Já existe um material cadastrado com o código '{$codigo}'.");
        }

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE materiais SET 
                codigo = ?, descricao = ?, tipo = ?, peso_unitario_kg = ?, 
                comprimento_m = ?, largura_m = ?, altura_m = ?, volume_unitario_m3 = ?, 
                quantidade_padrao = ?, permite_empilhamento = ?, max_lastros = ?, 
                fragilidade = ?, observacoes = ? 
                WHERE id = ?");
            $stmt->execute([$codigo, $descricao, $tipo, $peso_unitario_kg, $comprimento_m, $largura_m, $altura_m, $volume_unitario_m3, $quantidade_padrao, $permite_empilhamento, $max_lastros, $fragilidade, $observacoes, $id]);
            return $id;
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO materiais 
                (codigo, descricao, tipo, peso_unitario_kg, comprimento_m, largura_m, altura_m, volume_unitario_m3, quantidade_padrao, permite_empilhamento, max_lastros, fragilidade, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$codigo, $descricao, $tipo, $peso_unitario_kg, $comprimento_m, $largura_m, $altura_m, $volume_unitario_m3, $quantidade_padrao, $permite_empilhamento, $max_lastros, $fragilidade, $observacoes]);
            return $this->pdo->lastInsertId();
        }
    }

    public function excluir($id) {
        $stmt = $this->pdo->prepare("DELETE FROM materiais WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
