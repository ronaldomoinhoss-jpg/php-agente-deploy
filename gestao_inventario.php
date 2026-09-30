<?php
/**
 * Gestão de Dados de Inventário
 */

$config = [
    'host'     => 'localhost',
    'port'     => 3306,
    'user'     => 'root',
    'password' => '',
    'dbname'   => 'controle_estoque',
    'charset'  => 'utf8mb4',
];

$pdo = null;
try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

$tablesToCheck = ['inv', 'controle_estoque'];
$tableName = 'inv';
foreach ($tablesToCheck as $t) {
    try {
        $pdo->query("SELECT 1 FROM `$t` LIMIT 1");
        $tableName = $t;
        break;
    } catch (Exception $e) {}
}

// ─── EXPORTAÇÃO ───────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $sql = "SELECT * FROM `$tableName`";
    $params = [];
    $where = [];
    if (!empty($_GET['data'])) { $where[] = "data = ?"; $params[] = $_GET['data']; }
    if (!empty($_GET['empresa'])) { $where[] = "empresa = ?"; $params[] = $_GET['empresa']; }
    if (!empty($_GET['deposito'])) { $where[] = "deposito = ?"; $params[] = $_GET['deposito']; }
    
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventario_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    
    $first = true;
    while ($row = $stmt->fetch()) {
        if ($first) {
            fputcsv($output, array_keys($row), ';');
            $first = false;
        }
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit;
}

$message = '';
$msgType = '';

// ─── AÇÕES POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // IMPORTAR
    if ($action === 'import' && !empty($_POST['bulk_data'])) {
        $rawData = trim($_POST['bulk_data']);
        $lines = explode("\n", $rawData);
        
        $expectedCols = [
            'id_neoex', 'tipo_inventario', 'tipo_base', 'data', 'deposito', 
            'empresa', 'codigo', 'descricao', 'familia', 'unidade_med', 'qtd_sap', 'qtd_contada', 
            'diferenca', 'pmm', 'justificativa', 'qtd_aceita', 'qtd_final', 'dif_final'
        ];
        $colCount = count($expectedCols);
        
        $successCount = 0;
        $errorCount = 0;

        $pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, $colCount, '?'));
            $sql = "INSERT IGNORE INTO `$tableName` (" . implode(',', $expectedCols) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $sep = strpos($line, "\t") !== false ? "\t" : ";";
                $cols = explode($sep, $line);
                
                if ($index === 0 && strtolower(trim($cols[0])) === 'id_neoex') continue;

                if (count($cols) < $colCount) {
                    $cols = array_pad($cols, $colCount, null);
                } elseif (count($cols) > $colCount) {
                    $cols = array_slice($cols, 0, $colCount);
                }

                foreach ($cols as $k => $v) {
                    $val = trim((string)$v);
                    if ($val === '') { $cols[$k] = null; continue; }
                    
                    if ($k === 3) { // data
                        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $val, $matches)) {
                            $cols[$k] = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                        }
                    }
                    if (in_array($k, [10, 11, 12, 13, 15, 16, 17])) { // campos numericos
                        $cols[$k] = str_replace(['R$', ' ', '.'], '', $val);
                        $cols[$k] = str_replace(',', '.', $cols[$k]);
                    }
                }

                try {
                    $stmt->execute($cols);
                    if ($stmt->rowCount() > 0) $successCount++;
                } catch (Exception $e) {
                    $errorCount++;
                }
            }
            $pdo->commit();
            $message = "Operação concluída. $successCount registros inseridos." . ($errorCount > 0 ? " ($errorCount erros de dados)" : "");
            $msgType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erro grave ao importar: " . $e->getMessage();
            $msgType = 'error';
        }
    }

    // DELETAR LOTE
    if ($action === 'delete_batch') {
        $bData = $_POST['b_data'] ?? '';
        $bEmp  = $_POST['b_emp'] ?? '';
        $bDep  = $_POST['b_dep'] ?? '';
        
        if ($bData && $bEmp) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `$tableName` WHERE data = ? AND empresa = ? AND deposito = ?");
                $stmt->execute([$bData, $bEmp, $bDep]);
                $message = "Lote removido com sucesso (" . $stmt->rowCount() . " linhas).";
                $msgType = 'success';
            } catch (Exception $e) {
                $message = "Erro ao remover: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    // DELETAR POR ID_NEOEX
    if ($action === 'delete_id') {
        $idNeo = trim($_POST['id_neoex'] ?? '');
        if ($idNeo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `$tableName` WHERE id_neoex = ?");
                $stmt->execute([$idNeo]);
                if ($stmt->rowCount() > 0) {
                    $message = "Item '$idNeo' removido com sucesso!";
                    $msgType = 'success';
                } else {
                    $message = "Nenhum item encontrado com o ID '$idNeo'.";
                    $msgType = 'error';
                }
            } catch (Exception $e) {
                $message = "Erro ao remover item: " . $e->getMessage();
                $msgType = 'error';
            }
        }
    }

    // DELETAR TUDO
    if ($action === 'delete_all') {
        if (isset($_POST['confirm_all']) && $_POST['confirm_all'] === 'DELETAR TUDO') {
            try {
                $pdo->exec("TRUNCATE TABLE `$tableName`");
                $message = "Banco de dados limpo com sucesso! Todos os inventários foram excluídos.";
                $msgType = 'success';
            } catch (Exception $e) {
                $message = "Erro ao limpar o banco: " . $e->getMessage();
                $msgType = 'error';
            }
        } else {
            $message = "Palavra de confirmação incorreta. O banco não foi limpo.";
            $msgType = 'error';
        }
    }
}

