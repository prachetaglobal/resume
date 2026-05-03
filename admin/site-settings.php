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
        'app_name'           => trim($_POST['app_name']    ?? ''),
        'app_tagline'        => trim($_POST['app_tagline'] ?? ''),
        'app_logo_url'       => trim($_POST['app_logo_url'] ?? ''),
        'primary_color'      => trim($_POST['primary_color'] ?? ''),
        'support_email'      => trim($_POST['support_email'] ?? ''),
        'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
        'maintenance_mode'   => isset($_POST['maintenance_mode'])   ? '1' : '0',
    ];

    if (!$fields['app_name'])
        $errors[] = 'App name is required.';
    if ($fields['support_email'] && !filter_var($fields['support_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid support email address.';
    if ($fields['primary_color'] && !preg_match('/^#[0-9a-fA-F]{6}$/', $fields['primary_color']))
        $errors[] = 'Primary colour must be a valid hex code, e.g. #6366f1.';

    if (empty($errors)) {
        SiteSettings::setMany($fields);
        SiteSettings::flushCache();
        ActivityLog::siteSettingsChanged($adminUser['id'], 'Updated: ' . implode(', ', array_keys($fields)));
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Site settings saved successfully.'];
        redirect(APP_URL . '/admin/site-settings.php');
    }
}

$s = SiteSettings::all();

// Helper to get a setting value, falling back to a default
$sv = fn(string $key, string $default = '') => $s[$key] ?? $default;

$adminTitle = 'Site Settings';
$adminPage  = 'site-settings';
include __DIR__ . '/layout_start.php';
?>

<style>
/* ── Site-settings specific styles ─────────────────────────────── */
.ss-section {
    background: #161b22;
    border: 1px solid #21262d;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.ss-section-header {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .9rem 1.4rem;
    border-bottom: 1px solid #21262d;
    font-size: .875rem;
    font-weight: 600;
    color: #c9d1d9;
}
.ss-section-header i { color: #6366f1; font-size: 1rem; }
.ss-body { padding: 1.5rem 1.4rem; }

.ss-field { margin-bottom: 1.25rem; }
.ss-field:last-child { margin-bottom: 0; }
.ss-label {
    display: block;
    font-size: .78rem;
    font-weight: 600;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: .45rem;
}
.ss-hint {
    font-size: .73rem;
    color: #6e7681;
    margin-top: .3rem;
    line-height: 1.4;
}
.ss-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}
@media (max-width: 640px) {
    .ss-grid-2 { grid-template-columns: 1fr; }
}

/* Colour picker row */
.ss-color-row {
    display: flex;
    align-items: center;
    gap: .75rem;
}
.ss-color-swatch {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    border: 1px solid #30363d;
    background: #0d1117;
    cursor: pointer;
    padding: 3px;
    flex-shrink: 0;
}
.ss-color-hex {
    width: 120px;
    font-family: monospace;
}

/* Toggle rows */
.ss-toggle-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid #1c2128;
}
.ss-toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
.ss-toggle-row:first-child { padding-top: 0; }
.ss-toggle-switch { flex-shrink: 0; padding-top: 2px; }
.ss-toggle-label  { font-size: .875rem; font-weight: 600; color: #f0f6fc; margin-bottom: .2rem; }
.ss-toggle-desc   { font-size: .78rem; color: #8b949e; line-height: 1.45; }

/* Save bar */
.ss-save-bar {
    background: #161b22;
    border: 1px solid #21262d;
    border-radius: 12px;
    padding: 1.1rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.ss-save-btn {
    background: #6366f1;
    color: #fff;
    border: none;
    padding: .6rem 1.75rem;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.ss-save-btn:hover { background: #4f46e5; }
.ss-save-note {
    font-size: .78rem;
    color: #8b949e;
    display: flex;
    align-items: center;
    gap: .35rem;
}
</style>

<!-- Page header -->
<div class="d-flex align-items-center gap-3 mb-4">
    <div style="width:40px;height:40px;background:#6366f122;border-radius:10px;
                display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi bi-brush" style="color:#6366f1;font-size:1.1rem"></i>
    </div>
    <div>
        <h5 class="mb-0 fw-bold" style="color:#f0f6fc">Site Settings</h5>
        <p class="mb-0" style="font-size:.8rem;color:#8b949e">
            Manage app branding, logo, colours, and global feature flags.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-flash danger mb-4">
    <i class="bi bi-exclamation-circle" style="flex-shrink:0"></i>
    <div><?= implode('<br>', array_map('e', $errors)) ?></div>
</div>
<?php endif; ?>

<form method="POST" novalidate>
    <?= csrfField() ?>

    <!-- ── Branding ─────────────────────────────────────────────────── -->
    <div class="ss-section">
        <div class="ss-section-header">
            <i class="bi bi-type-bold"></i> Branding
        </div>
        <div class="ss-body">

            <!-- Row 1: App Name + Tagline -->
            <div class="ss-grid-2">
                <div class="ss-field">
                    <label class="ss-label" for="app_name">App Name</label>
                    <input type="text" id="app_name" name="app_name"
                           class="admin-input"
                           value="<?= e($sv('app_name', APP_NAME)) ?>" required>
                    <p class="ss-hint">Shown in browser tab, navbar, and emails.</p>
                </div>
                <div class="ss-field">
                    <label class="ss-label" for="app_tagline">Tagline</label>
                    <input type="text" id="app_tagline" name="app_tagline"
                           class="admin-input"
                           value="<?= e($sv('app_tagline')) ?>"
                           placeholder="Build ATS-Ready Resumes in Minutes">
                    <p class="ss-hint">Displayed on the landing page hero section.</p>
                </div>
            </div>

            <!-- Row 2: Support Email + Primary Colour -->
            <div class="ss-grid-2">
                <div class="ss-field">
                    <label class="ss-label" for="support_email">Support Email</label>
                    <input type="email" id="support_email" name="support_email"
                           class="admin-input"
                           value="<?= e($sv('support_email')) ?>"
                           placeholder="support@example.com">
                    <p class="ss-hint">Shown in contact / footer sections.</p>
                </div>
                <div class="ss-field">
                    <label class="ss-label" for="primary_color">Primary Colour</label>
                    <div class="ss-color-row">
                        <input type="color" id="primaryColorSwatch"
                               class="ss-color-swatch"
                               value="<?= e($sv('primary_color', '#6366f1')) ?>"
                               title="Pick a colour">
                        <input type="text" id="primary_color" name="primary_color"
                               class="admin-input ss-color-hex"
                               value="<?= e($sv('primary_color', '#6366f1')) ?>"
                               maxlength="7" placeholder="#6366f1"
                               pattern="^#[0-9a-fA-F]{6}$">
                        <div style="width:32px;height:32px;border-radius:8px;border:1px solid #30363d;flex-shrink:0"
                             id="colorPreview"></div>
                    </div>
                    <p class="ss-hint">Used as accent colour for buttons and highlights (6-digit hex).</p>
                </div>
            </div>

            <!-- Row 3: Logo URL (full-width) -->
            <div class="ss-field" style="margin-bottom:0">
                <label class="ss-label" for="app_logo_url">Logo URL</label>
                <input type="url" id="app_logo_url" name="app_logo_url"
                       class="admin-input"
                       value="<?= e($sv('app_logo_url')) ?>"
                       placeholder="https://example.com/logo.png">
                <p class="ss-hint">Leave blank to display the text logo. Recommended: 140 × 36 px PNG on a transparent background.</p>

                <?php if ($sv('app_logo_url')): ?>
                <div style="margin-top:.85rem;display:flex;align-items:center;gap:.75rem">
                    <img src="<?= e($sv('app_logo_url')) ?>"
                         alt="Current logo"
                         style="max-height:36px;max-width:200px;border-radius:6px;
                                border:1px solid #21262d;padding:4px;background:#0d1117">
                    <span style="font-size:.75rem;color:#8b949e">Current logo</span>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ── Feature Flags ────────────────────────────────────────────── -->
    <div class="ss-section">
        <div class="ss-section-header">
            <i class="bi bi-toggles"></i> Feature Flags
        </div>
        <div class="ss-body" style="padding-top:1.1rem;padding-bottom:1.1rem">

            <!-- Allow Registration -->
            <div class="ss-toggle-row">
                <div class="ss-toggle-switch">
                    <div class="form-check form-switch m-0" style="padding-left:0">
                        <input class="form-check-input m-0" type="checkbox" role="switch"
                               id="allow_registration" name="allow_registration"
                               <?= $sv('allow_registration', '1') === '1' ? 'checked' : '' ?>
                               style="width:2.4em;height:1.2em;cursor:pointer">
                    </div>
                </div>
                <div>
                    <label class="ss-toggle-label" for="allow_registration">Allow New Registrations</label>
                    <p class="ss-toggle-desc">
                        When disabled, the Register page shows a "closed" message and no new accounts can be created.
                        Existing users are unaffected.
                    </p>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="ss-toggle-row">
                <div class="ss-toggle-switch">
                    <div class="form-check form-switch m-0" style="padding-left:0">
                        <input class="form-check-input m-0" type="checkbox" role="switch"
                               id="maintenance_mode" name="maintenance_mode"
                               <?= $sv('maintenance_mode', '0') === '1' ? 'checked' : '' ?>
                               style="width:2.4em;height:1.2em;cursor:pointer">
                    </div>
                </div>
                <div>
                    <label class="ss-toggle-label" for="maintenance_mode">
                        Maintenance Mode
                        <?php if ($sv('maintenance_mode', '0') === '1'): ?>
                        <span style="display:inline-block;background:#7f1d1d;color:#f87171;
                                     font-size:.65rem;padding:.1rem .5rem;border-radius:999px;
                                     margin-left:.4rem;font-weight:600;vertical-align:middle">ACTIVE</span>
                        <?php endif; ?>
                    </label>
                    <p class="ss-toggle-desc">
                        Displays a "down for maintenance" page to all non-admin visitors.
                        Admin accounts bypass this restriction automatically.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Save bar ──────────────────────────────────────────────────── -->
    <div class="ss-save-bar">
        <button type="submit" class="ss-save-btn">
            <i class="bi bi-check-lg"></i> Save Settings
        </button>
        <span class="ss-save-note">
            <i class="bi bi-info-circle"></i>
            Changes take effect on the next page load for all visitors.
        </span>
    </div>

</form>

<script>
(function () {
    const swatch  = document.getElementById('primaryColorSwatch');
    const hexInput = document.getElementById('primary_color');
    const preview  = document.getElementById('colorPreview');

    function applyColor(hex) {
        if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
            preview.style.background = hex;
            swatch.value  = hex;
            hexInput.value = hex;
        }
    }

    // Init preview
    applyColor(hexInput.value);

    // Swatch → hex text + preview
    swatch.addEventListener('input', () => applyColor(swatch.value));

    // Hex text → swatch + preview
    hexInput.addEventListener('input', () => {
        const v = hexInput.value.trim();
        applyColor(v.startsWith('#') ? v : '#' + v);
    });
})();
</script>

<?php include __DIR__ . '/layout_end.php'; ?>
