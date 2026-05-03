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

$errors = [];
$search      = trim($_GET['q'] ?? '');
$filterPlan  = $_GET['plan'] ?? '';
$filterRole  = $_GET['role'] ?? '';
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 20;

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token.'];
        redirect(APP_URL . '/admin/users.php');
    }

    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId === (int)$adminUser['id'] && in_array($action, ['delete','toggle_active'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'You cannot perform this action on your own account.'];
        redirect(APP_URL . '/admin/users.php');
    }

    $target = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$targetId]);
    if (!$target && $action !== '') {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'User not found.'];
        redirect(APP_URL . '/admin/users.php');
    }

    // ── Update profile ────────────────────────────────────────────────────────
    if ($action === 'update') {
        $name   = trim($_POST['name'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $plan   = $_POST['plan']  ?? $target['plan'];
        $role   = $_POST['role']  ?? $target['role'];
        $active = (int)isset($_POST['is_active']);

        if (!$name)                                    $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!in_array($plan, ['free','pro','enterprise'])) $errors[] = 'Invalid plan.';
        if (!in_array($role, ['user','admin']))          $errors[] = 'Invalid role.';

        if (empty($errors)) {
            $dup = Database::fetchOne('SELECT id FROM users WHERE email=? AND id!=?', [$email, $targetId]);
            if ($dup) $errors[] = 'Email already in use by another account.';
        }

        if (empty($errors)) {
            Database::query(
                'UPDATE users SET name=?, email=?, plan=?, role=?, is_active=?, updated_at=NOW() WHERE id=?',
                [$name, $email, $plan, $role, $active, $targetId]
            );
            ActivityLog::userUpdated($adminUser['id'], $targetId,
                "name=$name, plan=$plan, role=$role, active=$active");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "User \"$name\" updated."];
            redirect(APP_URL . '/admin/users.php');
        }
    }

    // ── Reset password ────────────────────────────────────────────────────────
    if ($action === 'reset_password') {
        $newPw = $_POST['new_password'] ?? '';
        if (strlen($newPw) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::query('UPDATE users SET password=?, updated_at=NOW() WHERE id=?', [$hash, $targetId]);
            ActivityLog::userUpdated($adminUser['id'], $targetId, 'Password reset by admin');
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password reset successfully.'];
            redirect(APP_URL . '/admin/users.php');
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        Database::query('DELETE FROM users WHERE id=?', [$targetId]);
        ActivityLog::userDeleted($adminUser['id'], $targetId, $target['email']);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "User \"{$target['name']}\" deleted."];
        redirect(APP_URL . '/admin/users.php');
    }

    // ── Toggle active ─────────────────────────────────────────────────────────
    if ($action === 'toggle_active') {
        $new = $target['is_active'] ? 0 : 1;
        Database::query('UPDATE users SET is_active=?, updated_at=NOW() WHERE id=?', [$new, $targetId]);
        ActivityLog::userUpdated($adminUser['id'], $targetId, 'is_active → ' . $new);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'User status updated.'];
        redirect(APP_URL . '/admin/users.php');
    }
}

// ── Build filtered query ──────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterPlan) { $where[] = 'u.plan=?';  $params[] = $filterPlan; }
if ($filterRole) { $where[] = 'u.role=?';  $params[] = $filterRole; }

$whereStr   = implode(' AND ', $where);
$total      = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM users u WHERE $whereStr", $params)['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$users = Database::fetchAll(
    "SELECT u.*,
            (SELECT COUNT(*) FROM resumes       r  WHERE r.user_id  = u.id) AS resume_count,
            (SELECT COUNT(*) FROM resume_export_log el WHERE el.user_id = u.id) AS export_count
       FROM users u
      WHERE $whereStr
      ORDER BY u.created_at DESC
      LIMIT $perPage OFFSET $offset",
    $params
);

// Pull the user being edited (if ?edit=N)
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = Database::fetchOne('SELECT * FROM users WHERE id=?', [(int)$_GET['edit']]);
}

