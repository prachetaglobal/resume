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

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token.'];
        redirect(APP_URL . '/admin/templates.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $slug    = trim($_POST['slug'] ?? '');
        $cat     = $_POST['category'] ?? 'classic';
        $ats     = (int)isset($_POST['is_ats_friendly']);
        $active  = (int)isset($_POST['is_active']);
        $order   = (int)($_POST['sort_order'] ?? 0);

        if (!$name || !$slug) {
            $errors[] = 'Name and Slug are required.';
        } else {
            // Check if folder exists
            if (!is_dir(TEMPLATES_PATH . $slug)) {
                $errors[] = "Template folder '/templates/$slug' not found.";
            }
        }

        if (empty($errors)) {
            if ($id > 0) {
                Database::query(
                    'UPDATE templates SET name=?, slug=?, category=?, is_ats_friendly=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?',
                    [$name, $slug, $cat, $ats, $active, $order, $id]
                );
                ActivityLog::log($adminUser['id'], 'template_updated', "Updated template: $name ($slug)");
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Template updated.'];
            } else {
                Database::insert(
                    'INSERT INTO templates (name, slug, category, is_ats_friendly, is_active, sort_order) VALUES (?,?,?,?,?,?)',
                    [$name, $slug, $cat, $ats, $active, $order]
                );
                ActivityLog::log($adminUser['id'], 'template_created', "Registered new template: $name ($slug)");
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Template registered.'];
            }
            redirect(APP_URL . '/admin/templates.php');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $tpl = Database::fetchOne('SELECT name, slug FROM templates WHERE id = ?', [$id]);
        if ($tpl) {
            Database::query('DELETE FROM templates WHERE id = ?', [$id]);
            ActivityLog::log($adminUser['id'], 'template_deleted', "Deleted template: {$tpl['name']} ({$tpl['slug']})");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Template removed from system.'];
        }
        redirect(APP_URL . '/admin/templates.php');
    }
}

$templates = Database::fetchAll('SELECT * FROM templates ORDER BY sort_order ASC, name ASC');

$adminTitle = 'Manage Templates';
$adminPage  = 'templates';
include __DIR__ . '/layout_start.php';
?>

<div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-3">
        <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-layout-text-sidebar" style="color:#6366f1;font-size:1.1rem"></i>
        </div>
        <div>
            <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Templates</h5>
            <p class="mb-0" style="font-size:.8rem;color:#8b949e"><?= count($templates) ?> templates registered</p>
        </div>
    </div>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tplModal" onclick="clearForm()">
        <i class="bi bi-plus-lg me-1"></i>Register New
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-flash danger mb-4">
    <i class="bi bi-exclamation-circle"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<div class="admin-card mb-4">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Template Name</th>
                    <th>Slug / Folder</th>
                    <th>Category</th>
                    <th>ATS</th>
                    <th>Status</th>
                    <th style="text-align:center">Order</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#f0f6fc"><?= e($t['name']) ?></div>
                    </td>
                    <td><code style="color:#8b949e">/templates/<?= e($t['slug']) ?>/</code></td>
                    <td><span class="plan-badge plan-free"><?= ucfirst($t['category']) ?></span></td>
                    <td>
                        <?php if ($t['is_ats_friendly']): ?>
                        <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i>ATS</span>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['is_active']): ?>
                        <span class="text-success small fw-bold">Active</span>
                        <?php else: ?>
                        <span class="text-danger small fw-bold">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;color:#8b949e"><?= $t['sort_order'] ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <button class="btn btn-xs btn-outline-secondary" style="padding:.2rem .5rem"
                                onclick='editTpl(<?= json_encode($t) ?>)'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this template from the system?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card p-4">
    <h6 style="color:#f0f6fc"><i class="bi bi-info-circle me-2 text-primary"></i>How to add a new template manually?</h6>
    <ol style="font-size:.85rem;color:#8b949e;line-height:1.6" class="mt-3">
        <li>Create a new directory in <code>/var/www/html/resume/templates/[your-slug]</code></li>
        <li>Inside that folder, create <code>template.php</code> (The HTML structure)</li>
        <li>Create <code>style.css</code> (The styling)</li>
        <li>Use the "Register New" button above to link it to the database.</li>
    </ol>
</div>

<!-- Template Modal -->
<div class="modal fade" id="tplModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="background:#161b22;border:1px solid #30363d!important">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold text-white" id="modalTitle">Register Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="tplId" value="0">
                    
                    <div class="mb-3">
                        <label class="ss-label">Template Name</label>
                        <input type="text" name="name" id="tplName" class="admin-input" placeholder="e.g. Modern Executive" required>
                    </div>
                    <div class="mb-3">
                        <label class="ss-label">Slug / Folder Name</label>
                        <input type="text" name="slug" id="tplSlug" class="admin-input" placeholder="e.g. modern-executive" required>
                        <p class="ss-hint">Must match the folder name in /templates/</p>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="ss-label">Category</label>
                            <select name="category" id="tplCat" class="admin-input">
                                <option value="classic">Classic</option>
                                <option value="modern">Modern</option>
                                <option value="creative">Creative</option>
                                <option value="tech">Tech</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="ss-label">Sort Order</label>
                            <input type="number" name="sort_order" id="tplOrder" class="admin-input" value="0">
                        </div>
                    </div>
                    <div class="d-flex gap-4">
                        <label class="d-flex align-items-center gap-2 cursor-pointer text-white-50 small">
                            <input type="checkbox" name="is_ats_friendly" id="tplAts" style="accent-color:#6366f1">
                            ATS Friendly
                        </label>
                        <label class="d-flex align-items-center gap-2 cursor-pointer text-white-50 small">
                            <input type="checkbox" name="is_active" id="tplActive" checked style="accent-color:#6366f1">
                            Active / Visible
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('modalTitle').innerText = 'Register Template';
    document.getElementById('tplId').value = '0';
    document.getElementById('tplName').value = '';
    document.getElementById('tplSlug').value = '';
    document.getElementById('tplOrder').value = '0';
    document.getElementById('tplAts').checked = false;
    document.getElementById('tplActive').checked = true;
}
function editTpl(t) {
    document.getElementById('modalTitle').innerText = 'Edit Template';
    document.getElementById('tplId').value = t.id;
    document.getElementById('tplName').value = t.name;
    document.getElementById('tplSlug').value = t.slug;
    document.getElementById('tplCat').value = t.category;
    document.getElementById('tplOrder').value = t.sort_order;
    document.getElementById('tplAts').checked = parseInt(t.is_ats_friendly) === 1;
    document.getElementById('tplActive').checked = parseInt(t.is_active) === 1;
    new bootstrap.Modal(document.getElementById('tplModal')).show();
}
</script>

<?php include __DIR__ . '/layout_end.php'; ?>
