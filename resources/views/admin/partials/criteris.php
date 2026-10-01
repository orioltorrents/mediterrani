<?php
/** @var mixed $objectives */
/** @var mixed $criteriaByObjective */
/** @var mixed $projectNamesById */
/** @var mixed $csrfToken */
?>
<section id="criteris" class="card admin-panel admin-collapsible is-collapsed">
    <div class="admin-panel__header">
        <h2>Criteris d'assoliment dels objectius d'aprenentatge</h2>
        <div class="admin-actions">
            <span class="status">Per objectiu d'aprenentatge</span>
            <button class="collapse-toggle" type="button" data-collapse="criteris-content">Mostrar</button>
        </div>
    </div>
        <div id="criteris-content" class="admin-collapsible__content">
        <?php if ($objectives !== []): ?>
            <?php
            $objectivesByProject = [];
            foreach ($objectives as $objective) {
                $projectId = (int) ($objective['project_id'] ?? 0);
                $objectivesByProject[$projectId][] = $objective;
            }
            ?>
            <div class="admin-projects-grid">
                <?php foreach ($objectivesByProject as $projectId => $projectObjectives): ?>
                    <?php
                    $projectName = (string) ($projectNamesById[(int) $projectId] ?? 'Projecte general');
                    $projectContentId = 'criteris-projecte-content-' . (int) $projectId;
                    $projectCriteriaCount = 0;
                    foreach ($projectObjectives as $projectObjective) {
                        $projectCriteriaCount += count($criteriaByObjective[(int) ($projectObjective['id'] ?? 0)] ?? []);
                    }
                    ?>
                    <article class="project-admin-card admin-collapsible is-collapsed">
                        <div class="project-admin-card__header">
                            <div class="project-admin-card__title">
                                <strong><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= count($projectObjectives) ?> OA · <?= $projectCriteriaCount ?> CA</span>
                            </div>
                            <button class="collapse-toggle" type="button" data-collapse="<?= htmlspecialchars($projectContentId, ENT_QUOTES, 'UTF-8') ?>">Mostrar</button>
                        </div>
                        <div id="<?= htmlspecialchars($projectContentId, ENT_QUOTES, 'UTF-8') ?>" class="admin-collapsible__content project-admin-card__content">
                            <div class="admin-projects-grid">
                <?php foreach ($projectObjectives as $objective): ?>
                    <?php
                    $objectiveId = (int) ($objective['id'] ?? 0);
                    $objectiveCriteria = $criteriaByObjective[$objectiveId] ?? [];
                    $objectiveLabel = trim((string) ($objective['codi'] ?? ''));
                    $objectiveDescription = (string) ($objective['descripcio_simplificada'] ?? $objective['descripcio_completa'] ?? '');
                    $contentId = 'criteris-objectiu-content-' . $objectiveId;
                    ?>
                    <article class="project-admin-card admin-collapsible is-collapsed" data-objective-card>
                        <div class="project-admin-card__header">
                            <div class="project-admin-card__title">
                                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: .4rem;">
                                    <strong><?= htmlspecialchars($objectiveLabel !== '' ? $objectiveLabel : 'Objectiu ' . $objectiveId, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <button class="button button--small criteria-description-toggle is-active" type="button" data-objective-description-mode="completa" aria-pressed="true">Completa</button>
                                    <button class="button button--small criteria-description-toggle is-active" type="button" data-objective-description-mode="simplificada" aria-pressed="true">Simplificada</button>
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="collapse-toggle" type="button" data-collapse="<?= htmlspecialchars($contentId, ENT_QUOTES, 'UTF-8') ?>">Mostrar</button>
                            </div>
                        </div>
                        <div id="<?= htmlspecialchars($contentId, ENT_QUOTES, 'UTF-8') ?>" class="admin-collapsible__content project-admin-card__content">
                            <div data-objective-descriptions style="margin-bottom: 1rem;">
                                <p data-objective-description="completa"><strong>Descripció completa:</strong><br><?= htmlspecialchars((string) ($objective['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p data-objective-description="simplificada"><strong>Descripció simplificada:</strong><br><?= htmlspecialchars($objectiveDescription, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <div class="status" style="margin-bottom: 1rem;">
                                <?= count($objectiveCriteria) ?> criteris d'avaluació
                            </div>
                            <h3 style="margin: 0 0 1rem;">Criteris d'avaluació de l'objectiu d'aprenentatge</h3>

                            <?php if ($objectiveCriteria !== []): ?>
                                <div class="admin-table__wrapper" style="margin-top: 1rem;">
                                    <table class="admin-table admin-table--compact">
                                        <thead>
                                            <tr><th>Codi</th><th>Descripció completa</th><th>Descripció simplificada</th><th>Accions</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($objectiveCriteria as $criterion): ?>
                                                <?php $criterionId = (int) ($criterion['id'] ?? 0); ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) ($criterion['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($criterion['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><?= htmlspecialchars((string) ($criterion['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><button class="button button--small" type="button" data-target="criterion-editor-<?= $criterionId ?>">Editar</button></td>
                                                </tr>
                                                <tr id="criterion-editor-<?= $criterionId ?>" class="student-editor-row">
                                                    <td colspan="4">
                                                        <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>">
                                                            <input type="hidden" name="action" value="update_criterion">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="criterion_id" value="<?= $criterionId ?>">
                                                            <input type="hidden" name="objective_id" value="<?= $objectiveId ?>">
                                                            <div class="form__grid form__grid--compact">
                                                                <label>Codi
                                                                    <input type="text" name="codi" maxlength="50" value="<?= htmlspecialchars((string) ($criterion['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                </label>
                                                                <label>Descripció completa
                                                                    <textarea name="descripcio_completa" rows="3" required><?= htmlspecialchars((string) ($criterion['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                                </label>
                                                                <label>Descripció simplificada
                                                                    <textarea name="descripcio_simplificada" rows="3" required><?= htmlspecialchars((string) ($criterion['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
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
                                <p class="muted" style="margin-top: 1rem;">Aquest objectiu encara no té criteris d'assoliment.</p>
                            <?php endif; ?>

                            <section class="card admin-subpanel" style="margin-top: 1rem;">
                                <h3>Afegir criteri a <?= htmlspecialchars($objectiveLabel !== '' ? $objectiveLabel : "l'objectiu", ENT_QUOTES, 'UTF-8') ?></h3>
                                <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                    <input type="hidden" name="action" value="create_criterion">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="objective_id" value="<?= $objectiveId ?>">
                                    <div class="form__grid form__grid--compact">
                                        <label>Codi
                                            <input type="text" name="codi" maxlength="50" placeholder="Opcional">
                                        </label>
                                        <label>Descripció completa
                                            <textarea name="descripcio_completa" rows="3" required></textarea>
                                        </label>
                                        <label>Descripció simplificada
                                            <textarea name="descripcio_simplificada" rows="3" required></textarea>
                                        </label>
                                    </div>
                                    <button class="button" type="submit" style="margin-top: 1rem;">Crear criteri</button>
                                </form>
                            </section>
                        </div>
                    </article>
                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Primer has de crear objectius d'aprenentatge.</p>
        <?php endif; ?>
    </div>
</section>
