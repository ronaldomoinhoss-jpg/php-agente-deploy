<?php
/**
 * Dashboard de Inventário e Indicadores de Acurácia
 * Integração com a tabela de inventários do banco `controle_estoque`
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

$empresasDisponiveis = [];
$depositosDisponiveis = [];
try {
    $stmtEmp = $pdo->query("SELECT DISTINCT empresa FROM `$tableName` WHERE empresa IS NOT NULL AND empresa != '' ORDER BY empresa");
    $empresasDisponiveis = $stmtEmp->fetchAll(PDO::FETCH_COLUMN);
    $stmtDep = $pdo->query("SELECT DISTINCT deposito FROM `$tableName` WHERE deposito IS NOT NULL AND deposito != '' ORDER BY deposito");
    $depositosDisponiveis = $stmtDep->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {}

$fEmp = $_GET['empresa'] ?? '';
$fDep = $_GET['deposito'] ?? '';

$whereClauses = [];
$params = [];
if ($fEmp !== '') { $whereClauses[] = "empresa = ?"; $params[] = $fEmp; }
if ($fDep !== '') { $whereClauses[] = "deposito = ?"; $params[] = $fDep; }

$whereSql = $whereClauses ? "WHERE " . implode(' AND ', $whereClauses) : "";

// Busca todos os dados agregados por Ano, Tipo de Inventário e Tipo de Base
$sqlStats = "SELECT 
    YEAR(data) as ano,
    LOWER(TRIM(tipo_inventario)) as tipo_inventario,
    LOWER(TRIM(tipo_base)) as tipo_base,
    SUM(qtd_sap * pmm) as valor_sap,
    SUM(ABS(diferenca) * pmm) as valor_dif_inicial_abs,
    SUM(ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm) as valor_dif_abs,
    SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) < 0 THEN ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm ELSE 0 END) as valor_faltas,
    SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) > 0 THEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) * pmm ELSE 0 END) as valor_sobras
FROM `$tableName`
$whereSql
GROUP BY ano, LOWER(TRIM(tipo_inventario)), LOWER(TRIM(tipo_base))";

$metrics = [];
try {
    $stmt = $pdo->prepare($sqlStats);
    $stmt->execute($params);
    $metrics = $stmt->fetchAll();
} catch (Exception $e) {}

// Estrutura de dados para os cards
$stats = [];
$years = array_filter(array_unique(array_column($metrics, 'ano')));
if (empty($years)) $years = [2026];

$baseStruct = ['sap'=>0, 'abs_inicial'=>0, 'abs'=>0, 'faltas'=>0, 'sobras'=>0];
foreach ($years as $y) {
    $stats[$y] = [
        'geral'        => $baseStruct,
        'oficial'      => $baseStruct,
        'rotativo'     => $baseStruct,
        'eps'          => $baseStruct,
        'utd'          => $baseStruct,
        'oficial_eps'  => $baseStruct,
        'rotativo_eps' => $baseStruct,
        'rotativo_utd' => $baseStruct,
    ];
}

// Popula os dados
foreach ($metrics as $row) {
    $y = $row['ano'];
    if (!$y) continue;
    
    $ti = $row['tipo_inventario'];
    $tb = $row['tipo_base'];
    $sap = (float)$row['valor_sap'];
    $abs_inicial = (float)$row['valor_dif_inicial_abs'];
    $abs = (float)$row['valor_dif_abs'];
    $fal = (float)$row['valor_faltas'];
    $sob = (float)$row['valor_sobras'];

    $add = function($cat) use (&$stats, $y, $sap, $abs_inicial, $abs, $fal, $sob) {
        $stats[$y][$cat]['sap'] += $sap;
        $stats[$y][$cat]['abs_inicial'] += $abs_inicial;
        $stats[$y][$cat]['abs'] += $abs;
        $stats[$y][$cat]['faltas'] += $fal;
        $stats[$y][$cat]['sobras'] += $sob;
    };

    $add('geral');
    if ($ti === 'oficial') $add('oficial');
    if ($ti === 'rotativo') $add('rotativo');
    if ($tb === 'eps') $add('eps');
    if ($tb === 'utd') $add('utd');
    if ($ti === 'oficial' && $tb === 'eps') $add('oficial_eps');
    if ($ti === 'rotativo' && $tb === 'eps') $add('rotativo_eps');
    if ($ti === 'rotativo' && $tb === 'utd') $add('rotativo_utd');
}

$targetYear = '2026';
if (!isset($stats[$targetYear])) {
    $stats[$targetYear] = $stats[$years[0]] ?? [];
}

function getAccVal($data) {
    if (!$data || $data['sap'] == 0) return null;
    $acc = (1 - ($data['abs'] / $data['sap'])) * 100;
    return max(0, min(100, $acc));
}

function getAccInicialVal($data) {
    if (!$data || $data['sap'] == 0) return null;
    $acc = (1 - ($data['abs_inicial'] / $data['sap'])) * 100;
    return max(0, min(100, $acc));
}

function formatAcc($val) {
    if ($val === null) return "N/D";
    return number_format($val, 2, ',', '.') . '%';
}

function getAccColor($val) {
    if ($val === null) return 'text-muted';
    if ($val >= 98) return 'text-green';
    if ($val >= 95) return 'text-yellow';
    return 'text-red';
}

$cardsRow1 = [
    ['id' => 'geral',    'title' => 'Acurácia Final 2026 (Geral)'],
    ['id' => 'oficial',  'title' => 'Acurácia Oficiais'],
    ['id' => 'rotativo', 'title' => 'Acurácia Rotativos'],
];

$cardsRow2 = [
    ['id' => 'eps',          'title' => 'Acurácia EPS'],
    ['id' => 'utd',          'title' => 'Acurácia UTDs'],
    ['id' => 'oficial_eps',  'title' => 'Oficiais EPS'],
    ['id' => 'rotativo_eps', 'title' => 'Rotativos EPS'],
    ['id' => 'rotativo_utd', 'title' => 'Rotativos UTDs'],
];

// Dados de Evolução Temporal
$whereEvo = "WHERE data IS NOT NULL";
if ($whereClauses) {
    $whereEvo .= " AND " . implode(' AND ', $whereClauses);
}

$evoData = [];
try {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(data, '%Y-%m') as mes, 
        SUM(qtd_sap * pmm) as valor_sap, 
        SUM(ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm) as valor_dif_abs,
        SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) < 0 THEN ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm ELSE 0 END) as faltas,
        SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) > 0 THEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) * pmm ELSE 0 END) as sobras
        FROM `$tableName` $whereEvo GROUP BY mes ORDER BY mes");
    $stmt->execute($params);
    foreach($stmt->fetchAll() as $r) {
        $acc = null;
        if ($r['valor_sap'] > 0) {
            $acc = (1 - ($r['valor_dif_abs'] / $r['valor_sap'])) * 100;
            $acc = max(0, min(100, $acc));
        }
        $evoData[] = [
            'mes' => $r['mes'], 
            'acc' => $acc,
            'faltas' => (float)$r['faltas'],
            'sobras' => (float)$r['sobras']
        ];
    }
} catch (Exception $e) {}

// Dados por Base (Empresa + Depósito)
$baseData = [];
try {
    $stmt = $pdo->prepare("SELECT data, empresa, deposito, SUM(qtd_sap * pmm) as valor_sap, SUM(ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm) as valor_dif_abs, SUM(ABS(diferenca) * pmm) as valor_dif_inicial_abs, SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) < 0 THEN ABS(COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca))) * pmm ELSE 0 END) as faltas, SUM(CASE WHEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) > 0 THEN COALESCE(dif_final, IF(qtd_final IS NOT NULL, qtd_final, diferenca)) * pmm ELSE 0 END) as sobras FROM `$tableName` $whereSql GROUP BY data, empresa, deposito ORDER BY valor_sap DESC LIMIT 30");
    $stmt->execute($params);
    $baseData = $stmt->fetchAll();
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard de Inventários</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
body { background: var(--bg); color: var(--text); font-family: var(--font); line-height: 1.5; font-size: 14px; padding-bottom: 60px; }

/* HEADER */
.header {
  background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 32px;
  display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100;
}
.logo { font-size: 18px; font-weight: 800; color: var(--accent); display: flex; align-items: center; gap: 8px; }
.logo span { color: var(--muted); font-weight: 400; font-size: 14px; }