$adminTitle = 'User Management';
$adminPage  = 'users';
include __DIR__ . '/layout_start.php';
?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-people" style="color:#6366f1;font-size:1.1rem"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold" style="color:#f0f6fc">User Management</h5>
            <p class="mb-0" style="font-size:.8rem;color:#8b949e"><?= number_format($total) ?> user<?= $total !== 1 ? 's' : '' ?> found</p>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-flash danger mb-4">
    <i class="bi bi-exclamation-circle"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="admin-card mb-4">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center" style="padding:.9rem 1.25rem">
        <input type="text" name="q" value="<?= e($search) ?>"
               class="admin-input" style="width:220px;flex-shrink:0"
               placeholder="Search name or email…">
        <select name="plan" class="admin-input" style="width:140px">
            <option value="">All Plans</option>
            <?php foreach (['free','pro','enterprise'] as $p): ?>
            <option value="<?= $p ?>" <?= $filterPlan === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="role" class="admin-input" style="width:120px">
            <option value="">All Roles</option>
            <option value="user"  <?= $filterRole === 'user'  ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <button type="submit"
                style="background:#6366f1;color:#fff;border:none;padding:.48rem 1.1rem;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
            <i class="bi bi-search me-1"></i>Filter
        </button>
        <?php if ($search || $filterPlan || $filterRole): ?>
        <a href="<?= APP_URL ?>/admin/users.php"
           style="color:#8b949e;font-size:.8rem;text-decoration:none">
            <i class="bi bi-x-circle me-1"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Users table -->
<div class="admin-card mb-4">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Role</th>
                    <th style="text-align:center">Resumes</th>
                    <th style="text-align:center">Exports</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#f0f6fc"><?= e($u['name']) ?></div>
                        <div style="font-size:.75rem;color:#8b949e"><?= e($u['email']) ?></div>
                    </td>
                    <td>
                        <span class="plan-badge plan-<?= $u['plan'] ?>"><?= ucfirst($u['plan']) ?></span>
                    </td>
                    <td>
                        <span class="plan-badge"
                              style="<?= $u['role']==='admin'
                                  ? 'background:#1f2937;color:#6366f1;border:1px solid #374151'
                                  : 'background:#21262d;color:#8b949e;border:1px solid #30363d' ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td style="text-align:center;color:#c9d1d9"><?= $u['resume_count'] ?></td>
                    <td style="text-align:center;color:#c9d1d9"><?= $u['export_count'] ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span style="display:inline-flex;align-items:center;gap:.3rem;color:#3fb950;font-size:.75rem;font-weight:600">
                            <i class="bi bi-circle-fill" style="font-size:.45rem"></i>Active
                        </span>
                        <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:.3rem;color:#f87171;font-size:.75rem;font-weight:600">
                            <i class="bi bi-circle-fill" style="font-size:.45rem"></i>Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#8b949e;font-size:.8rem;white-space:nowrap"><?= timeAgo($u['created_at']) ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <!-- Edit -->
                        <?php $qs = $search ? '&q='.urlencode($search) : ''; ?>
                        <a href="?edit=<?= $u['id'] ?><?= $qs ?>"
                           style="display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .65rem;
                                  border-radius:6px;font-size:.75rem;font-weight:500;
                                  background:#21262d;color:#c9d1d9;text-decoration:none;
                                  border:1px solid #30363d;margin-right:3px">
                            <i class="bi bi-pencil"></i>Edit
                        </a>
                        <!-- Toggle active -->
                        <?php if ((int)$u['id'] !== (int)$adminUser['id']): ?>
                        <form method="POST" style="display:inline;margin-right:3px">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit"
                                    title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                    style="display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .65rem;
                                           border-radius:6px;font-size:.75rem;font-weight:500;cursor:pointer;
                                           background:<?= $u['is_active'] ? '#1c2a1c' : '#1c2a1c' ?>;
                                           color:<?= $u['is_active'] ? '#f87171' : '#3fb950' ?>;
                                           border:1px solid <?= $u['is_active'] ? '#7f1d1d55' : '#16653455' ?>">
                                <i class="bi bi-<?= $u['is_active'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <!-- Delete -->
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Permanently delete <?= e(addslashes($u['name'])) ?> and all their data?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit"
                                    style="display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .65rem;
                                           border-radius:6px;font-size:.75rem;font-weight:500;cursor:pointer;
                                           background:#2d0a0a;color:#f87171;border:1px solid #7f1d1d">
                                <i class="bi bi-trash"></i>Delete
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:2.5rem;color:#8b949e">No users found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:.9rem 1.25rem;border-top:1px solid #21262d;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <?php
        $qs = http_build_query(array_filter(['q'=>$search,'plan'=>$filterPlan,'role'=>$filterRole]));
        for ($i = 1; $i <= $totalPages; $i++):
            $active = $i === $page;
        ?>
        <a href="?p=<?= $i ?>&<?= $qs ?>"
           style="padding:.3rem .65rem;border-radius:6px;font-size:.8rem;text-decoration:none;
                  background:<?= $active ? '#6366f1' : '#21262d' ?>;
                  color:<?= $active ? '#fff' : '#8b949e' ?>;
                  border:1px solid <?= $active ? '#6366f1' : '#30363d' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>
        <span style="font-size:.75rem;color:#8b949e;margin-left:.5rem">
            Page <?= $page ?> of <?= $totalPages ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- ── Edit User Panel ─────────────────────────────────────────────────────── -->
