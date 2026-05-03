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

$errors = [];
$plans  = ['free', 'pro', 'enterprise'];

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token.'];
        redirect(APP_URL . '/admin/plan-settings.php');
    }

    foreach ($plans as $plan) {
        $maxResumes = $_POST["max_resumes_$plan"] ?? '';
        $maxExports = $_POST["max_daily_exports_$plan"] ?? '';
        $exportsOn  = isset($_POST["exports_enabled_$plan"]) ? 1 : 0;

        $maxResumes = ($maxResumes === '-1' || $maxResumes === '') ? -1 : (int)$maxResumes;
        $maxExports = ($maxExports === '-1' || $maxExports === '') ? -1 : (int)$maxExports;

        if ($maxResumes !== -1 && $maxResumes < 1)
            $errors[] = ucfirst($plan) . ': max resumes must be ≥ 1 or -1 (unlimited).';
        if ($maxExports !== -1 && $maxExports < 1)
            $errors[] = ucfirst($plan) . ': max daily exports must be ≥ 1 or -1 (unlimited).';

        if (empty($errors)) {
            Database::query(
                'INSERT INTO plan_settings (plan, max_resumes, max_daily_exports, exports_enabled)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     max_resumes       = VALUES(max_resumes),
                     max_daily_exports = VALUES(max_daily_exports),
                     exports_enabled   = VALUES(exports_enabled)',
                [$plan, $maxResumes, $maxExports, $exportsOn]
            );
        }
    }

    if (empty($errors)) {
        PlanLimits::flushCache();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Plan settings saved successfully.'];
        redirect(APP_URL . '/admin/plan-settings.php');
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$settings = [];
foreach ($plans as $plan) {
    $settings[$plan] = PlanLimits::get($plan);
}

$planMeta = [
    'free'       => ['icon' => 'bi-person',       'color' => '#8b949e', 'badge' => 'plan-free'],
    'pro'        => ['icon' => 'bi-star-fill',     'color' => '#90caf9', 'badge' => 'plan-pro'],
    'enterprise' => ['icon' => 'bi-building-fill', 'color' => '#fdd835', 'badge' => 'plan-enterprise'],
];

// Stats per plan
$stats = [];
foreach ($plans as $plan) {
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS users, COALESCE(SUM(r.total),0) AS resumes
           FROM users u
           LEFT JOIN (SELECT user_id, COUNT(*) AS total FROM resumes GROUP BY user_id) r
             ON r.user_id = u.id
          WHERE u.plan = ?',
        [$plan]
    );
    $exportToday = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM resume_export_log el
           JOIN users u ON u.id = el.user_id
          WHERE u.plan = ? AND DATE(el.exported_at) = CURDATE()",
        [$plan]
    );
    $stats[$plan] = array_merge($row, ['exports_today' => (int)($exportToday['cnt'] ?? 0)]);
}

// Recent export log
$logs = Database::fetchAll(
    "SELECT el.exported_at, u.name, u.email, u.plan, r.title
       FROM resume_export_log el
       JOIN users   u ON u.id = el.user_id
       JOIN resumes r ON r.id = el.resume_id
      ORDER BY el.exported_at DESC LIMIT 50"
);

$adminTitle = 'Plan Settings';
$adminPage  = 'plan-settings';
include __DIR__ . '/layout_start.php';
?>

