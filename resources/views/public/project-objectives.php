<?php
ob_start();
$project = is_array($project ?? null) ? $project : null;
$objectives = is_array($objectives ?? null) ? $objectives : [];
$studentEvaluations = is_array($studentEvaluations ?? null) ? $studentEvaluations : [];
$editionQuery = isset($projectAcademicYearId) && $projectAcademicYearId > 0 ? '?edicio=' . (int) $projectAcademicYearId : '';

$colorMeta = [
    'blau' => ['background' => '#dbeafe', 'border' => '#2563eb'],
    'verd' => ['background' => '#dcfce7', 'border' => '#16a34a'],
    'taronja' => ['background' => '#ffedd5', 'border' => '#ea580c'],
    'vermell' => ['background' => '#fee2e2', 'border' => '#dc2626'],
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

    <div class="student-objectives-list">
        <?php if ($objectives !== []): ?>
            <?php foreach ($objectives as $index => $obj): ?>
                <?php
                $objectiveId = (int) ($obj['id'] ?? 0);
                $objectiveCode = trim((string) ($obj['codi'] ?? ''));
                $objectiveContentId = 'student-objective-content-' . $objectiveId;
                $activeColor = (string) ($studentEvaluations[$objectiveId] ?? '');
                ?>
                <section class="student-objective collapsible-card is-collapsed">
                    <header class="student-objective__header">
                        <div class="student-objective__title-group">
                            <?php if ($objectiveCode !== ''): ?>
                                <span class="pill student-objective__code"><?= htmlspecialchars($objectiveCode, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <h2>Objectiu d'aprenentatge <?= $index + 1 ?></h2>
                        </div>
                        <button class="collapse-toggle student-objective__toggle" type="button" data-collapse="<?= htmlspecialchars($objectiveContentId, ENT_QUOTES, 'UTF-8') ?>">Mostrar</button>
                    </header>

                    <div id="<?= htmlspecialchars($objectiveContentId, ENT_QUOTES, 'UTF-8') ?>" class="student-objective__content">
                        <p class="student-objective__description"><?= htmlspecialchars((string) ($obj['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

                        <?php foreach (($obj['criteria'] ?? []) as $criterionIndex => $criterion): ?>
                            <?php
                            $criterionCode = trim((string) ($criterion['codi'] ?? ''));
                            $criterionLabel = $criterionCode !== '' ? $criterionCode : 'Criteri ' . ($criterionIndex + 1);
                            ?>
                            <article class="student-criterion">
                                <h3><?= htmlspecialchars($criterionLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="student-criterion__description"><?= htmlspecialchars((string) ($criterion['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

                                <div class="student-indicators" aria-label="Descriptors simplificats">
                                    <?php foreach (($criterion['indicators'] ?? []) as $colorKey => $indicator): ?>
                                        <?php
                                        $descriptor = trim((string) ($indicator['simplificat'] ?? ''));
                                        if ($descriptor === '' || !isset($colorMeta[$colorKey])) {
                                            continue;
                                        }
                                        $isActive = $activeColor === $colorKey;
                                        ?>
                                        <div class="student-indicator student-indicator--<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?><?= $isActive ? ' is-active' : '' ?>" style="--student-indicator-background: <?= htmlspecialchars($colorMeta[$colorKey]['background'], ENT_QUOTES, 'UTF-8') ?>; --student-indicator-border: <?= htmlspecialchars($colorMeta[$colorKey]['border'], ENT_QUOTES, 'UTF-8') ?>;" aria-label="Descriptor simplificat"<?= $isActive ? ' aria-current="true"' : '' ?>>
                                            <p><?= htmlspecialchars($descriptor, ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
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
