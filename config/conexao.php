<?php
// Configurações de Conexão com o Banco de Dados MySQL
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'cubagem_db';
$db_port = '3306';

try {
    // Tenta conexão direta com a base de dados
    $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Se a base não existe, tenta criar e importar a estrutura automaticamente
    try {
        $pdo_root = new PDO("mysql:host={$db_host};port={$db_port};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $sql_file = __DIR__ . '/../sql/database.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            $pdo_root->exec($sql_content);
        }
        
        // Reconecta na base recém criada
        $pdo = new PDO("mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e2) {
        die("Erro grave de conexão com o banco de dados MySQL: " . $e2->getMessage());
    }
}

// Funções Auxiliares Globais
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function json_response($status, $message, $data = [], $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function check_auth() {
    if (empty($_SESSION['usuario_id'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            json_response('error', 'Sessão expirada. Faça login novamente.', [], 401);
        } else {
            header('Location: login.php');
            exit;
        }
    }
}

function get_logged_user() {
    return [
        'id' => $_SESSION['usuario_id'] ?? 1,
        'nome' => $_SESSION['usuario_nome'] ?? 'Operador Logístico',
        'email' => $_SESSION['usuario_email'] ?? 'admin@energia.com.br',
        'cargo' => $_SESSION['usuario_cargo'] ?? 'Gerente de Logística'
    ];
}
