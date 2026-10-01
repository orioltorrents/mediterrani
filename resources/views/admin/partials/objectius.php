<?php
/** @var mixed $objectives */
/** @var mixed $projectAcademicYears */
/** @var mixed $projectYearObjectivesMap */
/** @var mixed $projectNamesById */
/** @var mixed $projects */
/** @var mixed $csrfToken */
?>
<section id="objectius" class="card admin-panel admin-collapsible is-collapsed">
    <div class="admin-panel__header">
        <h2>Objectius d'aprenentatge</h2>
        <div class="admin-actions">
            <span class="status"><?= count($objectives) ?> objectius</span>
            <button class="collapse-toggle" type="button" data-collapse="objectius-content">Mostrar</button>
        </div>
    </div>
    <div id="objectius-content" class="admin-collapsible__content">
        <?php $objectivesById = []; foreach ($objectives as $objective) { $objectivesById[(int) ($objective['id'] ?? 0)] = $objective; } ?>

        <?php if ($projectAcademicYears !== []): ?>
            <h3>Objectius per projecte i edició</h3>
            <div class="admin-projects-grid">
                <?php foreach ($projectAcademicYears as $edition): ?>
                    <?php
                    $editionId = (int) ($edition['id'] ?? 0);
                    $editionProjectId = (int) ($edition['project_id'] ?? 0);
                    $editionProjectName = (string) ($edition['project_name'] ?? ($projectNamesById[$editionProjectId] ?? 'Projecte'));
                    $editionYearName = (string) ($edition['academic_year_name'] ?? '');
                    $linkedObjIds = $projectYearObjectivesMap[$editionId] ?? [];
                    $editionContentId = 'objectius-edicio-content-' . $editionId;
                    ?>
                    <article class="project-admin-card admin-collapsible is-collapsed">
                        <div class="project-admin-card__header">
                            <div class="project-admin-card__title">
                                <strong><?= htmlspecialchars($editionProjectName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($editionYearName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= count($linkedObjIds) ?> objectius assignats</span>
                            </div>
                            <button class="collapse-toggle" type="button" data-collapse="<?= htmlspecialchars($editionContentId, ENT_QUOTES, 'UTF-8') ?>">Mostrar</button>
                        </div>
                        <div id="<?= htmlspecialchars($editionContentId, ENT_QUOTES, 'UTF-8') ?>" class="admin-collapsible__content project-admin-card__content">
                            <?php if ($linkedObjIds !== []): ?>
                                <div class="admin-table__wrapper">
                                    <table class="admin-table admin-table--compact">
                                        <thead><tr><th>Codi</th><th>Descripció completa</th><th>Descripció simplificada</th><th>Accions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($linkedObjIds as $objectiveId): ?>
                                                <?php $objective = $objectivesById[(int) $objectiveId] ?? null; if ($objective === null) continue; $editorId = 'objective-editor-' . (int) $objectiveId . '-' . $editionId; ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars((string) ($objective['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                    <td><?= htmlspecialchars((string) ($objective['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($objective['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><button class="button button--small" type="button" data-target="<?= htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8') ?>">Editar</button></td>
                                                </tr>
                                                <tr id="<?= htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8') ?>" class="student-editor-row">
                                                    <td colspan="4">
                                                        <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>">
                                                            <input type="hidden" name="action" value="update_objective">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="objective_id" value="<?= (int) $objectiveId ?>">
                                                            <input type="hidden" name="project_id" value="<?= (int) ($objective['project_id'] ?? $editionProjectId) ?>">
                                                            <div class="form__grid form__grid--compact">
                                                                <label>Codi
                                                                    <input type="text" name="codi" value="<?= htmlspecialchars((string) ($objective['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                                </label>
                                                                <label>Descripció completa
                                                                    <textarea name="descripcio_completa" rows="3" required><?= htmlspecialchars((string) ($objective['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                                </label>
                                                                <label>Descripció simplificada
                                                                    <textarea name="descripcio_simplificada" rows="3" required><?= htmlspecialchars((string) ($objective['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                                </label>
                                                            </div>
                                                            <button class="button" type="submit" style="margin-top: .75rem;">Guardar canvis</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="muted">No hi ha objectius assignats a aquesta edició.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="card admin-subpanel" style="margin-top: 1.5rem;">
            <h3>Afegir objectiu d'aprenentatge</h3>
            <form class="admin-form" method="post" action="<?= url('admin') ?>">
                <input type="hidden" name="action" value="create_objective">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="form__grid form__grid--compact">
                    <label>Projecte
                        <select name="project_id" data-objective-project-select required>
                            <option value="">Selecciona un projecte</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) ($project['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Edició / curs
                        <select name="project_academic_year_id" data-objective-edition-select required disabled>
                            <option value="">Selecciona primer un projecte</option>
                            <?php foreach ($projectAcademicYears as $edition): ?>
                                <option value="<?= (int) ($edition['id'] ?? 0) ?>" data-project-id="<?= (int) ($edition['project_id'] ?? 0) ?>" hidden>
                                    <?= htmlspecialchars((string) ($edition['project_name'] ?? 'Projecte'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($edition['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Codi (ex. OA4)
                        <input type="text" name="codi" required>
                    </label>
                    <label>Descripció completa
                        <textarea name="descripcio_completa" rows="3" required></textarea>
                    </label>
                    <label>Descripció simplificada
                        <textarea name="descripcio_simplificada" rows="3" required></textarea>
                    </label>
                </div>
                <button class="button" type="submit" style="margin-top: 1rem;">Crear objectiu</button>
            </form>
        </section>
    </div>
</section>
