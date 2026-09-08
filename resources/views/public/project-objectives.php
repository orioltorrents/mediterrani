<?php
ob_start();
$project = is_array($project ?? null) ? $project : null;
$objectives = is_array($objectives ?? null) ? $objectives : [];
$studentEvaluations = is_array($studentEvaluations ?? null) ? $studentEvaluations : [];
$editionQuery = isset($projectAcademicYearId) && $projectAcademicYearId > 0 ? '?edicio=' . (int) $projectAcademicYearId : '';

$colorMeta = [
    'vermell' => ['label' => 'No assolit', 'bg' => '#fef2f2', 'border' => '#fecaca', 'color' => '#b91c1c'],
    'groc' => ['label' => 'Assoliment satisfactori', 'bg' => '#fefce8', 'border' => '#fef08a', 'color' => '#a16207'],
    'verd_clar' => ['label' => 'Assoliment notable', 'bg' => '#f0fdf4', 'border' => '#bbf7d0', 'color' => '#15803d'],
    'verd_fosc' => ['label' => 'Assoliment excel·lent', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#047857'],
];
?>
<div class="public-project-detail">
    <div class="breadcrumb">
        <a href="<?= url(getLanguage() . '/projectes/' . ($project['slug'] ?? '')) . $editionQuery ?>">&larr; <?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <header class="public-project-detail__header card">
        <span class="public-project-detail__status status">Objectius d'aprenentatge</span>
        <h1 class="public-project-detail__title"><?= htmlspecialchars((string) ($project['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
    </header>

    <div class="card" style="margin-top: 1.5rem; padding: 1.5rem;">
        <?php if ($objectives !== []): ?>
            <div style="display: grid; gap: 1.75rem;">
                <?php foreach ($objectives as $index => $obj): ?>
                    <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: .75rem; margin-bottom: .75rem;">
                            <span class="pill" style="font-size: .9rem; padding: .3rem .75rem;"><?= htmlspecialchars((string) ($obj['codi'] ?? 'OA'), ENT_QUOTES, 'UTF-8') ?></span>
                            <h2 style="margin: 0; color: var(--ink); font-size: 1.2rem;">Objectiu d'aprenentatge <?= $index + 1 ?></h2>
                        </div>
                        <p style="margin-top: 0; margin-bottom: 1.25rem; line-height: 1.6; color: var(--text-body); font-size: 1.08rem; font-weight: 500;"><?= htmlspecialchars((string) ($obj['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php $activeColor = (string) ($studentEvaluations[(int) ($obj['id'] ?? 0)] ?? ''); ?>
                        <div class="student-achievement-lights" aria-label="Estat d'assoliment">
                            <?php foreach ($colorMeta as $colorKey => $meta): ?>
                                <?php $descriptor = (string) ($obj['indicators'][$colorKey] ?? ''); ?>
                                <span class="student-achievement-light student-achievement-light--<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?><?= $activeColor === $colorKey ? ' is-active' : '' ?>" title="<?= htmlspecialchars($meta['label'] . ($descriptor !== '' ? ': ' . $descriptor : ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($meta['label'] . ($descriptor !== '' ? ': ' . $descriptor : ''), ENT_QUOTES, 'UTF-8') ?>"></span>
                            <?php endforeach; ?>
                            <span class="student-achievement-status"><?= $activeColor !== '' ? 'Nivell actual: ' . htmlspecialchars($colorMeta[$activeColor]['label'] ?? $activeColor, ENT_QUOTES, 'UTF-8') : 'Pendent d’avaluació' ?></span>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No s'han trobat objectius d'aprenentatge definits per a aquest projecte.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
