<?php
require_once __DIR__ . '/../config/conexao.php';

class Rota {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas(): array {
        $stmt = $this->pdo->query(
            'SELECT r.*, bo.nome AS origem_base_nome,
                    (SELECT COUNT(*) FROM rota_bases rb WHERE rb.rota_id = r.id) AS total_bases
             FROM rotas r
             LEFT JOIN bases_operacionais bo ON bo.id = r.origem_base_id
             ORDER BY CASE WHEN r.data_planejada IS NULL THEN 1 ELSE 0 END ASC, r.data_planejada ASC, r.codigo ASC'
        );
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, bo.nome AS origem_base_nome
             FROM rotas r
             LEFT JOIN bases_operacionais bo ON bo.id = r.origem_base_id
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $rota = $stmt->fetch();
        if (!$rota) {
            return null;
        }

        $stmtBases = $this->pdo->prepare(
            'SELECT rb.*, b.codigo AS base_codigo, b.nome AS base_nome
             FROM rota_bases rb
             JOIN bases_operacionais b ON b.id = rb.base_id
             WHERE rb.rota_id = ?
             ORDER BY rb.sequencia ASC, rb.id ASC'
        );
        $stmtBases->execute([$id]);
        $rota['bases'] = $stmtBases->fetchAll();
        return $rota;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $codigo = sanitize_input($data['codigo'] ?? '');
        $descricao = sanitize_input($data['descricao'] ?? '');
        $dataPlanejada = sanitize_input($data['data_planejada'] ?? '');
        $origemBaseId = !empty($data['origem_base_id']) ? (int) $data['origem_base_id'] : null;
        $status = sanitize_input($data['status'] ?? 'planejada');
        $observacoes = sanitize_input($data['observacoes'] ?? '');
        $bases = $data['bases'] ?? [];

        if (is_string($bases)) {
            $decoded = json_decode($bases, true);
            $bases = is_array($decoded) ? $decoded : [];
        }

        if ($codigo === '' || $descricao === '') {
            throw new Exception('Informe o código e a descrição da rota.');
        }

        if (empty($bases)) {
            throw new Exception('Adicione ao menos uma base na rota.');
        }

        if (!in_array($status, ['planejada', 'ativa', 'encerrada', 'cancelada'], true)) {
            $status = 'planejada';
        }

        $this->pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rotas
                     SET codigo = ?, descricao = ?, data_planejada = ?, origem_base_id = ?, status = ?, observacoes = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $stmt->execute([$codigo, $descricao, $dataPlanejada ?: null, $origemBaseId, $status, $observacoes, $id]);
                $this->pdo->prepare('DELETE FROM rota_bases WHERE rota_id = ?')->execute([$id]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rotas (codigo, descricao, data_planejada, origem_base_id, status, observacoes)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$codigo, $descricao, $dataPlanejada ?: null, $origemBaseId, $status, $observacoes]);
                $id = (int) $this->pdo->lastInsertId();
            }

            $stmtBase = $this->pdo->prepare(
                'INSERT INTO rota_bases (rota_id, base_id, sequencia) VALUES (?, ?, ?)'
            );

            $seq = 1;
            foreach ($bases as $baseId) {
                $baseId = (int) $baseId;
                if ($baseId <= 0) {
                    continue;
                }
                $stmtBase->execute([$id, $baseId, $seq]);
                $seq++;
            }

            if ($seq === 1) {
                throw new Exception('Nenhuma base válida foi informada para a rota.');
            }

            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM rotas WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
