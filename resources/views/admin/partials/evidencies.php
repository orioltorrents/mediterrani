<?php
/** @var mixed $csrfToken */
/** @var mixed $evidenceSummary */
/** @var mixed $studentUsers */
/** @var mixed $projectAcademicYears */
/** @var mixed $objectives */
/** @var mixed $projectYearObjectivesMap */

$evidenceCategories = is_array($evidenceSummary['categories'] ?? null) ? $evidenceSummary['categories'] : [];
$evidenceTypes = is_array($evidenceSummary['types'] ?? null) ? $evidenceSummary['types'] : [];
$evidenceTypesWithRecords = array_values(array_filter(
    $evidenceTypes,
    static fn (array $type): bool => (int) ($type['evidence_count'] ?? 0) > 0
));
$studentEvidenceBreakdowns = is_array($evidenceSummary['student_breakdowns'] ?? null) ? $evidenceSummary['student_breakdowns'] : [];
$objectiveEditionIds = [];
foreach ($projectYearObjectivesMap as $editionId => $objectiveIds) {
    foreach ((array) $objectiveIds as $objectiveId) {
        $objectiveEditionIds[(int) $objectiveId][] = (int) $editionId;
    }
}
?>
<section id="evidencies" class="card admin-panel admin-collapsible is-collapsed">
    <div class="admin-panel__header">
        <div>
            <h2>Evidències</h2>
            <p class="muted">Catàleg de categories i tipus d'evidència.</p>
        </div>
        <div class="admin-actions">
            <span class="status"><?= (int) ($evidenceSummary['count'] ?? 0) ?> evidències d'alumnes</span>
            <button class="collapse-toggle" type="button" data-collapse="evidencies-content">Mostrar</button>
        </div>
    </div>

    <div id="evidencies-content" class="admin-collapsible__content">
        <div class="admin-summary__section-grid admin-summary__section-grid--dashboard evidence-metrics">
            <div class="admin-summary__card">
                <div class="admin-summary__body">
                    <span class="evidence-metric-pill">Categories d'evidències</span>
                    <strong class="admin-summary__value"><?= count($evidenceCategories) ?></strong>
                    <span class="admin-summary__desc">Grups generals del catàleg</span>
                    <div class="evidence-category-pills" aria-label="Tipus per categoria">
                        <?php foreach ($evidenceCategories as $category): ?>
                            <span class="evidence-category-pill" style="--evidence-color: <?= htmlspecialchars((string) ($category['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>">
                                <span class="admin-evidence-dot"></span>
                                <strong><?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= (int) ($category['type_count'] ?? 0) ?> tipus</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="admin-summary__card">
                <div class="admin-summary__body">
                    <span class="evidence-metric-pill">Tipus d'evidències</span>
                    <strong class="admin-summary__value"><?= count($evidenceTypes) ?></strong>
                    <span class="admin-summary__desc">Formes concretes de recollida</span>
                    <span class="evidence-filter-notice">Només es mostren els tipus que tenen evidències d'alumnes registrades.</span>
                    <?php if ($evidenceTypesWithRecords !== []): ?>
                        <div class="evidence-category-pills" aria-label="Evidències registrades per tipus">
                            <?php foreach ($evidenceTypesWithRecords as $type): ?>
                                <span class="evidence-count-pill" style="--evidence-color: <?= htmlspecialchars((string) ($type['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>">
                                    <span><?= htmlspecialchars((string) ($type['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= (int) ($type['evidence_count'] ?? 0) ?></strong>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="evidence-empty-state">Cap tipus té evidències registrades.</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-summary__card">
                <div class="admin-summary__body">
                    <span class="evidence-metric-pill">Evidències d'alumnes</span>
                    <strong class="admin-summary__value"><?= (int) ($evidenceSummary['count'] ?? 0) ?></strong>
                    <span class="admin-summary__desc">Registres vinculats a objectius</span>
                    <?php if ((int) ($evidenceSummary['count'] ?? 0) > 0): ?>
                        <?php foreach ([
                            'projects' => 'Per projecte',
                            'types' => 'Per tipus',
                            'categories' => 'Per categoria',
                            'classes' => 'Per grup classe',
                        ] as $breakdownKey => $breakdownLabel): ?>
                            <div class="evidence-breakdown-group">
                                <span class="evidence-breakdown-group__label"><?= $breakdownLabel ?></span>
                                <div class="evidence-category-pills">
                                    <?php foreach (($studentEvidenceBreakdowns[$breakdownKey] ?? []) as $item): ?>
                                        <span class="evidence-count-pill"<?php if (!empty($item['color_code'])): ?> style="--evidence-color: <?= htmlspecialchars((string) $item['color_code'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                                            <span><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            <strong><?= (int) ($item['evidence_count'] ?? 0) ?></strong>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="evidence-empty-state">Encara no hi ha evidències registrades.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section id="entrada-manual-evidencia" class="evidence-admin-section">
            <div class="admin-panel__header">
                <div>
                    <h3>Entrada manual</h3>
                    <p class="muted">Registra una observació d'un alumne i vincula-la a un objectiu.</p>
                </div>
            </div>

            <form class="admin-form evidence-entry-form" method="post" action="<?= url('admin') ?>" data-evidence-entry-form>
                <input type="hidden" name="action" value="create_student_evidence">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="evidence-entry-grid">
                    <div class="form__group">
                        <label for="evidence-student">Alumne</label>
                        <select id="evidence-student" name="user_id" required>
                            <option value="">Selecciona un alumne</option>
                            <?php foreach ($studentUsers as $student): ?>
                                <option value="<?= (int) ($student['id'] ?? 0) ?>">
                                    <?= htmlspecialchars(trim((string) ($student['name'] ?? '') . ' ' . (string) ($student['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($student['class_code'])): ?> · <?= htmlspecialchars((string) $student['class_code'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form__group">
                        <label for="evidence-project-year">Edició del projecte</label>
                        <select id="evidence-project-year" name="project_academic_year_id" data-evidence-project-year required>
                            <option value="">Selecciona una edició</option>
                            <?php foreach ($projectAcademicYears as $edition): ?>
                                <option value="<?= (int) ($edition['id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($edition['project_name'] ?? 'Projecte'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($edition['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form__group">
                        <label for="evidence-objective">Objectiu d'aprenentatge</label>
                        <select id="evidence-objective" name="objective_id" data-evidence-objective required disabled>
                            <option value="">Selecciona primer una edició</option>
                            <?php foreach ($objectives as $objective): ?>
                                <option value="<?= (int) ($objective['id'] ?? 0) ?>" data-editions="<?= htmlspecialchars(implode(',', $objectiveEditionIds[(int) ($objective['id'] ?? 0)] ?? []), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) ($objective['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($objective['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form__group">
                        <label for="evidence-category">Categoria</label>
                        <select id="evidence-category" data-evidence-category required>
                            <option value="">Selecciona una categoria</option>
                            <?php foreach ($evidenceCategories as $category): ?>
                                <option value="<?= (int) ($category['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form__group">
                        <label for="evidence-type">Tipus d'evidència</label>
                        <select id="evidence-type" name="type_id" data-evidence-type required disabled>
                            <option value="">Selecciona primer una categoria</option>
                            <?php foreach ($evidenceTypes as $type): ?>
                                <option value="<?= (int) ($type['id'] ?? 0) ?>" data-category-id="<?= (int) ($type['evidencia_categoria_id'] ?? 0) ?>">
                                    <?= htmlspecialchars((string) ($type['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form__group">
                    <label for="evidence-observation">Observació</label>
                    <textarea id="evidence-observation" name="observation" rows="5" required></textarea>
                </div>

                <button class="button" type="submit">Registrar evidència</button>
            </form>
        </section>

        <section id="categories-evidencies" class="evidence-admin-section">
            <div class="admin-panel__header">
                <div>
                    <h3>Categories</h3>
                    <p class="muted">Procés, producte i metacognició.</p>
                </div>
            </div>

            <div class="evidence-admin-list">
                <?php foreach ($evidenceCategories as $category): ?>
                    <?php $categoryId = (int) ($category['id'] ?? 0); ?>
                    <article class="evidence-admin-item admin-collapsible is-collapsed">
                        <div class="evidence-admin-item__header">
                            <span class="admin-evidence-label">
                                <span class="admin-evidence-dot" style="--evidence-color: <?= htmlspecialchars((string) ($category['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>"></span>
                                <span>
                                    <strong><?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small class="admin-evidence-category"><?= (int) ($category['type_count'] ?? 0) ?> tipus · <?= (int) ($category['evidence_count'] ?? 0) ?> evidències</small>
                                </span>
                            </span>
                            <button class="collapse-toggle" type="button" data-collapse="evidence-category-<?= $categoryId ?>">Editar</button>
                        </div>
                        <div id="evidence-category-<?= $categoryId ?>" class="admin-collapsible__content">
                            <form class="admin-form evidence-admin-form" method="post" action="<?= url('admin') ?>">
                                <input type="hidden" name="action" value="update_evidence_category">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                                <div class="form__group">
                                    <label for="evidence-category-name-<?= $categoryId ?>">Nom</label>
                                    <input id="evidence-category-name-<?= $categoryId ?>" type="text" name="name" maxlength="50" value="<?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="form__group">
                                    <label for="evidence-category-description-<?= $categoryId ?>">Descripció</label>
                                    <textarea id="evidence-category-description-<?= $categoryId ?>" name="description" rows="3"><?= htmlspecialchars((string) ($category['descripcio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <div class="form__group evidence-color-field">
                                    <label for="evidence-category-color-<?= $categoryId ?>">Color</label>
                                    <input id="evidence-category-color-<?= $categoryId ?>" type="color" name="color_code" value="<?= htmlspecialchars((string) ($category['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <button class="button" type="submit">Guardar categoria</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="tipus-evidencies" class="evidence-admin-section">
            <div class="admin-panel__header">
                <div>
                    <h3>Tipus d'evidència</h3>
                    <p class="muted">Formes concretes amb què es recull l'aprenentatge.</p>
                </div>
            </div>

            <div class="evidence-admin-list">
                <?php foreach ($evidenceTypes as $type): ?>
                    <?php $typeId = (int) ($type['id'] ?? 0); ?>
                    <article class="evidence-admin-item admin-collapsible is-collapsed">
                        <div class="evidence-admin-item__header">
                            <span class="admin-evidence-label">
                                <span class="admin-evidence-dot" style="--evidence-color: <?= htmlspecialchars((string) ($type['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>"></span>
                                <span>
                                    <strong><?= htmlspecialchars((string) ($type['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small class="admin-evidence-category"><?= htmlspecialchars((string) ($type['category_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= (int) ($type['evidence_count'] ?? 0) ?> evidències</small>
                                </span>
                            </span>
                            <button class="collapse-toggle" type="button" data-collapse="evidence-type-<?= $typeId ?>">Editar</button>
                        </div>
                        <div id="evidence-type-<?= $typeId ?>" class="admin-collapsible__content">
                            <form class="admin-form evidence-admin-form" method="post" action="<?= url('admin') ?>">
                                <input type="hidden" name="action" value="update_evidence_type">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="type_id" value="<?= $typeId ?>">
                                <div class="form__group">
                                    <label for="evidence-type-category-<?= $typeId ?>">Categoria</label>
                                    <select id="evidence-type-category-<?= $typeId ?>" name="category_id" required>
                                        <?php foreach ($evidenceCategories as $category): ?>
                                            <option value="<?= (int) ($category['id'] ?? 0) ?>" <?= (int) ($type['evidencia_categoria_id'] ?? 0) === (int) ($category['id'] ?? 0) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form__group">
                                    <label for="evidence-type-key-<?= $typeId ?>">Clau tècnica</label>
                                    <input id="evidence-type-key-<?= $typeId ?>" type="text" name="type_key" maxlength="50" pattern="[a-z0-9_]+" value="<?= htmlspecialchars((string) ($type['tipus_evidencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="form__group">
                                    <label for="evidence-type-title-<?= $typeId ?>">Títol</label>
                                    <input id="evidence-type-title-<?= $typeId ?>" type="text" name="title" maxlength="255" value="<?= htmlspecialchars((string) ($type['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="form__group">
                                    <label for="evidence-type-description-<?= $typeId ?>">Descripció</label>
                                    <textarea id="evidence-type-description-<?= $typeId ?>" name="description" rows="3"><?= htmlspecialchars((string) ($type['descripcio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                <button class="button" type="submit">Guardar tipus</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
