<?php
ob_start();
?>
<section class="student-dashboard__hero">
    <div>
        <p class="student-dashboard__eyebrow"><?= htmlspecialchars(trans('student_space'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="student-dashboard__title"><?= htmlspecialchars(trans('your_projects'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="student-dashboard__text"><?= htmlspecialchars(trans('student_dashboard_intro'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
</section>

<?php if (empty($classes)): ?>
    <div class="empty-state student-dashboard__empty">
        <p><?= htmlspecialchars(trans('no_projects_yet'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php else: ?>
    <div class="student-dashboard__classes">
        <?php foreach ($classes as $class): ?>
            <section class="student-dashboard__class">
                <div class="student-dashboard__class-header">
                    <h2 class="student-dashboard__class-title"><?= htmlspecialchars($class['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <span class="status student-dashboard__class-count"><?= sprintf(trans('projects_count'), count($class['projects'])) ?></span>
                </div>
                <div class="student-dashboard__projects">
                <?php foreach ($class['projects'] as $project): ?>
                    <?php $projectAsset = $project['logo_asset'] ?? ($project['assets'][0] ?? null); ?>
                    <article class="student-project-card project-card project-card--student<?= empty($projectAsset['logo_path']) ? ' project-card--without-media' : '' ?>">
                        <?php if (!empty($projectAsset['logo_path'])): ?>
                            <div class="student-project-card__media project-card__media">
                                <img class="student-project-card__logo project-card__logo" src="<?= url((string) $projectAsset['logo_path']) ?>" alt="<?= htmlspecialchars((string) $projectAsset['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="student-project-card__body project-card__body">
                            <div class="student-project-card__header project-card__header">
                                <h3 class="student-project-card__title project-card__title"><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="student-project-card__status project-card__status status"><?= htmlspecialchars(trans('status_' . $project['status']), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if ($project['description'] !== ''): ?>
                                <p class="student-project-card__text project-card__text"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="student-project-card__actions project-card__actions">
                                <a class="button student-project-card__button project-card__button" href="<?= url(getLanguage() . '/projectes/' . $project['slug']) ?>?edicio=<?= (int) ($project['project_academic_year_id'] ?? 0) ?>"><?= htmlspecialchars(trans('open'), ENT_QUOTES, 'UTF-8') ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
