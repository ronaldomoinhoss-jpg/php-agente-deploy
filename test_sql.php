<?php
$pdo = new PDO('mysql:host=localhost;port=3306;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

try { $pdo->query("SHOW TABLES FROM ``"); } catch (Exception $e) { echo "Test 1: " . $e->getMessage() . "\n"; }
try { $pdo->query("ALTER TABLE ``.`test` CHANGE `a` `b` "); } catch (Exception $e) { echo "Test 2: " . $e->getMessage() . "\n"; }
try { $pdo->query("CREATE TABLE ``.`test` (a INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); } catch (Exception $e) { echo "Test 3: " . $e->getMessage() . "\n"; }
try { $pdo->query("ALTER TABLE `test`.`test` "); } catch (Exception $e) { echo "Test 4: " . $e->getMessage() . "\n"; }
try { $pdo->query("ALTER TABLE `test`.`test` ADD COLUMN `a` "); } catch (Exception $e) { echo "Test 5: " . $e->getMessage() . "\n"; }
