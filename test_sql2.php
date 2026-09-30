<?php
$pdo = new PDO('mysql:host=localhost;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

try { $pdo->query("ALTER TABLE `test`.`test` CHANGE `a` `b` "); } catch (Exception $e) { echo "Test 6: " . $e->getMessage() . "\n"; }
