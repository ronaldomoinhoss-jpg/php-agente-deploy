<?php
/**
 * Detalhes do Inventário
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

$pData = $_GET['data'] ?? '';
$pEmp  = $_GET['empresa'] ?? '';
$pDep  = $_GET['deposito'] ?? '';

if (!$pData || !$pEmp) {
    die("Filtros insuficientes para exibir detalhes.");
}

$items = [];
$stats = [
    'linhas' => 0,
    'valor_sap' => 0,
    'dif_inicial' => 0,
    'dif_final' => 0,
    'faltas_finais' => 0,
    'sobras_finais' => 0,
    'tipo_inventario' => 'N/D',
    'tipo_base' => 'N/D'
];

try {
    $sql = "SELECT * FROM `$tableName` WHERE data = ? AND empresa = ? AND deposito = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pData, $pEmp, $pDep]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $stats['linhas']++;
        if ($stats['tipo_inventario'] === 'N/D' && $item['tipo_inventario']) $stats['tipo_inventario'] = $item['tipo_inventario'];
        if ($stats['tipo_base'] === 'N/D' && $item['tipo_base']) $stats['tipo_base'] = $item['tipo_base'];
        
        $pmm = (float)$item['pmm'];
        $sap = (float)$item['qtd_sap'] * $pmm;
        $stats['valor_sap'] += $sap;
        
        $stats['dif_inicial'] += abs((float)$item['diferenca']) * $pmm;
        
        $dFinal = null;
        if ($item['dif_final'] !== null) {
            $dFinal = (float)$item['dif_final'];
        } elseif ($item['qtd_final'] !== null) {
            $dFinal = (float)$item['qtd_final'];
        } else {
            $dFinal = (float)$item['diferenca'];
        }

        $valFinal = abs($dFinal) * $pmm;
        $stats['dif_final'] += $valFinal;
        
        if ($dFinal < 0) {
            $stats['faltas_finais'] += abs($dFinal) * $pmm;
        } elseif ($dFinal > 0) {
            $stats['sobras_finais'] += $dFinal * $pmm;
        }
    }
} catch (Exception $e) {
    die("Erro ao consultar dados: " . $e->getMessage());
}

$accIni = $stats['valor_sap'] > 0 ? (1 - ($stats['dif_inicial'] / $stats['valor_sap'])) * 100 : 0;
$accFin = $stats['valor_sap'] > 0 ? (1 - ($stats['dif_final'] / $stats['valor_sap'])) * 100 : 0;

function formatCurrency($v) { return 'R$ ' . number_format($v, 2, ',', '.'); }
function formatAcc($v) { return number_format(max(0, min(100, $v)), 2, ',', '.') . '%'; }
function getAccColor($v) {
    if ($v >= 98) return 'var(--green)';
    if ($v >= 95) return 'var(--yellow)';
    return 'var(--red)';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detalhes do Inventário</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
  --yellow: #f59e0b;
  --text: #f8fafc;
  --muted: #94a3b8;
  --font: 'Inter', sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.5; font-size: 13px; padding-bottom: 60px; }

/* HEADER */
.header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.logo { font-size: 18px; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 8px; }
.logo span { color: var(--muted); font-weight: 400; font-size: 14px; }
.btn-nav-outline { color: var(--muted); text-decoration: none; font-size: 13px; transition: color 0.2s; }
.btn-nav-outline:hover { color: var(--text); }

/* CONTAINER */
.container { max-width: 1400px; margin: 0 auto; padding: 32px; }

