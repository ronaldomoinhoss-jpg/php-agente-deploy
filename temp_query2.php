<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=controle_estoque;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SHOW CREATE TABLE inv');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query('SELECT * FROM inv LIMIT 1');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
