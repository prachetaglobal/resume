<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();
Auth::redirectIfLoggedIn();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $result = Auth::login(
            $_POST['email'] ?? '',
            $_POST['password'] ?? '',
            !empty($_POST['remember'])
        );
        if ($result['ok']) {
            redirect(APP_URL . '/dashboard.php');
        }
        $error = $result['msg'];
    }
}

$pageTitle = 'Login — ' . APP_NAME;
$bodyClass = 'auth-page d-flex flex-column min-vh-100 bg-light';
$extraCss  = [ASSETS_URL . '/css/auth.css'];
include __DIR__ . '/includes/header.php';
?>

<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="auth-card card shadow-sm border-0 w-100" style="max-width:420px">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-file-earmark-person-fill fs-1 text-primary"></i>
                <h2 class="fw-bold mt-2 mb-1">Welcome back</h2>
                <p class="text-muted small">Log in to your <?= APP_NAME ?> account</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-medium">Email address</label>
                    <input type="email" name="email" class="form-control form-control-lg"
                           value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdInput" class="form-control form-control-lg" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Keep me logged in</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                </button>
            </form>

            <hr class="my-4">
            <p class="text-center text-muted small mb-0">
                Don't have an account? <a href="<?= APP_URL ?>/register.php" class="text-primary fw-medium">Sign up free</a>
            </p>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function(){
    const inp = document.getElementById('pwdInput');
    const icon = this.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
