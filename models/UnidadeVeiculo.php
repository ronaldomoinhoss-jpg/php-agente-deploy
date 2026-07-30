<?php
require_once __DIR__ . '/../config/conexao.php';

class UnidadeVeiculo {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas(bool $somenteAtivas = false): array {
        $sql = 'SELECT u.*, v.nome AS veiculo_nome, v.tipo AS veiculo_tipo, v.capacidade_kg, v.capacidade_m3
                FROM unidades_veiculo u
                JOIN veiculos v ON v.id = u.veiculo_id';
        if ($somenteAtivas) {
            $sql .= ' WHERE u.ativo = 1';
        }
        $sql .= ' ORDER BY v.tipo ASC, u.codigo_unidade ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, v.nome AS veiculo_nome, v.tipo AS veiculo_tipo, v.capacidade_kg, v.capacidade_m3
             FROM unidades_veiculo u
             JOIN veiculos v ON v.id = u.veiculo_id
             WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $veiculoId = (int) ($data['veiculo_id'] ?? 0);
        $codigo = sanitize_input($data['codigo_unidade'] ?? '');
        $status = sanitize_input($data['status_operacional'] ?? 'disponivel');
        $ativo = parse_bool_flag($data['ativo'] ?? 1) ? 1 : 0;
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if ($veiculoId <= 0 || $codigo === '') {
            throw new Exception('Informe o tipo de veículo e o código da unidade.');
        }

        if (!in_array($status, ['disponivel', 'manutencao', 'indisponivel'], true)) {
            $status = 'disponivel';
        }

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE unidades_veiculo
                 SET veiculo_id = ?, codigo_unidade = ?, status_operacional = ?, ativo = ?, observacoes = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([$veiculoId, $codigo, $status, $ativo, $observacoes, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO unidades_veiculo (veiculo_id, codigo_unidade, status_operacional, ativo, observacoes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$veiculoId, $codigo, $status, $ativo, $observacoes]);
        return (int) $this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM unidades_veiculo WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function listarDisponiveisNaData(string $dataOperacao): array {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, v.nome AS veiculo_nome, v.tipo AS veiculo_tipo
             FROM unidades_veiculo u
             JOIN veiculos v ON v.id = u.veiculo_id
             WHERE u.ativo = 1
               AND u.status_operacional = "disponivel"
               AND u.id NOT IN (
                   SELECT pc.unidade_veiculo_id
                   FROM planejamento_cargas pc
                   JOIN planejamento_rotas pr ON pr.id = pc.planejamento_id
                   WHERE pc.unidade_veiculo_id IS NOT NULL
                     AND pr.data_operacao = ?
                     AND pr.status <> "cancelado"
               )
             ORDER BY v.tipo ASC, u.codigo_unidade ASC'
        );
        $stmt->execute([$dataOperacao]);
        return $stmt->fetchAll();
    }
}
