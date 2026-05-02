<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Resume.php';
require_once __DIR__ . '/includes/functions.php';

Auth::boot();
Auth::requireLogin();

$resumeId = (int)($_GET['id'] ?? 0);
$userId   = Auth::id();
$resume   = Resume::getById($resumeId, $userId);
if (!$resume) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Resume not found.'];
    redirect(APP_URL . '/dashboard.php');
}

$sections      = Resume::getSections($resumeId);
$customization = Resume::getCustomization($resumeId);
$templates     = getTemplates();
$themes        = getTemplateThemes($resume['template_id']);

$pageTitle = 'Edit: ' . $resume['title'] . ' — ' . APP_NAME;
$bodyClass = 'editor-layout d-flex flex-column';
$extraCss  = [ASSETS_URL . '/css/editor.css'];
$extraJs   = [
    ASSETS_URL . '/js/editor.js',
    ASSETS_URL . '/js/customizer.js',
];
include __DIR__ . '/includes/header.php';
?>

<div class="editor-container flex-grow-1 d-flex overflow-hidden">

    <!-- LEFT: Edit Panel -->
    <aside class="editor-panel d-flex flex-column border-end" id="editorPanel">

        <!-- Top bar -->
        <div class="editor-topbar px-3 py-2 border-bottom d-flex align-items-center gap-2">
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <input type="text" id="resumeTitle" class="form-control form-control-sm fw-semibold"
                   value="<?= e($resume['title']) ?>" style="max-width:220px">
            <span class="badge bg-secondary ms-auto" id="saveStatus">Saved</span>
            <a href="<?= APP_URL ?>/preview.php?id=<?= $resumeId ?>" target="_blank"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Preview
            </a>
            <a href="<?= APP_URL ?>/api/export.php?id=<?= $resumeId ?>" target="_blank"
               class="btn btn-sm btn-success">
                <i class="bi bi-filetype-pdf me-1"></i>Export PDF
            </a>
        </div>

        <!-- Tabs: Content / Template / Style -->
        <ul class="nav nav-tabs nav-fill border-bottom-0 px-3 pt-2" id="editorTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabContent">
                    <i class="bi bi-pencil-square me-1"></i>Content
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTemplate">
                    <i class="bi bi-layout-text-sidebar me-1"></i>Template
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStyle">
                    <i class="bi bi-palette2 me-1"></i>Style
                </button>
            </li>
        </ul>

        <!-- Tab panels -->
        <div class="tab-content flex-grow-1 overflow-auto px-3 py-3" id="editorTabContent">

            <!-- CONTENT TAB -->
            <div class="tab-pane fade show active" id="tabContent">
                <div id="sectionsList">
                    <?php foreach ($sections as $sec): ?>
                    <div class="section-block card mb-2 border-0 shadow-sm" data-section-id="<?= $sec['id'] ?>" data-section-type="<?= $sec['type'] ?>">
                        <div class="card-header bg-white border-0 d-flex align-items-center px-3 py-2 section-header" style="cursor:pointer">
                            <i class="bi bi-grip-vertical text-muted me-2 drag-handle"></i>
                            <span class="fw-medium small flex-grow-1"><?= e($sec['title']) ?></span>
                            <div class="form-check form-switch mb-0 me-2">
                                <input class="form-check-input section-toggle" type="checkbox" role="switch"
                                    data-section-id="<?= $sec['id'] ?>"
                                    <?= $sec['is_visible'] ? 'checked' : '' ?>>
                            </div>
                            <i class="bi bi-chevron-down section-chevron small text-muted"></i>
                        </div>
                        <div class="card-body px-3 py-2 section-body" style="display:none">
                            <?= renderSectionForm($sec, Resume::getItems($sec['id'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary w-100 mt-2" id="addSectionBtn" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Section
                </button>
            </div>

            <!-- TEMPLATE TAB -->
            <div class="tab-pane fade" id="tabTemplate">
                <p class="text-muted small mb-3">Switch template — your content stays intact.</p>
                <div class="row g-2" id="templateSwitcher">
                    <?php foreach ($templates as $t): ?>
                    <div class="col-6">
                        <label class="d-block cursor-pointer">
                            <input type="radio" name="switch_template" value="<?= $t['id'] ?>"
                                class="d-none tpl-radio" <?= $t['id'] == $resume['template_id'] ? 'checked' : '' ?>>
                            <div class="tpl-option card border-2 text-center p-2 <?= $t['id'] == $resume['template_id'] ? 'border-primary' : '' ?>">
                                <i class="bi bi-file-earmark-text text-primary fs-3"></i>
                                <div class="small fw-medium mt-1"><?= e($t['name']) ?></div>
                                <div class="text-muted" style="font-size:.65rem"><?= e(ucfirst($t['category'])) ?></div>
                                <?php if ($t['is_ats_friendly']): ?>
                                <span class="badge bg-success-subtle text-success mt-1" style="font-size:.6rem">ATS</span>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STYLE TAB -->
            <div class="tab-pane fade" id="tabStyle">
                <div class="mb-3">
                    <label class="form-label small fw-medium mb-2">Colour Presets</label>
                    <div class="d-flex flex-wrap gap-2" id="themePresets">
                        <?php foreach ($themes as $th): ?>
                        <button type="button" class="theme-preset btn btn-sm border"
                            data-primary="<?= e($th['primary_color']) ?>"
                            data-accent="<?= e($th['accent_color']) ?>"
                            style="background:<?= e($th['primary_color']) ?>;border-color:<?= e($th['accent_color']) ?>!important"
                            title="<?= e($th['name']) ?>">
                            <span class="visually-hidden"><?= e($th['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-medium">Primary Color</label>
                        <input type="color" class="form-control form-control-color w-100" id="primaryColor"
                               value="<?= e($customization['primary_color']) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">Accent Color</label>
                        <input type="color" class="form-control form-control-color w-100" id="accentColor"
                               value="<?= e($customization['accent_color']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium">Heading Font</label>
                    <select class="form-select form-select-sm" id="fontHeading">
                        <?php foreach (['Arial','Calibri','Georgia','Times New Roman','Helvetica','Verdana','Trebuchet MS'] as $f): ?>
                        <option value="<?= $f ?>" <?= $customization['font_heading'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium">Body Font</label>
                    <select class="form-select form-select-sm" id="fontBody">
                        <?php foreach (['Calibri','Arial','Georgia','Times New Roman','Helvetica','Verdana'] as $f): ?>
                        <option value="<?= $f ?>" <?= $customization['font_body'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium d-flex justify-content-between">
                        Heading Size <span id="fontSizeHVal"><?= $customization['font_size_heading'] ?>px</span>
                    </label>
                    <input type="range" class="form-range" id="fontSizeHeading"
                           min="12" max="24" value="<?= $customization['font_size_heading'] ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium d-flex justify-content-between">
                        Body Size <span id="fontSizeBVal"><?= $customization['font_size_body'] ?>px</span>
                    </label>
                    <input type="range" class="form-range" id="fontSizeBody"
                           min="9" max="14" value="<?= $customization['font_size_body'] ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium d-flex justify-content-between">
                        Section Spacing <span id="sectionSpacingVal"><?= $customization['section_spacing'] ?>px</span>
                    </label>
                    <input type="range" class="form-range" id="sectionSpacing"
                           min="8" max="40" value="<?= $customization['section_spacing'] ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium d-flex justify-content-between">
                        Line Height <span id="lineHeightVal"><?= $customization['line_height'] ?></span>
                    </label>
                    <input type="range" class="form-range" id="lineHeight"
                           min="10" max="22" step="1" value="<?= (int)($customization['line_height'] * 10) ?>">
                </div>
            </div>
        </div>
    </aside>

    <!-- RIGHT: Live Preview -->
    <main class="preview-pane flex-grow-1 overflow-auto bg-gray-100 d-flex flex-column" id="previewPane">
        <div class="preview-toolbar px-3 py-2 border-bottom bg-white d-flex align-items-center gap-2">
            <span class="text-muted small"><i class="bi bi-eye me-1"></i>Live Preview</span>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active" id="previewDesktop" title="Desktop">
                        <i class="bi bi-display"></i>
                    </button>
                    <button class="btn btn-outline-secondary" id="previewMobile" title="Mobile">
                        <i class="bi bi-phone"></i>
                    </button>
                </div>
                <select class="form-select form-select-sm" id="zoomLevel" style="width:90px">
                    <option value="0.6">60%</option>
                    <option value="0.75">75%</option>
                    <option value="0.9" selected>90%</option>
                    <option value="1">100%</option>
                </select>
            </div>
        </div>
        <div class="preview-wrapper flex-grow-1 d-flex justify-content-center align-items-start p-4">
            <div class="preview-page shadow" id="previewFrame">
                <iframe id="previewIframe"
                    src="<?= APP_URL ?>/preview.php?id=<?= $resumeId ?>&embed=1"
                    frameborder="0" scrolling="no"></iframe>
            </div>
        </div>
    </main>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold">Add Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <?php
                    $sectionTypes = [
                        ['certifications','bi-patch-check','Certifications'],
                        ['projects','bi-kanban','Projects'],
                        ['languages','bi-translate','Languages'],
                        ['awards','bi-trophy','Awards'],
                        ['publications','bi-journal-text','Publications'],
                        ['references','bi-people','References'],
                        ['custom','bi-plus-square','Custom Section'],
                    ];
                    foreach ($sectionTypes as [$type, $icon, $label]):
                    ?>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary w-100 text-start add-section-btn"
                                data-type="<?= $type ?>" data-label="<?= $label ?>">
                            <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const RESUME_ID   = <?= $resumeId ?>;
const APP_URL     = '<?= APP_URL ?>';
const CSRF_TOKEN  = '<?= Auth::csrfToken() ?>';
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php

function renderSectionForm(array $sec, array $items): string {
    ob_start();
    $type = $sec['type'];
    $sid  = $sec['id'];
    ?>
    <div class="section-form-inner" data-type="<?= $type ?>">
        <?php if ($type === 'personal'): $f = $items[0]['fields'] ?? []; ?>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small">Full Name</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="name" value="<?= e($f['name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Job Title</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="job_title" value="<?= e($f['job_title'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Email</label>
                    <input type="email" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="email" value="<?= e($f['email'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Phone</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="phone" value="<?= e($f['phone'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Location</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="location" value="<?= e($f['location'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">LinkedIn</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="linkedin" value="<?= e($f['linkedin'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">Website / Portfolio</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="website" value="<?= e($f['website'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label small">GitHub</label>
                    <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="github" value="<?= e($f['github'] ?? '') ?>"></div>
            </div>

        <?php elseif ($type === 'summary'): $f = $items[0]['fields'] ?? []; ?>
            <label class="form-label small">Professional Summary</label>
            <textarea class="form-control form-control-sm field-input" rows="5"
                data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="summary"><?= e($f['summary'] ?? '') ?></textarea>
            <div class="text-muted small mt-1">Tip: 3–4 sentences. Include years of experience, top skills, and a measurable win.</div>

        <?php elseif ($type === 'experience'): ?>
            <div class="items-list" data-section-id="<?= $sid ?>">
                <?php foreach ($items as $item): $f = $item['fields']; ?>
                <div class="item-block card mb-2 border-0 bg-light" data-item-id="<?= $item['id'] ?>">
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small">Job Title</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="job_title" value="<?= e($f['job_title'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Company</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="company" value="<?= e($f['company'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">Start Date</label>
                                <input type="text" class="form-control form-control-sm field-input" placeholder="Jan 2022" data-item-id="<?= $item['id'] ?>" data-key="start_date" value="<?= e($f['start_date'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">End Date</label>
                                <input type="text" class="form-control form-control-sm field-input" placeholder="Present" data-item-id="<?= $item['id'] ?>" data-key="end_date" value="<?= e($f['end_date'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">Location</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="location" value="<?= e($f['location'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label small">Description / Achievements</label>
                                <textarea class="form-control form-control-sm field-input" rows="3"
                                    data-item-id="<?= $item['id'] ?>" data-key="description"><?= e($f['description'] ?? '') ?></textarea></div>
                        </div>
                        <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="<?= $item['id'] ?>">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-outline-secondary w-100 add-item-btn mt-1" data-section-id="<?= $sid ?>">
                <i class="bi bi-plus-lg me-1"></i>Add Experience
            </button>

        <?php elseif ($type === 'education'): ?>
            <div class="items-list" data-section-id="<?= $sid ?>">
                <?php foreach ($items as $item): $f = $item['fields']; ?>
                <div class="item-block card mb-2 border-0 bg-light" data-item-id="<?= $item['id'] ?>">
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small">Degree / Qualification</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="degree" value="<?= e($f['degree'] ?? '') ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Institution</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="institution" value="<?= e($f['institution'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">Start</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="start_date" value="<?= e($f['start_date'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">End</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="end_date" value="<?= e($f['end_date'] ?? '') ?>"></div>
                            <div class="col-md-4"><label class="form-label small">GPA / Grade</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="gpa" value="<?= e($f['gpa'] ?? '') ?>"></div>
                        </div>
                        <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="<?= $item['id'] ?>">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-outline-secondary w-100 add-item-btn mt-1" data-section-id="<?= $sid ?>">
                <i class="bi bi-plus-lg me-1"></i>Add Education
            </button>

        <?php elseif ($type === 'skills'): $f = $items[0]['fields'] ?? []; ?>
            <label class="form-label small">Skills (comma-separated)</label>
            <textarea class="form-control form-control-sm field-input" rows="3"
                data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="skills"><?= e($f['skills'] ?? '') ?></textarea>
            <div class="text-muted small mt-1">e.g. PHP, MySQL, JavaScript, Bootstrap, Git</div>

        <?php elseif ($type === 'languages'): $f = $items[0]['fields'] ?? []; ?>
            <label class="form-label small">Languages (e.g. English – Native, French – B2)</label>
            <textarea class="form-control form-control-sm field-input" rows="3"
                data-item-id="<?= $items[0]['id'] ?? 0 ?>" data-key="languages"><?= e($f['languages'] ?? '') ?></textarea>

        <?php else: ?>
            <div class="items-list" data-section-id="<?= $sid ?>">
                <?php foreach ($items as $item): $f = $item['fields']; ?>
                <div class="item-block card mb-2 border-0 bg-light" data-item-id="<?= $item['id'] ?>">
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <div class="col-md-6"><label class="form-label small">Title</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="title" value="<?= e($f['title'] ?? '') ?>"></div>
                            <div class="col-md-3"><label class="form-label small">Date</label>
                                <input type="text" class="form-control form-control-sm field-input" data-item-id="<?= $item['id'] ?>" data-key="date" value="<?= e($f['date'] ?? '') ?>"></div>
                            <div class="col-12"><label class="form-label small">Description</label>
                                <textarea class="form-control form-control-sm field-input" rows="2"
                                    data-item-id="<?= $item['id'] ?>" data-key="description"><?= e($f['description'] ?? '') ?></textarea></div>
                        </div>
                        <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="<?= $item['id'] ?>">
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-outline-secondary w-100 add-item-btn mt-1" data-section-id="<?= $sid ?>">
                <i class="bi bi-plus-lg me-1"></i>Add Entry
            </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>
