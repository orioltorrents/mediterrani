<?php
/** @var mixed $objectives */
/** @var mixed $projectAcademicYears */
/** @var mixed $projectYearObjectivesMap */
/** @var mixed $projectNamesById */
/** @var mixed $csrfToken */
/** @var mixed $renderProjectOptions */
/** @var mixed $renderObjectiveChoices */
?>
<section id="objectius" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Objectius d'aprenentatge</h2>
                <div class="admin-actions"><span class="status"><?= count($objectives) ?> objectius</span><button class="collapse-toggle" type="button" data-collapse="objectius-content">Mostrar</button></div>
            </div>
            <div id="objectius-content" class="admin-collapsible__content">
                <div class="admin-panels admin-panels--stacked">
                    <section class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Crear objectiu</h3>
                            <button class="collapse-toggle" type="button" data-collapse="crear-objectiu-content">Mostrar</button>
                        </div>
                        <div id="crear-objectiu-content" class="admin-collapsible__content">
                            <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                <input type="hidden" name="action" value="create_objective">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form__grid form__grid--compact">
                                    <label>Projecte<select name="project_id"><?php $renderProjectOptions(null); ?></select></label>
                                    <label>Codi (ex. OA1)<input type="text" name="codi" required></label>
                                    <label style="grid-column: 1 / -1;">Descripció de l'objectiu<textarea name="titol" rows="3" required style="width: 100%; border: 1px solid var(--border-soft); border-radius: 8px; padding: .6rem; font: inherit;"></textarea></label>
                                </div>
                                <button class="button" type="submit" style="margin-top: 1rem;">Crear objectiu</button>
                            </form>
                        </div>
                    </section>

                    <section class="card admin-subpanel admin-collapsible">
                        <div class="admin-panel__header">
                            <h3>Llista d'objectius i assignació per edició de projecte</h3>
                            <button class="collapse-toggle" type="button" data-collapse="llista-objectius-content">Amagar</button>
                        </div>
                        <div id="llista-objectius-content" class="admin-collapsible__content">
                            <?php if ($objectives !== []): ?>
                                <div class="admin-table__wrapper" style="margin-bottom: 1.5rem;">
                                    <table class="admin-table admin-table--compact">
                                        <thead><tr><th>Codi</th><th>Descripció</th><th>Accions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($objectives as $obj): ?>
                                                <?php $objId = (int) ($obj['id'] ?? 0); ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars((string) ($obj['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                    <td><?= htmlspecialchars((string) ($obj['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><button class="button button--small" type="button" data-target="objective-editor-<?= $objId ?>">Editar</button></td>
                                                </tr>
                                                <tr id="objective-editor-<?= $objId ?>" class="student-editor-row">
                                                    <td colspan="3">
                                                        <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>">
                                                            <input type="hidden" name="action" value="update_objective">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="objective_id" value="<?= $objId ?>">
                                                            <div class="form__grid form__grid--compact">
                                                                <label>Projecte<select name="project_id"><?php $renderProjectOptions(isset($obj['project_id']) ? (int) $obj['project_id'] : null); ?></select></label>
                                                                <label>Codi<input type="text" name="codi" value="<?= htmlspecialchars((string) ($obj['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
                                                                <label style="grid-column: 1 / -1;">Descripció<textarea name="titol" rows="2" style="width: 100%; border: 1px solid var(--border-soft); border-radius: 8px; padding: .5rem; font: inherit;" required><?= htmlspecialchars((string) ($obj['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
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
                                <p class="muted">Encara no hi ha cap objectiu creat.</p>
                            <?php endif; ?>

                            <?php if ($projectAcademicYears !== []): ?>
                                <h3 style="margin-top: 1.5rem; margin-bottom: .75rem;">Assignació d'objectius per edició de projecte</h3>
                                <div class="admin-projects-grid">
                                    <?php foreach ($projectAcademicYears as $edition): ?>
                                         <?php
                                        $editionId = (int) ($edition['id'] ?? 0);
                                        $editionProjectName = (string) ($edition['project_name'] ?? ($projectNamesById[(int) ($edition['project_id'] ?? 0)] ?? 'Projecte'));
                                        $editionYearName = (string) ($edition['academic_year_name'] ?? '');
                                        $linkedObjIds = $projectYearObjectivesMap[$editionId] ?? [];
                                        ?>
                                        <article class="project-admin-card admin-collapsible is-collapsed">
                                            <div class="project-admin-card__header">
                                                <div class="project-admin-card__title">
                                                    <strong><?= htmlspecialchars($editionProjectName, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($editionYearName, ENT_QUOTES, 'UTF-8') ?>)</strong>
                                                    <span><?= count($linkedObjIds) ?> objectius assignats</span>
                                                </div>
                                                <button class="collapse-toggle" type="button" data-collapse="edition-obj-<?= $editionId ?>">Mostrar</button>
                                            </div>
                                            <div id="edition-obj-<?= $editionId ?>" class="admin-collapsible__content project-admin-card__content">
                                                <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                                    <input type="hidden" name="action" value="sync_project_objectives">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="project_academic_year_id" value="<?= $editionId ?>">
                                                    <div class="form__group">
                                                        <label>Selecciona els objectius per a aquesta edició:</label>
                                                        <div class="form__choices" style="display: grid; gap: .35rem; margin-top: .5rem;">
                                                            <?php $renderObjectiveChoices($linkedObjIds, (int) ($edition['project_id'] ?? 0)); ?>
                                                        </div>
                                                    </div>
                                                    <button class="button" type="submit" style="margin-top: 1rem;">Guardar objectius d'edició</button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </section>
