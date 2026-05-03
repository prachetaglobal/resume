<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/SiteSettings.php';
require_once __DIR__ . '/../includes/ActivityLog.php';

Auth::boot();
Auth::requireLogin();

$adminUser = Auth::user();
if ($adminUser['role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Access denied.'];
    redirect(APP_URL . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Invalid token.'];
        redirect(APP_URL . '/admin/site-settings.php');
    }

    $fields = [
        'app_name'            => trim($_POST['app_name'] ?? ''),
        'app_tagline'         => trim($_POST['app_tagline'] ?? ''),
        'app_logo_url'        => trim($_POST['app_logo_url'] ?? ''),
        'primary_color'       => trim($_POST['primary_color'] ?? ''),
        'support_email'       => trim($_POST['support_email'] ?? ''),
        'allow_registration'  => isset($_POST['allow_registration'])  ? '1' : '0',
        'maintenance_mode'    => isset($_POST['maintenance_mode'])     ? '1' : '0',
    ];

    if (!$fields['app_name'])  $errors[] = 'App name is required.';
    if (!filter_var($fields['support_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid support email.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $fields['primary_color'])) $errors[] = 'Invalid color format (use #rrggbb).';

    if (empty($errors)) {
        SiteSettings::setMany($fields);
        SiteSettings::flushCache();
        ActivityLog::siteSettingsChanged($adminUser['id'], 'App name: ' . $fields['app_name']);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Site settings saved.'];
        redirect(APP_URL . '/admin/site-settings.php');
    }
}

$s = SiteSettings::all();

$adminTitle = 'Site Settings';
$adminPage  = 'site-settings';
include __DIR__ . '/layout_start.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-brush" style="color:#6366f1;font-size:1.1rem"></i>
    </div>
    <div>
        <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Site Settings</h5>
        <p class="mb-0" style="font-size:.8rem;color:#8b949e">Manage branding, logo, and global app behaviour.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-flash danger mb-4">
    <i class="bi bi-exclamation-circle"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <?= csrfField() ?>

    <!-- Branding -->
    <div class="admin-card mb-4">
        <div class="admin-card-header"><i class="bi bi-type" style="color:#6366f1"></i> Branding</div>
        <div style="padding:1.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

            <div>
                <label style="display:block;font-size:.8rem;color:#8b949e;margin-bottom:.4rem">App Name</label>
                <input type="text" name="app_name" value="<?= e($s['app_name'] ?? APP_NAME) ?>" class="admin-input" required>
                <p style="font-size:.72rem;color:#6e7681;margin-top:.3rem">Shown in browser tab title, navbar, and emails.</p>
            </div>

            <div>
                <label style="display:block;font-size:.8rem;color:#8b949e;margin-bottom:.4rem">Tagline</label>
                <input type="text" name="app_tagline" value="<?= e($s['app_tagline'] ?? '') ?>" class="admin-input">
            </div>

            <div style="grid-column:1/-1">
                <label style="display:block;font-size:.8rem;color:#8b949e;margin-bottom:.4rem">Logo URL</label>
                <input type="url" name="app_logo_url" value="<?= e($s['app_logo_url'] ?? '') ?>"
                       class="admin-input" placeholder="https://example.com/logo.png">
                <p style="font-size:.72rem;color:#6e7681;margin-top:.3rem">Leave blank to use the text logo. Recommended size: 140×36 px.</p>
                <?php if (!empty($s['app_logo_url'])): ?>
                <img src="<?= e($s['app_logo_url']) ?>" alt="Current logo"
                     style="margin-top:.75rem;max-height:40px;border-radius:6px;border:1px solid #21262d;padding:4px;background:#0d1117">
                <?php endif; ?>
            </div>

            <div>
                <label style="display:block;font-size:.8rem;color:#8b949e;margin-bottom:.4rem">Primary Colour</label>
                <div style="display:flex;gap:.75rem;align-items:center">
                    <input type="color" name="primary_color" id="primaryColor"
                           value="<?= e($s['primary_color'] ?? '#6366f1') ?>"
                           style="width:48px;height:38px;padding:2px;border-radius:8px;border:1px solid #30363d;background:#0d1117;cursor:pointer">
                    <input type="text" id="primaryColorText"
                           value="<?= e($s['primary_color'] ?? '#6366f1') ?>"
                           class="admin-input" style="width:110px;font-family:monospace"
                           oninput="document.getElementById('primaryColor').value=this.value">
                </div>
            </div>

            <div>
                <label style="display:block;font-size:.8rem;color:#8b949e;margin-bottom:.4rem">Support Email</label>
                <input type="email" name="support_email" value="<?= e($s['support_email'] ?? '') ?>" class="admin-input">
            </div>
        </div>
    </div>

    <!-- Feature flags -->
    <div class="admin-card mb-4">
        <div class="admin-card-header"><i class="bi bi-toggles" style="color:#6366f1"></i> Feature Flags</div>
        <div style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem">

            <label style="display:flex;align-items:flex-start;gap:.85rem;cursor:pointer">
                <div class="form-check form-switch m-0" style="padding:0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="allow_registration" id="allow_reg"
                           <?= ($s['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?>
                           style="width:2.5em;height:1.25em">
                </div>
                <div>
                    <div style="font-size:.875rem;font-weight:600;color:#f0f6fc">Allow New Registrations</div>
                    <div style="font-size:.78rem;color:#8b949e">When off, the Register page returns a "closed" message and no new accounts can be created.</div>
                </div>
            </label>

            <label style="display:flex;align-items:flex-start;gap:.85rem;cursor:pointer">
                <div class="form-check form-switch m-0" style="padding:0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="maintenance_mode" id="maint_mode"
                           <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>
                           style="width:2.5em;height:1.25em">
                </div>
                <div>
                    <div style="font-size:.875rem;font-weight:600;color:#f0f6fc">Maintenance Mode</div>
                    <div style="font-size:.78rem;color:#8b949e">Shows a "down for maintenance" page to all non-admin visitors.</div>
                </div>
            </label>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:1rem">
        <button type="submit"
                style="background:#6366f1;color:#fff;border:none;padding:.6rem 1.6rem;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer">
            <i class="bi bi-check-lg me-1"></i>Save Settings
        </button>
        <span style="font-size:.78rem;color:#8b949e">
            <i class="bi bi-info-circle me-1"></i>Changes take effect on the next page load.
        </span>
    </div>
</form>

<script>
document.getElementById('primaryColor').addEventListener('input', function(){
    document.getElementById('primaryColorText').value = this.value;
});
</script>

<?php include __DIR__ . '/layout_end.php'; ?>
