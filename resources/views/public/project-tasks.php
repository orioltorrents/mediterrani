<?php
ob_start();
?>
<?php if (($project ?? null) === null): ?>
    <section class="page-header">
        <h1><?= htmlspecialchars(trans('tasks_not_found'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars(trans('project_not_found_desc'), ENT_QUOTES, 'UTF-8') ?></p>
        <a class="button" href="<?= url(getLanguage() . '/projectes') ?>"><?= htmlspecialchars(trans('back_to_projects'), ENT_QUOTES, 'UTF-8') ?></a>
    </section>
<?php else: ?>
    <?php $editionQuery = !empty($projectAcademicYearId) ? '?edicio=' . (int) $projectAcademicYearId : ''; ?>
    <?php $academicYearName = (string) ($projectAcademicYear['academic_year_name'] ?? ''); ?>
    <?php $teamCodes = array_values(array_filter(array_map('strval', $context['team_codes'] ?? []))); ?>
    <?php $projectRoles = array_values(array_filter(array_map('strval', $context['project_roles'] ?? []))); ?>
    <article class="public-project-detail">
        <p class="breadcrumb public-project-detail__breadcrumb"><a href="<?= url(getLanguage() . '/projectes/' . $project['slug']) . $editionQuery ?>"><?= htmlspecialchars(trans('back_to_project'), ENT_QUOTES, 'UTF-8') ?></a></p>
        <div class="public-project-detail__hero project-tasks-hero">
            <div>
                <h1 class="public-project-detail__title"><?= htmlspecialchars(sprintf(trans('your_tasks_of'), $project['title']), ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="project-task-context" aria-label="Context de les tasques">
                    <span class="project-task-context__pill">
                        <span><?= htmlspecialchars(trans('project_and_year'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars((string) $project['title'], ENT_QUOTES, 'UTF-8') ?><?= $academicYearName !== '' ? ' · ' . htmlspecialchars($academicYearName, ENT_QUOTES, 'UTF-8') : '' ?></strong>
                    </span>
                    <span class="project-task-context__pill">
                        <span><?= htmlspecialchars(trans('your_team_code'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars($teamCodes !== [] ? implode(', ', $teamCodes) : trans('no_team_assigned'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                    <span class="project-task-context__pill">
                        <span><?= htmlspecialchars(trans('your_team_role'), ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars($projectRoles !== [] ? implode(', ', $projectRoles) : trans('no_role_assigned'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </div>
            </div>
        </div>
    </article>

    <?php if (!empty($tasks)): ?>
        <div class="project-task-phases">
            <?php foreach ($tasks as $phase): ?>
                <article class="project-task-phase card">
                    <div class="project-task-phase__header">
                        <h2 class="project-task-phase__title"><?= htmlspecialchars((string) $phase['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if (!empty($phase['description'])): ?>
                            <p class="project-task-phase__description"><?= htmlspecialchars((string) $phase['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <ul class="project-task-list">
                        <?php foreach ($phase['items'] as $task): ?>
                            <li class="project-task-item">
                                <div class="project-task-item__header">
                                    <strong><?= htmlspecialchars((string) $task['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($task['task_url'])): ?>
                                        <a class="project-task-item__classroom-link" href="<?= htmlspecialchars((string) $task['task_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars(trans('open_task_in_classroom'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(trans('open_task_in_classroom'), ENT_QUOTES, 'UTF-8') ?>">↗</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($task['description'])): ?>
                                    <p><?= htmlspecialchars((string) $task['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p><?= htmlspecialchars(trans('no_tasks_visible'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
