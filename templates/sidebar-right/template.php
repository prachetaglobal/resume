<?php
$personal = [];
$summary  = '';
$sidebarTypes = ['personal','summary','skills','languages','certifications'];
$mainSections = [];
$sidebarSections = [];

foreach ($sections as $sec) { 
        if ($sec['type'] === 'personal') $personalSectionId = $sec['id'];
        if ($sec['type'] === 'summary')  $summarySectionId = $sec['id'];
    if ($sec['type'] === 'personal' && !empty($sec['items'])) { $personal = $sec['items'][0]['fields'] ?? []; $personalItemId = $sec['items'][0]['id']; }
    if ($sec['type'] === 'summary'  && !empty($sec['items'])) $summary  = $sec['items'][0]['fields']['summary'] ?? '';
    if ($sec['layout_area'] === 'main') {
        $mainSections[] = $sec;
    } else {
        $sidebarSections[] = $sec;
    }
}
?>
<div class="resume-wrap">
    <!-- SIDEBAR -->
    <div class="r-main" data-item-id="<?= $personalItemId ?? 0 ?>">
        <?php foreach ($sidebarSections as $sec):
            if (empty($sec['items'])) continue; ?>
            
            <?php if ($sec['type'] === 'personal'): ?>
                <div class="r-header r-section" data-section-id="<?= $personalSectionId ?? 0 ?>" data-item-id="<?= $personalItemId ?? 0 ?>">
                    <div class="r-name r-header-item" data-field-key="name"><?= e($personal['name'] ?? 'Your Name') ?></div>
                    <?php if (!empty($personal['job_title'])): ?><div class="r-title r-header-item" data-field-key="job_title"><?= e($personal['job_title']) ?></div><?php endif; ?>
                    <div class="r-sec-title">Contact</div>
                    <div class="r-contact" data-field-key="contact" data-item-id="<?= $personalItemId ?? 0 ?>">
                        <?php foreach ($personal as $key => $val): 
                            if (in_array($key, ['name','job_title']) || empty($val)) continue;
                            $icon = ['email'=>'✉', 'phone'=>'✆', 'location'=>'⌖', 'linkedin'=>'in', 'website'=>'⬡', 'github'=>'⌥'][$key] ?? '•';
                        ?>
                            <p class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icon ?> <?= e($val) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="r-section" data-section-id="<?= $sec['id'] ?>" style="<?= $sec['is_visible'] ? '' : 'display:none' ?>">
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
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- MAIN -->
    <div class="r-sidebar">
        <?php foreach ($mainSections as $sec):
            if (empty($sec['items'])) continue; ?>
            
            <?php if ($sec['type'] === 'personal'): ?>
                <div class="r-header r-section" data-section-id="<?= $personalSectionId ?? 0 ?>" data-item-id="<?= $personalItemId ?? 0 ?>">
                    <div class="r-name r-header-item" data-field-key="name"><?= e($personal['name'] ?? 'Your Name') ?></div>
                    <?php if (!empty($personal['job_title'])): ?><div class="r-title r-header-item" data-field-key="job_title"><?= e($personal['job_title']) ?></div><?php endif; ?>
                    <div class="r-sec-title">Contact</div>
                    <div class="r-contact" data-field-key="contact" data-item-id="<?= $personalItemId ?? 0 ?>">
                        <?php foreach ($personal as $key => $val): 
                            if (in_array($key, ['name','job_title']) || empty($val)) continue;
                            $icon = ['email'=>'✉', 'phone'=>'✆', 'location'=>'⌖', 'linkedin'=>'in', 'website'=>'⬡', 'github'=>'⌥'][$key] ?? '•';
                        ?>
                            <p class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icon ?> <?= e($val) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <section class="r-section" data-section-id="<?= $sec["id"] ?>" style="<?= $sec['is_visible'] ? '' : 'display:none' ?>">
                    <h2 class="r-section-title"><?= e($sec['title']) ?></h2>
            <?php if ($sec['type'] === 'experience'): ?>
                <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
                <div class="r-item" data-item-id="<?= $item["id"] ?>">
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
                <div class="r-item" data-item-id="<?= $item["id"] ?>">
                    <div class="r-item-header">
                        <span class="r-item-title"><?= e($f['degree'] ?? '') ?></span>
                        <span class="r-item-date"><?= e($f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – '.e($f['end_date']) : '' ?></span>
                    </div>
                    <div class="r-item-sub"><?= e($f['institution'] ?? '') ?></div>
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
        <?php endforeach; ?>
    </div>
</div>
