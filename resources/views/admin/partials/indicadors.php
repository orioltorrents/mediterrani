<?php
/** @var mixed $objectives */
/** @var mixed $criteriaByObjective */
/** @var mixed $indicators */
/** @var mixed $csrfToken */

$indicatorColors = [
    'blau' => ['code' => 'AE', 'label' => 'Assoliment excel·lent'],
    'verd' => ['code' => 'AN', 'label' => 'Assoliment notable'],
    'taronja' => ['code' => 'AS', 'label' => 'Assoliment satisfactori'],
    'vermell' => ['code' => 'NA', 'label' => 'No assolit'],
];
?>
<section id="indicadors" class="card admin-panel admin-collapsible is-collapsed">
    <div class="admin-panel__header">
        <h2>Indicadors d'assoliment</h2>
        <div class="admin-actions">
            <span class="status">Per criteri d'assoliment</span>
            <button class="collapse-toggle" type="button" data-collapse="indicadors-content">Mostrar</button>
        </div>
    </div>
    <div id="indicadors-content" class="admin-collapsible__content">
        <?php if ($objectives !== []): ?>
            <div class="indicator-objectives">
                <?php foreach ($objectives as $objective): ?>
                    <?php
                    $objectiveId = (int) ($objective['id'] ?? 0);
                    $objectiveCriteria = $criteriaByObjective[$objectiveId] ?? [];
                    if ($objectiveCriteria === []) {
                        continue;
                    }
                    $indicatorCardId = 'indicator-objective-' . $objectiveId;
                    ?>
                    <section class="card admin-subpanel indicator-objective admin-collapsible is-collapsed" data-indicator-card>
                        <div class="indicator-objective__header">
                            <div>
                                <h3>
                                    <?= htmlspecialchars((string) ($objective['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                            </div>
                            <div class="indicator-objective__actions">
                                <button class="collapse-toggle" type="button" data-collapse="<?= htmlspecialchars($indicatorCardId, ENT_QUOTES, 'UTF-8') ?>">Mostrar</button>
                            </div>
                        </div>

                        <div id="<?= htmlspecialchars($indicatorCardId, ENT_QUOTES, 'UTF-8') ?>" class="admin-collapsible__content indicator-objective__content">
                            <div class="indicator-objective__identity">
                                <strong><?= htmlspecialchars((string) ($objective['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="indicator-description-toggles" aria-label="Descripció de l'objectiu">
                                    <span>Descripció de l'OA:</span>
                                    <button class="button button--small criteria-description-toggle is-active" type="button" data-indicator-objective-description-mode="completa" aria-pressed="true">Completa</button>
                                    <button class="button button--small criteria-description-toggle is-active" type="button" data-indicator-objective-description-mode="simplificada" aria-pressed="true">Simplificada</button>
                                </div>
                            </div>
                            <div class="indicator-objective__descriptions">
                                <p data-indicator-objective-description="completa"><strong>Descripció completa:</strong><br><?= htmlspecialchars((string) ($objective['descripcio_completa'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p data-indicator-objective-description="simplificada"><strong>Descripció simplificada:</strong><br><?= htmlspecialchars((string) ($objective['descripcio_simplificada'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="indicator-objective__criteria-count">
                                <?= count($objectiveCriteria) ?> criteris
                            </div>
                            <div class="indicator-level-toggles" aria-label="Descriptors dels indicadors">
                                <span>Descriptors:</span>
                                <button class="button button--small criteria-description-toggle is-active" type="button" data-indicator-description-mode="complet" aria-pressed="true">Complets</button>
                                <button class="button button--small criteria-description-toggle is-active" type="button" data-indicator-description-mode="simplificat" aria-pressed="true">Simplificats</button>
                            </div>
                            <div class="admin-table__wrapper indicator-table-wrapper">
                            <table class="admin-table achievement-indicators-table">
                                <thead>
                                    <tr>
                                        <th>Criteri</th>
                                        <?php foreach ($indicatorColors as $colorKey => $color): ?>
                                            <th class="indicator-table__color-heading indicator-table__color-heading--<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?>">
                                                <span title="<?= htmlspecialchars($color['label'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($color['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($objectiveCriteria as $criterion): ?>
                                        <?php
                                        $criterionId = (int) ($criterion['id'] ?? 0);
                                        $criterionCode = trim((string) ($criterion['codi'] ?? ''));
                                        $criterionLabel = $criterionCode !== '' ? $criterionCode : 'CA' . $criterionId;
                                        $criterionIndicators = $indicators[$criterionId] ?? [];
                                        $editorId = 'indicator-editor-' . $criterionId . '-' . $objectiveId;
                                        ?>
                                        <tr>
                                            <th class="indicator-table__criterion" scope="row">
                                                <strong><?= htmlspecialchars($criterionLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <button class="button button--small" type="button" data-target="<?= htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8') ?>">Editar</button>
                                            </th>
                                            <?php foreach ($indicatorColors as $colorKey => $color): ?>
                                                <?php $indicator = $criterionIndicators[$colorKey] ?? []; ?>
                                                <td class="indicator-cell indicator-cell--<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="indicator-cell__content">
                                                        <span class="indicator-descriptor indicator-descriptor--complet" data-indicator-description="complet"><strong>Complet:</strong> <?= htmlspecialchars((string) ($indicator['descriptor_complet'] ?? 'Pendent de definir'), ENT_QUOTES, 'UTF-8') ?></span>
                                                        <span class="indicator-descriptor indicator-descriptor--simplificat" data-indicator-description="simplificat"><strong>Simplificat:</strong> <?= htmlspecialchars((string) ($indicator['descriptor_simplificat'] ?? 'Pendent de definir'), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr id="<?= htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8') ?>" class="student-editor-row">
                                            <td colspan="5">
                                                <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>">
                                                    <input type="hidden" name="action" value="update_objective_indicators">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="criterion_id" value="<?= $criterionId ?>">
                                                    <div class="indicator-editor-grid">
                                                        <?php foreach ($indicatorColors as $colorKey => $color): ?>
                                                            <?php $indicator = $criterionIndicators[$colorKey] ?? []; ?>
                                                            <fieldset class="indicator-editor__field indicator-editor__field--<?= htmlspecialchars($colorKey, ENT_QUOTES, 'UTF-8') ?>">
                                                                <legend><?= htmlspecialchars($color['label'], ENT_QUOTES, 'UTF-8') ?></legend>
                                                                <label>Complet
                                                                    <textarea name="descriptors[<?= $colorKey ?>][complet]" rows="3" required><?= htmlspecialchars((string) ($indicator['descriptor_complet'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                                </label>
                                                                <label>Simplificat
                                                                    <textarea name="descriptors[<?= $colorKey ?>][simplificat]" rows="3" required><?= htmlspecialchars((string) ($indicator['descriptor_simplificat'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                                </label>
                                                            </fieldset>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button class="button" type="submit" style="margin-top: 1rem;">Guardar indicadors</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php elseif ($objectives === []): ?>
            <p class="muted">Primer has de crear objectius d'aprenentatge.</p>
        <?php else: ?>
            <p class="muted">Primer has de crear criteris d'assoliment.</p>
        <?php endif; ?>
    </div>
</section>
