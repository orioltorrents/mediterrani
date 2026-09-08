<?php
ob_start();
$gradeAchievementClass = static function (array $grade): string {
    if (($grade['type'] ?? '') !== 'achievement' || empty($grade['achievement_value'])) {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $grade['achievement_value']));

    if (!is_string($value) || $value === '') {
        return '';
    }

    return ' grade-achievement--' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};
?>
<?php if (($project ?? null) === null): ?>
    <section class="page-header">
        <h1><?= htmlspecialchars(trans('notes_not_found'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars(trans('requested_project_not_published'), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="button" href="<?= url(getLanguage() . '/projectes') ?>"><?= htmlspecialchars(trans('back_to_projects'), ENT_QUOTES, 'UTF-8') ?></a>
    </section>
<?php elseif (!empty($accessDenied)): ?>
    <?php $editionQuery = !empty($projectAcademicYearId) ? '?edicio=' . (int) $projectAcademicYearId : ''; ?>
    <section class="page-header">
        <h1><?= htmlspecialchars(trans('access_restricted'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars(trans('no_permission_notes'), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="button" href="<?= url(getLanguage() . '/projectes/' . $project['slug']) . $editionQuery ?>"><?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a>
    </section>
<?php else: ?>
    <?php
    $projectAcademicYear = $notes['projectAcademicYear'] ?? null;
    $editionQuery = !empty($projectAcademicYearId) ? '?edicio=' . (int) $projectAcademicYearId : '';
    ?>
    <article class="public-project-detail">
        <p class="breadcrumb public-project-detail__breadcrumb"><a href="<?= url(getLanguage() . '/projectes/' . $project['slug']) . $editionQuery ?>"><?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a></p>
        <div class="public-project-detail__hero">
            <div>
                <p class="public-project-detail__eyebrow"><?= htmlspecialchars(trans('projects'), ENT_QUOTES, 'UTF-8') ?><?php if (!empty($projectAcademicYear['academic_year_name'])): ?> · <?= htmlspecialchars((string) $projectAcademicYear['academic_year_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                <h1 class="public-project-detail__title"><?= htmlspecialchars(sprintf(trans('notes_of'), $project['title']), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="lead public-project-detail__lead"><?= htmlspecialchars(trans('notes_intro'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <span class="public-project-detail__status status"><?= htmlspecialchars(trans('section_notes'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </article>

    <?php if (is_array($notes) && array_key_exists('hasRecords', $notes)): ?>
        <?php $studentGrades = $notes; ?>
        <?php if (empty($studentGrades['hasRecords'])): ?>
            <div class="empty-state">
                <p><?= htmlspecialchars(trans('no_notes_yet'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php else: ?>
            <?php $metadata = $studentGrades['metadata'] ?? []; ?>
            <section class="grades-panel public-project-detail__grades">
                <div class="grades-panel__header public-project-detail__grades-header">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars(trans('student_private_space'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="grades-panel__title"><?= htmlspecialchars(sprintf(trans('my_project_notes'), (string) $project['title']), ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <?php if (!empty($metadata['imported_at'])): ?>
                        <span class="status"><?= htmlspecialchars(sprintf(trans('updated_at'), (string) $metadata['imported_at']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($metadata['group_name']) || !empty($metadata['team_code']) || !empty($metadata['role_name'])): ?>
                    <div class="grades-panel__meta public-project-detail__grades-meta">
                        <?php if (!empty($metadata['role_name'])): ?>
                            <span class="grades-panel__meta-item"><?= htmlspecialchars(sprintf(trans('role_label'), (string) $metadata['role_name']), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if (!empty($metadata['group_name']) || !empty($metadata['team_code'])): ?>
                            <span class="grades-panel__meta-item">
                                <?php if (!empty($metadata['group_name'])): ?>
                                    <?= htmlspecialchars(sprintf(trans('class_group_label'), (string) $metadata['group_name']), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                                <?php if (!empty($metadata['group_name']) && !empty($metadata['team_code'])): ?>
                                    ·
                                <?php endif; ?>
                                <?php if (!empty($metadata['team_code'])): ?>
                                    <?= htmlspecialchars(sprintf(trans('team_code_label'), (string) $metadata['team_code']), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($studentGrades['sections'])): ?>
                    <div class="grades-timeline public-project-detail__grades-timeline">
                        <?php foreach ($studentGrades['sections'] as $phase): ?>
                            <section class="grade-phase">
                                <div class="grade-phase__header">
                                    <h3 class="grade-phase__title"><?= htmlspecialchars((string) $phase['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if (!empty($phase['description'])): ?>
                                        <p class="grade-phase__description"><?= htmlspecialchars((string) $phase['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="grade-list grade-list--polished">
                                    <?php foreach ($phase['items'] as $grade): ?>
                                        <div class="grade-row grade-row--polished<?= $gradeAchievementClass($grade) ?>">
                                            <div class="grade-row__main">
                                                <span class="grade-row__label"><?= htmlspecialchars((string) $grade['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($grade['weight'])): ?>
                                                    <small class="grade-row__weight"><?= htmlspecialchars((string) $grade['weight'], ENT_QUOTES, 'UTF-8') ?> de la fase</small>
                                                <?php endif; ?>
                                            </div>
                                            <strong class="grade-row__value"><?= htmlspecialchars((string) $grade['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if (!empty($studentGrades['summary'])): ?>
                        <div class="grades-overview public-project-detail__grades-overview">
                            <?php foreach ($studentGrades['summary'] as $grade): ?>
                                <article class="grade-card grade-card--highlight<?= $gradeAchievementClass($grade) ?>">
                                    <span class="grade-card__label"><?= htmlspecialchars((string) $grade['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong class="grade-card__value"><?= htmlspecialchars((string) $grade['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($studentGrades['grades'])): ?>
                        <section class="grades-section public-project-detail__grades-section">
                            <h3 class="grades-section__title"><?= htmlspecialchars(trans('notes_and_achievements'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="grade-list">
                                <?php foreach ($studentGrades['grades'] as $grade): ?>
                                    <div class="grade-row<?= $gradeAchievementClass($grade) ?>">
                                        <span class="grade-row__label"><?= htmlspecialchars((string) $grade['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong class="grade-row__value"><?= htmlspecialchars((string) $grade['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($studentGrades['final'])): ?>
                    <section class="grades-final public-project-detail__grades-final">
                        <div>
                            <p class="eyebrow"><?= htmlspecialchars(trans('final_result'), ENT_QUOTES, 'UTF-8') ?></p>
                            <h3 class="grades-final__title"><?= htmlspecialchars(trans('final_grade_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <div class="grades-final__grid">
                            <?php foreach ($studentGrades['final'] as $grade): ?>
                                <article class="grade-final-card<?= $gradeAchievementClass($grade) ?>">
                                    <span class="grade-final-card__label"><?= htmlspecialchars((string) $grade['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong class="grade-final-card__value"><?= htmlspecialchars((string) $grade['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($studentGrades['comments'])): ?>
                    <section class="grades-section public-project-detail__grades-section">
                        <h3 class="grades-section__title"><?= htmlspecialchars(trans('comments_observations'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="grade-comments">
                            <?php foreach ($studentGrades['comments'] as $comment): ?>
                                <details class="grade-comment">
                                    <summary class="grade-comment__summary"><?= htmlspecialchars((string) $comment['label'], ENT_QUOTES, 'UTF-8') ?></summary>
                                    <p class="grade-comment__text"><?= nl2br(htmlspecialchars((string) $comment['value'], ENT_QUOTES, 'UTF-8')) ?></p>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <div class="project-notes__summary-grid">
            <article class="grade-card grade-card--highlight">
                <span class="grade-card__label"><?= htmlspecialchars(trans('records'), ENT_QUOTES, 'UTF-8') ?></span>
                <strong class="grade-card__value"><?= (int) ($notes['summary']['total_records'] ?? 0) ?></strong>
            </article>
            <article class="grade-card grade-card--highlight">
                <span class="grade-card__label"><?= htmlspecialchars(trans('students'), ENT_QUOTES, 'UTF-8') ?></span>
                <strong class="grade-card__value"><?= (int) ($notes['summary']['total_students'] ?? 0) ?></strong>
            </article>
            <article class="grade-card grade-card--highlight">
                <span class="grade-card__label"><?= htmlspecialchars(trans('latest_import'), ENT_QUOTES, 'UTF-8') ?></span>
                <strong class="grade-card__value" style="font-size: 1rem;"><?= !empty($notes['summary']['latest_imported_at']) ? htmlspecialchars((string) $notes['summary']['latest_imported_at'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(trans('no_data'), ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
        </div>

        <?php if (!empty($notes['labels'])): ?>
            <section class="grades-section public-project-detail__grades-section">
                <h3 class="grades-section__title"><?= htmlspecialchars(trans('highlighted_labels'), ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="grade-list">
                    <?php foreach ($notes['labels'] as $label): ?>
                        <div class="grade-row">
                            <span class="grade-row__label"><?= htmlspecialchars((string) $label['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="grade-row__value"><?= (int) $label['total'] ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
