<?php
require_once __DIR__ . '/../config/conexao.php';

class PedidoCarga {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos(): array {
        $sql = 'SELECT p.*,
                       COUNT(i.id) AS linhas,
                       COALESCE(SUM(i.quantidade), 0) AS unidades
                FROM pedidos_carga p
                LEFT JOIN pedido_itens i ON i.pedido_id = p.id
                GROUP BY p.id
                ORDER BY p.id DESC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM pedidos_carga WHERE id = ?');
        $stmt->execute([$id]);
        $pedido = $stmt->fetch();
        if (!$pedido) {
            return null;
        }

        $stmtItens = $this->pdo->prepare(
            'SELECT i.*,
                    m.codigo AS material_codigo,
                    m.descricao AS material_descricao,
                    m.categoria,
                    m.formato_fisico,
                    m.peso_unitario_kg,
                    m.comprimento_m,
                    m.largura_m,
                    m.altura_m,
                    m.volume_unitario_m3,
                    m.empilhavel,
                    m.max_lastros,
                    m.perfil_empilhamento,
                    m.fragilidade,
                    m.amarracao_especial,
                    b.codigo AS base_codigo,
                    b.nome AS base_nome
             FROM pedido_itens i
             JOIN materiais m ON m.id = i.material_id
             JOIN bases_operacionais b ON b.id = i.base_id
             WHERE i.pedido_id = ?
             ORDER BY i.ordem_entrega ASC, b.ordem_padrao ASC, i.id ASC'
        );
        $stmtItens->execute([$id]);
        $pedido['itens'] = $stmtItens->fetchAll();
        return $pedido;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $codigo = sanitize_input($data['codigo_pedido'] ?? '');
        $descricao = sanitize_input($data['descricao'] ?? '');
        $status = sanitize_input($data['status'] ?? 'rascunho');
        $observacoes = sanitize_input($data['observacoes'] ?? '');
        $itens = $data['itens'] ?? [];

        if (is_string($itens)) {
            $decoded = json_decode($itens, true);
            $itens = is_array($decoded) ? $decoded : [];
        }

        if ($codigo === '') {
            $codigo = 'PED-' . date('Ymd-His');
        }

        if ($descricao === '') {
            throw new Exception('Informe a descrição do pedido de carga.');
        }

        if (empty($itens)) {
            throw new Exception('Adicione ao menos um item ao pedido.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $this->pdo->prepare(
                    'UPDATE pedidos_carga SET codigo_pedido = ?, descricao = ?, status = ?, observacoes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
                );
                $stmt->execute([$codigo, $descricao, $status, $observacoes, $id]);
                $this->pdo->prepare('DELETE FROM pedido_itens WHERE pedido_id = ?')->execute([$id]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO pedidos_carga (codigo_pedido, descricao, status, observacoes) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$codigo, $descricao, $status, $observacoes]);
                $id = (int) $this->pdo->lastInsertId();
            }

            $stmtItem = $this->pdo->prepare(
                'INSERT INTO pedido_itens (pedido_id, material_id, base_id, quantidade, ordem_entrega, observacoes_item)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($itens as $item) {
                $materialId = (int) ($item['material_id'] ?? 0);
                $baseId = (int) ($item['base_id'] ?? 0);
                $quantidade = max(1, (int) ($item['quantidade'] ?? 0));
                $ordem = max(1, (int) ($item['ordem_entrega'] ?? 1));
                $obs = sanitize_input($item['observacoes_item'] ?? '');

                if ($materialId <= 0 || $baseId <= 0) {
                    throw new Exception('Todos os itens precisam de material e base válidos.');
                }

                $stmtItem->execute([$id, $materialId, $baseId, $quantidade, $ordem, $obs]);
            }

            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM pedidos_carga WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

