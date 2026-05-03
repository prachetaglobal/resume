<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();
Auth::requireLogin();

$user   = Auth::user();
$errors = [];
$tab    = 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token. Please try again.'];
        redirect(APP_URL . '/settings.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (empty($errors)) {
            $existing = Database::fetchOne(
                'SELECT id FROM users WHERE email = ? AND id != ?',
                [$email, $user['id']]
            );
            if ($existing) {
                $errors[] = 'That email is already in use.';
            }
        }
        if (empty($errors)) {
            Database::query(
                'UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?',
                [$name, $email, $user['id']]
            );
            $_SESSION['user_name'] = $name;
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
            redirect(APP_URL . '/settings.php');
        }
        $tab = 'profile';
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if ($pwErr = validatePassword($new)) {
            $errors[] = $pwErr;
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::query(
                'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
                [$hash, $user['id']]
            );
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password changed successfully.'];
            redirect(APP_URL . '/settings.php');
        }
        $tab = 'password';
    }
}

$pageTitle = 'Settings — ' . APP_NAME;
$bodyClass = 'd-flex flex-column min-vh-100';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-4 flex-grow-1" style="max-width:680px">
    <h4 class="fw-bold mb-4">Account Settings</h4>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
        <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button class="nav-link <?= $tab === 'profile'  ? 'active' : '' ?>"
                    data-bs-toggle="tab" data-bs-target="#tab-profile">
                <i class="bi bi-person me-1"></i>Profile
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $tab === 'password' ? 'active' : '' ?>"
                    data-bs-toggle="tab" data-bs-target="#tab-password">
                <i class="bi bi-lock me-1"></i>Password
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade <?= $tab === 'profile' ? 'show active' : '' ?>" id="tab-profile">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="profile">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Full Name</label>
                            <input type="text" name="name" class="form-control form-control-lg"
                                   value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg"
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                            <span class="text-muted small">
                                <i class="bi bi-shield-check me-1"></i>
                                <?= $user['role'] === 'admin' ? 'Admin' : 'User' ?>
                                &nbsp;&middot;&nbsp;
                                <?= ucfirst($user['plan']) ?> plan
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?= $tab === 'password' ? 'show active' : '' ?>" id="tab-password">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="password">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Current Password</label>
                            <input type="password" name="current_password"
                                   class="form-control form-control-lg" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">New Password</label>
                            <input type="password" name="new_password" id="newPwd"
                                   class="form-control form-control-lg"
                                   placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                            <div class="d-none mt-2" id="strengthBar">
                                <div class="progress" style="height:4px">
                                    <div class="progress-bar" id="strengthFill" role="progressbar"></div>
                                </div>
                                <small id="strengthText" class="text-muted"></small>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium">Confirm New Password</label>
                            <input type="password" name="confirm_password"
                                   class="form-control form-control-lg" required>
                        </div>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-lock me-1"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
document.getElementById('newPwd').addEventListener('input', function () {
    const v = this.value;
    const bar = document.getElementById('strengthBar');
    bar.classList.toggle('d-none', v.length === 0);
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [['bg-danger','Weak'],['bg-warning','Fair'],['bg-info','Good'],['bg-success','Strong']];
    const [cls, label] = levels[Math.max(0, score - 1)];
    const fill = document.getElementById('strengthFill');
    fill.className = 'progress-bar ' + cls;
    fill.style.width = (score * 25) + '%';
    document.getElementById('strengthText').textContent = label;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
