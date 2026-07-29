<?php
require_once __DIR__ . '/../config/conexao.php';

class BaseOperacional {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodas(): array {
        $stmt = $this->pdo->query('SELECT * FROM bases_operacionais ORDER BY ordem_padrao ASC, nome ASC');
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM bases_operacionais WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorCodigoOuNome(string $valor): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM bases_operacionais WHERE codigo = ? OR nome = ? LIMIT 1');
        $stmt->execute([$valor, $valor]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $codigo = sanitize_input($data['codigo'] ?? '');
        $nome = sanitize_input($data['nome'] ?? '');
        $endereco = sanitize_input($data['endereco'] ?? '');
        $ordem = max(1, (int) ($data['ordem_padrao'] ?? 1));
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if ($codigo === '' || $nome === '') {
            throw new Exception('Informe código e nome da base operacional.');
        }

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE bases_operacionais SET codigo = ?, nome = ?, endereco = ?, ordem_padrao = ?, observacoes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            );
            $stmt->execute([$codigo, $nome, $endereco, $ordem, $observacoes, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO bases_operacionais (codigo, nome, endereco, ordem_padrao, observacoes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$codigo, $nome, $endereco, $ordem, $observacoes]);
        return (int) $this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM bases_operacionais WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

