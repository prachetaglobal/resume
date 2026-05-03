<?php
// ── Atomic Template ─────────────────────────────────────────────
// Matches myperfectresume.com skin: hrt1_atmc_7
// Two-col layout: section title | line divider, date col | content col

$personal        = [];
$personalItemId  = 0;
$personalSectionId = 0;
$allSectionsById = [];

foreach ($sections as $sec) {
    $allSectionsById[$sec['id']] = $sec;
    if ($sec['type'] === 'personal' && !empty($sec['items'])) {
        $personal          = $sec['items'][0]['fields'] ?? [];
        $personalItemId    = $sec['items'][0]['id'];
        $personalSectionId = $sec['id'];
    }
}

$name     = $personal['name']      ?? 'Your Name';
$jobTitle = $personal['job_title'] ?? '';
$email    = $personal['email']     ?? '';
$phone    = $personal['phone']     ?? '';
$location = $personal['location']  ?? '';
$linkedin = $personal['linkedin']  ?? '';
$website  = $personal['website']   ?? '';
$photo    = $personal['photo']     ?? '';

// Contact pieces
$contactParts = array_filter([$phone, $email, $location, $linkedin, $website]);
?>
<div class="at-wrap">

    <!-- ── HEADER ── -->
    <header class="at-header r-section" data-section-id="<?= $personalSectionId ?>" data-item-id="<?= $personalItemId ?>">

        <?php if ($photo): ?>
        <div class="at-photo-wrap">
            <img src="<?= e($photo) ?>" alt="Profile" class="at-photo">
        </div>
        <?php else: ?>
        <div class="at-photo-wrap">
            <div class="at-photo-placeholder">&#128100;</div>
        </div>
        <?php endif; ?>

        <div class="at-name r-header-item" data-field-key="name"><?= e($name) ?></div>
        <?php if ($jobTitle): ?>
        <div class="at-title r-header-item" data-field-key="job_title"><?= e($jobTitle) ?></div>
        <?php endif; ?>

        <?php if (!empty($contactParts)): ?>
        <div class="at-contact" data-field-key="contact" data-item-id="<?= $personalItemId ?>">
            <?php
            $icons = ['phone'=>'✆','email'=>'✉','location'=>'⌖','linkedin'=>'in','website'=>'⬡','github'=>'⌥'];
            $first = true;
            foreach ($personal as $key => $val):
                if (in_array($key, ['name','job_title','photo','sort_order']) || empty($val)) continue;
            ?>
            <?php if (!$first): ?><span class="at-sep"> · </span><?php endif; ?>
            <span class="r-contact-item" data-field-key="<?= e($key) ?>"><?= $icons[$key] ?? '' ?> <?= e($val) ?></span>
            <?php $first = false; endforeach; ?>
        </div>
        <?php endif; ?>
    </header>

    <!-- ── BODY SECTIONS (rendered by sort_order) ── -->
    <?php foreach ($sections as $sec):
        if ($sec['type'] === 'personal') continue;
        if (!$sec['is_visible']) continue;
        $items = $sec['items'] ?? [];
    ?>
    <section class="at-section r-section" data-section-id="<?= $sec['id'] ?>">
        <div class="at-section-header">
            <span class="at-section-title"><?= e($sec['title']) ?></span>
            <span class="at-section-line"></span>
        </div>

        <?php if ($sec['type'] === 'summary'): ?>
            <!-- Summary -->
            <?php $summary = $items[0]['fields']['summary'] ?? ''; ?>
            <?php if ($summary): ?>
            <div class="at-summary"><?= nl2br(e($summary)) ?></div>
            <?php endif; ?>

        <?php elseif ($sec['type'] === 'skills'): ?>
            <!-- Skills with bars -->
            <?php $rawSkills = array_filter(array_map('trim', explode(',', $items[0]['fields']['skills'] ?? ''))); ?>
            <div class="at-skills-list">
                <?php foreach ($rawSkills as $i => $sk): ?>
                <div class="at-skill-row r-item" data-item-id="skill-<?= $i ?>">
                    <div class="at-skill-name"><?= e($sk) ?></div>
                    <div class="at-skill-bar-track">
                        <div class="at-skill-bar-fill" style="width:<?= min(95, 50 + ($i % 4)*12) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($sec['type'] === 'languages'): ?>
            <!-- Languages -->
            <?php $rawLangs = array_filter(array_map('trim', explode(',', $items[0]['fields']['languages'] ?? ''))); ?>
            <div class="at-skills-list">
                <?php foreach ($rawLangs as $lang): ?>
                <div class="at-lang-row">
                    <span class="at-lang-name"><?= e($lang) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($sec['type'] === 'experience'): ?>
            <!-- Work History -->
            <?php foreach ($items as $item): $f = $item['fields']; ?>
            <div class="at-row r-item" data-item-id="<?= $item['id'] ?>">
                <div class="at-date-col">
                    <?= e($f['start_date'] ?? '') ?>
                    <?php if (!empty($f['end_date'])): ?> to <?= e($f['end_date']) ?><?php endif; ?>
                </div>
                <div class="at-content-col">
                    <div class="at-item-title"><?= e($f['job_title'] ?? '') ?></div>
                    <div class="at-item-subtitle">
                        <strong class="at-item-sub-dash"><?= e($f['company'] ?? '') ?></strong>
                        <?php if (!empty($f['location'])): ?> – <?= e($f['location']) ?><?php endif; ?>
                    </div>
                    <?php if (!empty($f['description'])): ?>
                    <div class="at-item-desc">
                        <?php
                        $desc = $f['description'];
                        // If lines start with bullet markers, render as <ul>
                        $lines = array_filter(array_map('trim', explode("\n", $desc)));
                        $hasBullets = !empty($lines) && (substr($lines[array_key_first($lines)], 0, 1) === '-' || substr($lines[array_key_first($lines)], 0, 1) === '•');
                        if ($hasBullets):
                        ?>
                        <ul>
                            <?php foreach ($lines as $line): ?>
                            <li><?= e(ltrim($line, '-•· ')) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <?= nl2br(e($desc)) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif ($sec['type'] === 'education'): ?>
            <!-- Education -->
            <?php foreach ($items as $item): $f = $item['fields']; ?>
            <div class="at-row r-item" data-item-id="<?= $item['id'] ?>">
                <div class="at-date-col">
                    <?= e($f['start_date'] ?? '') ?>
                    <?php if (!empty($f['end_date'])): ?> to <?= e($f['end_date']) ?><?php endif; ?>
                </div>
                <div class="at-content-col">
                    <div class="at-item-title"><?= e($f['degree'] ?? '') ?></div>
                    <div class="at-item-subtitle">
                        <strong class="at-item-sub-dash"><?= e($f['institution'] ?? '') ?></strong>
                        <?php if (!empty($f['location'])): ?> - <?= e($f['location']) ?><?php endif; ?>
                    </div>
                    <?php if (!empty($f['gpa'])): ?>
                    <div class="at-item-subtitle">GPA: <?= e($f['gpa']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php elseif (in_array($sec['type'], ['certifications', 'awards', 'publications', 'references'])): ?>
            <!-- Bullet list sections -->
            <ul class="at-bullet-list">
                <?php foreach ($items as $item): $f = $item['fields']; ?>
                <li class="r-item" data-item-id="<?= $item['id'] ?>">
                    <strong><?= e($f['title'] ?? $f['name'] ?? '') ?></strong>
                    <?php if (!empty($f['date'])): ?> (<?= e($f['date']) ?>)<?php endif; ?>
                    <?php if (!empty($f['description'])): ?> — <?= e($f['description']) ?><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>

        <?php else: ?>
            <!-- Generic / Custom -->
            <?php foreach ($items as $item): $f = $item['fields']; ?>
            <div class="at-row r-item" data-item-id="<?= $item['id'] ?>">
                <div class="at-date-col"><?= e($f['date'] ?? '') ?></div>
                <div class="at-content-col">
                    <div class="at-item-title"><?= e($f['title'] ?? '') ?></div>
                    <?php if (!empty($f['description'])): ?>
                    <div class="at-item-desc"><?= nl2br(e($f['description'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </section>
    <?php endforeach; ?>

</div>