// RESUMO GERAL
$totalRows = 0;
$lastUpdate = null;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as c, MAX(data) as d FROM `$tableName`");
    $res = $stmt->fetch();
    $totalRows = $res['c'] ?? 0;
    $lastUpdate = $res['d'] ?? null;
} catch (Exception $e) {}

// AGRUPAMENTO DE LOTES
$batches = [];
try {
    $stmt = $pdo->query("SELECT data, empresa, deposito, MAX(tipo_inventario) as tipo_inventario, COUNT(*) as linhas, SUM(qtd_sap * pmm) as valor_sap FROM `$tableName` GROUP BY data, empresa, deposito ORDER BY data DESC LIMIT 50");
    $batches = $stmt->fetchAll();
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestão de Inventários</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0a0c10;
  --surface: #111318;
  --surface2: #181c22;
  --border: #252a34;
  --border2: #2e3545;
  --accent: #3b82f6;
  --accent-hover: #60a5fa;
  --green: #10b981;
  --red: #ef4444;
  --text: #f8fafc;
  --muted: #94a3b8;
  --font: 'Inter', sans-serif;
  --mono: 'JetBrains Mono', monospace;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.5; font-size: 14px; padding-bottom: 60px; }

/* HEADER */
.header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.logo { font-size: 18px; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 8px; }
.logo span { color: var(--muted); font-weight: 400; font-size: 14px; }
.nav-links { display: flex; gap: 16px; align-items: center; }
.btn-nav { color: var(--accent); text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 12px; border: 1px solid var(--accent); border-radius: 6px; transition: all 0.2s; }
.btn-nav:hover { background: rgba(59,130,246,0.1); }
.btn-nav-outline { color: var(--muted); text-decoration: none; font-size: 13px; transition: color 0.2s; }
.btn-nav-outline:hover { color: var(--text); }

/* CONTAINER */
.container { max-width: 1200px; margin: 0 auto; padding: 32px; }
.page-title { font-size: 24px; font-weight: 800; margin-bottom: 8px; color: var(--text); }
.page-sub { color: var(--muted); margin-bottom: 32px; }

/* MESSAGES */
.msg { padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; font-weight: 500; }
.msg.success { background: rgba(16,185,129,0.1); color: var(--green); border: 1px solid rgba(16,185,129,0.3); }
.msg.error { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }

