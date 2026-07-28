<?php
require_once __DIR__ . '/config/conexao.php';
session_destroy();
header('Location: login.php');
exit;
