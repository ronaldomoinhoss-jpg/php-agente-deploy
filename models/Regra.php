<?php
require_once __DIR__ . '/../config/conexao.php';

class Regra {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas() {
        $sql = "SELECT r.*, 
                    m1.codigo as origem_codigo, m1.descricao as origem_descricao,
                    m2.codigo as destino_codigo, m2.descricao as destino_descricao
                FROM regras_empilhamento r
                LEFT JOIN materiais m1 ON r.material_origem_id = m1.id
                LEFT JOIN materiais m2 ON r.material_destino_id = m2.id
                ORDER BY r.prioridade DESC, r.id DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function listarAtivas() {
        $sql = "SELECT r.*, 
                    m1.codigo as origem_codigo, m1.descricao as origem_descricao, m1.tipo as origem_tipo,
                    m2.codigo as destino_codigo, m2.descricao as destino_descricao, m2.tipo as destino_tipo
                FROM regras_empilhamento r
                LEFT JOIN materiais m1 ON r.material_origem_id = m1.id
                LEFT JOIN materiais m2 ON r.material_destino_id = m2.id
                WHERE r.ativo = 1
                ORDER BY r.prioridade DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    public function buscarPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM regras_empilhamento WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function salvar($data) {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $material_origem_id = !empty($data['material_origem_id']) ? (int)$data['material_origem_id'] : null;
        $tipo_material_origem = !empty($data['tipo_material_origem']) ? sanitize_input($data['tipo_material_origem']) : null;
        
        $material_destino_id = !empty($data['material_destino_id']) ? (int)$data['material_destino_id'] : null;
        $tipo_material_destino = !empty($data['tipo_material_destino']) ? sanitize_input($data['tipo_material_destino']) : null;

        $tipo_regra = sanitize_input($data['tipo_regra']);
        $prioridade = in_array($data['prioridade'] ?? '', ['baixa', 'media', 'alta', 'bloqueante']) ? $data['prioridade'] : 'media';
        $justificativa = sanitize_input($data['justificativa'] ?? '');
        $ativo = isset($data['ativo']) && $data['ativo'] == '1' ? 1 : 0;

        if (empty($tipo_regra)) {
            throw new Exception("Selecione o tipo da regra.");
        }

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE regras_empilhamento SET 
                material_origem_id = ?, tipo_material_origem = ?, 
                material_destino_id = ?, tipo_material_destino = ?, 
                tipo_regra = ?, prioridade = ?, justificativa = ?, ativo = ? 
                WHERE id = ?");
            $stmt->execute([$material_origem_id, $tipo_material_origem, $material_destino_id, $tipo_material_destino, $tipo_regra, $prioridade, $justificativa, $ativo, $id]);
            return $id;
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO regras_empilhamento 
                (material_origem_id, tipo_material_origem, material_destino_id, tipo_material_destino, tipo_regra, prioridade, justificativa, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$material_origem_id, $tipo_material_origem, $material_destino_id, $tipo_material_destino, $tipo_regra, $prioridade, $justificativa, $ativo]);
            return $this->pdo->lastInsertId();
        }
    }

    public function excluir($id) {
        $stmt = $this->pdo->prepare("DELETE FROM regras_empilhamento WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
