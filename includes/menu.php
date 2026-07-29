<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar-nav">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-truck-ramp-box"></i>
        </div>
        <div class="brand-text">
            <h2>CUBAGEM<span>2</span></h2>
            <small>CD Elétrico Multi-Base</small>
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
            <i class="fa-solid fa-truck-front"></i> <span>Veículos</span>
        </a>
        <a href="materiais.php" class="nav-link <?= $currentPage == 'materiais.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i> <span>Materiais</span>
        </a>
        <a href="bases.php" class="nav-link <?= $currentPage == 'bases.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-warehouse"></i> <span>Bases</span>
        </a>
        <a href="regras.php" class="nav-link <?= $currentPage == 'regras.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-shield-halved"></i> <span>Regras Operacionais</span>
        </a>

        <div class="menu-category">Pedidos & Simulação</div>
        <a href="pedidos.php" class="nav-link <?= $currentPage == 'pedidos.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i> <span>Pedidos de Carga</span>
        </a>
        <a href="importar_materiais.php" class="nav-link <?= $currentPage == 'importar_materiais.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-file-import"></i> <span>Importações</span>
        </a>
        <a href="simulacao.php" class="nav-link nav-link-highlight <?= $currentPage == 'simulacao.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-cube"></i> <span>Simulador 3D</span>
        </a>
        <a href="historico.php" class="nav-link <?= $currentPage == 'historico.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i> <span>Histórico</span>
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
