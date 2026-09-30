<?php
/**
 * DB Table Manager - Gerenciador de Tabelas MySQL
 * Funcionalidades: Criar, Editar, Remover, Exportar tabelas
 * Com suporte a colar colunas em massa, renomear e remover colunas
 */

// ─── CONFIGURAÇÃO DO BANCO ────────────────────────────────────────────────────
$config = [
    'host'     => 'localhost',
    'port'     => 3306,
    'user'     => 'root',
    'password' => '',
    'dbname'   => 'controle_estoque',  // Banco padrão
    'charset'  => 'utf8mb4',
];

// ─── TIPOS DE COLUNAS DISPONÍVEIS ────────────────────────────────────────────
$columnTypes = [
    'INT','BIGINT','TINYINT','SMALLINT','MEDIUMINT','FLOAT','DOUBLE','DECIMAL',
    'VARCHAR','CHAR','TEXT','TINYTEXT','MEDIUMTEXT','LONGTEXT',
    'DATE','DATETIME','TIMESTAMP','TIME','YEAR',
    'BOOLEAN','ENUM','SET','JSON','BLOB','MEDIUMBLOB','LONGBLOB'
];

// ─── CONEXÃO ─────────────────────────────────────────────────────────────────
$pdo = null;
$dbError = '';
$selectedDb = $_GET['db'] ?? $_POST['db'] ?? 'controle_estoque';

function connectDB($cfg, $db = '') {
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
    if ($db) $dsn .= ";dbname={$db}";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

$pdo = connectDB($config, $selectedDb);

// ─── FUNÇÕES AUXILIARES ───────────────────────────────────────────────────────
function getDatabases($pdo) {
    if (!$pdo) return [];
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = array_column($stmt->fetchAll(), 'Database');
    return array_filter($dbs, fn($d) => !in_array($d, ['information_schema','performance_schema','mysql','sys']));
}

function getTables($pdo, $db) {
    if (!$pdo || !$db) return [];
    try {
        $stmt = $pdo->query("SHOW TABLES FROM `$db`");
        return array_column($stmt->fetchAll(PDO::FETCH_NUM), 0);
    } catch (\Exception $e) { return []; }
}

function getColumns($pdo, $db, $table) {
    if (!$pdo || !$db || !$table) return [];
    try {
        $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$db`.`$table`");
        return $stmt->fetchAll();
    } catch (\Exception $e) { return []; }
}

function getTableInfo($pdo, $db, $table) {
    if (!$pdo || !$db || !$table) return [];
    try {
        $stmt = $pdo->query("SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table'");
        return $stmt->fetch();
    } catch (\Exception $e) { return []; }
}

function exportTable($pdo, $db, $table) {
    if (!$pdo || !$db || !$table) return '';
    $sql = "-- Exportado por DB Table Manager\n-- Banco: $db | Tabela: $table\n-- Data: " . date('Y-m-d H:i:s') . "\n\n";
    // CREATE TABLE
    $stmt = $pdo->query("SHOW CREATE TABLE `$db`.`$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";
    // INSERT DATA
    $stmt = $pdo->query("SELECT * FROM `$db`.`$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    if ($rows) {
        $sql .= "INSERT INTO `$table` VALUES\n";
        $lines = [];
        foreach ($rows as $r) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $r);
            $lines[] = "(" . implode(", ", $vals) . ")";
        }
        $sql .= implode(",\n", $lines) . ";\n";
    }
    return $sql;
}