/* FILTERS */
.filters { background:var(--surface); padding: 16px 32px; border-bottom: 1px solid var(--border); display:flex; gap:16px; align-items:center; }
.filters form { display:flex; gap:16px; align-items:center; width:100%; flex-wrap:wrap; }
.filter-group { display:flex; align-items:center; gap:8px; }
.filter-group label { color:var(--muted); font-size:13px; font-weight:600; }
.filter-group select { background:var(--bg); border:1px solid var(--border2); color:var(--text); padding:6px 12px; border-radius:6px; outline:none; }
.filter-group select:focus { border-color:var(--accent); }
.btn-filter { background:var(--accent); color:#fff; border:none; padding:6px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-size:13px; transition: background 0.2s; }
.btn-filter:hover { background:var(--accent-hover); }
.btn-clear { color:var(--muted); text-decoration:none; font-size:13px; margin-left:8px; transition: color 0.2s; }
.btn-clear:hover { color:var(--text); }

/* CONTAINER */
.container { max-width: 1400px; margin: 0 auto; padding: 32px; }
.section-title { font-size: 18px; font-weight: 700; margin: 32px 0 16px 0; color: var(--text); display: flex; align-items: center; gap: 8px; }
.section-title::before { content:''; display:block; width:4px; height:18px; background:var(--accent); border-radius:2px; }

/* GRID */
.grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 32px; }
@media (max-width: 1024px) { .grid-2 { grid-template-columns: 1fr; } }

