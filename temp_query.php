<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=controle_estoque;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SELECT dif_final, qtd_final, qtd_sap, diferenca, pmm FROM inv LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
