<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_driver = 'sqlite';
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'cubagem2_db';
$db_port = '3306';
$sqlite_file = __DIR__ . '/../sql/cubagem2.sqlite';

$pdo = null;

if ($db_driver === 'sqlite') {
    try {
        $sqlite_dir = dirname($sqlite_file);
        if (!is_dir($sqlite_dir)) {
            mkdir($sqlite_dir, 0777, true);
        }

        $needs_init = !file_exists($sqlite_file) || filesize($sqlite_file) === 0;
        $pdo = new PDO('sqlite:' . $sqlite_file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        if ($needs_init) {
            $sql_file = __DIR__ . '/../sql/database_sqlite.sql';
            if (file_exists($sql_file)) {
                $pdo->exec(file_get_contents($sql_file));
            }
        }

        $requiredTables = ['veiculos', 'bases_operacionais', 'materiais', 'regras_operacionais', 'pedidos_carga', 'simulacoes'];
        $existingTables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $missingTables = array_diff($requiredTables, $existingTables ?: []);
        if (!empty($missingTables)) {
            $sql_file = __DIR__ . '/../sql/database_sqlite.sql';
            if (file_exists($sql_file)) {
                $pdo->exec(file_get_contents($sql_file));
            }
        }
    } catch (PDOException $e) {
        die('Erro ao inicializar o banco SQLite local: ' . $e->getMessage());
    }
} else {
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        try {
            $pdo_root = new PDO(
                "mysql:host={$db_host};port={$db_port};charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $sql_file = __DIR__ . '/../sql/database.sql';
            if (file_exists($sql_file)) {
                $pdo_root->exec(file_get_contents($sql_file));
            }

            $pdo = new PDO(
                "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e2) {
            die('Erro de conexão com o banco de dados: ' . $e2->getMessage());
        }
    }
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }

    return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
}

function parse_bool_flag($value) {
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'sim', 'yes', 'y', 'on'], true);
}

function json_response($status, $message, $data = [], $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function check_auth() {
    if (empty($_SESSION['usuario_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response('error', 'Sessão expirada. Faça login novamente.', [], 401);
        }

        header('Location: login.php');
        exit;
    }
}

function get_logged_user() {
    return [
        'id' => $_SESSION['usuario_id'] ?? 1,
        'nome' => $_SESSION['usuario_nome'] ?? 'Operador Logístico',
        'email' => $_SESSION['usuario_email'] ?? 'admin@energia.com.br',
        'cargo' => $_SESSION['usuario_cargo'] ?? 'Gerente de Logística',
    ];
}