/* PANELS */
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 32px; }
.panel h3 { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text); display: flex; align-items: center; justify-content: space-between; }
.panel-header-actions { display: flex; gap: 12px; }

/* FORM */
textarea { width: 100%; height: 200px; background: var(--bg); border: 1px solid var(--border2); color: var(--text); font-family: var(--mono); font-size: 12px; padding: 16px; border-radius: 8px; resize: vertical; outline: none; line-height: 1.6; white-space: pre; }
textarea:focus { border-color: var(--accent); }
input[type=text] { background: var(--bg); border: 1px solid var(--border2); color: var(--text); padding: 8px 12px; border-radius: 6px; font-size: 13px; outline: none; }
input[type=text]:focus { border-color: var(--accent); }

.btn { background: var(--accent); color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
.btn:hover { background: var(--accent-hover); }
.btn-sm { padding: 4px 10px; font-size: 11px; }
.btn-danger { background: rgba(239,68,68,0.1); color: var(--red); border: 1px solid rgba(239,68,68,0.3); }
.btn-danger:hover { background: rgba(239,68,68,0.2); }
.btn-outline { background: transparent; border: 1px solid var(--border2); color: var(--text); }
.btn-outline:hover { border-color: var(--accent); color: var(--accent); }

/* HINT */
.hint-box { background: rgba(255,255,255,0.02); border: 1px dashed var(--border2); border-radius: 8px; padding: 16px; margin-bottom: 24px; }
.hint-box code { background: var(--bg); padding: 4px 8px; border-radius: 4px; font-family: var(--mono); font-size: 11px; color: var(--muted); display: block; overflow-x: auto; margin-top: 8px; border: 1px solid var(--border2); }
.hint-box p { font-size: 13px; color: var(--muted); margin-bottom: 4px; }

/* TABLES */
table { width: 100%; border-collapse: collapse; text-align: left; }
th { background: var(--bg); color: var(--muted); font-size: 12px; font-weight: 600; padding: 12px; border-bottom: 1px solid var(--border); }
td { padding: 12px; font-size: 13px; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--surface2); }
.text-right { text-align: right; }
th.text-right { text-align: right; }

::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }
</style>
</head>
<body>

<header class="header">
  <div class="logo">◈ Analytics <span>/ Gestão de Dados</span></div>
  <div class="nav-links">
    <a href="dashboard_inventario.php" class="btn-nav">Ver Dashboard</a>
    <a href="db_manager.php" class="btn-nav-outline">← Voltar ao Banco</a>
  </div>
</header>

