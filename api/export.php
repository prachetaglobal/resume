<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Resume.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/PlanLimits.php';
require_once __DIR__ . '/../includes/ActivityLog.php';

Auth::boot();
Auth::requireLogin();

$resumeId = (int)($_GET['id'] ?? 0);
$userId   = Auth::id();
$resume   = Resume::getById($resumeId, $userId);

if (!$resume) {
    http_response_code(404);
    exit('Resume not found.');
}

// ── Plan limit checks ─────────────────────────────────────────────────────────
$user = Auth::user();
if (!PlanLimits::exportsEnabled($user['plan'])) {
    http_response_code(403);
    exit('PDF export is not available on your current plan. Please upgrade.');
}
if (!PlanLimits::canExport($userId, $user['plan'])) {
    $max = PlanLimits::maxDailyExports($user['plan']);
    http_response_code(429);
    exit("Daily PDF download limit reached ({$max}/day). Please try again tomorrow.");
}

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    exit('PDF library not installed. Run: composer install');
}
require_once $vendorAutoload;

// ── Build template data ────────────────────────────────────────────────────────
$sections      = Resume::buildData($resumeId);
$customization = Resume::getCustomization($resumeId);
$templateSlug  = $resume['template_slug'];
$tplFile       = TEMPLATES_PATH . $templateSlug . '/template.php';
$cssFile       = TEMPLATES_PATH . $templateSlug . '/style.css';

if (!file_exists($tplFile)) {
    $tplFile = TEMPLATES_PATH . 'classic/template.php';
    $cssFile = TEMPLATES_PATH . 'classic/style.css';
}

// ── Build CSS ─────────────────────────────────────────────────────────────────
$rawCss = file_exists($cssFile) ? file_get_contents($cssFile) : '';

// Resolve custom property VALUES from the :root block + customization overrides
$vars = extractCssVars($rawCss, $customization);

// Replace every var(--xxx) occurrence with its resolved literal value
$resolvedCss = resolveCssVars($rawCss, $vars);

// mPDF-compat overrides: fix flex/table layouts + zero extra margins
$compatCss = buildMpdfCompatCss($templateSlug, $vars);

// ── Build HTML body ───────────────────────────────────────────────────────────
ob_start();
include $tplFile;
$bodyHtml = ob_get_clean();

// Resolve any var(--x) that appear inside inline style="..." attributes
$bodyHtml = resolveCssVars($bodyHtml, $vars);

// Build final HTML document — no <link> tags, all CSS inline
$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
' . $resolvedCss . '
' . $compatCss . '
</style>
</head>
<body>' . $bodyHtml . '</body>
</html>';

// ── mPDF configuration ────────────────────────────────────────────────────────
// Templates manage their own padding through .resume-wrap, so mPDF margins = 0
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => 0,
    'margin_bottom' => 0,
    'margin_left'   => 0,
    'margin_right'  => 0,
    'default_font'  => 'dejavusans',
    'tempDir'       => sys_get_temp_dir(),
    'autoScriptToLang' => true,
    'autoLangToFont'   => true,
]);

$mpdf->SetTitle($resume['title']);
$mpdf->SetAuthor('ResumeCraft');
$mpdf->SetDisplayMode('fullpage');
// Allow background colours to render
$mpdf->SetHTMLFooter('');
$mpdf->showImageErrors = true;

$mpdf->WriteHTML($html);

// ── Audit & rate-limit ────────────────────────────────────────────────────────
ActivityLog::resumeExported($userId, $resumeId, $resume['title']);
PlanLimits::logExport($userId, $resumeId);
Database::query('UPDATE resumes SET last_exported_at = NOW() WHERE id = ?', [$resumeId]);

// ── Output ────────────────────────────────────────────────────────────────────
$filename = preg_replace('/[^a-z0-9_-]/i', '_', $resume['title']) . '.pdf';
$mpdf->Output($filename, 'D');
exit;

// ═══════════════════════════════════════════════════════════════════════════════
// Helper functions
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Extract CSS custom property definitions from the :root block in a stylesheet,
 * then merge with the user's customization record.
 */
function extractCssVars(string $css, array $c): array {
    // Defaults from :root { ... }
    $vars = [];
    if (preg_match('/:root\s*\{([^}]+)\}/s', $css, $m)) {
        preg_match_all('/--([a-z0-9_-]+)\s*:\s*([^;]+);/i', $m[1], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $vars['--' . trim($match[1])] = trim($match[2]);
        }
    }

    // Merge user customization overrides
    $map = [
        '--primary-color'   => $c['primary_color']      ?? null,
        '--accent-color'    => $c['accent_color']        ?? null,
        '--font-heading'    => $c['font_heading']        ? "'{$c['font_heading']}', DejaVu Sans, sans-serif" : null,
        '--font-body'       => $c['font_body']           ? "'{$c['font_body']}', DejaVu Sans, sans-serif"    : null,
        '--font-size-h'     => isset($c['font_size_heading']) ? $c['font_size_heading'] . 'px' : null,
        '--font-size-b'     => isset($c['font_size_body'])    ? $c['font_size_body'] . 'px'    : null,
        '--line-height'     => $c['line_height']         ?? null,
        '--section-spacing' => isset($c['section_spacing']) ? $c['section_spacing'] . 'px'  : null,
        '--page-margin'     => isset($c['page_margin'])     ? $c['page_margin'] . 'mm'       : null,
    ];

    foreach ($map as $key => $val) {
        if ($val !== null && $val !== '') {
            $vars[$key] = $val;
        }
    }

    return $vars;
}

