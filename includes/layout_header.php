<?php
// Must set $pageTitle and include auth.php before this file
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../functions.php';
$_user = currentUser();
$_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — Asset Manager</title>
<link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/assets/images/favicon.svg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<div class="wrapper">

<!-- ══ SIDEBAR ══════════════════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">
    <a href="<?= SITE_URL ?>/dashboard.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-dollar-sign"></i></div>
        <div>
            <div class="brand-name">Asset Manager</div>
           
        </div>
    </a>

    <ul class="sidebar-nav">
        <li class="nav-section-label">Main</li>

        <li class="<?= $_page === 'dashboard' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/dashboard.php">
                <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
        </li>
        <li class="<?= $_page === 'accounts' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/accounts.php">
                <span class="nav-icon"><i class="fas fa-wallet"></i></span> Accounts
            </a>
        </li>
        <li class="<?= $_page === 'categories' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/categories.php">
                <span class="nav-icon"><i class="fas fa-tags"></i></span> Categories
            </a>
        </li>

        <li class="nav-section-label">Investments</li>

        <li class="<?= $_page === 'investments' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/investments.php">
                <span class="nav-icon"><i class="fas fa-chart-line"></i></span> Investments
            </a>
        </li>
        <li class="<?= $_page === 'transactions' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/transactions.php">
                <span class="nav-icon"><i class="fas fa-exchange-alt"></i></span> Transactions
            </a>
        </li>
        <li class="<?= $_page === 'reports' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/reports.php">
                <span class="nav-icon"><i class="fas fa-file-alt"></i></span> Reports
            </a>
        </li>

        <?php if ($_user['role'] === 'admin'): ?>
        <li class="nav-section-label">Settings</li>
        <li class="<?= $_page === 'users' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/users.php">
                <span class="nav-icon"><i class="fas fa-users-cog"></i></span> Users
            </a>
        </li>
        <li class="<?= $_page === 'maintenance' ? 'active' : '' ?>">
            <a href="<?= SITE_URL ?>/maintenance.php">
                <span class="nav-icon"><i class="fas fa-screwdriver-wrench"></i></span> Maintenance
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="user-avatar"><?= strtoupper(substr($_user['fullname'] ?: $_user['username'], 0, 1)) ?></div>
        <div class="user-meta">
            <div class="u-name"><?= htmlspecialchars($_user['fullname'] ?: $_user['username']) ?></div>
            <div class="u-role"><?= ucfirst($_user['role']) ?></div>
        </div>
        <a href="<?= SITE_URL ?>/logout.php" class="btn-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</nav>

<!-- ══ MAIN CONTENT ════════════════════════════════════════════ -->
<div class="main-content">
    <div class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <span class="page-heading"><?= htmlspecialchars($pageTitle ?? '') ?></span>
        <div class="topbar-meta d-none d-md-block"><?= date('d M Y, D') ?></div>
    </div>
    <div class="content-area">

<!-- ── Toast area ── -->
<div id="toastArea" class="toast-area"></div>
