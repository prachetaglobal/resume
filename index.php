<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();
if (Auth::check()) redirect(APP_URL . '/dashboard.php');

$pageTitle = APP_NAME . ' — Build ATS-Ready Resumes';
$bodyClass = 'landing-page d-flex flex-column min-vh-100';
include __DIR__ . '/includes/header.php';
?>

<section class="hero-section text-center py-5 flex-grow-1 d-flex align-items-center">
    <div class="container">
        <span class="badge bg-success mb-3 px-3 py-2 rounded-pill">
            <i class="bi bi-check-circle me-1"></i> ATS-Friendly Templates
        </span>
        <h1 class="display-4 fw-bold mb-3">Build Resumes That <span class="text-primary">Actually Get Read</span></h1>
        <p class="lead text-muted mx-auto mb-4" style="max-width:580px">
            8 modern templates, live preview, one-click PDF export — and every template is optimised to pass Applicant Tracking Systems.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap mb-5">
            <a href="<?= APP_URL ?>/register.php" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-rocket-takeoff me-2"></i>Get Started Free
            </a>
            <a href="<?= APP_URL ?>/login.php" class="btn btn-outline-secondary btn-lg px-4">Login</a>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $features = [
                ['bi-palette2','8 Modern Templates','Classic, Modern, Minimal, Sidebar, Executive, Tech & Creative.'],
                ['bi-robot','ATS Optimised','Semantic HTML, standard fonts, zero layout hacks — built to pass ATS scans.'],
                ['bi-sliders','Fully Customisable','Change colours, fonts, spacing and section order in real time.'],
                ['bi-filetype-pdf','One-Click PDF','High-quality PDF export that matches exactly what you see on screen.'],
                ['bi-eye','Live Preview','See every change instantly — no save required.'],
                ['bi-cloud-check','Auto Save','AJAX auto-save every 30 seconds. Never lose your work.'],
            ];
            foreach ($features as [$icon, $title, $desc]):
            ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm text-start p-3">
                    <div class="card-body">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 d-inline-block mb-3">
                            <i class="bi <?= $icon ?> fs-4"></i>
                        </div>
                        <h5 class="fw-semibold mb-1"><?= $title ?></h5>
                        <p class="text-muted small mb-0"><?= $desc ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
