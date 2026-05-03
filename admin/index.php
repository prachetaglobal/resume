<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PlanLimits.php';

Auth::boot();
Auth::requireLogin();

$adminUser = Auth::user();
if ($adminUser['role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    redirect(APP_URL . '/dashboard.php');
}

// ── Stats ─────────────────────────────────────────────────────────────────────

$totalUsers   = Database::fetchOne('SELECT COUNT(*) AS c FROM users')['c'];
$totalResumes = Database::fetchOne('SELECT COUNT(*) AS c FROM resumes')['c'];
$totalExports = Database::fetchOne('SELECT COUNT(*) AS c FROM resume_export_log')['c'];
$exportsToday = Database::fetchOne("SELECT COUNT(*) AS c FROM resume_export_log WHERE DATE(exported_at) = CURDATE()")['c'];
$newUsersToday = Database::fetchOne("SELECT COUNT(*) AS c FROM users WHERE DATE(created_at) = CURDATE()")['c'];
$newUsersWeek  = Database::fetchOne("SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'];
$activeUsers   = Database::fetchOne("SELECT COUNT(*) AS c FROM users WHERE is_active = 1")['c'];

// Users per plan
$usersByPlan = Database::fetchAll(
    "SELECT plan, COUNT(*) AS cnt FROM users GROUP BY plan ORDER BY FIELD(plan,'enterprise','pro','free')"
);

// Resumes per template
$resumesByTemplate = Database::fetchAll(
    "SELECT t.name, COUNT(r.id) AS cnt
       FROM templates t
       LEFT JOIN resumes r ON r.template_id = t.id
      GROUP BY t.id, t.name ORDER BY cnt DESC LIMIT 8"
);

// Recent users (last 8)
$recentUsers = Database::fetchAll(
    "SELECT name, email, plan, role, created_at FROM users ORDER BY created_at DESC LIMIT 8"
);

// Recent exports (last 8)
$recentExports = Database::fetchAll(
    "SELECT el.exported_at, u.name, u.plan, r.title
       FROM resume_export_log el
       JOIN users u ON u.id = el.user_id
       JOIN resumes r ON r.id = el.resume_id
      ORDER BY el.exported_at DESC LIMIT 8"
);

// Exports per day (last 7 days) — for spark display
$exportTrend = Database::fetchAll(
    "SELECT DATE(exported_at) AS day, COUNT(*) AS cnt
       FROM resume_export_log
      WHERE exported_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(exported_at)
      ORDER BY day ASC"
);

$adminTitle = 'Dashboard';
$adminPage  = 'dashboard';
include __DIR__ . '/layout_start.php';
?>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="--stat-color:#6366f1">
            <i class="bi bi-people stat-icon"></i>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-label">Total Users</div>
            <div class="stat-delta up"><i class="bi bi-arrow-up-short"></i><?= $newUsersWeek ?> this week</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="--stat-color:#3fb950">
            <i class="bi bi-file-earmark-person stat-icon"></i>
            <div class="stat-value"><?= number_format($totalResumes) ?></div>
            <div class="stat-label">Total Resumes</div>
            <div class="stat-delta flat">across all users</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="--stat-color:#f9a825">
            <i class="bi bi-download stat-icon"></i>
            <div class="stat-value"><?= number_format($exportsToday) ?></div>
            <div class="stat-label">PDF Downloads Today</div>
            <div class="stat-delta flat"><?= number_format($totalExports) ?> total all-time</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="--stat-color:#38bdf8">
            <i class="bi bi-person-check stat-icon"></i>
            <div class="stat-value"><?= number_format($activeUsers) ?></div>
            <div class="stat-label">Active Accounts</div>
            <div class="stat-delta up"><i class="bi bi-arrow-up-short"></i><?= $newUsersToday ?> joined today</div>
        </div>
    </div>

</div>

<!-- ── Row 2: Users by plan + Template popularity ─────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Users by plan -->
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <i class="bi bi-pie-chart text-indigo-400" style="color:#6366f1"></i>
                Users by Plan
            </div>
            <div style="padding:1rem 1.25rem">
                <?php
                $planMeta = [
                    'free'       => ['label' => 'Free',       'color' => '#8b949e', 'class' => 'plan-free'],
                    'pro'        => ['label' => 'Pro',        'color' => '#90caf9', 'class' => 'plan-pro'],
                    'enterprise' => ['label' => 'Enterprise', 'color' => '#fdd835', 'class' => 'plan-enterprise'],
                ];
                $planMap = array_column($usersByPlan, 'cnt', 'plan');
                foreach (['enterprise','pro','free'] as $p):
                    $cnt  = (int)($planMap[$p] ?? 0);
                    $pct  = $totalUsers > 0 ? round($cnt / $totalUsers * 100) : 0;
                    $meta = $planMeta[$p];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="plan-badge <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                        <span style="font-size:.8rem;color:#8b949e"><?= $cnt ?> users (<?= $pct ?>%)</span>
                    </div>
                    <div style="background:#21262d;border-radius:4px;height:6px;overflow:hidden">
                        <div style="background:<?= $meta['color'] ?>;height:100%;width:<?= $pct ?>%;border-radius:4px;transition:width .6s ease"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Template popularity -->
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <i class="bi bi-layout-text-sidebar" style="color:#6366f1"></i>
                Template Popularity
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Resumes</th>
                            <th style="width:45%">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumesByTemplate as $row):
                            $pct = $totalResumes > 0 ? round($row['cnt'] / $totalResumes * 100) : 0;
                        ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= $row['cnt'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="flex:1;background:#21262d;border-radius:4px;height:5px;overflow:hidden">
                                        <div style="background:#6366f1;height:100%;width:<?= $pct ?>%;border-radius:4px"></div>
                                    </div>
                                    <span style="font-size:.75rem;color:#8b949e;width:2.5rem;text-align:right"><?= $pct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ── Row 3: Recent users + Recent exports ───────────────────────────────── -->
<div class="row g-3">

    <!-- Recent users -->
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <i class="bi bi-person-plus" style="color:#6366f1"></i>
                Recent Registrations
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;color:#f0f6fc"><?= e($u['name']) ?></div>
                                <div style="font-size:.75rem;color:#8b949e"><?= e($u['email']) ?></div>
                            </td>
                            <td>
                                <span class="plan-badge plan-<?= $u['plan'] ?>">
                                    <?= ucfirst(e($u['plan'])) ?>
                                </span>
                                <?php if ($u['role'] === 'admin'): ?>
                                <span class="plan-badge" style="background:#1f2937;color:#6366f1;border-color:#374151;margin-left:3px">Admin</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#8b949e;font-size:.8rem"><?= timeAgo($u['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent exports -->
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="admin-card-header">
                <i class="bi bi-file-earmark-arrow-down" style="color:#6366f1"></i>
                Recent PDF Downloads
                <?php if ($exportsToday > 0): ?>
                <span class="plan-badge plan-pro ms-auto"><?= $exportsToday ?> today</span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Resume</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentExports)): ?>
                        <tr><td colspan="3" style="text-align:center;color:#8b949e;padding:2rem">No exports yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentExports as $ex): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;color:#f0f6fc"><?= e($ex['name']) ?></div>
                                <span class="plan-badge plan-<?= $ex['plan'] ?>"><?= ucfirst($ex['plan']) ?></span>
                            </td>
                            <td style="font-size:.8rem;color:#c9d1d9;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($ex['title']) ?></td>
                            <td style="color:#8b949e;font-size:.8rem"><?= timeAgo($ex['exported_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/layout_end.php'; ?>
