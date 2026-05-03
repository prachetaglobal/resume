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
    // Build a complete ordered list of ALL section IDs for this resume
    // This is injected into JS so we can always send a complete payload (not just visible ones)
    $allSectionIds = array_column($sections, 'id');
    $allSectionAreas = array_column($sections, 'layout_area', 'id');
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
    <body class="resume-preview-embed is-interactive">
        <?php 
        ob_start();
        include $tplFile;
        $tplHtml = ob_get_clean();
        echo $tplHtml;
        
        // Inject hidden anchors for any sections the template didn't render.
        // This ensures Sortable always has a complete section list.
        foreach ($sections as $sec) {
            if (strpos($tplHtml, 'data-section-id="' . $sec['id'] . '"') === false) {
                echo '<div data-section-id="' . (int)$sec['id'] . '" style="display:none;height:0;overflow:hidden;" class="r-section-ghost"></div>';
            }
        }
        ?>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <script>
        // All section IDs in their current DB order (including invisible/empty ones)
        const ALL_SECTION_IDS = <?= json_encode(array_map('intval', $allSectionIds)) ?>;
        const ALL_SECTION_AREAS = <?= json_encode($allSectionAreas) ?>;

        function broadcastSections() {
            // Step 1: Get the visible sections in their new DOM order
            const visibleSections = Array.from(document.querySelectorAll('[data-section-id]'));
            const visibleOrder = {}; // id -> {order, area}
            visibleSections.forEach((el, idx) => {
                const id = parseInt(el.getAttribute('data-section-id'), 10);
                if (!id) return;
                const parent = el.closest('.r-main, .r-sidebar, .r-body, .resume-wrap');
                let area = ALL_SECTION_AREAS[id] || 'main';
                if (parent) {
                    if (parent.classList.contains('r-sidebar')) area = 'sidebar';
                    else if (parent.classList.contains('r-main')) area = 'main';
                }
                visibleOrder[id] = { order: idx, area: area };
            });

            // Step 2: Build a complete payload — visible sections get their new order/area,
            //         invisible sections keep their existing values (we offset their order so they go to the end)
            const payload = [];
            let hiddenOffset = visibleSections.length;
            ALL_SECTION_IDS.forEach(id => {
                if (visibleOrder[id] !== undefined) {
                    payload.push({ id: id, area: visibleOrder[id].area, order: visibleOrder[id].order });
                } else {
                    // Not in DOM — keep existing area, push to end
                    payload.push({ id: id, area: ALL_SECTION_AREAS[id] || 'main', order: hiddenOffset++ });
                }
            });

            console.log('[DnD] Sending full payload:', payload);
            window.parent.postMessage({ type: 'reorder_sections', sections: payload }, '*');
        }

        function initDragDrop() {
            // Initialize section containers
            const containers = document.querySelectorAll('.resume-wrap, .r-main, .r-sidebar, .r-body');
            containers.forEach(container => {
                // Skip resume-wrap if it contains r-main/r-sidebar (use the children instead)
                if (container.classList.contains('resume-wrap') &&
                    (container.querySelector('.r-main') || container.querySelector('.r-sidebar'))) {
                    return;
                }
                // Skip containers that are field-level (have data-item-id for contact, etc.)
                if (container.hasAttribute('data-item-id') && !container.hasAttribute('data-section-id')) {
                    return;
                }

                new Sortable(container, {
                    group: 'sections',
                    draggable: '[data-section-id]',
                    animation: 150,
                    ghostClass: 'dnd-ghost',
                    chosenClass: 'dnd-chosen',
                    dragClass: 'dnd-drag',
                    onEnd: function() {
                        setTimeout(broadcastSections, 30);
                    }
                });
            });

            // Items within sections
            document.querySelectorAll('[data-section-id]').forEach(container => {
                new Sortable(container, {
                    group: 'items-' + container.getAttribute('data-section-id'),
                    draggable: '.r-item',
                    animation: 150,
                    ghostClass: 'dnd-ghost',
                    onEnd: function() {
                        const sectionId = container.getAttribute('data-section-id');
                        const ids = Array.from(container.querySelectorAll('.r-item'))
                            .map(el => el.getAttribute('data-item-id'))
                            .filter(Boolean);
                        window.parent.postMessage({ type: 'reorder_items', sectionId, ids }, '*');
                    }
                });
            });
        }

        // Run after DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDragDrop);
        } else {
            initDragDrop();
        }
        </script>

        <style>
            /* Draggable indicators */
            .is-interactive [data-section-id],
            .is-interactive .r-item {
                cursor: grab;
                position: relative;
                transition: box-shadow 0.15s ease;
            }
            .is-interactive [data-section-id]:hover {
                outline: 2px dashed rgba(99,102,241,0.6);
                outline-offset: 3px;
            }
            .is-interactive .r-item:hover {
                outline: 1px dashed rgba(99,102,241,0.4);
                outline-offset: 2px;
            }
            .dnd-ghost {
                opacity: 0.35;
                background: rgba(99,102,241,0.08) !important;
                outline: 2px dashed #6366f1 !important;
            }
            .dnd-chosen {
                box-shadow: 0 4px 20px rgba(99,102,241,0.25);
                cursor: grabbing;
            }
            .dnd-drag {
                cursor: grabbing !important;
            }
        </style>
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
