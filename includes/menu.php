<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Navigation -->
<aside class="sidebar-nav">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-truck-ramp-box"></i>
        </div>
        <div class="brand-text">
            <h2>CUBAGEM<span>LOG</span></h2>
            <small>Distribuição Elétrica</small>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fa-solid fa-user-gear"></i>
        </div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($usuarioLogado['nome']) ?></span>
            <span class="user-role"><?= htmlspecialchars($usuarioLogado['cargo']) ?></span>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="menu-category">Geral</div>
        <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>

        <div class="menu-category">Cadastros Operacionais</div>
        <a href="veiculos.php" class="nav-link <?= $currentPage == 'veiculos.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-front"></i> <span>Veículos de Carga</span>
        </a>
        <a href="materiais.php" class="nav-link <?= $currentPage == 'materiais.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i> <span>Materiais & Itens</span>
        </a>
        <a href="importar_materiais.php" class="nav-link <?= $currentPage == 'importar_materiais.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-import"></i> <span>Importar Lista</span>
        </a>
        <a href="regras.php" class="nav-link <?= $currentPage == 'regras.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-shield-halved"></i> <span>Regras de Empilhamento</span>
        </a>

        <div class="menu-category">Simulação & Análise</div>
        <a href="simulacao.php" class="nav-link nav-link-highlight <?= $currentPage == 'simulacao.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cube"></i> <span>Simulador Visual 3D</span>
        </a>
        <a href="historico.php" class="nav-link <?= $currentPage == 'historico.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> <span>Histórico de Cargas</span>
        </a>
        <a href="comparar.php" class="nav-link <?= $currentPage == 'comparar.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-code-compare"></i> <span>Comparar Veículos</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Sair do Sistema
        </a>
    </div>
</aside>

<!-- Top Mobile & Desktop Action Header -->
<header class="top-header">
    <button class="btn-toggle-sidebar" id="toggleSidebar">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="top-header-title">
        <h1 class="h5 mb-0"><?= $pageTitle ?? 'Painel Logístico' ?></h1>
    </div>
    <div class="top-header-actions">
        <a href="simulacao.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Nova Simulação
        </a>
    </div>
</header>

<main class="main-content">