// ─── PROCESSAR AÇÕES POST ────────────────────────────────────────────────────
$message = '';
$messageType = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $db    = $_POST['db'] ?? $selectedDb;
    $table = $_POST['table'] ?? '';

    // CRIAR TABELA
    if ($action === 'create_table') {
        $newTable = trim($_POST['table_name'] ?? '');
        $engine   = $_POST['engine'] ?? 'InnoDB';
        $charset  = $_POST['charset'] ?? 'utf8mb4';
        $cols     = $_POST['columns'] ?? [];

        if ($newTable && $cols) {
            $colDefs = [];
            $primaryKey = null;
            foreach ($cols as $col) {
                $name    = trim($col['name'] ?? '');
                $type    = $col['type'] ?? 'VARCHAR';
                $length  = trim($col['length'] ?? '');
                $notnull = !empty($col['notnull']);
                $ai      = !empty($col['ai']);
                $pk      = !empty($col['pk']);
                $default = trim($col['default'] ?? '');
                if (!$name) continue;

                $def = "`$name` $type";
                if ($length && !in_array(strtoupper($type), ['TEXT','BLOB','DATE','DATETIME','TIMESTAMP','BOOLEAN','JSON'])) {
                    $def .= "($length)";
                } elseif (!$length && in_array(strtoupper($type), ['VARCHAR', 'CHAR'])) {
                    $def .= "(255)";
                }
                if ($notnull) $def .= " NOT NULL";
                if ($ai) $def .= " AUTO_INCREMENT";
                if ($default !== '') $def .= " DEFAULT '$default'";
                if ($pk) $primaryKey = $name;
                $colDefs[] = $def;
            }
            if ($primaryKey) $colDefs[] = "PRIMARY KEY (`$primaryKey`)";

            $sql = "CREATE TABLE `$db`.`$newTable` (\n  " . implode(",\n  ", $colDefs) . "\n) ENGINE=$engine DEFAULT CHARSET=$charset;";
            try {
                $pdo->exec($sql);
                $message = "✓ Tabela '$newTable' criada com sucesso!";
                $messageType = 'success';
                header("Location: ?db=$db&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // RENOMEAR TABELA
    if ($action === 'rename_table') {
        $newName = trim($_POST['new_table_name'] ?? '');
        if ($table && $newName) {
            try {
                $pdo->exec("RENAME TABLE `$db`.`$table` TO `$db`.`$newName`");
                $message = "✓ Tabela renomeada para '$newName'!";
                $messageType = 'success';
                header("Location: ?db=$db&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // REMOVER TABELA
    if ($action === 'drop_table') {
        if ($table && ($_POST['confirm'] ?? '') === $table) {
            try {
                $pdo->exec("DROP TABLE `$db`.`$table`");
                $message = "✓ Tabela '$table' removida!";
                $messageType = 'success';
                header("Location: ?db=$db&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = "✗ Confirmação incorreta. Digite o nome exato da tabela.";
            $messageType = 'error';
        }
    }

    // ADICIONAR COLUNAS (incluindo colagem em massa)
    if ($action === 'add_columns') {
        $cols = $_POST['columns'] ?? [];
        $after = $_POST['after_column'] ?? '';
        $alters = [];
        foreach ($cols as $col) {
            $name    = trim($col['name'] ?? '');
            $type    = $col['type'] ?? 'VARCHAR';
            $length  = trim($col['length'] ?? '');
            $notnull = !empty($col['notnull']);
            $default = trim($col['default'] ?? '');
            if (!$name) continue;

            $def = "ADD COLUMN `$name` $type";
            if ($length && !in_array(strtoupper($type), ['TEXT','BLOB','DATE','DATETIME','TIMESTAMP','BOOLEAN','JSON'])) {
                $def .= "($length)";
            } elseif (!$length && in_array(strtoupper($type), ['VARCHAR', 'CHAR'])) {
                $def .= "(255)";
            }
            if ($notnull) $def .= " NOT NULL";
            if ($default !== '') $def .= " DEFAULT '$default'";
            $alters[] = $def;
        }
        if ($alters && $table) {
            $sql = "ALTER TABLE `$db`.`$table` " . implode(", ", $alters);
            try {
                $pdo->exec($sql);
                $message = "✓ " . count($alters) . " coluna(s) adicionada(s) com sucesso!";
                $messageType = 'success';
                header("Location: ?db=$db&table=$table&view=edit&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // RENOMEAR COLUNA
    if ($action === 'rename_column') {
        $oldCol  = $_POST['old_column'] ?? '';
        $newCol  = trim($_POST['new_column'] ?? '');
        $colType = $_POST['col_type'] ?? 'VARCHAR(255)';
        if ($table && $oldCol && $newCol) {
            try {
                $pdo->exec("ALTER TABLE `$db`.`$table` CHANGE `$oldCol` `$newCol` $colType");
                $message = "✓ Coluna '$oldCol' renomeada para '$newCol'!";
                $messageType = 'success';
                header("Location: ?db=$db&table=$table&view=edit&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // REMOVER COLUNA
    if ($action === 'drop_column') {
        $col = $_POST['column_name'] ?? '';
        if ($table && $col) {
            try {
                $pdo->exec("ALTER TABLE `$db`.`$table` DROP COLUMN `$col`");
                $message = "✓ Coluna '$col' removida!";
                $messageType = 'success';
                header("Location: ?db=$db&table=$table&view=edit&msg=" . urlencode($message) . "&mtype=success");
                exit;
            } catch (PDOException $e) {
                $message = "✗ Erro: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Mensagens via GET (após redirect)
if (!$message && isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['mtype'] ?? 'info';
}

// ─── EXPORTAR (GET) ───────────────────────────────────────────────────────────
if ($action === 'export' && $pdo) {
    $db    = $_GET['db'] ?? $selectedDb;
    $table = $_GET['table'] ?? '';
    if ($db && $table) {
        $sql = exportTable($pdo, $db, $table);
        header('Content-Type: text/plain; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$db}_{$table}_" . date('Ymd_His') . ".sql\"");
        echo $sql;
        exit;
    }
}

// ─── DADOS PARA TEMPLATE ──────────────────────────────────────────────────────
$databases  = getDatabases($pdo);
$tables     = $selectedDb ? getTables($pdo, $selectedDb) : [];
$view       = $_GET['view'] ?? 'list';
$selectedTable = $_GET['table'] ?? '';
$columns    = ($selectedDb && $selectedTable) ? getColumns($pdo, $selectedDb, $selectedTable) : [];
$tableInfo  = ($selectedDb && $selectedTable) ? getTableInfo($pdo, $selectedDb, $selectedTable) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DB Table Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;600;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --bg:        #0a0c10;
  --surface:   #111318;
  --surface2:  #181c22;
  --border:    #252a34;
  --border2:   #2e3545;
  --accent:    #00d4ff;
  --accent2:   #7c3aed;
  --green:     #10b981;
  --red:       #ef4444;
  --yellow:    #f59e0b;
  --text:      #e2e8f0;
  --muted:     #64748b;
  --mono:      'JetBrains Mono', monospace;
  --sans:      'Syne', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--mono);
  font-size: 13px;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ─── HEADER ─────────────────────────────────────── */
header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 24px;
  display: flex;
  align-items: center;
  gap: 32px;
  height: 56px;
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo {
  font-family: var(--sans);
  font-size: 18px;
  font-weight: 800;
  color: var(--accent);
  letter-spacing: -0.5px;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 8px;
}
.logo span { color: var(--muted); font-weight: 400; font-size: 13px; }

.db-selector {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}
.db-selector label { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }

select, input[type=text], input[type=number], input[type=password] {
  background: var(--bg);
  border: 1px solid var(--border2);
  color: var(--text);
  padding: 7px 12px;
  border-radius: 6px;
  font-family: var(--mono);
  font-size: 13px;
  outline: none;
  transition: border-color .2s;
}
select:focus, input:focus { border-color: var(--accent); }
select { cursor: pointer; }

/* ─── LAYOUT ─────────────────────────────────────── */
.layout {
  display: flex;
  flex: 1;
  min-height: 0;
}

/* ─── SIDEBAR ────────────────────────────────────── */
.sidebar {
  width: 240px;
  min-width: 240px;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.sidebar-header {
  padding: 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sidebar-header h3 {
  font-family: var(--sans);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--muted);
}

.sidebar-items { padding: 8px 0; }
.table-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  cursor: pointer;
  border-left: 2px solid transparent;
  transition: all .15s;
  color: var(--text);
  text-decoration: none;
  font-size: 12px;
}
.table-item:hover { background: var(--surface2); color: var(--accent); border-left-color: var(--border2); }
.table-item.active { background: rgba(0,212,255,.07); border-left-color: var(--accent); color: var(--accent); }
.table-item .icon { color: var(--muted); font-size: 10px; }
.table-item.active .icon { color: var(--accent); }

.table-count {
  margin-left: auto;
  background: var(--border2);
  color: var(--muted);
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 10px;
}

/* ─── MAIN ───────────────────────────────────────── */
.main {
  flex: 1;
  padding: 28px 32px;
  overflow-y: auto;
}

/* ─── MESSAGES ───────────────────────────────────── */
.msg {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  border: 1px solid;
}
.msg.success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: var(--green); }
.msg.error   { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: var(--red); }
.msg.info    { background: rgba(0,212,255,.07); border-color: rgba(0,212,255,.2);  color: var(--accent); }

/* ─── PAGE TITLE ─────────────────────────────────── */
.page-title {
  font-family: var(--sans);
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-title .badge {
  font-size: 10px;
  background: var(--border2);
  color: var(--muted);
  padding: 3px 10px;
  border-radius: 20px;
  font-family: var(--mono);
  font-weight: 400;
}
.page-sub { color: var(--muted); font-size: 12px; margin-bottom: 28px; }

/* ─── CARDS GRID ─────────────────────────────────── */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
  margin-bottom: 28px;
}
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  transition: border-color .2s, transform .15s;
}
.card:hover { border-color: var(--border2); transform: translateY(-1px); }
.card-title {
  font-family: var(--sans);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 6px;
  color: var(--accent);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.card-meta { color: var(--muted); font-size: 11px; margin-bottom: 14px; line-height: 1.8; }
.card-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ─── BUTTONS ────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 7px;
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  text-decoration: none;
  transition: all .15s;
  white-space: nowrap;
}
.btn-primary  { background: var(--accent);  color: #000; border-color: var(--accent); }
.btn-primary:hover  { filter: brightness(1.1); }
.btn-outline  { background: transparent; color: var(--text); border-color: var(--border2); }
.btn-outline:hover  { border-color: var(--accent); color: var(--accent); }
.btn-danger   { background: rgba(239,68,68,.1); color: var(--red); border-color: rgba(239,68,68,.3); }
.btn-danger:hover   { background: rgba(239,68,68,.2); }
.btn-success  { background: rgba(16,185,129,.1); color: var(--green); border-color: rgba(16,185,129,.3); }
.btn-success:hover  { background: rgba(16,185,129,.2); }
.btn-purple   { background: rgba(124,58,237,.15); color: #a78bfa; border-color: rgba(124,58,237,.4); }
.btn-purple:hover   { background: rgba(124,58,237,.25); }
.btn-sm { padding: 4px 10px; font-size: 11px; }
.btn-icon { padding: 6px 8px; }

/* ─── SECTION / PANEL ────────────────────────────── */
.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
}
.panel-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.panel-title {
  font-family: var(--sans);
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.panel-body { padding: 20px; }

/* ─── TABLE ──────────────────────────────────────── */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th {
  background: var(--bg);
  color: var(--muted);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
  padding: 10px 14px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
td {
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
  font-size: 12px;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--surface2); }

.type-badge {
  display: inline-block;
  background: rgba(124,58,237,.15);
  color: #a78bfa;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-family: var(--mono);
}
.null-badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 10px;
}
.null-yes { background: rgba(239,68,68,.1); color: var(--red); }
.null-no  { background: rgba(16,185,129,.1); color: var(--green); }
.pk-badge  { background: rgba(245,158,11,.15); color: var(--yellow); padding: 2px 7px; border-radius: 4px; font-size: 10px; }

/* ─── FORM ───────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-label {
  display: block;
  color: var(--muted);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 7px;
}
.form-row {
  display: grid;
  gap: 14px;
  margin-bottom: 14px;
}
.form-row-3 { grid-template-columns: 2fr 1fr 1fr; }
.form-row-4 { grid-template-columns: 2fr 1fr 1fr 1fr; }
.form-row-5 { grid-template-columns: 2fr 1.2fr .8fr 1fr .5fr; }

input[type=text], input[type=number], select {
  width: 100%;
}
input[type=checkbox] {
  width: 16px; height: 16px;
  accent-color: var(--accent);
  cursor: pointer;
  margin: 0;
}

.col-row {
  display: grid;
  grid-template-columns: 2fr 1.4fr .9fr 1.2fr 36px 36px 36px;
  gap: 8px;
  align-items: center;
  padding: 8px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 6px;
}
.col-row:hover { border-color: var(--border2); }
.col-center { display: flex; align-items: center; justify-content: center; }
.col-label {
  font-size: 10px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 2px;
}

.col-rows-header {
  display: grid;
  grid-template-columns: 2fr 1.4fr .9fr 1.2fr 36px 36px 36px;
  gap: 8px;
  padding: 6px 8px;
  color: var(--muted);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 4px;
}

textarea {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border2);
  color: var(--text);
  font-family: var(--mono);
  font-size: 13px;
  padding: 12px;
  border-radius: 8px;
  resize: vertical;
  outline: none;
  line-height: 1.6;
}
textarea:focus { border-color: var(--accent); }
textarea.bulk { min-height: 120px; font-size: 12px; }

/* ─── TABS ───────────────────────────────────────── */
.tabs {
  display: flex;
  gap: 2px;
  background: var(--bg);
  padding: 4px;
  border-radius: 10px;
  margin-bottom: 24px;
  width: fit-content;
}
.tab {
  padding: 8px 18px;
  border-radius: 7px;
  font-family: var(--mono);
  font-size: 12px;
  cursor: pointer;
  border: none;
  background: transparent;
  color: var(--muted);
  transition: all .15s;
}
.tab.active { background: var(--surface); color: var(--accent); box-shadow: 0 1px 3px rgba(0,0,0,.4); }
.tab:hover:not(.active) { color: var(--text); }

/* ─── MODALS ─────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.75);
  z-index: 200;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 14px;
  width: 100%;
  max-width: 500px;
  padding: 28px;
  animation: slideUp .2s ease;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-wide { max-width: 760px; }
@keyframes slideUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
.modal-title {
  font-family: var(--sans);
  font-size: 17px;
  font-weight: 800;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

/* ─── MISC ───────────────────────────────────────── */
.divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
.text-muted { color: var(--muted); }
.text-accent { color: var(--accent); }
.text-red { color: var(--red); }
.mt-0 { margin-top: 0 !important; }
.gap-10 { display: flex; gap: 10px; flex-wrap: wrap; }
.empty {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}
.empty-icon { font-size: 40px; margin-bottom: 14px; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); display: inline-block; }
.dot.red { background: var(--red); }

.bulk-hint {
  background: rgba(0,212,255,.05);
  border: 1px dashed rgba(0,212,255,.25);
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 14px;
  line-height: 1.7;
}
.bulk-hint strong { color: var(--accent); }

.kbd {
  background: var(--bg);
  border: 1px solid var(--border2);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 11px;
  color: var(--text);
  font-family: var(--mono);
}

/* ─── SCROLLBAR ──────────────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }
</style>
</head>
<body>

<!-- ─── HEADER ─────────────────────────────────── -->
<header>
  <div class="logo">
    ◈ DBManager <span>/ phpMyAdmin Bridge</span>
  </div>
  <div class="db-selector">
    <label>Banco de Dados</label>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
      <select name="db" onchange="this.form.submit()" style="min-width:180px;">
        <option value="">— selecione —</option>
        <?php foreach ($databases as $db): ?>
        <option value="<?= htmlspecialchars($db) ?>" <?= $db === $selectedDb ? 'selected' : '' ?>>
          <?= htmlspecialchars($db) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if (!$pdo): ?>
      <span class="dot red"></span>
      <?php else: ?>
      <span class="dot"></span>
      <?php endif; ?>
    </form>
  </div>
</header>

<!-- ─── BODY ───────────────────────────────────── -->
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <h3>Tabelas <?= $selectedDb ? "({$selectedDb})" : '' ?></h3>
      <?php if ($selectedDb && $pdo): ?>
      <button class="btn btn-primary btn-sm" onclick="openModal('modal-create')">+ Nova</button>
      <?php endif; ?>
    </div>
    <div class="sidebar-items">
      <?php if (!$selectedDb): ?>
        <div style="padding:20px;color:var(--muted);font-size:12px;text-align:center;">
          Selecione um banco acima
        </div>
      <?php elseif (empty($tables)): ?>
        <div style="padding:20px;color:var(--muted);font-size:12px;text-align:center;">
          Nenhuma tabela encontrada
        </div>
      <?php else: ?>
        <?php foreach ($tables as $t): ?>
        <a class="table-item <?= $t === $selectedTable ? 'active' : '' ?>"
           href="?db=<?= urlencode($selectedDb) ?>&table=<?= urlencode($t) ?>&view=edit">
          <span class="icon">▤</span>
          <?= htmlspecialchars($t) ?>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">

    <?php if ($message): ?>
    <div class="msg <?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
      <span style="margin-left:auto;cursor:pointer;opacity:.5" onclick="this.parentElement.remove()">✕</span>
    </div>
    <?php endif; ?>

    <?php if (!$selectedDb || !$pdo): ?>
    <!-- ─── HOME / NO DB ─────────────────────────── -->
    <div class="empty">
      <div class="empty-icon">⬡</div>
      <h2 style="font-family:var(--sans);font-size:20px;margin-bottom:8px;color:var(--text)">DB Table Manager</h2>
      <p>Selecione um banco de dados para começar.</p>
      <?php if (!$pdo): ?>
      <p class="text-red" style="margin-top:12px;font-size:12px;">⚠ Sem conexão com o MySQL. Verifique as configurações no topo do arquivo.</p>
      <?php endif; ?>
    </div>

    <?php elseif ($view === 'list' || !$selectedTable): ?>
    <!-- ─── TABLE LIST ─────────────────────────────── -->
    <div class="page-title">
      <?= htmlspecialchars($selectedDb) ?>
      <span class="badge"><?= count($tables) ?> tabelas</span>
    </div>
    <p class="page-sub">Gerencie as tabelas do banco de dados.</p>

    <div style="margin-bottom:18px;" class="gap-10">
      <button class="btn btn-primary" onclick="openModal('modal-create')">+ Criar Tabela</button>
    </div>

    <?php if (empty($tables)): ?>
    <div class="empty">
      <div class="empty-icon">☰</div>
      <p>Nenhuma tabela neste banco. Crie a primeira!</p>
    </div>
    <?php else: ?>
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">◈ Tabelas</div>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Linhas (aprox.)</th>
              <th>Engine</th>
              <th>Charset</th>
              <th>Tamanho</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tables as $t):
              $info = getTableInfo($pdo, $selectedDb, $t);
            ?>
            <tr>
              <td>
                <a href="?db=<?= urlencode($selectedDb) ?>&table=<?= urlencode($t) ?>&view=edit"
                   style="color:var(--accent);text-decoration:none;font-weight:500;">
                  <?= htmlspecialchars($t) ?>
                </a>
              </td>
              <td class="text-muted"><?= number_format((int)($info['TABLE_ROWS'] ?? 0)) ?></td>
              <td class="text-muted"><?= $info['ENGINE'] ?? '—' ?></td>
              <td class="text-muted"><?= $info['TABLE_COLLATION'] ?? '—' ?></td>
              <td class="text-muted"><?= number_format((($info['DATA_LENGTH'] ?? 0) + ($info['INDEX_LENGTH'] ?? 0)) / 1024, 1) ?> KB</td>
              <td>
                <div class="gap-10">
                  <a class="btn btn-outline btn-sm" href="?db=<?= urlencode($selectedDb) ?>&table=<?= urlencode($t) ?>&view=edit">Editar</a>
                  <a class="btn btn-success btn-sm" href="?db=<?= urlencode($selectedDb) ?>&table=<?= urlencode($t) ?>&action=export">↓ SQL</a>
                  <button class="btn btn-danger btn-sm" onclick="openDropTable('<?= addslashes($t) ?>')">Remover</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php elseif ($view === 'edit' && $selectedTable): ?>
    <!-- ─── EDIT TABLE ─────────────────────────────── -->
    <div class="page-title">
      <a href="?db=<?= urlencode($selectedDb) ?>" style="color:var(--muted);text-decoration:none;font-size:16px;">←</a>
      <?= htmlspecialchars($selectedTable) ?>
      <span class="badge"><?= count($columns) ?> colunas</span>
    </div>
    <p class="page-sub">
      <?= htmlspecialchars($selectedDb) ?> ·
      Rows: <?= number_format((int)($tableInfo['TABLE_ROWS'] ?? 0)) ?> ·
      Engine: <?= $tableInfo['ENGINE'] ?? '?' ?>
    </p>

    <div class="gap-10" style="margin-bottom:24px;">
      <button class="btn btn-primary" onclick="openModal('modal-addcols')">+ Adicionar Colunas</button>
      <button class="btn btn-purple" onclick="openModal('modal-bulk')">⊞ Colar em Massa</button>
      <button class="btn btn-outline" onclick="openModal('modal-rename-table')">✎ Renomear Tabela</button>
      <a class="btn btn-success" href="?db=<?= urlencode($selectedDb) ?>&table=<?= urlencode($selectedTable) ?>&action=export">↓ Exportar SQL</a>
      <button class="btn btn-danger" onclick="openDropTable('<?= addslashes($selectedTable) ?>')">✕ Remover Tabela</button>
    </div>

    <!-- COLUMNS TABLE -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">◈ Colunas da Tabela</div>
        <span style="font-size:11px;color:var(--muted)">Clique para renomear ou remover</span>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Nome</th>
              <th>Tipo</th>
              <th>Nulo</th>
              <th>Padrão</th>
              <th>Extra</th>
              <th>Chave</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($columns as $i => $col): ?>
            <tr>
              <td class="text-muted"><?= $i + 1 ?></td>
              <td style="font-weight:600;color:var(--text);"><?= htmlspecialchars($col['Field']) ?></td>
              <td><span class="type-badge"><?= htmlspecialchars($col['Type']) ?></span></td>
              <td>
                <span class="null-badge <?= $col['Null'] === 'YES' ? 'null-yes' : 'null-no' ?>">
                  <?= $col['Null'] ?>
                </span>
              </td>
              <td class="text-muted"><?= $col['Default'] !== null ? htmlspecialchars($col['Default']) : '<span style="opacity:.4">NULL</span>' ?></td>
              <td class="text-muted"><?= $col['Extra'] ?: '—' ?></td>
              <td>
                <?php if ($col['Key'] === 'PRI'): ?>
                <span class="pk-badge">PK</span>
                <?php elseif ($col['Key']): ?>
                <span class="pk-badge" style="background:rgba(100,116,139,.15);color:var(--muted);"><?= $col['Key'] ?></span>
                <?php else: echo '—'; endif; ?>
              </td>
              <td>
                <div class="gap-10">
                  <button class="btn btn-outline btn-sm"
                    onclick="openRenameCol('<?= addslashes($col['Field']) ?>', '<?= addslashes($col['Type']) ?>')">
                    ✎
                  </button>
                  <button class="btn btn-danger btn-sm"
                    onclick="openDropCol('<?= addslashes($col['Field']) ?>')">
                    ✕
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>

  </main>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- MODAIS                                         -->
<!-- ══════════════════════════════════════════════ -->

<!-- ─── MODAL: CRIAR TABELA ─────────────────────── -->
<div class="modal-overlay" id="modal-create">
  <div class="modal modal-wide">
    <div class="modal-title">◈ Criar Nova Tabela</div>
    <form method="POST">
      <input type="hidden" name="action" value="create_table">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">

      <div class="form-row form-row-3">
        <div>
          <div class="form-label">Nome da Tabela</div>
          <input type="text" name="table_name" placeholder="ex: usuarios" required>
        </div>
        <div>
          <div class="form-label">Engine</div>
          <select name="engine">
            <option value="InnoDB" selected>InnoDB</option>
            <option value="MyISAM">MyISAM</option>
            <option value="MEMORY">MEMORY</option>
          </select>
        </div>
        <div>
          <div class="form-label">Charset</div>
          <select name="charset">
            <option value="utf8mb4" selected>utf8mb4</option>
            <option value="utf8">utf8</option>
            <option value="latin1">latin1</option>
          </select>
        </div>
      </div>

      <hr class="divider" style="margin:16px 0;">

      <div class="col-rows-header">
        <span>Nome da Coluna</span><span>Tipo</span><span>Tamanho</span><span>Padrão</span>
        <span title="NOT NULL">NN</span><span title="AUTO INCREMENT">AI</span><span title="Primary Key">PK</span>
      </div>

      <div id="create-col-list">
        <!-- coluna padrão id -->
        <div class="col-row">
          <input type="text" name="columns[0][name]" value="id" placeholder="nome">
          <select name="columns[0][type]"><?php foreach($columnTypes as $t) echo "<option".($t==='INT'?' selected':'').">$t</option>"; ?></select>
          <input type="number" name="columns[0][length]" placeholder="tam" min="1">
          <input type="text" name="columns[0][default]" placeholder="default">
          <div class="col-center"><input type="checkbox" name="columns[0][notnull]" checked title="NOT NULL"></div>
          <div class="col-center"><input type="checkbox" name="columns[0][ai]" checked title="AUTO_INCREMENT"></div>
          <div class="col-center"><input type="checkbox" name="columns[0][pk]" checked title="Primary Key"></div>
        </div>
      </div>

      <div class="gap-10" style="margin-top:12px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="addColRow('create-col-list','create')">+ Adicionar Coluna</button>
        <button type="button" class="btn btn-purple btn-sm" onclick="openModal('modal-bulk-create')">⊞ Colar em Massa</button>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-create')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Criar Tabela →</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL: BULK PASTE (CRIAR) ───────────────── -->
<div class="modal-overlay" id="modal-bulk-create">
  <div class="modal">
    <div class="modal-title">⊞ Colar Colunas em Massa</div>
    <div class="bulk-hint">
      Cole os nomes das colunas — <strong>um por linha</strong> ou separados por <strong>vírgula</strong>, espaço ou tab.<br>
      Ex: <span class="kbd">nome, email, senha, criado_em</span><br>
      As colunas serão adicionadas ao formulário de criação.
    </div>
    <div class="form-group">
      <div class="form-label">Colunas</div>
      <textarea class="bulk" id="bulk-create-input" placeholder="nome&#10;email&#10;senha&#10;criado_em&#10;..."></textarea>
    </div>
    <div class="form-group">
      <div class="form-label">Tipo Padrão para Todas</div>
      <select id="bulk-create-type" style="width:auto;">
        <?php foreach($columnTypes as $t) echo "<option".($t==='VARCHAR'?' selected':'').">$t</option>"; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-bulk-create')">Voltar</button>
      <button type="button" class="btn btn-primary" onclick="applyBulkToCreate()">Aplicar →</button>
    </div>
  </div>
</div>

<!-- ─── MODAL: ADICIONAR COLUNAS (EDIÇÃO) ───────── -->
<div class="modal-overlay" id="modal-addcols">
  <div class="modal modal-wide">
    <div class="modal-title">+ Adicionar Colunas</div>
    <form method="POST">
      <input type="hidden" name="action" value="add_columns">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">
      <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">

      <div class="col-rows-header">
        <span>Nome da Coluna</span><span>Tipo</span><span>Tamanho</span><span>Padrão</span>
        <span title="NOT NULL">NN</span><span></span><span></span>
      </div>

      <div id="add-col-list">
        <div class="col-row">
          <input type="text" name="columns[0][name]" placeholder="nome_coluna" required>
          <select name="columns[0][type]"><?php foreach($columnTypes as $t) echo "<option".($t==='VARCHAR'?' selected':'').">$t</option>"; ?></select>
          <input type="number" name="columns[0][length]" placeholder="255" min="1">
          <input type="text" name="columns[0][default]" placeholder="default">
          <div class="col-center"><input type="checkbox" name="columns[0][notnull]" title="NOT NULL"></div>
          <div></div><div></div>
        </div>
      </div>

      <div class="gap-10" style="margin-top:12px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="addColRow('add-col-list','add')">+ Coluna</button>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-addcols')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Colunas →</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL: BULK PASTE (EDIÇÃO) ──────────────── -->
<div class="modal-overlay" id="modal-bulk">
  <div class="modal">
    <div class="modal-title">⊞ Colar Colunas em Massa</div>
    <div class="bulk-hint">
      Cole os nomes — <strong>um por linha</strong>, vírgula ou tab.<br>
      Todas serão adicionadas à tabela <strong><?= htmlspecialchars($selectedTable) ?></strong>.
    </div>
    <div class="form-group">
      <div class="form-label">Colunas</div>
      <textarea class="bulk" id="bulk-edit-input" placeholder="coluna_a&#10;coluna_b&#10;coluna_c&#10;..."></textarea>
    </div>
    <div class="form-row form-row-3" style="gap:12px;margin-bottom:0;">
      <div>
        <div class="form-label">Tipo</div>
        <select id="bulk-edit-type" style="width:100%;">
          <?php foreach($columnTypes as $t) echo "<option".($t==='VARCHAR'?' selected':'').">$t</option>"; ?>
        </select>
      </div>
      <div>
        <div class="form-label">Tamanho</div>
        <input type="number" id="bulk-edit-length" placeholder="255" min="1" style="width:100%;">
      </div>
      <div>
        <div class="form-label">NOT NULL</div>
        <div class="col-center" style="justify-content:flex-start;padding-top:6px;">
          <input type="checkbox" id="bulk-edit-notnull">
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-bulk')">Cancelar</button>
      <button type="button" class="btn btn-primary" onclick="submitBulkAdd()">Adicionar Colunas →</button>
    </div>
  </div>
</div>

<!-- ─── MODAL: RENOMEAR TABELA ───────────────────── -->
<div class="modal-overlay" id="modal-rename-table">
  <div class="modal">
    <div class="modal-title">✎ Renomear Tabela</div>
    <form method="POST">
      <input type="hidden" name="action" value="rename_table">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">
      <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
      <div class="form-group">
        <div class="form-label">Nome Atual</div>
        <input type="text" value="<?= htmlspecialchars($selectedTable) ?>" disabled style="opacity:.5;">
      </div>
      <div class="form-group">
        <div class="form-label">Novo Nome</div>
        <input type="text" name="new_table_name" placeholder="novo_nome" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-rename-table')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Renomear →</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL: REMOVER TABELA ────────────────────── -->
<div class="modal-overlay" id="modal-drop-table">
  <div class="modal">
    <div class="modal-title" style="color:var(--red);">⚠ Remover Tabela</div>
    <p style="margin-bottom:16px;color:var(--muted);font-size:13px;">
      Esta ação é <strong style="color:var(--red)">irreversível</strong>. Todos os dados da tabela serão perdidos permanentemente.
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="drop_table">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">
      <input type="hidden" name="table" id="drop-table-name-hidden">
      <div class="form-group">
        <div class="form-label">Digite o nome da tabela para confirmar</div>
        <input type="text" name="confirm" id="drop-table-confirm" placeholder="nome_da_tabela" required autocomplete="off">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-drop-table')">Cancelar</button>
        <button type="submit" class="btn btn-danger">Remover Permanentemente</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL: EDITAR COLUNA ───────────────────── -->
<div class="modal-overlay" id="modal-rename-col">
  <div class="modal">
    <div class="modal-title">✎ Editar Coluna</div>
    <form method="POST">
      <input type="hidden" name="action" value="rename_column">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">
      <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
      <input type="hidden" name="old_column" id="rename-col-old">
      
      <div class="form-group">
        <div class="form-label">Coluna Atual</div>
        <input type="text" id="rename-col-old-display" disabled style="opacity:.5;">
      </div>
      <div class="form-group">
        <div class="form-label">Novo Nome</div>
        <input type="text" name="new_column" id="rename-col-new" placeholder="novo_nome" required>
      </div>
      <div class="form-group">
        <div class="form-label">Tipo e Tamanho</div>
        <input type="text" name="col_type" id="rename-col-type" placeholder="ex: VARCHAR(255)" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-rename-col')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Alterações →</button>
      </div>
    </form>
  </div>
</div>

<!-- ─── MODAL: REMOVER COLUNA ────────────────────── -->
<div class="modal-overlay" id="modal-drop-col">
  <div class="modal">
    <div class="modal-title" style="color:var(--red);">⚠ Remover Coluna</div>
    <p style="margin-bottom:16px;color:var(--muted);font-size:13px;">
      A coluna <strong id="drop-col-name-display" style="color:var(--text)"></strong> e todos os seus dados serão removidos.
    </p>
    <form method="POST">
      <input type="hidden" name="action" value="drop_column">
      <input type="hidden" name="db" value="<?= htmlspecialchars($selectedDb) ?>">
      <input type="hidden" name="table" value="<?= htmlspecialchars($selectedTable) ?>">
      <input type="hidden" name="column_name" id="drop-col-hidden">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-drop-col')">Cancelar</button>
        <button type="submit" class="btn btn-danger">Remover Coluna</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════ -->
<!-- JAVASCRIPT                                     -->
<!-- ══════════════════════════════════════════════ -->
<script>
// ─── Modal helpers ────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('active');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); });
});

// ─── Drop Table ───────────────────────────────────
function openDropTable(name) {
  document.getElementById('drop-table-name-hidden').value = name;
  document.getElementById('drop-table-confirm').value = '';
  openModal('modal-drop-table');
}

// ─── Rename Column ────────────────────────────────
function openRenameCol(name, type) {
  document.getElementById('rename-col-old').value = name;
  document.getElementById('rename-col-old-display').value = name;
  document.getElementById('rename-col-new').value = name;
  document.getElementById('rename-col-type').value = type;
  openModal('modal-rename-col');
}

// ─── Drop Column ──────────────────────────────────
function openDropCol(name) {
  document.getElementById('drop-col-hidden').value = name;
  document.getElementById('drop-col-name-display').textContent = name;
  openModal('modal-drop-col');
}

// ─── Column Types ────────────────────────────────
const COL_TYPES = <?= json_encode($columnTypes) ?>;
function typeSelect(name) {
  return `<select name="${name}">${COL_TYPES.map(t => `<option>${t}</option>`).join('')}</select>`;
}

// ─── Add Column Row (Create form) ─────────────────
let createIdx = 1, addIdx = 1;
function addColRow(containerId, mode) {
  const container = document.getElementById(containerId);
  const idx = mode === 'create' ? createIdx++ : addIdx++;
  const div = document.createElement('div');
  div.className = 'col-row';
  div.innerHTML = `
    <input type="text" name="columns[${idx}][name]" placeholder="nome_coluna">
    <select name="columns[${idx}][type]">${COL_TYPES.map(t => `<option${t==='VARCHAR'?' selected':''}>${t}</option>`).join('')}</select>
    <input type="number" name="columns[${idx}][length]" placeholder="255" min="1">
    <input type="text" name="columns[${idx}][default]" placeholder="default">
    <div class="col-center"><input type="checkbox" name="columns[${idx}][notnull]" title="NOT NULL"></div>
    ${mode==='create' ? `<div class="col-center"><input type="checkbox" name="columns[${idx}][ai]" title="AUTO_INCREMENT"></div>` : '<div></div>'}
    ${mode==='create' ? `<div class="col-center"><input type="checkbox" name="columns[${idx}][pk]" title="Primary Key"></div>` : '<div></div>'}
    <div class="col-center" style="grid-column:span 0;"><button type="button" class="btn btn-danger btn-icon btn-sm" onclick="this.closest('.col-row').remove()">✕</button></div>
  `;
  // Fix: last cell remove button
  div.style.gridTemplateColumns = '2fr 1.4fr .9fr 1.2fr 36px 36px 36px 36px';
  container.appendChild(div);
}

// ─── Parse Bulk Text ──────────────────────────────
function parseBulkNames(text) {
  return text.split(/[\n,\t]+/)
    .map(s => s.trim().replace(/\s+/g, '_'))
    .filter(s => s.length > 0 && /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(s));
}

// ─── Bulk → Create Form ───────────────────────────
function applyBulkToCreate() {
  const text  = document.getElementById('bulk-create-input').value;
  const type  = document.getElementById('bulk-create-type').value;
  const names = parseBulkNames(text);
  if (!names.length) { alert('Nenhum nome de coluna válido encontrado.'); return; }
  const container = document.getElementById('create-col-list');
  names.forEach(name => {
    const div = document.createElement('div');
    div.className = 'col-row';
    div.innerHTML = `
      <input type="text" name="columns[${createIdx}][name]" value="${name}">
      <select name="columns[${createIdx}][type]">${COL_TYPES.map(t => `<option${t===type?' selected':''}>${t}</option>`).join('')}</select>
      <input type="number" name="columns[${createIdx}][length]" placeholder="255" min="1">
      <input type="text" name="columns[${createIdx}][default]" placeholder="default">
      <div class="col-center"><input type="checkbox" name="columns[${createIdx}][notnull]"></div>
      <div class="col-center"><input type="checkbox" name="columns[${createIdx}][ai]"></div>
      <div class="col-center"><input type="checkbox" name="columns[${createIdx}][pk]"></div>
    `;
    container.appendChild(div);
    createIdx++;
  });
  closeModal('modal-bulk-create');
  document.getElementById('bulk-create-input').value = '';
}

// ─── Bulk Add to Existing Table ───────────────────
function submitBulkAdd() {
  const text    = document.getElementById('bulk-edit-input').value;
  const type    = document.getElementById('bulk-edit-type').value;
  const length  = document.getElementById('bulk-edit-length').value;
  const notnull = document.getElementById('bulk-edit-notnull').checked;
  const names   = parseBulkNames(text);
  if (!names.length) { alert('Nenhum nome válido.'); return; }

  const form = document.createElement('form');
  form.method = 'POST';
  form.style.display = 'none';

  const fields = {
    action: 'add_columns',
    db: '<?= addslashes($selectedDb) ?>',
    table: '<?= addslashes($selectedTable) ?>'
  };
  for (const [k, v] of Object.entries(fields)) {
    const i = document.createElement('input');
    i.type='hidden'; i.name=k; i.value=v;
    form.appendChild(i);
  }
  names.forEach((name, idx) => {
    const addField = (n, v) => {
      const i = document.createElement('input');
      i.type='hidden'; i.name=n; i.value=v;
      form.appendChild(i);
    };
    addField(`columns[${idx}][name]`, name);
    addField(`columns[${idx}][type]`, type);
    if (length) addField(`columns[${idx}][length]`, length);
    if (notnull) addField(`columns[${idx}][notnull]`, '1');
  });

  document.body.appendChild(form);
  form.submit();
}
</script>
</body>
</html>
