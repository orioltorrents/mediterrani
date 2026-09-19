<?php
/** @var array $users */
/** @var array $roles */
/** @var array $projects */
/** @var array $academicYears */
/** @var int $activeUsersCount */
/** @var int $inactiveUsersCount */
/** @var array $objectives */
/** @var array $classrooms */
?>
<section id="resum" class="card admin-panel admin-collapsible">
    <div class="admin-panel__header">
        <h2>Resum general</h2>
        <div class="admin-actions">
            <span class="status">Indicadors del sistema</span>
            <button class="collapse-toggle" type="button" data-collapse="resum-content">Amagar</button>
        </div>
    </div>
    <div id="resum-content" class="admin-collapsible__content" style="display: grid; gap: 1.5rem;">
        <!-- Secció Web -->
        <div class="card admin-subpanel" style="padding: 1.25rem;">
            <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Web</h3>
            <div class="admin-summary__section-grid admin-summary__section-grid--dashboard">
                <div class="admin-summary__card"><div class="admin-summary__icon">👥</div><div class="admin-summary__body"><span class="admin-summary__label">Usuaris</span><strong class="admin-summary__value"><?= count($users) ?></strong><div class="admin-summary__breakdown" aria-label="Usuaris per any acadèmic"><?php foreach (($usersByAcademicYear ?? []) as $yearName => $yearCount): ?><div class="admin-summary__breakdown-row"><span class="admin-summary__breakdown-count"><?= (int) $yearCount ?></span><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) $yearName, ENT_QUOTES, 'UTF-8') ?></span></div><?php endforeach; ?></div></div></div>
                <div class="admin-summary__card"><div class="admin-summary__icon">✅</div><div class="admin-summary__body"><span class="admin-summary__label">Usuaris actius</span><strong class="admin-summary__value"><?= $activeUsersCount ?></strong><div class="admin-summary__breakdown" aria-label="Usuaris actius per any acadèmic"><?php foreach (($activeUsersByAcademicYear ?? []) as $yearName => $yearCount): ?><div class="admin-summary__breakdown-row"><span class="admin-summary__breakdown-count"><?= (int) $yearCount ?></span><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) $yearName, ENT_QUOTES, 'UTF-8') ?></span></div><?php endforeach; ?></div></div></div>
                <div class="admin-summary__card">
                    <div class="admin-summary__icon">🛡️</div>
                    <div class="admin-summary__body">
                        <span class="admin-summary__label">Rols de la web</span>
                        <strong class="admin-summary__value"><?= count($roles) ?></strong>
                        <?php if ($roles !== []): ?>
                            <div class="admin-summary__breakdown" aria-label="Usuaris per rol web">
                                <?php foreach ($roles as $role): ?>
                                    <div class="admin-summary__breakdown-row">
                                        <span class="admin-summary__breakdown-count"><?= (int) ($role['user_count'] ?? 0) ?></span>
                                        <span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="admin-summary__desc">Sense rols configurats</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="admin-summary__card"><div class="admin-summary__icon">❌</div><div class="admin-summary__body"><span class="admin-summary__label">Usuaris inactius</span><strong class="admin-summary__value"><?= $inactiveUsersCount ?></strong><span class="admin-summary__desc">Comptes deshabilitats</span></div></div>
            </div>
        </div>

        <!-- Secció Projectes -->
        <div class="card admin-subpanel" style="padding: 1.25rem;">
            <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Projectes</h3>
            <div class="admin-summary__section-grid admin-summary__section-grid--dashboard">
                <div class="admin-summary__card"><div class="admin-summary__icon">📘</div><div class="admin-summary__body"><span class="admin-summary__label">Projectes</span><strong class="admin-summary__value"><?= count($projects) ?></strong><div class="admin-summary__breakdown" aria-label="Estat dels projectes"><?php foreach ($projects as $project): ?><?php $projectIsActive = (int) ($project['is_active'] ?? 0) === 1; ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($project['name'] ?? $project['slug'] ?? 'Projecte'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-status <?= $projectIsActive ? 'admin-summary__breakdown-status--active' : 'admin-summary__breakdown-status--inactive' ?>"><?= $projectIsActive ? 'Actiu' : 'Inactiu' ?></span></div><?php endforeach; ?></div></div></div>
                <div class="admin-summary__card"><div class="admin-summary__icon">📅</div><div class="admin-summary__body"><span class="admin-summary__label">Anys acadèmics</span><strong class="admin-summary__value" style="font-size: 1.4rem;"><?= count($academicYears) ?> anys</strong><div class="admin-summary__breakdown" aria-label="Anys acadèmics"><?php foreach ($academicYears as $academicYear): ?><div class="admin-summary__breakdown-row admin-summary__academic-year-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($academicYear['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><?php if ((int) ($academicYear['is_current'] ?? 0) === 1): ?><span class="admin-summary__breakdown-status">Actiu</span><?php endif; ?></div><?php endforeach; ?></div></div></div>
                <div class="admin-summary__card"><div class="admin-summary__icon">🏷️</div><div class="admin-summary__body"><span class="admin-summary__label">Rols del projecte</span><strong class="admin-summary__value"><?= (int) ($projectRolesCount ?? 0) ?></strong><?php if (!empty($projectRolesByMember)): ?><div class="admin-summary__breakdown" aria-label="Membres per rol de projecte"><?php foreach ($projectRolesByMember as $projectRole): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($projectRole['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($projectRole['member_count'] ?? 0) ?></span></div><?php endforeach; ?></div><?php else: ?><span class="admin-summary__desc">Rols dins equips</span><?php endif; ?></div></div>
                <div class="admin-summary__card">
                    <div class="admin-summary__icon">👥</div>
                    <div class="admin-summary__body">
                        <span class="admin-summary__label">Equips</span>
                        <strong class="admin-summary__value"><?= (int) ($projectTeamsCount ?? 0) ?></strong>
                        <?php if (!empty($projectTeamsByClass)): ?>
                            <div class="admin-summary__breakdown" aria-label="Equips per classe">
                                <?php foreach ($projectTeamsByClass as $teamClass): ?>
                                    <div class="admin-summary__breakdown-row">
                                        <span class="admin-summary__breakdown-count"><?= (int) ($teamClass['count'] ?? 0) ?></span>
                                        <span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($teamClass['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="admin-summary__desc">Equips de treball creats</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secció Objectius i Indicadors -->
        <div class="card admin-subpanel" style="padding: 1.25rem;">
            <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Objectius d'aprenentatge i Indicadors</h3>
            <div class="admin-summary__section-grid admin-summary__section-grid--dashboard">
                <div class="admin-summary__card"><div class="admin-summary__icon">🎯</div><div class="admin-summary__body"><span class="admin-summary__label">Objectius d'aprenentatge</span><strong class="admin-summary__value"><?= count($objectives) ?></strong><?php if ($projects !== []): ?><div class="admin-summary__breakdown" aria-label="Objectius d'aprenentatge per projecte"><?php foreach ($projects as $project): ?><?php $projectObjectiveCount = count(array_filter($objectives, static fn (array $objective): bool => (int) ($objective['project_id'] ?? 0) === (int) ($project['id'] ?? 0))); ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($project['name'] ?? $project['slug'] ?? 'Projecte'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= $projectObjectiveCount ?></span></div><?php endforeach; ?></div><?php else: ?><span class="admin-summary__desc">Objectius definits</span><?php endif; ?></div></div>
                <div class="admin-summary__card"><div class="admin-summary__icon">🚥</div><div class="admin-summary__body"><span class="admin-summary__label">Indicadors d'assoliment</span><strong class="admin-summary__value"><?= (int) ($indicatorsCount ?? 0) ?></strong><span class="admin-summary__desc">Nivells de semàfor (0%)</span></div></div>
            </div>
        </div>

        <!-- Secció Evidències -->
        <div class="card admin-subpanel" style="padding: 1.25rem;">
            <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Evidències</h3>
            <div class="admin-summary__section-grid admin-summary__section-grid--dashboard admin-evidence-summary">
                <div class="admin-summary__card">
                    <div class="admin-summary__icon">🗂️</div>
                    <div class="admin-summary__body">
                        <span class="admin-summary__label">Categories d'evidències</span>
                        <strong class="admin-summary__value"><?= count($evidenceSummary['categories'] ?? []) ?></strong>
                        <div class="admin-summary__breakdown" aria-label="Evidències per categoria">
                            <?php foreach (($evidenceSummary['categories'] ?? []) as $category): ?>
                                <div class="admin-summary__breakdown-row">
                                    <span class="admin-evidence-label"><span class="admin-evidence-dot" style="--evidence-color: <?= htmlspecialchars((string) ($category['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars((string) ($category['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="admin-summary__breakdown-count"><?= (int) ($category['evidence_count'] ?? 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="admin-summary__card">
                    <div class="admin-summary__icon">🏷️</div>
                    <div class="admin-summary__body">
                        <span class="admin-summary__label">Tipus d'evidències</span>
                        <strong class="admin-summary__value"><?= count($evidenceSummary['types'] ?? []) ?></strong>
                        <div class="admin-summary__breakdown" aria-label="Evidències per tipus">
                            <?php foreach (($evidenceSummary['types'] ?? []) as $type): ?>
                                <div class="admin-summary__breakdown-row">
                                    <span class="admin-evidence-label"><span class="admin-evidence-dot" style="--evidence-color: <?= htmlspecialchars((string) ($type['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>"></span><span><strong><?= htmlspecialchars((string) ($type['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><small class="admin-evidence-category"><?= htmlspecialchars((string) ($type['category_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></span></span>
                                    <span class="admin-summary__breakdown-count"><?= (int) ($type['evidence_count'] ?? 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="admin-summary__card admin-evidence-summary__list-card">
                    <div class="admin-summary__icon">📋</div>
                    <div class="admin-summary__body">
                        <span class="admin-summary__label">Evidències disponibles</span>
                        <strong class="admin-summary__value"><?= (int) ($evidenceSummary['count'] ?? 0) ?></strong>
                        <div class="admin-summary__breakdown" aria-label="Llista d'evidències">
                            <?php foreach (($evidenceSummary['evidences'] ?? []) as $evidence): ?>
                                <div class="admin-summary__breakdown-row admin-evidence-row">
                                    <span class="admin-evidence-label"><span class="admin-evidence-dot" style="--evidence-color: <?= htmlspecialchars((string) ($evidence['color_code'] ?? '#94a3b8'), ENT_QUOTES, 'UTF-8') ?>"></span><?= htmlspecialchars((string) ($evidence['titol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secció Classroom -->
        <div class="card admin-subpanel" style="padding: 1.25rem;">
            <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Google Classroom</h3>
            <div class="admin-summary__section-grid admin-summary__section-grid--dashboard">
                <div class="admin-summary__card"><div class="admin-summary__icon">🎓</div><div class="admin-summary__body"><span class="admin-summary__label">Classrooms</span><strong class="admin-summary__value"><?= count($classrooms) ?></strong><div class="admin-summary__breakdown" aria-label="Classrooms i estat"><?php foreach ($classrooms as $classroom): ?><?php $classroomIsActive = (int) ($classroom['is_active'] ?? 0) === 1; ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($classroom['classroom_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-status <?= $classroomIsActive ? 'admin-summary__breakdown-status--active' : 'admin-summary__breakdown-status--inactive' ?>"><?= $classroomIsActive ? 'Actiu' : 'Inactiu' ?></span></div><?php endforeach; ?></div></div></div>
                <div class="admin-summary__card"><div class="admin-summary__icon">📝</div><div class="admin-summary__body"><span class="admin-summary__label">Tasques Classroom</span><strong class="admin-summary__value">0</strong><span class="admin-summary__desc">Pròximament</span></div></div>
            </div>
        </div>
    </div>
</section>
