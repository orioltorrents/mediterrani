<?php
/** @var mixed $classes */
/** @var mixed $teamsWithMembers */
/** @var mixed $teamsByClass */
/** @var mixed $teamSizeCounts */
/** @var mixed $studentsWithTeams */
/** @var mixed $studentTeamLabels */
/** @var mixed $csrfToken */
/** @var mixed $renderTeamOptions */
?>
<section id="classes" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header"><h2>Classes</h2><div class="admin-actions"><span class="status"><?= count($classes) ?> classes</span><button class="collapse-toggle" type="button" data-collapse="classes-content">Mostrar</button></div></div>
            <div id="classes-content" class="admin-collapsible__content"><div class="admin-table__wrapper"><table class="admin-table admin-table--compact"><thead><tr><th>Curs</th><th>Classe</th><th>Codi</th><th>Alumnes</th><th>Equips</th><th>Equips de 3</th><th>Equips de 4</th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><?= htmlspecialchars((string) ($class['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($class['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td class="admin-table__count-cell"><span class="status"><?= (int) ($class['student_count'] ?? 0) ?></span></td><td class="admin-table__count-cell"><span class="status"><?= (int) ($class['team_count'] ?? 0) ?></span></td><td class="admin-table__count-cell"><span class="status"><?= (int) ($class['teams_of_3'] ?? 0) ?></span></td><td class="admin-table__count-cell"><span class="status"><?= (int) ($class['teams_of_4'] ?? 0) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
        </section>

        <section id="grups-alumnes" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Equips</h2>
                <div class="admin-actions"><span class="status"><?= count($teamsWithMembers) ?> equips</span><?php foreach ($teamSizeCounts as $memberCount => $teamCount): ?><span class="status"><?= (int) $teamCount ?> <?= ((int) $teamCount === 1) ? 'equip' : 'equips' ?> de <?= (int) $memberCount ?></span><?php endforeach; ?><button class="collapse-toggle" type="button" data-collapse="grups-alumnes-content">Mostrar</button></div>
            </div>
            <div id="grups-alumnes-content" class="admin-collapsible__content">
                <p class="muted">Equips de projecte amb els seus membres agrupats.</p>
                <?php if ($teamsWithMembers !== []): ?>
                    <div class="admin-team-classes">
                        <?php foreach ($teamsByClass as $classCode => $classTeams): ?>
                            <?php $classKey = 'teams-class-' . substr(md5($classCode), 0, 8); ?>
                            <?php $classTeamSizeCounts = []; foreach ($classTeams as $classTeam) { $classMemberCount = count($classTeam['members'] ?? []); if ($classMemberCount > 0) { $classTeamSizeCounts[$classMemberCount] = ($classTeamSizeCounts[$classMemberCount] ?? 0) + 1; } } ksort($classTeamSizeCounts); ?>
                            <section class="admin-team-class admin-collapsible is-collapsed">
                                <div class="admin-team-class__header"><h3><?= htmlspecialchars($classCode, ENT_QUOTES, 'UTF-8') ?></h3><div class="admin-actions"><span class="status"><?= count($classTeams) ?> equips</span><?php foreach ($classTeamSizeCounts as $memberCount => $teamCount): ?><span class="status"><?= (int) $teamCount ?> <?= ((int) $teamCount === 1) ? 'equip' : 'equips' ?> de <?= (int) $memberCount ?></span><?php endforeach; ?><button class="collapse-toggle" type="button" data-collapse="<?= $classKey ?>">Mostrar</button></div></div>
                                <div id="<?= $classKey ?>" class="admin-collapsible__content"><div class="admin-teams-grid">
                                    <?php foreach ($classTeams as $team): ?>
                                        <article class="admin-team-card">
                                            <div class="admin-team-card__header"><div><h3><?= htmlspecialchars((string) ($team['team_name'] ?: $team['team_code']), ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string) $team['team_code'], ENT_QUOTES, 'UTF-8') ?></p></div><span class="status" aria-label="Membres de l'equip"><?= count($team['members'] ?? []) ?></span></div>
                                            <p class="admin-team-card__context"><?= htmlspecialchars((string) $team['project_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($team['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if (($team['members'] ?? []) !== []): ?>
                                                <ul class="admin-team-members">
                                                    <?php foreach ($team['members'] as $member): ?>
                                                        <?php
                                                        $memberId = (int) ($member['id'] ?? 0);
                                                        $memberName = (string) ($member['name'] ?? '');
                                                        $teamClassId = !empty($team['class_id']) ? (int) $team['class_id'] : null;
                                                        $teamClassCode = (string) ($team['class_code'] ?? '');
                                                        $currentTeamId = (int) ($team['id'] ?? 0);
                                                        ?>
                                                        <li class="admin-team-member">
                                                            <div class="admin-team-member__identity">
                                                                <?php if (trim((string) ($member['avatar_url'] ?? '')) !== ''): ?>
                                                                    <button type="button" class="user-avatar-trigger" data-avatar-src="<?= url('user-avatar/' . $memberId) ?>" data-avatar-name="<?= htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8') ?>" title="Fes clic per ampliar"><img class="user-avatar" src="<?= url('user-avatar/' . $memberId) ?>" alt="Foto de <?= htmlspecialchars($memberName !== '' ? $memberName : 'alumne', ENT_QUOTES, 'UTF-8') ?>"></button>
                                                                <?php else: ?>
                                                                    <span class="user-avatar user-avatar--placeholder" aria-label="Sense foto">?</span>
                                                                <?php endif; ?>
                                                                <div class="admin-team-member__text"><strong><?= htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string) $member['email'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                                            </div>
                                                            <div class="admin-team-member__actions">
                                                                <form class="inline-form admin-team-change-form" method="post" action="<?= url('admin') ?>">
                                                                    <input type="hidden" name="action" value="update_student_team">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                    <input type="hidden" name="user_id" value="<?= $memberId ?>">
                                                                    <input type="hidden" name="project_academic_year_id" value="<?= (int) ($team['project_academic_year_id'] ?? 0) ?>">
                                                                    <select name="team_id" aria-label="Canviar grup de <?= htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8') ?>">
                                                                        <?php $renderTeamOptions($currentTeamId, $teamClassId, $teamClassCode); ?>
                                                                    </select>
                                                                    <button class="button button--small" type="submit">Canviar grup</button>
                                                                </form>
                                                                <form class="inline-form" method="post" action="<?= url('admin/impersonate-student') ?>"><input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="student_id" value="<?= $memberId ?>"><button class="button button--small button--secondary" type="submit">Veure com alumne</button></form>
                                                                <button class="button button--small button--secondary" type="button" data-target="student-editor-<?= $memberId ?>">Editar alumne</button>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="muted">Sense membres assignats.</p>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div></div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Encara no hi ha cap equip creat.</p>
                <?php endif; ?>

                <?php if ($studentsWithTeams !== []): ?>
                    <section class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Canviar equip d’alumnes</h3>
                            <div class="admin-actions"><span id="students-teams-count" class="status"><?= count($studentsWithTeams) ?> alumnes</span><button class="collapse-toggle" type="button" data-collapse="students-teams-content">Mostrar</button></div>
                        </div>
                        <div id="students-teams-content" class="admin-collapsible__content">
                    <div class="admin-filters" data-user-filter="students-teams-table" data-count-target="students-teams-count" data-count-label="alumnes">
                        <label>Cerca<input type="search" data-user-search placeholder="Nom o email"></label>
                        <button class="admin-filters__chip is-active" type="button" data-value="all">Totes</button>
                        <?php foreach ($classes as $class): ?>
                            <button class="admin-filters__chip" type="button" data-value="<?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-table__wrapper">
                        <table id="students-teams-table" class="admin-table admin-table--compact" data-sortable-table>
                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th data-sort-type="text">Alumne/a</th>
                                    <th>Email</th>
                                    <th>Classe</th>
                                    <th>Grup / Equip actual</th>
                                    <th>Canviar d'equip</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsWithTeams as $st): ?>
                                    <?php
                                    $stUserId = (int) ($st['user_id'] ?? 0);
                                    $stClassId = !empty($st['class_id']) ? (int) $st['class_id'] : null;
                                    $stClassCode = (string) ($st['class_code'] ?? '');
                                    $stTeamCode = (string) ($st['team_code'] ?? '');
                                    $stTeamName = (string) ($st['team_name'] ?? '');
                                    $stTeamId = !empty($st['team_id']) ? (int) $st['team_id'] : null;
                                    $stProjectAcademicYearId = !empty($st['project_academic_year_id']) ? (int) $st['project_academic_year_id'] : null;
                                    $currentTeamLabel = $stTeamName !== '' ? $stTeamName : ($stTeamCode !== '' ? $stTeamCode : 'Sense grup');
                                    ?>
                                     <tr data-user-row data-class="<?= htmlspecialchars($stClassCode, ENT_QUOTES, 'UTF-8') ?>" data-status="active" data-search="<?= htmlspecialchars(strtolower(trim((string) ($st['name'] ?? '') . ' ' . (string) ($st['surname'] ?? '') . ' ' . (string) ($st['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>">
                                         <td><?php if (trim((string) ($st['avatar_url'] ?? '')) !== ''): ?><button type="button" class="user-avatar-trigger" data-avatar-src="<?= url('user-avatar/' . $stUserId) ?>" data-avatar-name="<?= htmlspecialchars(trim((string) ($st['name'] ?? '') . ' ' . (string) ($st['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" title="Fes clic per ampliar"><img class="user-avatar" src="<?= url('user-avatar/' . $stUserId) ?>" alt="Foto de <?= htmlspecialchars((string) ($st['name'] ?? 'alumne'), ENT_QUOTES, 'UTF-8') ?>"></button><?php else: ?><span class="user-avatar user-avatar--placeholder" aria-label="Sense foto">?</span><?php endif; ?></td>
                                         <td><?= htmlspecialchars(trim((string) ($st['name'] ?? '') . ' ' . (string) ($st['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($st['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($stClassCode !== '' ? $stClassCode : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="pill"><?= htmlspecialchars($currentTeamLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td>
                                            <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>" style="display: flex; gap: .5rem; align-items: center; margin: 0;">
                                                <input type="hidden" name="action" value="update_student_team">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="user_id" value="<?= $stUserId ?>">
                                                <input type="hidden" name="project_academic_year_id" value="<?= (int) ($stProjectAcademicYearId ?? 0) ?>">
                                                <select name="team_id" style="background: white; border: 1px solid var(--border); border-radius: 6px; padding: .35rem .5rem; font: inherit;">
                                                    <?php $renderTeamOptions($stTeamId, $stClassId, $stClassCode); ?>
                                                </select>
                                                <button class="button button--small" type="submit">Desar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                        </div>
                    </section>
                <?php else: ?>
                    <p class="muted">No hi ha cap alumne registrat al sistema.</p>
                <?php endif; ?>
            </div>
        </section>