<!-- Page heading -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-sliders" style="color:#6366f1;font-size:1.1rem"></i>
    </div>
    <div>
        <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Plan Limits</h5>
        <p class="mb-0" style="font-size:.8rem;color:#8b949e">Configure per-plan resume and PDF export limits. Changes take effect immediately.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-flash danger mb-4">
    <i class="bi bi-exclamation-circle"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<!-- ── Per-plan stat cards ──────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php foreach ($plans as $plan):
        $m = $planMeta[$plan];
        $s = $stats[$plan];
        $limit = PlanLimits::get($plan);
    ?>
    <div class="col-md-4">
        <div class="admin-card h-100">
            <div style="padding:1.1rem 1.25rem">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="plan-badge <?= $m['badge'] ?>">
                        <i class="bi <?= $m['icon'] ?>"></i>
                        <?= ucfirst($plan) ?>
                    </span>
                    <?php if (!$limit['exports_enabled']): ?>
                    <span class="plan-badge" style="background:#2d0a0a;color:#f87171;border-color:#7f1d1d;margin-left:auto">PDF Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="row g-0 text-center">
                    <div class="col-4" style="border-right:1px solid #21262d">
                        <div style="font-size:1.5rem;font-weight:700;color:#f0f6fc"><?= $s['users'] ?></div>
                        <div style="font-size:.7rem;color:#8b949e">Users</div>
                    </div>
                    <div class="col-4" style="border-right:1px solid #21262d">
                        <div style="font-size:1.5rem;font-weight:700;color:#f0f6fc"><?= $s['resumes'] ?></div>
                        <div style="font-size:.7rem;color:#8b949e">Resumes</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:1.5rem;font-weight:700;color:#f0f6fc"><?= $s['exports_today'] ?></div>
                        <div style="font-size:.7rem;color:#8b949e">DL Today</div>
                    </div>
                </div>
                <div style="margin-top:1rem;padding-top:.9rem;border-top:1px solid #21262d;display:flex;justify-content:space-between;font-size:.75rem;color:#8b949e">
                    <span>
                        <i class="bi bi-file-earmark me-1"></i>
                        <?= $limit['max_resumes'] === -1 ? 'Unlimited resumes' : $limit['max_resumes'] . ' resume max' ?>
                    </span>
                    <span>
                        <i class="bi bi-download me-1"></i>
                        <?= $limit['max_daily_exports'] === -1 ? 'Unlimited DL' : $limit['max_daily_exports'] . '/day' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Edit form ─────────────────────────────────────────────────────────── -->
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <i class="bi bi-pencil-square" style="color:#6366f1"></i>
        Edit Limits
    </div>
    <form method="POST" novalidate>
        <?= csrfField() ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:160px">Plan</th>
                        <th>
                            Max Resumes
                            <div style="font-weight:400;color:#6e7681;text-transform:none;letter-spacing:0;font-size:.7rem">-1 = unlimited</div>
                        </th>
                        <th>
                            Max PDF Downloads / Day
                            <div style="font-weight:400;color:#6e7681;text-transform:none;letter-spacing:0;font-size:.7rem">-1 = unlimited</div>
                        </th>
                        <th style="text-align:center">
                            PDF Export Enabled
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan):
                        $s = $settings[$plan];
                        $m = $planMeta[$plan];
                    ?>
                    <tr>
                        <td>
                            <span class="plan-badge <?= $m['badge'] ?>">
                                <i class="bi <?= $m['icon'] ?>"></i>
                                <?= ucfirst($plan) ?>
                            </span>
                        </td>
                        <td>
                            <input type="number"
                                   id="max_resumes_<?= $plan ?>"
                                   name="max_resumes_<?= $plan ?>"
                                   class="admin-input" style="width:110px"
                                   value="<?= (int)$s['max_resumes'] ?>"
                                   min="-1" step="1" required>
                        </td>
                        <td>
                            <input type="number"
                                   id="max_daily_exports_<?= $plan ?>"
                                   name="max_daily_exports_<?= $plan ?>"
                                   class="admin-input" style="width:110px"
                                   value="<?= (int)$s['max_daily_exports'] ?>"
                                   min="-1" step="1" required>
                        </td>
                        <td style="text-align:center">
                            <div class="form-check form-switch d-inline-block m-0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       id="exports_enabled_<?= $plan ?>"
                                       name="exports_enabled_<?= $plan ?>"
                                       <?= $s['exports_enabled'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:1rem 1.25rem;border-top:1px solid #21262d;display:flex;align-items:center;gap:1rem">
            <button type="submit"
                    style="background:#6366f1;color:#fff;border:none;padding:.55rem 1.4rem;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;transition:background .15s"
                    onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='#6366f1'">
                <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <span style="font-size:.78rem;color:#8b949e">
                <i class="bi bi-info-circle me-1"></i>
                Changes apply immediately to all new requests.
            </span>
        </div>
    </form>
</div>

<!-- ── Export log ────────────────────────────────────────────────────────── -->
<div class="admin-card">
    <div class="admin-card-header">
        <i class="bi bi-clock-history" style="color:#6366f1"></i>
        Recent PDF Downloads
        <span class="plan-badge plan-pro ms-auto"><?= count($logs) ?> shown</span>
    </div>
    <?php if (empty($logs)): ?>
    <div style="padding:2.5rem;text-align:center;color:#8b949e">No exports recorded yet.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Resume</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color:#8b949e;white-space:nowrap"><?= e($log['exported_at']) ?></td>
                    <td>
                        <div style="font-weight:500;color:#f0f6fc"><?= e($log['name']) ?></div>
                        <div style="font-size:.75rem;color:#8b949e"><?= e($log['email']) ?></div>
                    </td>
                    <td><span class="plan-badge plan-<?= e($log['plan']) ?>"><?= ucfirst(e($log['plan'])) ?></span></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($log['title']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout_end.php'; ?>
