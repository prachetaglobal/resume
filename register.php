<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();
Auth::redirectIfLoggedIn();

$error = '';
$data  = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $data['name']  = trim($_POST['name'] ?? '');
        $data['email'] = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirm       = $_POST['confirm_password'] ?? '';

        if (strlen($data['name']) < 2) {
            $error = 'Please enter your full name.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif ($pwdError = validatePassword($password)) {
            $error = $pwdError;
        } else {
            $result = Auth::register($data['name'], $data['email'], $password);
            if ($result['ok']) {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Welcome! Your account is ready.'];
                redirect(APP_URL . '/dashboard.php');
            }
            $error = $result['msg'];
        }
    }
}

$pageTitle = 'Create Account — ' . APP_NAME;
$bodyClass = 'auth-page d-flex flex-column min-vh-100 bg-light';
$extraCss  = [ASSETS_URL . '/css/auth.css'];
include __DIR__ . '/includes/header.php';
?>

<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="auth-card card shadow-sm border-0 w-100" style="max-width:440px">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-file-earmark-person-fill fs-1 text-primary"></i>
                <h2 class="fw-bold mt-2 mb-1">Create your account</h2>
                <p class="text-muted small">Free forever — no credit card required</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-medium">Full Name</label>
                    <input type="text" name="name" class="form-control form-control-lg"
                           value="<?= e($data['name']) ?>" placeholder="Jane Smith" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Email address</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           value="<?= e($data['email']) ?>" placeholder="you@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdInput" class="form-control form-control-lg"
                               placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-2 d-none" id="strengthBar">
                        <div class="progress" style="height:4px">
                            <div class="progress-bar" id="strengthFill" role="progressbar"></div>
                        </div>
                        <small id="strengthText" class="text-muted"></small>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-medium">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control form-control-lg" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg fw-semibold">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                Already have an account? <a href="<?= APP_URL ?>/login.php" class="text-primary fw-medium">Log in</a>
            </p>
        </div>
    </div>
</div>

<script>
const pwd = document.getElementById('pwdInput');
const bar = document.getElementById('strengthFill');
const txt = document.getElementById('strengthText');

document.getElementById('togglePwd').addEventListener('click', function(){
    const icon = this.querySelector('i');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
    icon.className = pwd.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

pwd.addEventListener('input', function(){
    const v = this.value;
    document.getElementById('strengthBar').classList.toggle('d-none', v.length === 0);
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [['bg-danger','Weak'],['bg-warning','Fair'],['bg-info','Good'],['bg-success','Strong']];
    const [cls, label] = levels[Math.max(0, score - 1)];
    bar.className = 'progress-bar ' + cls;
    bar.style.width = (score * 25) + '%';
    txt.textContent = label;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