/* CARDS */
.card {
  background: linear-gradient(145deg, var(--surface) 0%, var(--surface2) 100%);
  border: 1px solid var(--border); border-radius: 12px; padding: 24px;
  position: relative; overflow: hidden; transition: transform 0.2s, border-color 0.2s;
}
.card:hover { transform: translateY(-2px); border-color: var(--border2); }
.card::before {
  content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px;
  background: linear-gradient(90deg, var(--accent), #8b5cf6); opacity: 0; transition: opacity 0.3s;
}
.card:hover::before { opacity: 1; }
.card h3 { font-size: 13px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.card .value { font-size: 36px; font-weight: 800; margin: 4px 0; line-height: 1; }
.card .sub-value { font-size: 11px; font-weight: 500; color: var(--muted); margin-bottom: 12px; }
.card .btn-details {
  background: transparent; border: 1px solid var(--border2); color: var(--text);
  padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer;
  transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
}
.card .btn-details:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.05); }

/* UTILS */
.text-green { color: var(--green) !important; }
.text-red { color: var(--red) !important; }
.text-yellow { color: var(--yellow) !important; }
.text-muted { color: var(--muted) !important; }

/* PANELS */
.panel { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.panel h3 { font-size: 15px; font-weight: 600; margin-bottom: 20px; color: var(--text); }

/* TABLES */
.table-wrap { overflow-x: auto; max-height: 400px; overflow-y: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th { position: sticky; top: 0; background: var(--surface); color: var(--muted); font-size: 12px; font-weight: 600; padding: 12px; border-bottom: 1px solid var(--border); }
td { padding: 12px; font-size: 13px; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--surface2); }
.text-right { text-align: right; }
th.text-right { text-align: right; }

/* MODAL */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; z-index: 200; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
  background: var(--surface); border: 1px solid var(--border2); border-radius: 16px;
  width: 100%; max-width: 600px; padding: 32px; animation: slideUp 0.2s ease;
}
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.modal-title { font-size: 20px; font-weight: 700; color: var(--text); }
.modal-close { background: transparent; border: none; color: var(--muted); font-size: 24px; cursor: pointer; }
.modal-close:hover { color: var(--text); }
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
.stat-box { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 16px; text-align: center; }
.stat-box label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; }
.stat-box .val { font-size: 20px; font-weight: 700; }

::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }
</style>
</head>
<body>

<header class="header">
  <div class="logo">◈ Analytics <span>/ Inventário de Materiais</span></div>
  <div style="display:flex; gap: 16px; align-items: center;">
    <a href="gestao_inventario.php" style="color:var(--accent);text-decoration:none;font-size:13px;font-weight:600;padding:6px 12px;border:1px solid var(--accent);border-radius:6px;">Gestão de Dados</a>
  </div>
</header>

