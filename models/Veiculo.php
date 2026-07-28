<?php
require_once __DIR__ . '/../config/conexao.php';

class Veiculo {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos() {
        $stmt = $this->pdo->query("SELECT * FROM veiculos ORDER BY tipo ASC, nome ASC");
        return $stmt->fetchAll();
    }

    public function buscarPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM veiculos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function salvar($data) {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $tipo = sanitize_input($data['tipo']);
        $nome = sanitize_input($data['nome']);
        $capacidade_kg = (float)$data['capacidade_kg'];
        $capacidade_m3 = (float)$data['capacidade_m3'];
        $comprimento_m = (float)$data['comprimento_m'];
        $largura_m = (float)$data['largura_m'];
        $altura_m = (float)$data['altura_m'];
        $max_lastros = isset($data['max_lastros']) ? min(2, max(1, (int)$data['max_lastros'])) : 2;
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if (empty($nome) || $capacidade_kg <= 0 || $capacidade_m3 <= 0 || $comprimento_m <= 0 || $largura_m <= 0 || $altura_m <= 0) {
            throw new Exception("Preencha todos os campos obrigatórios com valores válidos maior que zero.");
        }

        if ($id) {
            $stmt = $this->pdo->prepare("UPDATE veiculos SET 
                tipo = ?, nome = ?, capacidade_kg = ?, capacidade_m3 = ?, 
                comprimento_m = ?, largura_m = ?, altura_m = ?, max_lastros = ?, observacoes = ? 
                WHERE id = ?");
            $stmt->execute([$tipo, $nome, $capacidade_kg, $capacidade_m3, $comprimento_m, $largura_m, $altura_m, $max_lastros, $observacoes, $id]);
            return $id;
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO veiculos 
                (tipo, nome, capacidade_kg, capacidade_m3, comprimento_m, largura_m, altura_m, max_lastros, observacoes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tipo, $nome, $capacidade_kg, $capacidade_m3, $comprimento_m, $largura_m, $altura_m, $max_lastros, $observacoes]);
            return $this->pdo->lastInsertId();
        }
    }

    public function excluir($id) {
        $stmt = $this->pdo->prepare("DELETE FROM veiculos WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function getEstatísticas() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total, 
            SUM(CASE WHEN tipo='Munck' THEN 1 ELSE 0 END) as total_munck,
            SUM(CASE WHEN tipo='Truck' THEN 1 ELSE 0 END) as total_truck,
            SUM(CASE WHEN tipo='Carreta' THEN 1 ELSE 0 END) as total_carreta
            FROM veiculos");
        return $stmt->fetch();
    }
}