/* MACRO CARDS */
.macro-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
.macro-card { background: linear-gradient(145deg, var(--surface) 0%, var(--surface2) 100%); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.macro-title { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
.macro-val { font-size: 24px; font-weight: 800; color: var(--text); }
.macro-sub { font-size: 11px; color: var(--muted); margin-top: 4px; }

.badge { display: inline-block; background: var(--surface2); border: 1px solid var(--border2); padding: 2px 8px; border-radius: 4px; font-size: 11px; color: var(--muted); text-transform: uppercase; }

/* PANEL & TABLE */
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.panel-header h3 { font-size: 16px; font-weight: 600; }

input[type=text] { background: var(--bg); border: 1px solid var(--border2); color: var(--text); padding: 8px 12px; border-radius: 6px; font-size: 13px; outline: none; width: 300px; }
input[type=text]:focus { border-color: var(--accent); }

.table-wrap { overflow-x: auto; max-height: 600px; overflow-y: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th { position: sticky; top: 0; background: var(--surface); color: var(--muted); font-size: 11px; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid var(--border); text-transform: uppercase; cursor: pointer; user-select: none; }
th:hover { color: var(--text); }
td { padding: 10px 12px; font-size: 12px; border-bottom: 1px solid var(--border); }
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
  <div class="logo">◈ Analytics <span>/ Detalhes do Lote</span></div>
  <a href="gestao_inventario.php" class="btn-nav-outline">← Voltar à Gestão</a>
</header>

<div class="container">
  
  <div style="margin-bottom: 24px;">
    <h1 style="font-size: 24px; font-weight: 800; margin-bottom: 8px;">Inventário: <?= htmlspecialchars($pEmp) ?> <span style="color:var(--muted); font-weight:500;">(Depósito <?= htmlspecialchars($pDep) ?>)</span></h1>
    <div style="display:flex; gap:12px; align-items:center;">
        <span class="badge">Data: <?= date('d/m/Y', strtotime($pData)) ?></span>
        <span class="badge">Tipo: <?= htmlspecialchars($stats['tipo_inventario']) ?></span>
        <span class="badge">Base: <?= htmlspecialchars($stats['tipo_base']) ?></span>
    </div>
  </div>

  <div class="macro-grid">
    <div class="macro-card">
      <div class="macro-title">Acurácia Final</div>
      <div class="macro-val" style="color: <?= getAccColor($accFin) ?>;"><?= formatAcc($accFin) ?></div>
      <div class="macro-sub">Inicial: <?= formatAcc($accIni) ?></div>
    </div>
    <div class="macro-card">
      <div class="macro-title">Valor Total SAP</div>
      <div class="macro-val" style="color:var(--accent);"><?= formatCurrency($stats['valor_sap']) ?></div>
      <div class="macro-sub"><?= number_format($stats['linhas'], 0, '', '.') ?> itens registrados</div>
    </div>
    <div class="macro-card">
      <div class="macro-title">Faltas Finais</div>
      <div class="macro-val" style="color:var(--red);"><?= formatCurrency($stats['faltas_finais']) ?></div>
    </div>
    <div class="macro-card">
      <div class="macro-title">Sobras Finais</div>
      <div class="macro-val" style="color:var(--green);"><?= formatCurrency($stats['sobras_finais']) ?></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h3>Lista de Itens do Inventário</h3>
      <input type="text" id="searchInput" placeholder="Pesquisar código, descrição ou ID...">
    </div>
    
    <div class="table-wrap">
      <table id="dataTable">
        <thead>
          <tr>
            <th onclick="sortTable(0)">ID Neoex ⇕</th>
            <th onclick="sortTable(1)">Código ⇕</th>
            <th onclick="sortTable(2)">Descrição ⇕</th>
            <th class="text-right" onclick="sortTable(3)">Qtd SAP ⇕</th>
            <th class="text-right" onclick="sortTable(4)">Qtd Contada ⇕</th>
            <th class="text-right" onclick="sortTable(5)">Dif Inicial ⇕</th>
            <th class="text-right" onclick="sortTable(6)">Dif Final ⇕</th>
            <th class="text-right" onclick="sortTable(7)">PMM (R$) ⇕</th>
            <th>Justificativa</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($items as $i): 
            $df = $i['dif_final'] !== null ? $i['dif_final'] : ($i['qtd_final'] !== null ? $i['qtd_final'] : $i['diferenca']);
          ?>
          <tr>
            <td style="color:var(--muted);"><?= htmlspecialchars($i['id_neoex']) ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($i['codigo']) ?></td>
            <td><?= htmlspecialchars($i['descricao']) ?></td>
            <td class="text-right"><?= htmlspecialchars($i['qtd_sap']) ?></td>
            <td class="text-right"><?= htmlspecialchars($i['qtd_contada']) ?></td>
            <td class="text-right" style="color:<?= $i['diferenca'] < 0 ? 'var(--red)' : ($i['diferenca'] > 0 ? 'var(--green)' : 'var(--muted)') ?>; font-weight:600;">
              <?= htmlspecialchars($i['diferenca']) ?>
            </td>
            <td class="text-right" style="color:<?= $df < 0 ? 'var(--red)' : ($df > 0 ? 'var(--green)' : 'var(--muted)') ?>; font-weight:600;">
              <?= htmlspecialchars($df) ?>
            </td>
            <td class="text-right"><?= number_format((float)$i['pmm'], 2, ',', '.') ?></td>
            <td style="font-size:11px; color:var(--muted); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($i['justificativa']) ?>">
              <?= htmlspecialchars($i['justificativa'] ?: '-') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// Filtro Simples
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#dataTable tbody tr');
    
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Ordenação
let sortDirection = false;
function sortTable(columnIndex) {
    const table = document.getElementById("dataTable");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));
    
    sortDirection = !sortDirection;

    rows.sort((a, b) => {
        let aText = a.cells[columnIndex].textContent.trim();
        let bText = b.cells[columnIndex].textContent.trim();
        
        // Tenta converter para número se for possível (tratando vírgula como ponto para ordenar)
        let aNum = parseFloat(aText.replace(/\./g, '').replace(',', '.'));
        let bNum = parseFloat(bText.replace(/\./g, '').replace(',', '.'));
        
        let isNum = !isNaN(aNum) && !isNaN(bNum);
        
        if (isNum) {
            return sortDirection ? aNum - bNum : bNum - aNum;
        } else {
            return sortDirection ? aText.localeCompare(bText) : bText.localeCompare(aText);
        }
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>
</body>
</html>