<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ActivityLog.php';

Auth::boot();
Auth::requireLogin();

$adminUser = Auth::user();
if ($adminUser['role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    redirect(APP_URL . '/dashboard.php');
}

// Filters
$search      = trim($_GET['q'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 50;

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        Database::query('DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        ActivityLog::log('admin.logs.clear', $adminUser['id'], null, null, 'Cleared logs older than 30 days');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Logs older than 30 days cleared.'];
        redirect(APP_URL . '/admin/logs.php');
    }
}

// Distinct actions for filter dropdown
try {
    $actionTypes = Database::fetchAll('SELECT DISTINCT action FROM activity_log ORDER BY action');
} catch (Throwable $e) { $actionTypes = []; }

// Build query
$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(al.action LIKE ? OR al.detail LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterAction) { $where[] = 'al.action = ?'; $params[] = $filterAction; }

$whereStr = implode(' AND ', $where);

try {
    $total = (int)Database::fetchOne(
        "SELECT COUNT(*) AS c FROM activity_log al LEFT JOIN users u ON u.id = al.user_id WHERE $whereStr", $params
    )['c'];
} catch (Throwable $e) { $total = 0; }

$totalPages = max(1, (int)ceil($total / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

try {
    $logs = Database::fetchAll(
        "SELECT al.*, u.name AS user_name, u.email AS user_email
           FROM activity_log al
           LEFT JOIN users u ON u.id = al.user_id
          WHERE $whereStr
          ORDER BY al.created_at DESC
          LIMIT $perPage OFFSET $offset", $params
    );
} catch (Throwable $e) { $logs = []; }

// Action colour map
$actionColors = [
    'user.login'          => ['#3fb950','#052e16'],
    'user.logout'         => ['#8b949e','#21262d'],
    'user.register'       => ['#58a6ff','#0d2137'],
    'resume.create'       => ['#d2a8ff','#2d1f52'],
    'resume.delete'       => ['#f87171','#2d0a0a'],
    'resume.export'       => ['#fdd835','#2d2400'],
    'admin.user.update'   => ['#90caf9','#0d2137'],
    'admin.user.delete'   => ['#f87171','#2d0a0a'],
    'admin.plan.update'   => ['#ffb347','#2d1800'],
    'admin.site.update'   => ['#6366f1','#1a1040'],
    'admin.logs.clear'    => ['#8b949e','#21262d'],
];

$adminTitle = 'Activity Logs';
$adminPage  = 'logs';
include __DIR__ . '/layout_start.php';
?>

<div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-journal-text" style="color:#6366f1;font-size:1.1rem"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Activity Logs</h5>
            <p class="mb-0" style="font-size:.8rem;color:#8b949e"><?= number_format($total) ?> entries</p>
        </div>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="clear">
        <button type="submit"
                onclick="return confirm('Delete all logs older than 30 days?')"
                style="background:#2d0a0a;color:#f87171;border:1px solid #7f1d1d;padding:.45rem 1rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer">
            <i class="bi bi-trash me-1"></i>Clear Old Logs (&gt;30d)
        </button>
    </form>
</div>

<!-- Filters -->
<div class="admin-card mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" style="padding:.9rem 1.25rem">
        <input type="text" name="q" value="<?= e($search) ?>"
               class="admin-input" style="width:240px" placeholder="Search action, user, detail…">
        <select name="action_filter" class="admin-input" style="width:200px">
            <option value="">All Actions</option>
            <?php foreach ($actionTypes as $at): ?>
            <option value="<?= e($at['action']) ?>" <?= $filterAction === $at['action'] ? 'selected' : '' ?>>
                <?= e($at['action']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.48rem 1.1rem;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
            <i class="bi bi-search me-1"></i>Filter
        </button>
        <?php if ($search || $filterAction): ?>
        <a href="<?= APP_URL ?>/admin/logs.php" style="color:#8b949e;font-size:.8rem;text-decoration:none">
            <i class="bi bi-x-circle me-1"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-card mb-4">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Entity</th>
                    <th>Detail</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2.5rem;color:#8b949e">No log entries found.</td></tr>
                <?php else: ?>
                <?php foreach ($logs as $log):
                    [$fg, $bg] = $actionColors[$log['action']] ?? ['#8b949e','#21262d'];
                ?>
                <tr>
                    <td style="white-space:nowrap;color:#8b949e;font-size:.78rem"><?= e($log['created_at']) ?></td>
                    <td>
                        <span style="display:inline-block;padding:.2rem .6rem;border-radius:6px;font-size:.72rem;font-weight:600;background:<?= $bg ?>;color:<?= $fg ?>;border:1px solid <?= $fg ?>33">
                            <?= e($log['action']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['user_name']): ?>
                        <div style="color:#c9d1d9;font-size:.85rem"><?= e($log['user_name']) ?></div>
                        <div style="color:#8b949e;font-size:.72rem"><?= e($log['user_email']) ?></div>
                        <?php else: ?>
                        <span style="color:#6e7681;font-size:.8rem">System / Deleted</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;color:#8b949e">
                        <?php if ($log['entity']): ?>
                        <span style="background:#21262d;padding:.15rem .5rem;border-radius:5px;border:1px solid #30363d"><?= e($log['entity']) ?></span>
                        <?php if ($log['entity_id']): ?> #<?= $log['entity_id'] ?><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.8rem;color:#c9d1d9;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= e($log['detail'] ?? '') ?>">
                        <?= e($log['detail'] ?? '—') ?>
                    </td>
                    <td style="font-size:.78rem;color:#8b949e;white-space:nowrap"><?= e($log['ip'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:.9rem 1.25rem;border-top:1px solid #21262d;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <?php
        $qs = http_build_query(array_filter(['q'=>$search,'action_filter'=>$filterAction]));
        for ($i = 1; $i <= $totalPages; $i++):
            $active = $i === $page;
        ?>
        <a href="?p=<?= $i ?>&<?= $qs ?>"
           style="padding:.3rem .65rem;border-radius:6px;font-size:.8rem;text-decoration:none;
                  background:<?= $active ? '#6366f1' : '#21262d' ?>;
                  color:<?= $active ? '#fff' : '#8b949e' ?>;
                  border:1px solid <?= $active ? '#6366f1' : '#30363d' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <span style="font-size:.75rem;color:#8b949e;margin-left:.5rem">Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
