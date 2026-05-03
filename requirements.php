<?php
/**
 * ResumeCraft — Requirements Checker
 * Run this script before (or after) installation to verify the environment.
 * Delete or restrict access to this file in production.
 */

// ── Checks definition ────────────────────────────────────────────────────────

$phpMinVersion = '8.0.0';

$requiredExtensions = [
    'pdo'       => 'Database abstraction layer (PDO)',
    'pdo_mysql' => 'MySQL driver for PDO',
    'mbstring'  => 'Multi-byte string handling (required by mPDF)',
    'gd'        => 'Image processing (required by mPDF for PDF export)',
    'xml'       => 'XML parsing (required by mPDF)',
    'zip'       => 'ZIP archive support (required by mPDF font handling)',
    'json'      => 'JSON encode/decode',
    'curl'      => 'HTTP client (Composer & remote assets)',
    'session'   => 'Session management',
    'fileinfo'  => 'File type detection (avatar uploads)',
];

$writablePaths = [
    __DIR__ . '/uploads'        => 'uploads/',
    sys_get_temp_dir()          => 'System temp dir (used by mPDF)',
];

$composerPaths = [
    __DIR__ . '/vendor/autoload.php' => 'vendor/autoload.php (run: composer install)',
    __DIR__ . '/vendor/mpdf/mpdf'    => 'vendor/mpdf/mpdf (PDF library)',
    __DIR__ . '/vendor/ramsey/uuid'  => 'vendor/ramsey/uuid (UUID generation)',
];

// DB tables required after migration
$requiredTables = [
    'plan_settings'     => 'Admin-configurable plan limits (run: migration_plan_settings.sql)',
    'resume_export_log' => 'PDF export audit log / rate-limiting (run: migration_plan_settings.sql)',
];

// Admin pages
$adminPaths = [
    __DIR__ . '/admin/index.php'         => 'admin/index.php (admin dashboard)',
    __DIR__ . '/admin/plan-settings.php' => 'admin/plan-settings.php (plan settings panel)',
];

// ── Evaluate ─────────────────────────────────────────────────────────────────

$pass = true;

function check(bool $ok): string {
    return $ok ? '✔' : '✘';
}

function rowClass(bool $ok): string {
    return $ok ? 'pass' : 'fail';
}

$phpOk = version_compare(PHP_VERSION, $phpMinVersion, '>=');
$pass  = $pass && $phpOk;

$extResults = [];
foreach ($requiredExtensions as $ext => $label) {
    $ok              = extension_loaded($ext);
    $extResults[$ext] = ['ok' => $ok, 'label' => $label];
    $pass            = $pass && $ok;
}

$pathResults = [];
foreach ($writablePaths as $absPath => $label) {
    $exists   = file_exists($absPath) || @mkdir($absPath, 0775, true);
    $writable = $exists && is_writable($absPath);
    $pathResults[$label] = ['ok' => $writable, 'path' => $absPath];
    $pass                = $pass && $writable;
}

$composerResults = [];
foreach ($composerPaths as $absPath => $label) {
    $ok = file_exists($absPath);
    $composerResults[$label] = ['ok' => $ok, 'path' => $absPath];
    $pass                    = $pass && $ok;
}

// DB table check — try to connect using config/database.php
$tableResults = [];
try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/includes/Database.php';
    $pdo = Database::get();
    foreach ($requiredTables as $table => $label) {
        $exists = (bool)$pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        $tableResults[$table] = ['ok' => $exists, 'label' => $label];
        $pass                 = $pass && $exists;
    }
} catch (Throwable $e) {
    foreach ($requiredTables as $table => $label) {
        $tableResults[$table] = ['ok' => false, 'label' => $label . ' (DB connection failed: ' . $e->getMessage() . ')'];
        $pass = false;
    }
}

// Admin files
$adminResults = [];
foreach ($adminPaths as $absPath => $label) {
    $ok = file_exists($absPath);
    $adminResults[$label] = ['ok' => $ok, 'path' => $absPath];
    $pass                 = $pass && $ok;
}