<div class="container">
  
  <h1 class="page-title">Gestão de Inventários</h1>
  <p class="page-sub">Importação, exclusão e exportação de dados para a tabela <strong><?= htmlspecialchars($tableName) ?></strong></p>

  <?php if ($message): ?>
  <div class="msg <?= $msgType ?>">
    <?= htmlspecialchars($message) ?>
    <span style="cursor:pointer;opacity:0.5;" onclick="this.parentElement.remove()">✕</span>
  </div>
  <?php endif; ?>

  <!-- INSERÇÃO EM MASSA -->
  <div class="panel">
    <h3>⊞ Colar Dados em Massa</h3>
    <div class="hint-box">
      <p>Cole os dados do Excel/Sheets (separados por <strong>TAB</strong> ou <strong>Ponto e Vírgula</strong>).</p>
      <p>A ordem exata das 18 colunas deve ser (incluindo a nova coluna <strong>dif_final</strong>):</p>
      <code>id_neoex | tipo_inventario | tipo_base | data | deposito | empresa | codigo | descricao | familia | unidade_med | qtd_sap | qtd_contada | diferenca | pmm | justificativa | qtd_aceita | qtd_final | dif_final</code>
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="import">
      <textarea name="bulk_data" placeholder="Cole os dados aqui..." required></textarea>
      <div style="margin-top:16px;">
        <button type="submit" class="btn"><span>↓</span> Importar Dados</button>
      </div>
    </form>
  </div>

  <!-- AÇÕES DIRETAS (EXCLUIR POR ID / EXPORTAR TUDO) -->
  <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 32px; margin-bottom: 32px;">
    
    <div class="panel" style="margin-bottom:0;">
      <h3>Excluir Item Específico</h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:12px;">Remove uma única linha do banco baseada no ID.</p>
      <form method="POST" style="display:flex;gap:12px;">
        <input type="hidden" name="action" value="delete_id">
        <input type="text" name="id_neoex" placeholder="Ex: INV-12345" required style="flex:1;">
        <button type="submit" class="btn btn-danger">Excluir Linha</button>
      </form>
    </div>

    <div class="panel" style="margin-bottom:0;">
      <h3>Exportar Base Completa</h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:12px;">Baixa todos os <?= number_format($totalRows, 0, ',', '.') ?> registros em CSV.</p>
      <a href="?action=export" class="btn btn-outline" style="width:fit-content;">↓ Baixar CSV Completo</a>
    </div>

    <div class="panel" style="margin-bottom:0; border-color: rgba(239,68,68,0.3);">
      <h3 style="color:var(--red);">Excluir TODOS os Inventários</h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:12px;">Apaga <strong style="color:var(--red)">definitivamente</strong> todos os registros.</p>
      <form method="POST" style="display:flex;gap:12px;">
        <input type="hidden" name="action" value="delete_all">
        <input type="text" name="confirm_all" placeholder="Digite DELETAR TUDO" required style="flex:1;">
        <button type="submit" class="btn btn-danger">Excluir Tudo</button>
      </form>
    </div>

  </div>

  <!-- GERENCIAR LOTES -->
  <div class="panel">
    <h3>Lotes de Inventário Importados</h3>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Empresa</th>
            <th>Depósito</th>
            <th class="text-right">Linhas Registradas</th>
            <th class="text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($batches)): ?>
            <tr><td colspan="6" class="text-muted" style="text-align:center;">Nenhum inventário registrado.</td></tr>
          <?php endif; ?>
          <?php foreach ($batches as $b): ?>
          <tr>
            <td style="font-weight:600;"><?= date('d/m/Y', strtotime($b['data'])) ?></td>
            <td><span style="background:var(--surface2);padding:2px 8px;border-radius:4px;font-size:11px;color:var(--muted);text-transform:uppercase;"><?= htmlspecialchars($b['tipo_inventario'] ?: 'N/D') ?></span></td>
            <td><?= htmlspecialchars($b['empresa']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($b['deposito']) ?></td>
            <td class="text-right" style="font-weight:600;"><?= number_format($b['linhas'], 0, ',', '.') ?></td>
            <td class="text-right">
              <div style="display:flex; gap:8px; justify-content:flex-end;">
                <a href="detalhes_inventario.php?data=<?= urlencode($b['data']) ?>&empresa=<?= urlencode($b['empresa']) ?>&deposito=<?= urlencode($b['deposito']) ?>" class="btn btn-outline" style="color:var(--accent); border-color:var(--accent);">Ver Detalhes</a>
                <a href="?action=export&data=<?= urlencode($b['data']) ?>&empresa=<?= urlencode($b['empresa']) ?>&deposito=<?= urlencode($b['deposito']) ?>" class="btn btn-outline btn-sm">CSV</a>
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir as <?= $b['linhas'] ?> linhas deste lote?')">
                  <input type="hidden" name="action" value="delete_batch">
                  <input type="hidden" name="b_data" value="<?= htmlspecialchars($b['data']) ?>">
                  <input type="hidden" name="b_emp" value="<?= htmlspecialchars($b['empresa']) ?>">
                  <input type="hidden" name="b_dep" value="<?= htmlspecialchars($b['deposito']) ?>">
                  <button type="submit" class="btn btn-danger btn-sm">✕ Excluir Lote</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

</body>
</html>