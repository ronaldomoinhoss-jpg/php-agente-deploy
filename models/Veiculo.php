<?php
require_once __DIR__ . '/../config/conexao.php';

class Veiculo {
    private PDO $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function listarTodos(): array {
        $stmt = $this->pdo->query('SELECT * FROM veiculos ORDER BY tipo ASC, nome ASC');
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM veiculos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function salvar(array $data): int {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $tipo = sanitize_input($data['tipo'] ?? '');
        $nome = sanitize_input($data['nome'] ?? '');
        $capacidadeKg = (float) ($data['capacidade_kg'] ?? 0);
        $capacidadeM3 = (float) ($data['capacidade_m3'] ?? 0);
        $comprimento = (float) ($data['comprimento_m'] ?? 0);
        $largura = (float) ($data['largura_m'] ?? 0);
        $altura = (float) ($data['altura_m'] ?? 0);
        $maxLastros = min(2, max(1, (int) ($data['max_lastros'] ?? 2)));
        $acesso = sanitize_input($data['acesso_descarga'] ?? 'traseira');
        $qtdDisponivel = max(1, (int) ($data['quantidade_disponivel'] ?? 1));
        $observacoes = sanitize_input($data['observacoes'] ?? '');

        if ($tipo === '' || $nome === '' || $capacidadeKg <= 0 || $capacidadeM3 <= 0 || $comprimento <= 0 || $largura <= 0 || $altura <= 0) {
            throw new Exception('Preencha os dados do veículo com valores válidos.');
        }

        if (!in_array($acesso, ['traseira', 'lateral', 'misto'], true)) {
            $acesso = 'traseira';
        }

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE veiculos SET
                    tipo = ?, nome = ?, capacidade_kg = ?, capacidade_m3 = ?, comprimento_m = ?, largura_m = ?, altura_m = ?,
                    max_lastros = ?, acesso_descarga = ?, quantidade_disponivel = ?, observacoes = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $stmt->execute([
                $tipo,
                $nome,
                $capacidadeKg,
                $capacidadeM3,
                $comprimento,
                $largura,
                $altura,
                $maxLastros,
                $acesso,
                $qtdDisponivel,
                $observacoes,
                $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO veiculos
                (tipo, nome, capacidade_kg, capacidade_m3, comprimento_m, largura_m, altura_m, max_lastros, acesso_descarga, quantidade_disponivel, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tipo,
            $nome,
            $capacidadeKg,
            $capacidadeM3,
            $comprimento,
            $largura,
            $altura,
            $maxLastros,
            $acesso,
            $qtdDisponivel,
            $observacoes,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function excluir(int $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM veiculos WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

