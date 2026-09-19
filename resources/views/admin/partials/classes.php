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
