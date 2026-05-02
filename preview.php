<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Resume.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();

$resumeId = (int)($_GET['id'] ?? 0);
$embed    = !empty($_GET['embed']);
$userId   = Auth::id();

$resume = $userId
    ? Resume::getById($resumeId, $userId)
    : Database::fetchOne(
        'SELECT r.*, t.slug AS template_slug FROM resumes r JOIN templates t ON t.id = r.template_id
         WHERE r.id = ? AND r.is_public = 1', [$resumeId]
      );

if (!$resume) {
    if ($embed) { echo '<p style="padding:2rem;color:#999">Resume not found.</p>'; exit; }
    Auth::requireLogin();
    redirect(APP_URL . '/dashboard.php');
}

$sections      = Resume::buildData($resumeId);
$customization = Resume::getCustomization($resumeId);
$templateSlug  = $resume['template_slug'];
$tplFile       = TEMPLATES_PATH . $templateSlug . '/template.php';
if (!file_exists($tplFile)) $tplFile = TEMPLATES_PATH . 'classic/template.php';

if ($embed) {
    // Stripped output for iframe preview
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?= APP_URL ?>/templates/<?= e($templateSlug) ?>/style.css" id="templateStylesheet">
        <style id="customStyles">
            <?= buildCustomStyles($customization) ?>
        </style>
    </head>
    <body class="resume-preview-embed">
        <?php include $tplFile; ?>
    </body>
    </html>
    <?php
    exit;
}

// Full preview page
$pageTitle = e($resume['title']) . ' — Preview — ' . APP_NAME;
$bodyClass = 'preview-full d-flex flex-column min-vh-100';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <?php if (Auth::check()): ?>
        <a href="<?= APP_URL ?>/editor.php?id=<?= $resumeId ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Editor
        </a>
        <?php endif; ?>
        <h5 class="fw-bold mb-0 flex-grow-1"><?= e($resume['title']) ?></h5>
        <a href="<?= APP_URL ?>/api/export.php?id=<?= $resumeId ?>" class="btn btn-success btn-sm">
            <i class="bi bi-filetype-pdf me-1"></i>Download PDF
        </a>
    </div>

    <div class="d-flex justify-content-center">
        <div class="resume-print-wrapper shadow" style="width:210mm; min-height:297mm; background:#fff; padding:0">
            <link rel="stylesheet" href="<?= APP_URL ?>/templates/<?= e($templateSlug) ?>/style.css">
            <style><?= buildCustomStyles($customization) ?></style>
            <?php include $tplFile; ?>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';

function buildCustomStyles(array $c): string {
    $pc = htmlspecialchars($c['primary_color'], ENT_QUOTES);
    $ac = htmlspecialchars($c['accent_color'],  ENT_QUOTES);
    $fh = htmlspecialchars($c['font_heading'],  ENT_QUOTES);
    $fb = htmlspecialchars($c['font_body'],     ENT_QUOTES);
    return "
    :root {
        --primary-color:    {$pc};
        --accent-color:     {$ac};
        --font-heading:     '{$fh}', Arial, sans-serif;
        --font-body:        '{$fb}', Calibri, sans-serif;
        --font-size-h:      {$c['font_size_heading']}px;
        --font-size-b:      {$c['font_size_body']}px;
        --line-height:      {$c['line_height']};
        --section-spacing:  {$c['section_spacing']}px;
        --page-margin:      {$c['page_margin']}mm;
    }";
}
?>