/**
 * Replace all var(--xxx) references in a CSS string with their resolved values.
 * Handles calc() expressions containing vars by substituting first.
 * Falls back to sensible defaults for unknown variables.
 */
function resolveCssVars(string $css, array $vars): string {
    $fallbacks = [
        '--primary-color'   => '#1a2e4a',
        '--accent-color'    => '#2c5f8a',
        '--font-heading'    => 'DejaVu Sans, sans-serif',
        '--font-body'       => 'DejaVu Sans, sans-serif',
        '--font-size-h'     => '16px',
        '--font-size-b'     => '11px',
        '--line-height'     => '1.5',
        '--section-spacing' => '16px',
        '--page-margin'     => '20mm',
        '--sidebar-width'   => '200px',
    ];

    // Merge
    $all = array_merge($fallbacks, $vars);

    // Replace var(--name, fallback) or var(--name)
    $css = preg_replace_callback(
        '/var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*[^)]+)?\s*\)/i',
        function ($m) use ($all) {
            $key = $m[1];
            return $all[$key] ?? $m[0]; // keep original if truly unknown
        },
        $css
    );

    // Resolve any remaining calc() with simple px arithmetic (e.g. calc(11px * 1.6))
    $css = preg_replace_callback(
        '/calc\(\s*([\d.]+)px\s*\*\s*([\d.]+)\s*\)/i',
        fn($m) => round((float)$m[1] * (float)$m[2], 2) . 'px',
        $css
    );
    $css = preg_replace_callback(
        '/calc\(\s*([\d.]+)px\s*\*\s*([\d.]+)\s*\)/i',
        fn($m) => round((float)$m[1] * (float)$m[2], 2) . 'px',
        $css
    );

    return $css;
}

/**
 * Generate mPDF-compatible CSS overrides for layouts that mPDF doesn't fully support.
 *
 * mPDF supports:  block, inline, inline-block, table, table-cell, table-row
 * mPDF does NOT:  flexbox (display:flex / gap), CSS grid, position:sticky
 */
function buildMpdfCompatCss(string $slug, array $vars): string {

    $primary = $vars['--primary-color'] ?? '#1a2e4a';
    $accent  = $vars['--accent-color']  ?? '#2c5f8a';

    // Base resets that apply to every template
    $base = '
        /* mPDF base compat */
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; }

        /* Flex → inline-block fallback for contact rows */
        .r-contact { display: block; }
        .r-contact span {
            display: inline-block;
            margin-right: 12px;
            margin-bottom: 3px;
        }

        /* Flex → inline-block for skill tags */
        .r-skills-list { display: block; }
        .r-skill-tag   { display: inline-block; margin: 2px 4px 2px 0; }

        /* Item header: block layout instead of flex */
        .r-item-header { display: block; overflow: hidden; }
        .r-item-title  { display: block; float: left; max-width: 70%; }
        .r-item-date   { display: block; float: right; text-align: right; }
        .r-item-sub    { display: block; clear: both; }
        .r-item-desc   { display: block; clear: both; margin-top: 4px; }
    ';

    // Template-specific layout overrides
    switch ($slug) {
        // ── Sidebar templates: use mPDF table layout ──────────────────────────
        case 'sidebar-left':
            return $base . '
                .resume-wrap { display: table; width: 210mm; }
                .r-sidebar   { display: table-cell; width: 200px; vertical-align: top;
                               background: ' . $primary . '; color: #fff; padding: 24px 14px; }
                .r-main      { display: table-cell; vertical-align: top; padding: 24px 18px; }
                .r-sidebar .r-skill-tag  { background: rgba(255,255,255,.15); color: #fff; }
                .r-sidebar .r-sec-title  { color: rgba(255,255,255,.7); border-color: rgba(255,255,255,.2); }
                .r-sidebar .r-contact    { display: block; }
                .r-sidebar .r-contact span { display: block; margin-bottom: 5px; }
            ';

        case 'sidebar-right':
            return $base . '
                .resume-wrap { display: table; width: 210mm; }
                .r-main    { display: table-cell; vertical-align: top; padding: 24px 18px; }
                .r-sidebar { display: table-cell; width: 190px; vertical-align: top;
                             background: ' . $primary . '; color: #fff; padding: 24px 14px; }
                .r-sidebar .r-skill-tag  { background: rgba(255,255,255,.15); color: #fff; }
                .r-sidebar .r-sec-title  { color: rgba(255,255,255,.7); border-color: rgba(255,255,255,.2); }
                .r-sidebar .r-contact    { display: block; }
                .r-sidebar .r-contact span { display: block; margin-bottom: 5px; }
            ';

        // ── Modern: coloured header band ──────────────────────────────────────
        case 'modern':
            return $base . '
                .r-header { background: ' . $primary . '; color: #fff; padding: 28px 24px 20px; }
                .r-body   { padding: 20px 24px; }
                .r-name   { color: #fff; }
                .r-title  { color: rgba(255,255,255,.8); }
                .r-contact span { color: rgba(255,255,255,.85); }
            ';

        // ── Executive: header with left border accent ─────────────────────────
        case 'executive':
            return $base . '
                .r-header-band {
                    background: ' . $primary . ';
                    color: #fff; padding: 24px 26px;
                }
                .r-header-band .r-name  { color: #fff; }
                .r-header-band .r-title { color: rgba(255,255,255,.8); }
            ';

        // ── Tech: dark background needs explicit colour output ─────────────────
        case 'tech':
            return $base . '
                html, body { background: ' . $primary . '; }
                .resume-wrap { background: ' . $primary . '; }
                .r-name, .r-item-title { color: #fff; }
            ';

        default:
            return $base;
    }
}
