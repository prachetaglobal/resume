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

// ── Plan limit checks ────────────────────────────────────────────────────────
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

$sections      = Resume::buildData($resumeId);
$customization = Resume::getCustomization($resumeId);
$templateSlug  = $resume['template_slug'];
$tplFile       = TEMPLATES_PATH . $templateSlug . '/template.php';
$cssFile       = TEMPLATES_PATH . $templateSlug . '/style.css';
if (!file_exists($tplFile)) {
    $tplFile = TEMPLATES_PATH . 'classic/template.php';
    $cssFile = TEMPLATES_PATH . 'classic/style.css';
}

// Build CSS
$customCss = buildPdfCustomStyles($customization);
$tplCss    = file_exists($cssFile) ? file_get_contents($cssFile) : '';

// Build HTML body
ob_start();
include $tplFile;
$bodyHtml = ob_get_clean();

$html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>' . $tplCss . $customCss . '</style>
</head><body>' . $bodyHtml . '</body></html>';

// Generate PDF with mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => PDF_MARGIN_TOP,
    'margin_bottom' => PDF_MARGIN_BOTTOM,
    'margin_left'   => PDF_MARGIN_LEFT,
    'margin_right'  => PDF_MARGIN_RIGHT,
    'default_font'  => 'dejavusans',
    'tempDir'       => sys_get_temp_dir(),
]);

$mpdf->SetTitle($resume['title']);
$mpdf->SetAuthor('ResumeCraft');
$mpdf->WriteHTML($html);

// Log to activity log
ActivityLog::resumeExported($userId, $resumeId, $resume['title']);

// Log the export for rate-limiting
PlanLimits::logExport($userId, $resumeId);

// Mark as exported
Database::query(
    'UPDATE resumes SET last_exported_at = NOW() WHERE id = ?', [$resumeId]
);

$filename = preg_replace('/[^a-z0-9_-]/i', '_', $resume['title']) . '.pdf';
$mpdf->Output($filename, 'D');
exit;

function buildPdfCustomStyles(array $c): string {
    return "
    body { font-family: '" . addslashes($c['font_body']) . "', DejaVu Sans, sans-serif;
           font-size: {$c['font_size_body']}px; line-height: {$c['line_height']}; }
    h1,h2,h3,h4 { font-family: '" . addslashes($c['font_heading']) . "', DejaVu Sans, sans-serif; }
    :root {
        --primary-color: {$c['primary_color']};
        --accent-color:  {$c['accent_color']};
        --font-size-h:   {$c['font_size_heading']}px;
        --font-size-b:   {$c['font_size_body']}px;
        --line-height:   {$c['line_height']};
        --section-spacing: {$c['section_spacing']}px;
    }";
}
?>
