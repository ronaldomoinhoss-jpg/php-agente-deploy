<?php
require_once __DIR__ . '/../config/conexao.php';

class Regra {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas(): array {
        $sql = 'SELECT r.*,
                       mo.codigo AS origem_codigo,
                       md.codigo AS destino_codigo
                FROM regras_operacionais r
                LEFT JOIN materiais mo ON mo.id = r.material_origem_id
                LEFT JOIN materiais md ON md.id = r.material_destino_id
                ORDER BY r.ativo DESC, r.severidade DESC, r.id DESC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function listarAtivas(): array {
        $sql = 'SELECT r.*,
                       mo.codigo AS origem_codigo,
                       mo.categoria AS origem_categoria_material,
                       md.codigo AS destino_codigo,
                       md.categoria AS destino_categoria_material
                FROM regras_operacionais r
                LEFT JOIN materiais mo ON mo.id = r.material_origem_id
                LEFT JOIN materiais md ON md.id = r.material_destino_id
                WHERE r.ativo = 1
                ORDER BY CASE r.severidade WHEN "bloqueante" THEN 2 ELSE 1 END DESC, r.id ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM regras_operacionais WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $materialOrigemId = !empty($data['material_origem_id']) ? (int) $data['material_origem_id'] : null;
        $categoriaOrigem = sanitize_input($data['categoria_origem'] ?? '');
        $materialDestinoId = !empty($data['material_destino_id']) ? (int) $data['material_destino_id'] : null;
        $categoriaDestino = sanitize_input($data['categoria_destino'] ?? '');
        $tipoRegra = sanitize_input($data['tipo_regra'] ?? '');
        $severidade = sanitize_input($data['severidade'] ?? 'alerta');
        $justificativa = sanitize_input($data['justificativa'] ?? '');
        $ativo = parse_bool_flag($data['ativo'] ?? 0) ? 1 : 0;

        if ($tipoRegra === '') {
            throw new Exception('Informe o tipo da regra operacional.');
        }

        if (!in_array($severidade, ['alerta', 'bloqueante'], true)) {
            $severidade = 'alerta';
        }

        $payload = [
            $materialOrigemId ?: null,
            $categoriaOrigem !== '' ? $categoriaOrigem : null,
            $materialDestinoId ?: null,
            $categoriaDestino !== '' ? $categoriaDestino : null,
            $tipoRegra,
            $severidade,
            $justificativa,
            $ativo,
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE regras_operacionais SET
                    material_origem_id = ?, categoria_origem = ?, material_destino_id = ?, categoria_destino = ?,
                    tipo_regra = ?, severidade = ?, justificativa = ?, ativo = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $payload[] = $id;
            $stmt->execute($payload);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO regras_operacionais
                (material_origem_id, categoria_origem, material_destino_id, categoria_destino, tipo_regra, severidade, justificativa, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM regras_operacionais WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

