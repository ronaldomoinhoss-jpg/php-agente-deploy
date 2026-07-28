<?php
require_once __DIR__ . '/config/conexao.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
