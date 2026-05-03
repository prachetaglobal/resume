<?php
// Freeform Template — Zero restrictions, absolute flexibility.
// Everything is a draggable block.

$personal = [];
foreach ($sections as $sec) {
    if ($sec['type'] === 'personal' && !empty($sec['items'])) {
        $personal = $sec['items'][0]['fields'] ?? [];
        $personalItemId = $sec['items'][0]['id'];
        $personalSectionId = $sec['id'];
    }
}
?>
<div class="resume-wrap freeform-layout">
    <div class="canvas-grid">
        <?php foreach ($sections as $sec): 
            if (empty($sec['items'])) continue; 
            $area = $sec['layout_area'] ?? 'main';
        ?>
            <div class="r-section-wrapper" data-area="<?= $area ?>">
                <?php if ($sec['type'] === 'personal'): ?>
                    <header class="r-header r-section" data-section-id="<?= $sec['id'] ?>" data-item-id="<?= $personalItemId ?? 0 ?>">
                        <div class="r-name r-header-item" data-field-key="name"><?= e($personal['name'] ?? 'Your Name') ?></div>
                        <?php if (!empty($personal['job_title'])): ?>
                            <div class="r-title r-header-item" data-field-key="job_title"><?= e($personal['job_title']) ?></div>
                        <?php endif; ?>
                        <div class="r-contact" data-field-key="contact" data-item-id="<?= $personalItemId ?? 0 ?>">
                            <?php foreach ($personal as $key => $val): 
                                if (in_array($key, ['name','job_title']) || empty($val)) continue;
                                $icon = ['email'=>'✉', 'phone'=>'✆', 'location'=>'⌖', 'linkedin'=>'in', 'website'=>'⬡', 'github'=>'⌥'][$key] ?? '•';
                            ?>
                                <span class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icon ?> <?= e($val) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </header>
                <?php else: ?>
                    <section class="r-section" data-section-id="<?= $sec['id'] ?>" style="<?= $sec['is_visible'] ? '' : 'display:none' ?>">
                        <h2 class="r-section-title"><?= e($sec['title']) ?></h2>
                        <div class="r-section-content">
                            <?php if ($sec['type'] === 'summary'): ?>
                                <div class="r-summary"><p><?= nl2br(e($sec['items'][0]['fields']['summary'] ?? '')) ?></p></div>
                            <?php elseif ($sec['type'] === 'skills' || $sec['type'] === 'languages'): ?>
                                <?php $list = array_filter(array_map('trim', explode(',', $sec['items'][0]['fields'][$sec['type']] ?? ''))); ?>
                                <div class="r-skills-list">
                                    <?php foreach ($list as $item): ?>
                                        <span class="r-skill-tag"><?= e($item) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($sec['items'] as $item): $f = $item['fields']; ?>
                                    <div class="r-item" data-item-id="<?= $item['id'] ?>">
                                        <div class="r-item-header">
                                            <span class="r-item-title"><?= e($f['title'] ?? $f['job_title'] ?? $f['degree'] ?? '') ?></span>
                                            <span class="r-item-date"><?= e($f['date'] ?? $f['start_date'] ?? '') ?><?= !empty($f['end_date']) ? ' – ' . e($f['end_date']) : '' ?></span>
                                        </div>
                                        <div class="r-item-sub"><?= e($f['company'] ?? $f['institution'] ?? '') ?><?= !empty($f['location']) ? ', ' . e($f['location']) : '' ?></div>
                                        <?php if (!empty($f['description'])): ?>
                                            <div class="r-item-desc"><?= nl2br(e($f['description'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.freeform-layout .canvas-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.freeform-layout .r-section-wrapper[data-area="sidebar"] {
    border-left: 4px solid var(--primary-color);
    padding-left: 15px;
}
</style>
