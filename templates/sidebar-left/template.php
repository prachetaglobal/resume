<?php
$personal = [];
$summary  = '';
$sidebarTypes = ['personal','summary','skills','languages','certifications'];
$mainSections = [];
$sidebarSections = [];

foreach ($sections as $sec) {
    if ($sec['type'] === 'personal' && !empty($sec['items'])) $personal = $sec['items'][0]['fields'] ?? [];
    if ($sec['type'] === 'summary'  && !empty($sec['items'])) $summary  = $sec['items'][0]['fields']['summary'] ?? '';
    if (in_array($sec['type'], ['experience','education','projects','awards','publications','references','custom'])) {
        $mainSections[] = $sec;
    } else {
        $sidebarSections[] = $sec;
    }
}
?>
<div class="resume-wrap">
    <!-- SIDEBAR -->
    <div class="r-sidebar">
        <div class="r-name"><?= e($personal['name'] ?? 'Your Name') ?></div>
        <?php if (!empty($personal['job_title'])): ?><div class="r-title"><?= e($personal['job_title']) ?></div><?php endif; ?>

        <div class="r-sec-title">Contact</div>
        <?php if (!empty($personal['email'])): ?><p>✉ <?= e($personal['email']) ?></p><?php endif; ?>
        <?php if (!empty($personal['phone'])): ?><p>✆ <?= e($personal['phone']) ?></p><?php endif; ?>
        <?php if (!empty($personal['location'])): ?><p>⌖ <?= e($personal['location']) ?></p><?php endif; ?>
        <?php if (!empty($personal['linkedin'])): ?><p>in <?= e($personal['linkedin']) ?></p><?php endif; ?>
        <?php if (!empty($personal['website'])): ?><p>⬡ <?= e($personal['website']) ?></p><?php endif; ?>

        <?php foreach ($sidebarSections as $sec):
            if (!$sec['is_visible'] || empty($sec['items']) || $sec['type'] === 'personal') continue; ?>
        <div class="r-sec-title"><?= e($sec['title']) ?></div>
        <?php if ($sec['type'] === 'summary'): ?>
            <p style="font-size:calc(var(--font-size-b)*.92);color:rgba(255,255,255,.85)"><?= nl2br(e($summary)) ?></p>
        <?php elseif ($sec['type'] === 'skills'): ?>
            <?php $skills = array_filter(array_map('trim', explode(',', $sec['items'][0]['fields']['skills'] ?? ''))); ?>
            <ul><?php foreach ($skills as $sk): ?><li><?= e($sk) ?></li><?php endforeach; ?></ul>
        <?php elseif ($sec['type'] === 'languages'): ?>
            <?php $langs = array_filter(array_map('trim', explode(',', $sec['items'][0]['fields']['languages'] ?? ''))); ?>
            <ul><?php foreach ($langs as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- MAIN -->
    <div class="r-main">
        <?php foreach ($mainSections as $sec):
            if (!$sec['is_visible'] || empty($sec['items'])) continue; ?>
        <section class="r-section">
            <h2 class="r-section-title"><?= e($sec['title']) ?></h2>
            <?php if ($sec['type'] === 'experience'): ?>
                <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
                <div class="r-item">
                    <div class="r-item-header">
                        <span class="r-item-title"><?= e($f['job_title'] ?? '') ?></span>
                        <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
                    </div>
                    <div class="r-item-sub"><?= e($f['company'] ?? '') ?><?= !empty($f['location']) ? ', '.e($f['location']) : '' ?></div>
                    <?php if (!empty($f['description'])): ?><div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php elseif ($sec['type'] === 'education'): ?>
                <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
                <div class="r-item">
                    <div class="r-item-header">
                        <span class="r-item-title"><?= e($f['degree'] ?? '') ?></span>
                        <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
                    </div>
                    <div class="r-item-sub"><?= e($f['institution'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
                <div class="r-item">
                    <div class="r-item-header">
                        <span class="r-item-title"><?= e($f['title'] ?? '') ?></span>
                        <span class="r-item-date"><?= e($f['date'] ?? '') ?></span>
                    </div>
                    <?php if (!empty($f['description'])): ?><div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
</div>
