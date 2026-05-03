<?php
$personal = [];
$summary  = '';
foreach ($sections as $sec) {
    if ($sec['type'] === 'personal' && !empty($sec['items'])) $personal = $sec['items'][0]['fields'] ?? [];
    if ($sec['type'] === 'summary'  && !empty($sec['items'])) $summary  = $sec['items'][0]['fields']['summary'] ?? '';
}
?>
<div class="resume-wrap">
    <header class="r-header">
        <div class="r-name"><?= e($personal['name'] ?? 'Your Name') ?></div>
        <?php if (!empty($personal['job_title'])): ?><div class="r-title"><?= e($personal['job_title']) ?></div><?php endif; ?>
        <div class="r-contact" data-item-id="<?= $sections[0]['items'][0]['id'] ?? 0 ?>">
            <?php foreach ($personal as $key => $val): 
                if (in_array($key, ['name','job_title']) || empty($val)) continue;
                $icon = ['email'=>'✉', 'phone'=>'✆', 'location'=>'⌖', 'linkedin'=>'in', 'website'=>'⬡', 'github'=>'⌥'][$key] ?? '•';
            ?>
                <span class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icon ?> <?= e($val) ?></span>
            <?php endforeach; ?>
        </div>
    </header>

    <?php foreach ($sections as $sec):
        if (!$sec['is_visible'] || $sec['type'] === 'personal' || empty($sec['items'])) continue; ?>
    <section class="r-section" data-section-id="<?= $sec["id"] ?>">
        <h2 class="r-section-title"><?= e($sec['title']) ?></h2>
        <?php if ($sec['type'] === 'summary'): ?>
            <p style="text-align:justify"><?= nl2br(e($summary)) ?></p>
        <?php elseif ($sec['type'] === 'skills'): ?>
            <?php $skills = array_filter(array_map('trim', explode(',', $sec['items'][0]['fields']['skills'] ?? ''))); ?>
            <div class="r-skills-list"><?php foreach ($skills as $sk): ?><span class="r-skill-tag"><?= e($sk) ?></span><?php endforeach; ?></div>
        <?php elseif ($sec['type'] === 'languages'): ?>
            <?php $langs = array_filter(array_map('trim', explode(',', $sec['items'][0]['fields']['languages'] ?? ''))); ?>
            <div class="r-skills-list"><?php foreach ($langs as $l): ?><span class="r-skill-tag"><?= e($l) ?></span><?php endforeach; ?></div>
        <?php elseif ($sec['type'] === 'experience'): ?>
            <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
            <div class="r-item" data-item-id="<?= $item["id"] ?>">
                <div class="r-item-header">
                    <span class="r-item-title"><?= e($f['job_title'] ?? '') ?></span>
                    <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
                </div>
                <div class="r-item-sub"><?= e($f['company'] ?? '') ?><?= !empty($f['location']) ? ' · '.e($f['location']) : '' ?></div>
                <?php if (!empty($f['description'])): ?><div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php elseif ($sec['type'] === 'education'): ?>
            <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
            <div class="r-item" data-item-id="<?= $item["id"] ?>">
                <div class="r-item-header">
                    <span class="r-item-title"><?= e($f['degree'] ?? '') ?></span>
                    <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
                </div>
                <div class="r-item-sub"><?= e($f['institution'] ?? '') ?><?= !empty($f['gpa']) ? ' · GPA: '.e($f['gpa']) : '' ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
            <div class="r-item" data-item-id="<?= $item["id"] ?>">
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