<?php if ($editUser): ?>
<div style="position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:200;
            display:flex;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:16px;
                width:100%;max-width:540px;max-height:90vh;overflow-y:auto">

        <!-- Modal header -->
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #21262d;
                    display:flex;align-items:center;justify-content:space-between;
                    position:sticky;top:0;background:#161b22;z-index:1">
            <div>
                <div style="font-weight:700;color:#f0f6fc">Edit User</div>
                <div style="font-size:.75rem;color:#8b949e"><?= e($editUser['email']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/admin/users.php"
               style="color:#8b949e;text-decoration:none;font-size:1.4rem;line-height:1">&times;</a>
        </div>

        <!-- Profile form -->
        <form method="POST" style="padding:1.5rem">
            <?= csrfField() ?>
            <input type="hidden" name="action"  value="update">
            <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.1rem">
                <div>
                    <label style="display:block;font-size:.78rem;color:#8b949e;margin-bottom:.35rem">Full Name</label>
                    <input type="text" name="name"
                           value="<?= e($editUser['name']) ?>"
                           class="admin-input" required>
                </div>
                <div>
                    <label style="display:block;font-size:.78rem;color:#8b949e;margin-bottom:.35rem">Email</label>
                    <input type="email" name="email"
                           value="<?= e($editUser['email']) ?>"
                           class="admin-input" required>
                </div>
                <div>
                    <label style="display:block;font-size:.78rem;color:#8b949e;margin-bottom:.35rem">Plan</label>
                    <select name="plan" class="admin-input">
                        <?php foreach (['free','pro','enterprise'] as $p): ?>
                        <option value="<?= $p ?>" <?= $editUser['plan']===$p ? 'selected':'' ?>>
                            <?= ucfirst($p) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.78rem;color:#8b949e;margin-bottom:.35rem">Role</label>
                    <select name="role" class="admin-input">
                        <option value="user"  <?= $editUser['role']==='user'  ? 'selected':'' ?>>User</option>
                        <option value="admin" <?= $editUser['role']==='admin' ? 'selected':'' ?>>Admin</option>
                    </select>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;
                          font-size:.875rem;color:#c9d1d9;margin-bottom:1.25rem">
                <input type="checkbox" name="is_active"
                       <?= $editUser['is_active'] ? 'checked' : '' ?>
                       style="width:16px;height:16px;accent-color:#6366f1">
                Account Active
            </label>

            <div style="display:flex;gap:.75rem">
                <button type="submit"
                        style="background:#6366f1;color:#fff;border:none;
                               padding:.55rem 1.3rem;border-radius:8px;
                               font-size:.875rem;font-weight:600;cursor:pointer">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
                <a href="<?= APP_URL ?>/admin/users.php"
                   style="display:inline-flex;align-items:center;padding:.55rem 1rem;
                          border-radius:8px;font-size:.875rem;
                          background:#21262d;color:#8b949e;text-decoration:none;
                          border:1px solid #30363d">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Password reset sub-form -->
        <div style="border-top:1px solid #21262d;padding:1.25rem 1.5rem">
            <p style="font-size:.8rem;color:#8b949e;margin-bottom:.75rem">
                <i class="bi bi-shield-lock me-1"></i>Reset password for this user
            </p>
            <form method="POST" style="display:flex;gap:.75rem;align-items:center">
                <?= csrfField() ?>
                <input type="hidden" name="action"  value="reset_password">
                <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
                <input type="password" name="new_password" class="admin-input"
                       placeholder="New password (min 8 chars)" style="flex:1">
                <button type="submit"
                        style="background:#21262d;color:#c9d1d9;border:1px solid #30363d;
                               padding:.48rem 1rem;border-radius:8px;
                               font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap">
                    Reset
                </button>
            </form>
        </div>

        <!-- Stats summary -->
        <div style="border-top:1px solid #21262d;padding:1rem 1.5rem;
                    display:flex;gap:1.5rem;font-size:.78rem;color:#8b949e">
            <?php
            $uStats = Database::fetchOne(
                'SELECT COUNT(*) AS rc FROM resumes WHERE user_id=?', [$editUser['id']]
            );
            $uExports = Database::fetchOne(
                'SELECT COUNT(*) AS ec FROM resume_export_log WHERE user_id=?', [$editUser['id']]
            );
            ?>
            <span><i class="bi bi-file-earmark me-1"></i><?= $uStats['rc'] ?> resumes</span>
            <span><i class="bi bi-download me-1"></i><?= $uExports['ec'] ?> total exports</span>
            <span><i class="bi bi-calendar me-1"></i>Joined <?= timeAgo($editUser['created_at']) ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/layout_end.php'; ?>