<div class="filters">
    <form method="GET">
        <div class="filter-group">
            <label>Empresa:</label>
            <select name="empresa">
                <option value="">Todas</option>
                <?php foreach($empresasDisponiveis as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>" <?= $fEmp === (string)$e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Depósito:</label>
            <select name="deposito">
                <option value="">Todos</option>
                <?php foreach($depositosDisponiveis as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $fDep === (string)$d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">Filtrar</button>
        <?php if($fEmp !== '' || $fDep !== ''): ?>
            <a href="dashboard_inventario.php" class="btn-clear">Limpar Filtros</a>
        <?php endif; ?>
    </form>
</div>

<div class="container">
  
  <div class="grid-3">
    <?php foreach ($cardsRow1 as $card): 
      $data = $stats[$targetYear][$card['id']] ?? null;
      $accVal = getAccVal($data);
      $accIni = getAccInicialVal($data);
      $accStr = formatAcc($accVal);
      $color = getAccColor($accVal);
    ?>
    <div class="card">
      <h3><?= $card['title'] ?></h3>
      <div class="value <?= $color ?>"><?= $accStr ?></div>
      <div class="sub-value">Acurácia Inicial: <?= formatAcc($accIni) ?></div>
      <button class="btn-details" onclick="openDetails('<?= $card['id'] ?>', '<?= addslashes($card['title']) ?>')">
        <span>▤</span> Ver Detalhes
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 class="section-title">Detalhamento por Base (<?= $targetYear ?>)</h2>
  
  <div class="grid-3">
    <?php foreach ($cardsRow2 as $card): 
      $data = $stats[$targetYear][$card['id']] ?? null;
      $accVal = getAccVal($data);
      $accIni = getAccInicialVal($data);
      $accStr = formatAcc($accVal);
      $color = getAccColor($accVal);
    ?>
    <div class="card">
      <h3><?= $card['title'] ?></h3>
      <div class="value <?= $color ?>"><?= $accStr ?></div>
      <div class="sub-value">Acurácia Inicial: <?= formatAcc($accIni) ?></div>
      <button class="btn-details" onclick="openDetails('<?= $card['id'] ?>', '<?= addslashes($card['title']) ?>')">
        <span>▤</span> Ver Detalhes
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-2">
    <div class="panel">
      <h3>Evolução da Acurácia Final no Tempo</h3>
      <div style="position: relative; height: 300px; width: 100%;">
        <canvas id="chartEvo"></canvas>
      </div>
    </div>
    
    <div class="panel">
      <h3>Evolução de Faltas e Sobras (R$)</h3>
      <div style="position: relative; height: 300px; width: 100%;">
        <canvas id="chartFaltasSobras"></canvas>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-top: 32px;">
      <h3>Performance por Empresa/Depósito (Top 30)</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Empresa</th>
              <th>Depósito</th>
              <th class="text-right">Ac. Final</th>
              <th class="text-right text-muted">Ac. Inicial</th>
              <th class="text-right">Faltas (R$)</th>
              <th class="text-right">Sobras (R$)</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if (empty($baseData)): 
                echo "<tr><td colspan='6' class='text-muted' style='text-align:center;'>Sem dados suficientes</td></tr>";
            endif;
            foreach ($baseData as $b): 
              $bAcc = null;
              $bAccIni = null;
              if ($b['valor_sap'] > 0) {
                  $bAcc = (1 - ($b['valor_dif_abs'] / $b['valor_sap'])) * 100;
                  $bAccIni = (1 - ($b['valor_dif_inicial_abs'] / $b['valor_sap'])) * 100;
              }
              $bAccStr = formatAcc(max(0, min(100, $bAcc)));
              $bAccIniStr = formatAcc(max(0, min(100, $bAccIni)));
            ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($b['data'])) ?></td>
              <td style="font-weight:600;"><?= htmlspecialchars($b['empresa']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($b['deposito']) ?></td>
              <td class="text-right <?= getAccColor($bAcc) ?>" style="font-weight:600;"><?= $bAccStr ?></td>
              <td class="text-right text-muted" style="font-size:11px;"><?= $bAccIniStr ?></td>
              <td class="text-right text-red"><?= number_format($b['faltas'], 2, ',', '.') ?></td>
              <td class="text-right text-green"><?= number_format($b['sobras'], 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
  </div>

</div>

<!-- Modal -->
<div id="modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">Detalhes</div>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    
    <div class="stat-grid">
      <div class="stat-box">
        <label>Faltas Finais (<?= $targetYear ?>)</label>
        <div class="val text-red" id="modal-faltas">R$ 0,00</div>
      </div>
      <div class="stat-box">
        <label>Sobras Finais (<?= $targetYear ?>)</label>
        <div class="val text-green" id="modal-sobras">R$ 0,00</div>
      </div>
    </div>

    <h3 style="font-size:14px; color:var(--text); margin-bottom:12px;">Histórico de Anos Anteriores (Ac. Final)</h3>
    <div class="table-wrap" style="max-height:200px;">
      <table id="modal-history">
        <thead>
          <tr>
            <th>Ano</th>
            <th class="text-right">Ac. Final</th>
            <th class="text-right">Faltas (R$)</th>
            <th class="text-right">Sobras (R$)</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const rawStats = <?= json_encode($stats) ?>;
const targetYear = '<?= $targetYear ?>';

function formatCurrency(val) {
    return 'R$ ' + parseFloat(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function calcAcc(data) {
    if (!data || data.sap == 0) return null;
    let acc = (1 - (data.abs / data.sap)) * 100;
    if (acc < 0) acc = 0; if (acc > 100) acc = 100;
    return acc;
}

function openDetails(catId, title) {
    document.getElementById('modal-title').textContent = title;
    
    const dataTY = (rawStats[targetYear] && rawStats[targetYear][catId]) ? rawStats[targetYear][catId] : {faltas:0, sobras:0};
    document.getElementById('modal-faltas').textContent = formatCurrency(dataTY.faltas);
    document.getElementById('modal-sobras').textContent = formatCurrency(dataTY.sobras);

    const tbody = document.querySelector('#modal-history tbody');
    tbody.innerHTML = '';
    
    let hasHistory = false;
    const years = Object.keys(rawStats).sort((a,b) => b - a);
    
    years.forEach(y => {
        if (y === targetYear) return; 
        if (parseInt(y) >= parseInt(targetYear)) return;

        const d = rawStats[y][catId];
        if (!d || d.sap == 0) return;
        
        hasHistory = true;
        const acc = calcAcc(d);
        const tr = document.createElement('tr');
        
        let colorClass = 'text-red';
        if (acc >= 98) colorClass = 'text-green';
        else if (acc >= 95) colorClass = 'text-yellow';

        tr.innerHTML = `
            <td style="font-weight:600">${y}</td>
            <td class="text-right ${colorClass}" style="font-weight:600">${acc.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})}%</td>
            <td class="text-right text-red">${formatCurrency(d.faltas)}</td>
            <td class="text-right text-green">${formatCurrency(d.sobras)}</td>
        `;
        tbody.appendChild(tr);
    });

    if (!hasHistory) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted" style="text-align:center">Nenhum histórico encontrado para anos anteriores.</td></tr>';
    }

    document.getElementById('modal').classList.add('active');
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
}
document.getElementById('modal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});

const evoData = <?= json_encode($evoData) ?>;
if (evoData.length > 0) {
    const labels = evoData.map(d => d.mes);
    const dataAcc = evoData.map(d => d.acc !== null ? d.acc.toFixed(2) : null);
    const dataFaltas = evoData.map(d => d.faltas);
    const dataSobras = evoData.map(d => d.sobras);
    
    const ctx = document.getElementById('chartEvo').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Acurácia Final (%)',
                data: dataAcc,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#0f111a',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#181c22',
                    titleColor: '#94a3b8',
                    bodyColor: '#f1f5f9',
                    borderColor: '#2e3545',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(ctx) { return 'Ac. Final: ' + ctx.raw + '%'; }
                    }
                }
            },
            scales: {
                y: {
                    min: 0, max: 100,
                    grid: { color: '#252a34' },
                    ticks: { color: '#94a3b8', callback: function(val) { return val + '%' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });

    const ctxFS = document.getElementById('chartFaltasSobras').getContext('2d');
    new Chart(ctxFS, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Faltas',
                    data: dataFaltas,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'Sobras',
                    data: dataSobras,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { color: '#94a3b8' } },
                tooltip: {
                    backgroundColor: '#181c22',
                    titleColor: '#94a3b8',
                    bodyColor: '#f1f5f9',
                    borderColor: '#2e3545',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(ctx) { 
                            return ctx.dataset.label + ': R$ ' + ctx.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2}); 
                        }
                    }
                }
            },
            scales: {
                y: {
                    grid: { color: '#252a34' },
                    ticks: { color: '#94a3b8', callback: function(val) { return 'R$ ' + val.toLocaleString('pt-BR'); } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
}
</script>
</body>
</html>