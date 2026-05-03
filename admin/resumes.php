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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token.'];
        redirect(APP_URL . '/admin/resumes.php');
    }
    $action   = $_POST['action'] ?? '';
    $resumeId = (int)($_POST['resume_id'] ?? 0);

    if ($action === 'delete' && $resumeId) {
        $r = Database::fetchOne('SELECT title FROM resumes WHERE id = ?', [$resumeId]);
        Database::query('DELETE FROM resumes WHERE id = ?', [$resumeId]);
        ActivityLog::resumeDeleted($adminUser['id'], $resumeId, $r['title'] ?? '');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Resume deleted.'];
        redirect(APP_URL . '/admin/resumes.php');
    }
}

// Filters
$search      = trim($_GET['q'] ?? '');
$filterTpl   = trim($_GET['template'] ?? '');
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 25;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(r.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterTpl) { $where[] = 't.slug = ?'; $params[] = $filterTpl; }

$whereStr = implode(' AND ', $where);

$total = (int)Database::fetchOne(
    "SELECT COUNT(*) AS c FROM resumes r
       JOIN users u ON u.id = r.user_id
       JOIN templates t ON t.id = r.template_id
      WHERE $whereStr", $params
)['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$resumes = Database::fetchAll(
    "SELECT r.id, r.title, r.created_at, r.updated_at, r.last_exported_at, r.is_public,
            u.name AS user_name, u.email AS user_email, u.plan AS user_plan,
            t.name AS template_name, t.slug AS template_slug,
            (SELECT COUNT(*) FROM resume_export_log el WHERE el.resume_id = r.id) AS export_count
       FROM resumes r
       JOIN users u ON u.id = r.user_id
       JOIN templates t ON t.id = r.template_id
      WHERE $whereStr
      ORDER BY r.updated_at DESC
      LIMIT $perPage OFFSET $offset", $params
);

$templates = Database::fetchAll('SELECT slug, name FROM templates WHERE is_active=1 ORDER BY sort_order');

$adminTitle = 'Resume Management';
$adminPage  = 'resumes';
include __DIR__ . '/layout_start.php';
?>

<div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-file-earmark-person" style="color:#6366f1;font-size:1.1rem"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Resume Management</h5>
            <p class="mb-0" style="font-size:.8rem;color:#8b949e"><?= number_format($total) ?> resume<?= $total !== 1 ? 's' : '' ?> total</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="admin-card mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" style="padding:.9rem 1.25rem">
        <input type="text" name="q" value="<?= e($search) ?>"
               class="admin-input" style="width:240px" placeholder="Search title, user…">
        <select name="template" class="admin-input" style="width:160px">
            <option value="">All Templates</option>
            <?php foreach ($templates as $t): ?>
            <option value="<?= e($t['slug']) ?>" <?= $filterTpl === $t['slug'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="background:#6366f1;color:#fff;border:none;padding:.48rem 1.1rem;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
            <i class="bi bi-search me-1"></i>Filter
        </button>
        <?php if ($search || $filterTpl): ?>
        <a href="<?= APP_URL ?>/admin/resumes.php" style="color:#8b949e;font-size:.8rem;text-decoration:none">
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
                    <th>Resume</th>
                    <th>Owner</th>
                    <th>Template</th>
                    <th style="text-align:center">Exports</th>
                    <th>Last Updated</th>
                    <th>Public</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumes as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#f0f6fc"><?= e($r['title']) ?></div>
                        <div style="font-size:.72rem;color:#8b949e">Created <?= timeAgo($r['created_at']) ?></div>
                    </td>
                    <td>
                        <div style="color:#c9d1d9"><?= e($r['user_name']) ?></div>
                        <div style="font-size:.75rem;color:#8b949e"><?= e($r['user_email']) ?></div>
                        <span class="plan-badge plan-<?= $r['user_plan'] ?>"><?= ucfirst($r['user_plan']) ?></span>
                    </td>
                    <td style="color:#c9d1d9;font-size:.85rem"><?= e($r['template_name']) ?></td>
                    <td style="text-align:center;color:#c9d1d9"><?= $r['export_count'] ?></td>
                    <td style="color:#8b949e;font-size:.8rem"><?= timeAgo($r['updated_at']) ?></td>
                    <td>
                        <?php if ($r['is_public']): ?>
                        <span style="color:#3fb950;font-size:.8rem;font-weight:600"><i class="bi bi-globe me-1"></i>Yes</span>
                        <?php else: ?>
                        <span style="color:#8b949e;font-size:.8rem">Private</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="<?= APP_URL ?>/preview.php?id=<?= $r['id'] ?>" target="_blank"
                           style="display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .65rem;border-radius:6px;font-size:.75rem;font-weight:500;background:#21262d;color:#c9d1d9;text-decoration:none;border:1px solid #30363d;margin-right:3px">
                            <i class="bi bi-eye"></i>View
                        </a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete resume &quot;<?= e(addslashes($r['title'])) ?>&quot;?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="resume_id" value="<?= $r['id'] ?>">
                            <button type="submit" style="display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .65rem;border-radius:6px;font-size:.75rem;font-weight:500;background:#2d0a0a;color:#f87171;border:1px solid #7f1d1d;cursor:pointer">
                                <i class="bi bi-trash"></i>Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($resumes)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#8b949e">No resumes found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div style="padding:.9rem 1.25rem;border-top:1px solid #21262d;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <?php
        $qs = http_build_query(array_filter(['q'=>$search,'template'=>$filterTpl]));
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
