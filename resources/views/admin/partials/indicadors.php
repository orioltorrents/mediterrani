<?php
/** @var mixed $objectives */
/** @var mixed $projects */
/** @var mixed $projectNamesById */
/** @var mixed $indicators */
/** @var mixed $csrfToken */
?>
<section id="indicadors" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Indicadors d'assoliment</h2>
                <div class="admin-actions"><span class="status">Nivells de semàfor</span><button class="collapse-toggle" type="button" data-collapse="indicadors-content">Mostrar</button></div>
            </div>
            <div id="indicadors-content" class="admin-collapsible__content">
                <?php if ($objectives !== []): ?>
                    <?php
                    $objectivesByProject = [];
                    $generalObjectives = [];
                    foreach ($objectives as $obj) {
                        $projId = isset($obj['project_id']) && $obj['project_id'] !== null ? (int) $obj['project_id'] : null;
                        if ($projId !== null && isset($projectNamesById[$projId])) {
                            $objectivesByProject[$projId][] = $obj;
                        } else {
                            $generalObjectives[] = $obj;
                        }
                    }
                    ?>
                    <div style="display: grid; gap: 2rem;">
                        <?php foreach ($projects as $proj): ?>
                            <?php
                            $projId = (int) ($proj['id'] ?? 0);
                            $projName = (string) ($proj['name'] ?? 'Projecte');
                            $projObjectives = $objectivesByProject[$projId] ?? [];
                            ?>
                            <div class="card admin-subpanel" style="padding: 1.5rem;">
                                <h3 style="margin-top: 0; color: var(--ink); font-size: 1.3rem; border-bottom: 2px solid var(--border); padding-bottom: .5rem; margin-bottom: 1.25rem;">
                                    Indicadors del projecte: <?= htmlspecialchars($projName, ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <?php if ($projObjectives !== []): ?>
                                    <div style="display: grid; gap: 1.5rem;">
                                        <?php foreach ($projObjectives as $obj): ?>
                                            <?php
                                            $objId = (int) ($obj['id'] ?? 0);
                                            $objIndicators = $indicators[$objId] ?? [];
                                            ?>
                                            <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem;">
                                                <h4 style="margin-top: 0; color: var(--leaf); display: flex; align-items: center; gap: .5rem; font-size: 1.1rem;">
                                                    <span class="pill"><?= htmlspecialchars((string) ($obj['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span><?= htmlspecialchars((string) ($obj['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                </h4>
                                                <form class="admin-form" method="post" action="<?= url('admin') ?>" style="margin-top: 1rem;">
                                                    <input type="hidden" name="action" value="update_objective_indicators">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="objective_id" value="<?= $objId ?>">
                                                    <div style="display: grid; gap: .85rem;">
                                                        <?php foreach ([
                                                            'vermell' => 'Vermell (No Assolit)',
                                                            'groc' => 'Groc (Assoliment Satisfactori)',
                                                            'verd_clar' => 'Verd clar (Assoliment Notable)',
                                                            'verd_fosc' => 'Verd fosc (Assoliment Excel·lent)',
                                                        ] as $colorKey => $colorLabel): ?>
                                                            <label style="display: flex; flex-direction: column; gap: .3rem; font-size: .9rem; font-weight: 700; color: var(--text-secondary);">
                                                                <?= $colorLabel ?>
                                                                <textarea name="descriptors[<?= $colorKey ?>]" rows="2" style="width: 100%; border: 1px solid var(--border-soft); border-radius: 8px; padding: .5rem; font: inherit; font-weight: 400; color: var(--ink);" required><?= htmlspecialchars((string) ($objIndicators[$colorKey] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button class="button" type="submit" style="margin-top: 1rem;">Guardar indicadors</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="muted">No hi ha objectius assignats a aquest projecte encara.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($generalObjectives !== []): ?>
                            <div class="card admin-subpanel" style="padding: 1.5rem;">
                                <h3 style="margin-top: 0; color: var(--ink); font-size: 1.3rem; border-bottom: 2px solid var(--border); padding-bottom: .5rem; margin-bottom: 1.25rem;">
                                    Indicadors generals / transversals
                                </h3>
                                <div style="display: grid; gap: 1.5rem;">
                                    <?php foreach ($generalObjectives as $obj): ?>
                                        <?php
                                        $objId = (int) ($obj['id'] ?? 0);
                                        $objIndicators = $indicators[$objId] ?? [];
                                        ?>
                                        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem;">
                                            <h4 style="margin-top: 0; color: var(--leaf); display: flex; align-items: center; gap: .5rem; font-size: 1.1rem;">
                                                <span class="pill"><?= htmlspecialchars((string) ($obj['codi'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span><?= htmlspecialchars((string) ($obj['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            </h4>
                                            <form class="admin-form" method="post" action="<?= url('admin') ?>" style="margin-top: 1rem;">
                                                <input type="hidden" name="action" value="update_objective_indicators">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="objective_id" value="<?= $objId ?>">
                                                <div style="display: grid; gap: .85rem;">
                                                    <?php foreach ([
                                                        'vermell' => 'Vermell (No Assolit)',
                                                        'groc' => 'Groc (Assoliment Satisfactori)',
                                                        'verd_clar' => 'Verd clar (Assoliment Notable)',
                                                        'verd_fosc' => 'Verd fosc (Assoliment Excel·lent)',
                                                    ] as $colorKey => $colorLabel): ?>
                                                        <label style="display: flex; flex-direction: column; gap: .3rem; font-size: .9rem; font-weight: 700; color: var(--text-secondary);">
                                                            <?= $colorLabel ?>
                                                            <textarea name="descriptors[<?= $colorKey ?>]" rows="2" style="width: 100%; border: 1px solid var(--border-soft); border-radius: 8px; padding: .5rem; font: inherit; font-weight: 400; color: var(--ink);" required><?= htmlspecialchars((string) ($objIndicators[$colorKey] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button class="button" type="submit" style="margin-top: 1rem;">Guardar indicadors</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Primer has de crear objectius d'aprenentatge.</p>
                <?php endif; ?>
            </div>
        </section>
