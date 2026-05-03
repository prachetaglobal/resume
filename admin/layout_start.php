<?php
/**
 * Admin layout — sidebar + topbar.
 * Include at the top of every admin page AFTER setting:
 *   $adminPage  = 'dashboard' | 'plan-settings'   (highlights active nav item)
 *   $adminTitle = 'Page Title'
 *
 * The page should end with: include __DIR__ . '/layout_end.php';
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 240px;
            --sidebar-bg: #0d1117;
            --sidebar-border: #21262d;
            --sidebar-text: #8b949e;
            --sidebar-text-active: #f0f6fc;
            --sidebar-hover-bg: #161b22;
            --sidebar-active-bg: #1f2937;
            --topbar-h: 60px;
            --accent: #6366f1;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            margin: 0;
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        #admin-sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            padding: 1.25rem 1.4rem;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
        }

        .sidebar-brand .brand-icon {
            width: 32px; height: 32px;
            background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; color: #fff;
            flex-shrink: 0;
        }

        .sidebar-brand .brand-text {
            font-weight: 700;
            font-size: .95rem;
            color: var(--sidebar-text-active);
            line-height: 1.1;
        }

        .sidebar-brand .brand-sub {
            font-size: .65rem;
            color: var(--sidebar-text);
            font-weight: 400;
        }

        .sidebar-section {
            padding: 1.25rem .75rem .5rem;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--sidebar-text);
            font-weight: 600;
        }

        .sidebar-nav { padding: 0 .75rem; flex: 1; }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem .75rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: .875rem;
            font-weight: 500;
            color: var(--sidebar-text);
            margin-bottom: 2px;
            transition: background .15s, color .15s;
        }

        .sidebar-link i { font-size: 1rem; width: 1.1rem; text-align: center; }

        .sidebar-link:hover {
            background: var(--sidebar-hover-bg);
            color: var(--sidebar-text-active);
        }

        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-text-active);
        }

        .sidebar-link.active i { color: var(--accent); }

        .sidebar-footer {
            padding: 1rem .75rem;
            border-top: 1px solid var(--sidebar-border);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .5rem .75rem;
            border-radius: 8px;
            text-decoration: none;
            color: var(--sidebar-text);
            font-size: .8rem;
        }

        .sidebar-user:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-active); }

        .user-avatar {
            width: 30px; height: 30px;
            background: var(--accent);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }

        /* ── Main area ───────────────────────────────────── */
        #admin-main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Topbar ──────────────────────────────────────── */
        #admin-topbar {
            height: var(--topbar-h);
            background: #161b22;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #f0f6fc;
        }

        .topbar-actions { display: flex; align-items: center; gap: .75rem; }

        .topbar-btn {
            display: flex; align-items: center; gap: .4rem;
            padding: .4rem .85rem;
            border-radius: 8px;
            font-size: .8rem;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--sidebar-border);
            color: var(--sidebar-text);
            background: transparent;
            transition: all .15s;
        }

        .topbar-btn:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-active); }

        .topbar-badge {
            background: #21262d;
            color: #6366f1;
            border: 1px solid #30363d;
            padding: .2rem .55rem;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 600;
        }

        /* ── Content area ────────────────────────────────── */
        #admin-content {
            flex: 1;
            padding: 1.75rem;
        }

        /* ── Cards ───────────────────────────────────────── */
        .admin-card {
            background: #161b22;
            border: 1px solid #21262d;
            border-radius: 12px;
            overflow: hidden;
        }

        .admin-card-header {
            padding: .9rem 1.25rem;
            border-bottom: 1px solid #21262d;
            font-weight: 600;
            font-size: .875rem;
            color: #c9d1d9;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* ── Stat cards ──────────────────────────────────── */
        .stat-card {
            background: #161b22;
            border: 1px solid #21262d;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
            transition: border-color .2s;
        }

        .stat-card:hover { border-color: #6366f1; }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: var(--stat-color, #6366f1);
        }

        .stat-value { font-size: 2rem; font-weight: 700; color: #f0f6fc; line-height: 1; }
        .stat-label { font-size: .8rem; color: #8b949e; margin-top: .3rem; }
        .stat-icon {
            position: absolute;
            right: 1.25rem; top: 50%;
            transform: translateY(-50%);
            font-size: 2.5rem;
            opacity: .07;
            color: #fff;
        }
        .stat-delta { font-size: .75rem; margin-top: .5rem; }
        .stat-delta.up   { color: #3fb950; }
        .stat-delta.flat { color: #8b949e; }

        /* ── Tables ──────────────────────────────────────── */
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th {
            background: #0d1117;
            padding: .7rem 1rem;
            font-size: .75rem;
            font-weight: 600;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid #21262d;
        }
        .admin-table td {
            padding: .7rem 1rem;
            font-size: .875rem;
            color: #c9d1d9;
            border-bottom: 1px solid #161b22;
            vertical-align: middle;
        }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tbody tr:hover td { background: #1c2128; }

        /* ── Badges ──────────────────────────────────────── */
        .plan-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 600;
        }
        .plan-free       { background: #21262d; color: #8b949e; border: 1px solid #30363d; }
        .plan-pro        { background: #1a237e22; color: #90caf9; border: 1px solid #1565c055; }
        .plan-enterprise { background: #f9a82522; color: #fdd835; border: 1px solid #f9a82555; }

        /* ── Form controls override ──────────────────────── */
        .admin-input {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            color: #c9d1d9;
            padding: .45rem .75rem;
            font-size: .875rem;
            width: 100%;
            transition: border-color .15s;
        }
        .admin-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #0d1117;
            color: #f0f6fc;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        }

        .form-switch .form-check-input {
            background-color: #30363d;
            border-color: #30363d;
        }
        .form-switch .form-check-input:checked {
            background-color: #6366f1;
            border-color: #6366f1;
        }

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            #admin-sidebar { transform: translateX(-100%); }
            #admin-sidebar.open { transform: translateX(0); }
            #admin-main { margin-left: 0; }
        }

        /* Flash alert */
        .admin-flash {
            border-radius: 10px;
            padding: .8rem 1.1rem;
            font-size: .875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .admin-flash.success { background: #052e16; border: 1px solid #166534; color: #4ade80; }
        .admin-flash.danger  { background: #2d0a0a; border: 1px solid #7f1d1d; color: #f87171; }
    </style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<aside id="admin-sidebar">
    <a href="<?= APP_URL ?>/admin/" class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-shield-check"></i></div>
        <div>
            <div class="brand-text"><?= APP_NAME ?></div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </a>

    <div class="sidebar-nav">
        <div class="sidebar-section">Overview</div>

        <a href="<?= APP_URL ?>/admin/"
           class="sidebar-link <?= ($adminPage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>

        <div class="sidebar-section" style="margin-top:.5rem">Manage</div>

        <a href="<?= APP_URL ?>/admin/users.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users
        </a>

        <a href="<?= APP_URL ?>/admin/resumes.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'resumes' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-person"></i> Resumes
        </a>

        <a href="<?= APP_URL ?>/admin/templates.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'templates' ? 'active' : '' ?>">
            <i class="bi bi-layout-text-sidebar"></i> Templates
        </a>

        <div class="sidebar-section" style="margin-top:.5rem">Settings</div>

        <a href="<?= APP_URL ?>/admin/site-settings.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'site-settings' ? 'active' : '' ?>">
            <i class="bi bi-brush"></i> Site Settings
        </a>

        <a href="<?= APP_URL ?>/admin/plan-settings.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'plan-settings' ? 'active' : '' ?>">
            <i class="bi bi-sliders"></i> Plan Settings
        </a>

        <div class="sidebar-section" style="margin-top:.5rem">System</div>

        <a href="<?= APP_URL ?>/admin/logs.php"
           class="sidebar-link <?= ($adminPage ?? '') === 'logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Activity Logs
        </a>

        <a href="<?= APP_URL ?>/requirements.php" target="_blank" class="sidebar-link">
            <i class="bi bi-check2-circle"></i> Requirements
        </a>

        <div class="sidebar-section" style="margin-top:.5rem">App</div>

        <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-link">
            <i class="bi bi-arrow-left-circle"></i> Back to App
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="<?= APP_URL ?>/logout.php" class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($adminUser['name'], 0, 1)) ?></div>
            <div>
                <div style="color:#f0f6fc;font-size:.8rem;font-weight:500"><?= e($adminUser['name']) ?></div>
                <div style="font-size:.7rem">Logout</div>
            </div>
            <i class="bi bi-box-arrow-right ms-auto"></i>
        </a>
    </div>
</aside>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<div id="admin-main">
    <header id="admin-topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-md-none border-0" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <span class="topbar-title"><?= e($adminTitle ?? 'Admin') ?></span>
        </div>
        <div class="topbar-actions">
            <span class="topbar-badge"><i class="bi bi-shield-fill me-1"></i>Admin</span>
            <a href="<?= APP_URL ?>/dashboard.php" class="topbar-btn">
                <i class="bi bi-grid"></i> User Dashboard
            </a>
        </div>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
    <div style="padding:1.25rem 1.75rem 0">
        <div class="admin-flash <?= e($_SESSION['flash']['type']) ?>">
            <i class="bi bi-<?= $_SESSION['flash']['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= e($_SESSION['flash']['msg']) ?>
        </div>
    </div>
    <?php unset($_SESSION['flash']); endif; ?>

    <div id="admin-content">