$overallClass = $pass ? 'overall-pass' : 'overall-fail';
$overallText  = $pass
    ? '✔ All checks passed — your environment is ready!'
    : '✘ One or more checks failed. Fix the issues below before running the app.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ResumeCraft — Requirements Checker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f1117;
            color: #e2e8f0;
            min-height: 100vh;
            padding: 2.5rem 1rem;
        }

        .container {
            max-width: 780px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: .4rem;
        }

        header p {
            color: #94a3b8;
            font-size: .95rem;
        }

        .overall {
            border-radius: 12px;
            padding: 1.1rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .overall-pass { background: #052e16; border: 1px solid #166534; color: #4ade80; }
        .overall-fail { background: #2d0a0a; border: 1px solid #7f1d1d; color: #f87171; }

        .card {
            background: #1e2330;
            border: 1px solid #2d3748;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.75rem;
        }

        .card-header {
            padding: .9rem 1.4rem;
            font-weight: 600;
            font-size: .95rem;
            background: #252d3d;
            border-bottom: 1px solid #2d3748;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .card-header span.icon { font-size: 1.1rem; }

        table { width: 100%; border-collapse: collapse; }

        tr + tr { border-top: 1px solid #232b3a; }

        td {
            padding: .7rem 1.4rem;
            font-size: .875rem;
            vertical-align: middle;
        }

        td:first-child { width: 2.2rem; font-size: 1rem; }

        tr.pass td:first-child { color: #4ade80; }
        tr.fail td:first-child { color: #f87171; }

        .label { color: #e2e8f0; font-weight: 500; }
        .meta  { color: #64748b; font-size: .8rem; margin-top: .15rem; }

        .badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            margin-left: auto;
        }

        tr.pass .badge { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        tr.fail .badge { background: #2d0a0a; color: #f87171; border: 1px solid #7f1d1d; }

        .fix-hint {
            background: #1a1025;
            border: 1px solid #44337a;
            border-radius: 8px;
            padding: .8rem 1.2rem;
            font-size: .8rem;
            color: #c4b5fd;
            margin: 1rem 1.4rem 1.2rem;
        }

        .fix-hint code {
            background: #2d1f52;
            padding: .1rem .35rem;
            border-radius: 4px;
            font-family: monospace;
        }

        footer {
            text-align: center;
            color: #475569;
            font-size: .8rem;
            margin-top: 2rem;
        }

        footer strong { color: #6366f1; }
    </style>
</head>
<body>
<div class="container">

    <header>
        <h1>ResumeCraft</h1>
        <p>Environment Requirements Checker &mdash; PHP <?= PHP_VERSION ?></p>
    </header>

    <div class="overall <?= $overallClass ?>">
        <?= $overallText ?>
    </div>

    <!-- PHP Version -->
    <div class="card">
        <div class="card-header"><span class="icon">🐘</span> PHP Version</div>
        <table>
            <tr class="<?= rowClass($phpOk) ?>">
                <td><?= check($phpOk) ?></td>
                <td>
                    <div class="label">PHP &ge; <?= $phpMinVersion ?></div>
                    <div class="meta">Detected: PHP <?= PHP_VERSION ?></div>
                </td>
                <td><span class="badge"><?= $phpOk ? 'OK' : 'FAIL' ?></span></td>
            </tr>
        </table>
        <?php if (!$phpOk): ?>
        <div class="fix-hint">
            Upgrade PHP to 8.0 or later. On Debian/Ubuntu: <code>sudo apt install php8.4</code>
        </div>
        <?php endif; ?>
    </div>

    <!-- Extensions -->
    <div class="card">
        <div class="card-header"><span class="icon">🔌</span> PHP Extensions</div>
        <table>
            <?php foreach ($extResults as $ext => $info): ?>
            <tr class="<?= rowClass($info['ok']) ?>">
                <td><?= check($info['ok']) ?></td>
                <td>
                    <div class="label">ext-<?= htmlspecialchars($ext) ?></div>
                    <div class="meta"><?= htmlspecialchars($info['label']) ?></div>
                </td>
                <td><span class="badge"><?= $info['ok'] ? 'OK' : 'MISSING' ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        $missing = array_keys(array_filter($extResults, fn($r) => !$r['ok']));
        if ($missing):
        ?>
        <div class="fix-hint">
            Install missing extensions: <code>sudo apt install <?= implode(' ', array_map(fn($e) => "php8.4-$e", $missing)) ?></code>
            &mdash; then restart Apache: <code>sudo systemctl restart apache2</code>
        </div>
        <?php endif; ?>
    </div>

    <!-- Composer / Vendor -->
    <div class="card">
        <div class="card-header"><span class="icon">📦</span> Composer &amp; Dependencies</div>
        <table>
            <?php foreach ($composerResults as $label => $info): ?>
            <tr class="<?= rowClass($info['ok']) ?>">
                <td><?= check($info['ok']) ?></td>
                <td>
                    <div class="label"><?= htmlspecialchars($label) ?></div>
                    <div class="meta"><?= htmlspecialchars($info['path']) ?></div>
                </td>
                <td><span class="badge"><?= $info['ok'] ? 'FOUND' : 'MISSING' ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (!$composerResults[array_key_first($composerResults)]['ok']): ?>
        <div class="fix-hint">
            Run <code>composer install</code> from the project root to install all dependencies.
            If Composer is not installed: <code>sudo apt install composer</code>
        </div>
        <?php endif; ?>
    </div>

    <!-- Writable Paths -->
    <div class="card">
        <div class="card-header"><span class="icon">📁</span> Writable Directories</div>
        <table>
            <?php foreach ($pathResults as $label => $info): ?>
            <tr class="<?= rowClass($info['ok']) ?>">
                <td><?= check($info['ok']) ?></td>
                <td>
                    <div class="label"><?= htmlspecialchars($label) ?></div>
                    <div class="meta"><?= htmlspecialchars($info['path']) ?></div>
                </td>
                <td><span class="badge"><?= $info['ok'] ? 'WRITABLE' : 'NOT WRITABLE' ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php
        $notWritable = array_filter($pathResults, fn($r) => !$r['ok']);
        if ($notWritable):
        ?>
        <div class="fix-hint">
            Fix permissions: <code>chmod -R 775 uploads/</code> &mdash; or change the owner:
            <code>sudo chown -R www-data:www-data uploads/</code>
        </div>
        <?php endif; ?>
    </div>

    <!-- DB Tables -->
    <div class="card">
        <div class="card-header"><span class="icon">🗄️</span> Database Tables</div>
        <table>
            <?php foreach ($tableResults as $table => $info): ?>
            <tr class="<?= rowClass($info['ok']) ?>">
                <td><?= check($info['ok']) ?></td>
                <td>
                    <div class="label"><?= htmlspecialchars($table) ?></div>
                    <div class="meta"><?= htmlspecialchars($info['label']) ?></div>
                </td>
                <td><span class="badge"><?= $info['ok'] ? 'EXISTS' : 'MISSING' ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php if (array_filter($tableResults, fn($r) => !$r['ok'])): ?>
        <div class="fix-hint">
            Run the migration: <code>mysql -u USER -p DB_NAME &lt; migration_plan_settings.sql</code>
        </div>
        <?php endif; ?>
    </div>

    <!-- Admin Files -->
    <div class="card">
        <div class="card-header"><span class="icon">🔒</span> Admin Files</div>
        <table>
            <?php foreach ($adminResults as $label => $info): ?>
            <tr class="<?= rowClass($info['ok']) ?>">
                <td><?= check($info['ok']) ?></td>
                <td>
                    <div class="label"><?= htmlspecialchars($label) ?></div>
                    <div class="meta"><?= htmlspecialchars($info['path']) ?></div>
                </td>
                <td><span class="badge"><?= $info['ok'] ? 'FOUND' : 'MISSING' ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <footer>
        <strong>ResumeCraft</strong> &mdash; Delete or password-protect <code>requirements.php</code> before going live.
    </footer>

</div>
</body>
</html>
