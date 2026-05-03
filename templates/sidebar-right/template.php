<?php
$personal = []; $summary = '';
$personalItemId = 0; $personalSectionId = 0;
$mainSections = []; $sidebarSections = [];

foreach ($sections as $sec) {
    if ($sec['type'] === 'personal' && !empty($sec['items'])) {
        $personal          = $sec['items'][0]['fields'] ?? [];
        $personalItemId    = $sec['items'][0]['id'];
        $personalSectionId = $sec['id'];
    }
    if ($sec['type'] === 'summary' && !empty($sec['items'])) {
        $summary = $sec['items'][0]['fields']['summary'] ?? '';
    }
    if (($sec['layout_area'] ?? 'main') === 'sidebar') {
        $sidebarSections[] = $sec;
    } else {
        $mainSections[] = $sec;
    }
}

function renderSectionContentR(array $sec, string $summary): void {
    $items = $sec['items'];
    if ($sec['type'] === 'summary'): ?>
        <p style="font-size:calc(var(--font-size-b)*.92)"><?= nl2br(e($summary)) ?></p>
    <?php elseif ($sec['type'] === 'skills'):
        $skills = array_filter(array_map('trim', explode(',', $items[0]['fields']['skills'] ?? ''))); ?>
        <ul><?php foreach ($skills as $sk): ?><li><?= e($sk) ?></li><?php endforeach; ?></ul>
    <?php elseif ($sec['type'] === 'languages'):
        $langs = array_filter(array_map('trim', explode(',', $items[0]['fields']['languages'] ?? ''))); ?>
        <ul><?php foreach ($langs as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
    <?php elseif ($sec['type'] === 'experience'): ?>
        <?php foreach ($items as $item): $f = $item['fields']; ?>
        <div class="r-item" data-item-id="<?= $item['id'] ?>">
            <div class="r-item-header">
                <span class="r-item-title"><?= e($f['job_title'] ?? '') ?></span>
                <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
            </div>
            <div class="r-item-sub"><?= e($f['company'] ?? '') ?><?= !empty($f['location']) ? ', '.e($f['location']) : '' ?></div>
            <?php if (!empty($f['description'])): ?><div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php elseif ($sec['type'] === 'education'): ?>
        <?php foreach ($items as $item): $f = $item['fields']; ?>
        <div class="r-item" data-item-id="<?= $item['id'] ?>">
            <div class="r-item-header">
                <span class="r-item-title"><?= e($f['degree'] ?? '') ?></span>
                <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
            </div>
            <div class="r-item-sub"><?= e($f['institution'] ?? '') ?><?= !empty($f['gpa']) ? ' · GPA: '.e($f['gpa']) : '' ?></div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($items as $item): $f = $item['fields']; ?>
        <div class="r-item" data-item-id="<?= $item['id'] ?>">
            <div class="r-item-header">
                <span class="r-item-title"><?= e($f['title'] ?? '') ?></span>
                <span class="r-item-date"><?= e($f['date'] ?? '') ?></span>
            </div>
            <?php if (!empty($f['description'])): ?><div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif;
}
?>
<div class="resume-wrap">

    <!-- MAIN (left) -->
    <div class="r-main">
        <?php foreach ($mainSections as $sec):
            if ($sec['type'] === 'personal' || !$sec['is_visible'] || empty($sec['items'])) continue;
        ?>
        <section class="r-section" data-section-id="<?= $sec['id'] ?>">
            <h2 class="r-section-title"><?= e($sec['title']) ?></h2>
            <?php renderSectionContentR($sec, $summary); ?>
        </section>
        <?php endforeach; ?>
    </div>

    <!-- SIDEBAR (right) -->
    <div class="r-sidebar">

        <!-- Personal / Header block in sidebar -->
        <div class="r-header r-section" data-section-id="<?= $personalSectionId ?>" data-item-id="<?= $personalItemId ?>">
            <div class="r-name r-header-item" data-field-key="name"><?= e($personal['name'] ?? 'Your Name') ?></div>
            <?php if (!empty($personal['job_title'])): ?>
            <div class="r-title r-header-item" data-field-key="job_title"><?= e($personal['job_title']) ?></div>
            <?php endif; ?>
            <div class="r-sec-title">Contact</div>
            <div class="r-contact" data-field-key="contact" data-item-id="<?= $personalItemId ?>">
                <?php foreach ($personal as $key => $val):
                    if (in_array($key, ['name','job_title']) || empty($val)) continue;
                    $icon = ['email'=>'✉','phone'=>'✆','location'=>'⌖','linkedin'=>'in','website'=>'⬡','github'=>'⌥'][$key] ?? '•';
                ?>
                <p class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icon ?> <?= e($val) ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Other sidebar sections -->
        <?php foreach ($sidebarSections as $sec):
            if ($sec['type'] === 'personal' || !$sec['is_visible'] || empty($sec['items'])) continue;
        ?>
        <div class="r-section" data-section-id="<?= $sec['id'] ?>">
            <div class="r-sec-title"><?= e($sec['title']) ?></div>
            <?php renderSectionContentR($sec, $summary); ?>
        </div>
        <?php endforeach; ?>
    </div>

</div>
