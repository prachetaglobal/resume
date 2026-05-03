<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PlanLimits.php';

Auth::boot();
Auth::requireLogin();

// Admin only
$user = Auth::user();
if ($user['role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    redirect(APP_URL . '/dashboard.php');
}

$errors  = [];
$success = false;
$plans   = ['free', 'pro', 'enterprise'];

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

        // -1 means unlimited; otherwise must be a positive int
        $maxResumes = ($maxResumes === '-1' || $maxResumes === '') ? -1 : (int)$maxResumes;
        $maxExports = ($maxExports === '-1' || $maxExports === '') ? -1 : (int)$maxExports;

        if ($maxResumes !== -1 && $maxResumes < 1) {
            $errors[] = ucfirst($plan) . ': max resumes must be ≥ 1 or -1 (unlimited).';
        }
        if ($maxExports !== -1 && $maxExports < 1) {
            $errors[] = ucfirst($plan) . ': max daily exports must be ≥ 1 or -1 (unlimited).';
        }

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

// ── Load current settings ─────────────────────────────────────────────────────
$settings = [];
foreach ($plans as $plan) {
    $settings[$plan] = PlanLimits::get($plan);
}

// ── User stats per plan ───────────────────────────────────────────────────────
$stats = [];
foreach ($plans as $plan) {
    $row = Database::fetchOne(
        'SELECT COUNT(*) AS users,
                COALESCE(SUM(r.total),0) AS resumes
         FROM users u
         LEFT JOIN (
             SELECT user_id, COUNT(*) AS total FROM resumes GROUP BY user_id
         ) r ON r.user_id = u.id
         WHERE u.plan = ?',
        [$plan]
    );
    $stats[$plan] = $row;
}

// Today's exports per plan
$exportToday = [];
foreach ($plans as $plan) {
    $row = Database::fetchOne(
        "SELECT COUNT(*) AS cnt
           FROM resume_export_log el
           JOIN users u ON u.id = el.user_id
          WHERE u.plan = ? AND DATE(el.exported_at) = CURDATE()",
        [$plan]
    );
    $exportToday[$plan] = (int)($row['cnt'] ?? 0);
}

$pageTitle = 'Plan Settings — Admin — ' . APP_NAME;
$bodyClass = 'd-flex flex-column min-vh-100';
include __DIR__ . '/../includes/header.php';
?>

<main class="container py-4 flex-grow-1" style="max-width:860px">

    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="p-2 bg-primary bg-opacity-10 rounded-3">
            <i class="bi bi-sliders text-primary fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Plan Limits</h4>
            <p class="text-muted small mb-0">Configure per-plan resume and PDF export limits. Changes take effect immediately.</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
        <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <?php
        $planMeta = [
            'free'       => ['icon' => 'bi-person',        'color' => 'secondary'],
            'pro'        => ['icon' => 'bi-star-fill',      'color' => 'primary'],
            'enterprise' => ['icon' => 'bi-building-fill',  'color' => 'warning'],
        ];
        foreach ($plans as $plan):
            $meta = $planMeta[$plan];
        ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi <?= $meta['icon'] ?> text-<?= $meta['color'] ?> fs-5"></i>
                        <span class="fw-semibold text-capitalize"><?= ucfirst($plan) ?> Plan</span>
                    </div>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="fs-5 fw-bold"><?= $stats[$plan]['users'] ?></div>
                            <div class="text-muted" style="font-size:.75rem">Users</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-5 fw-bold"><?= $stats[$plan]['resumes'] ?></div>
                            <div class="text-muted" style="font-size:.75rem">Resumes</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-5 fw-bold"><?= $exportToday[$plan] ?></div>
                            <div class="text-muted" style="font-size:.75rem">DL Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Settings form -->
    <form method="POST" novalidate>
        <?= csrfField() ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold py-3 px-4">
                <i class="bi bi-table me-2 text-primary"></i>Limit Configuration
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Plan</th>
                                <th class="py-3">Max Resumes<br><small class="text-muted fw-normal">-1 = unlimited</small></th>
                                <th class="py-3">Max PDF Downloads / Day<br><small class="text-muted fw-normal">-1 = unlimited</small></th>
                                <th class="py-3 text-center">PDF Export<br><small class="text-muted fw-normal">Enabled?</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plans as $plan):
                                $s    = $settings[$plan];
                                $meta = $planMeta[$plan];
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="bi <?= $meta['icon'] ?> text-<?= $meta['color'] ?>"></i>
                                        <span class="fw-medium text-capitalize"><?= ucfirst($plan) ?></span>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <input type="number" id="max_resumes_<?= $plan ?>"
                                           name="max_resumes_<?= $plan ?>"
                                           class="form-control form-control-sm" style="width:100px"
                                           value="<?= (int)$s['max_resumes'] ?>"
                                           min="-1" step="1" required>
                                </td>
                                <td class="py-3">
                                    <input type="number" id="max_daily_exports_<?= $plan ?>"
                                           name="max_daily_exports_<?= $plan ?>"
                                           class="form-control form-control-sm" style="width:100px"
                                           value="<?= (int)$s['max_daily_exports'] ?>"
                                           min="-1" step="1" required>
                                </td>
                                <td class="py-3 text-center">
                                    <div class="form-check form-switch d-inline-block m-0">
                                        <input type="checkbox" id="exports_enabled_<?= $plan ?>"
                                               name="exports_enabled_<?= $plan ?>"
                                               class="form-check-input" role="switch"
                                               <?= $s['exports_enabled'] ? 'checked' : '' ?>>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent px-4 py-3 d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Changes apply to all new requests immediately — existing sessions are unaffected until their next page load.
                </small>
            </div>
        </div>
    </form>

    <!-- Export log preview -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent fw-semibold py-3 px-4">
            <i class="bi bi-download me-2 text-primary"></i>Recent PDF Downloads (last 50)
        </div>
        <div class="card-body p-0">
            <?php
            $logs = Database::fetchAll(
                "SELECT el.exported_at, u.name, u.email, u.plan, r.title
                   FROM resume_export_log el
                   JOIN users   u ON u.id = el.user_id
                   JOIN resumes r ON r.id = el.resume_id
                  ORDER BY el.exported_at DESC
                  LIMIT 50"
            );
            ?>
            <?php if (empty($logs)): ?>
            <p class="text-muted text-center py-4 mb-0">No exports recorded yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">When</th>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Resume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="px-4 text-muted small"><?= e($log['exported_at']) ?></td>
                            <td class="small"><?= e($log['name']) ?> <span class="text-muted">&lt;<?= e($log['email']) ?>&gt;</span></td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= e($log['plan']) ?></span></td>
                            <td class="small text-truncate" style="max-width:200px"><?= e($log['title']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
