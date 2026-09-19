<?php
/** @var mixed $projects */
/** @var mixed $projectAcademicYearsByProject */
/** @var mixed $projectAssignmentsByProjectClass */
/** @var mixed $classes */
/** @var mixed $csrfToken */
?>
<section id="projectes" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Projectes</h2>
                <div class="admin-actions"><span class="status"><?= count($projects) ?> projectes</span><button class="collapse-toggle" type="button" data-collapse="projectes-content">Mostrar</button></div>
            </div>
            <div id="projectes-content" class="admin-collapsible__content">
                <div class="admin-projects-grid">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectId = (int) ($project['id'] ?? 0);
                        $projectEditions = $projectAcademicYearsByProject[$projectId] ?? [];
                        $assignmentStatuses = $projectAssignmentsByProjectClass[$projectId] ?? [];
                        $isActiveProject = (int) ($project['is_active'] ?? 0) === 1;
                        ?>
                        <article class="project-admin-card admin-collapsible is-collapsed">
                            <div class="project-admin-card__header">
                                <div class="project-admin-card__title">
                                    <strong><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="status <?= $isActiveProject ? 'status--active' : 'status--inactive' ?>"><?= $isActiveProject ? 'Actiu' : 'Inactiu' ?></span>
                                <form class="inline-form" method="post" action="<?= url('admin') ?>">
                                    <input type="hidden" name="action" value="toggle_project">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                    <button class="button button--small" type="submit"><?= $isActiveProject ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                                <button class="collapse-toggle" type="button" data-collapse="project-card-<?= $projectId ?>">Mostrar</button>
                            </div>

                            <div id="project-card-<?= $projectId ?>" class="admin-collapsible__content project-admin-card__content">
                                <section class="admin-project-block">
                                    <div class="admin-panel__header admin-panel__header--compact">
                                        <h3>Edicions acadèmiques</h3>
                                        <span class="status"><?= count($projectEditions) ?> edicions</span>
                                    </div>
                                    <?php if ($projectEditions !== []): ?>
                                        <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                            <input type="hidden" name="action" value="update_project_academic_year_statuses">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                            <div class="admin-table__wrapper">
                                                <table class="admin-table admin-table--compact">
                                                    <thead><tr><th>Curs</th><th>Estat d'edició</th></tr></thead>
                                                    <tbody>
                                                        <?php foreach ($projectEditions as $edition): ?>
                                                            <?php $editionStatus = (string) ($edition['status'] ?? 'pendent'); ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars((string) ($edition['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td>
                                                                    <select name="project_academic_year_statuses[<?= (int) ($edition['id'] ?? 0) ?>]">
                                                                        <?php foreach (['pendent', 'actiu', 'realitzat', 'arxivat'] as $status): ?>
                                                                            <option value="<?= $status ?>" <?= $editionStatus === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <button class="button" type="submit">Guardar estats d'edició</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="muted">Aquest projecte encara no té edicions acadèmiques.</p>
                                    <?php endif; ?>
                                </section>

                                <section class="admin-project-block">
                                    <div class="admin-panel__header admin-panel__header--compact">
                                        <h3>Assignacions a classes</h3>
                                        <span class="status"><?= count($classes) ?> classes</span>
                                    </div>
                                    <?php if ($classes !== []): ?>
                                        <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                            <input type="hidden" name="action" value="sync_project_class_assignments">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                            <div class="admin-table__wrapper">
                                                <table class="admin-table admin-table--compact">
                                                    <thead><tr><th>Classe</th><th>Curs</th><th>Assignació</th></tr></thead>
                                                    <tbody>
                                                        <?php foreach ($classes as $class): ?>
                                                            <?php $assignmentStatus = $assignmentStatuses[(int) ($class['id'] ?? 0)] ?? 'no_assignat'; ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars((string) ($class['code'] ?? $class['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars((string) ($class['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td>
                                                                    <select name="class_statuses[<?= (int) ($class['id'] ?? 0) ?>]">
                                                                        <?php foreach (['no_assignat' => 'No assignat', 'pendent' => 'Pendent', 'actiu' => 'Actiu', 'realitzat' => 'Realitzat'] as $value => $label): ?>
                                                                            <option value="<?= $value ?>" <?= $assignmentStatus === $value ? 'selected' : '' ?>><?= $label ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <button class="button" type="submit">Guardar assignacions</button>
                                        </form>
                                    <?php else: ?>
                                        <p class="muted">Encara no hi ha classes creades.</p>
                                    <?php endif; ?>
                                </section>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
