<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Resume.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/PlanLimits.php';
require_once __DIR__ . '/includes/ActivityLog.php';

Auth::boot();
Auth::requireLogin();

$user      = Auth::user();
$userId    = Auth::id();
$resumes   = Resume::getAllByUser($userId);
$templates = getTemplates();
$maxResumes = PlanLimits::maxResumes($user['plan']); // -1 = unlimited
$atLimit    = $maxResumes !== -1 && count($resumes) >= $maxResumes;

// Handle create new resume via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        jsonResponse(['ok' => false, 'msg' => 'Invalid token'], 403);
    }
    if ($_POST['action'] === 'create') {
        $title      = trim($_POST['title'] ?? 'My Resume');
        $templateId = (int)($_POST['template_id'] ?? 1);
        $id         = Resume::create($userId, $title ?: 'My Resume', $templateId);
        ActivityLog::resumeCreated($userId, $id, $title ?: 'My Resume');
        redirect(APP_URL . '/editor.php?id=' . $id);
    }
    if ($_POST['action'] === 'delete') {
        $id    = (int)($_POST['resume_id'] ?? 0);
        $rdata = Database::fetchOne('SELECT title FROM resumes WHERE id = ? AND user_id = ?', [$id, $userId]);
        Resume::delete($id, $userId);
        ActivityLog::resumeDeleted($userId, $id, $rdata['title'] ?? '');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Resume deleted.'];
        redirect(APP_URL . '/dashboard.php');
    }
}

$pageTitle = 'Dashboard — ' . APP_NAME;
$bodyClass = 'd-flex flex-column min-vh-100';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-4 flex-grow-1">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-0">My Resumes</h4>
            <p class="text-muted small mb-0">
                <?= count($resumes) ?> resume<?= count($resumes) !== 1 ? 's' : '' ?> saved
                <?php if ($maxResumes !== -1): ?>
                — <span class="text-warning"><i class="bi bi-star-fill me-1"></i><?= ucfirst($user['plan']) ?> plan: <?= $maxResumes ?> max</span>
                <?php endif; ?>
            </p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newResumeModal"
            <?= $atLimit ? 'disabled title="You\'ve reached your plan\'s resume limit"' : '' ?>>
            <i class="bi bi-plus-lg me-1"></i> New Resume
        </button>
    </div>

    <?php if (empty($resumes)): ?>
    <div class="text-center py-5">
        <i class="bi bi-file-earmark-plus display-3 text-muted"></i>
        <h5 class="mt-3 fw-semibold">No resumes yet</h5>
        <p class="text-muted">Create your first resume — it takes less than 2 minutes.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newResumeModal">
            <i class="bi bi-plus-lg me-1"></i> Create Resume
        </button>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($resumes as $r): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0 resume-card">
                <div class="resume-thumb d-flex align-items-center justify-content-center bg-light rounded-top">
                    <i class="bi bi-file-earmark-person display-4 text-primary opacity-50"></i>
                </div>
                <div class="card-body">
                    <h6 class="fw-semibold mb-1 text-truncate"><?= e($r['title']) ?></h6>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-layout-text-sidebar-reverse me-1"></i><?= e($r['template_name']) ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-clock me-1"></i><?= timeAgo($r['updated_at']) ?>
                    </p>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0 d-flex gap-2">
                    <a href="<?= APP_URL ?>/editor.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                    </a>
                    <a href="<?= APP_URL ?>/preview.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Preview">
                        <i class="bi bi-eye"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger" title="Delete"
                        onclick="confirmDelete(<?= $r['id'] ?>, '<?= e(addslashes($r['title'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- New Resume Modal -->
<div class="modal fade" id="newResumeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Create New Resume</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label fw-medium">Resume Title</label>
                        <input type="text" name="title" class="form-control form-control-lg"
                               placeholder="e.g. Software Engineer Resume" value="My Resume">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-medium">Choose Template</label>
                    </div>
                    <div class="row g-3" id="templatePicker">
                        <?php foreach ($templates as $t): ?>
                        <div class="col-6 col-md-3">
                            <label class="template-option d-block cursor-pointer">
                                <input type="radio" name="template_id" value="<?= $t['id'] ?>"
                                    class="d-none" <?= $t['sort_order'] === 1 ? 'checked' : '' ?>>
                                <div class="template-card card border-2 text-center p-3 h-100">
                                    <i class="bi bi-file-earmark-text fs-2 text-primary mb-2"></i>
                                    <div class="fw-medium small"><?= e($t['name']) ?></div>
                                    <?php if ($t['is_ats_friendly']): ?>
                                    <span class="badge bg-success-subtle text-success mt-1" style="font-size:.65rem">ATS Safe</span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-arrow-right-circle me-1"></i>Create & Edit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirm modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="resume_id" id="deleteId">
                <div class="modal-body text-center py-4">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
                    <h5 class="mt-3 fw-bold">Delete Resume?</h5>
                    <p class="text-muted" id="deleteMsg"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Template picker highlight
document.querySelectorAll('#templatePicker input[type=radio]').forEach(r => {
    r.addEventListener('change', function(){
        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('border-primary'));
        this.closest('.template-option').querySelector('.template-card').classList.add('border-primary');
    });
});
document.querySelector('#templatePicker input:checked')
    ?.closest('.template-option')?.querySelector('.template-card')?.classList.add('border-primary');

function confirmDelete(id, title) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMsg').textContent = 'This will permanently delete "' + title + '".';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
