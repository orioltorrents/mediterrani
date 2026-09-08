<?php
ob_start();

$csrfToken = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$classes = is_array($classes ?? null) ? $classes : [];
$classrooms = is_array($classrooms ?? null) ? $classrooms : [];
$projects = is_array($projects ?? null) ? $projects : [];
$projectAssignments = is_array($projectAssignments ?? null) ? $projectAssignments : [];
$analytics = is_array($analytics ?? null) ? $analytics : [];
$classroomSummary = is_array($classroomSummary ?? null) ? $classroomSummary : [];

$projectNamesById = [];
foreach ($projects as $project) {
    $projectId = (int) ($project['id'] ?? 0);
    $projectName = (string) ($project['name'] ?? $project['slug'] ?? 'Projecte');
    $projectNamesById[$projectId] = $projectName;
}

$roleIdsByName = [];
foreach ($roles as $role) {
    $roleIdsByName[(string) ($role['name'] ?? '')] = (int) ($role['id'] ?? 0);
}

$studentUsers = array_values(array_filter($users, static fn (array $user): bool => in_array('student', $user['roles'] ?? [], true)));
$teacherUsers = array_values(array_filter($users, static fn (array $user): bool => in_array('teacher', $user['roles'] ?? [], true)));
$activeUsersCount = count(array_filter($users, static fn (array $user): bool => (int) ($user['is_active'] ?? 0) === 1));
$inactiveUsersCount = count($users) - $activeUsersCount;
$activeProjectsCount = count(array_filter($projects, static fn (array $project): bool => (int) ($project['is_active'] ?? 0) === 1));
$activeClassroomsCount = count(array_filter($classrooms, static fn (array $classroom): bool => (int) ($classroom['is_active'] ?? 0) === 1));
$projectAcademicYears = is_array($projectAcademicYears ?? null) ? $projectAcademicYears : [];
$objectives = is_array($objectives ?? null) ? $objectives : [];
$indicators = is_array($indicators ?? null) ? $indicators : [];
$projectYearObjectivesMap = is_array($projectYearObjectivesMap ?? null) ? $projectYearObjectivesMap : [];
$studentsWithTeams = is_array($studentsWithTeams ?? null) ? $studentsWithTeams : [];
$availableTeams = is_array($availableTeams ?? null) ? $availableTeams : [];

$projectAcademicYearsByProject = [];
foreach ($projectAcademicYears as $edition) {
    $projectAcademicYearsByProject[(int) ($edition['project_id'] ?? 0)][] = $edition;
}
$projectAssignmentsByProjectClass = [];
foreach ($projectAssignments as $assignment) {
    $projectAssignmentsByProjectClass[(int) ($assignment['project_id'] ?? 0)][(int) ($assignment['class_id'] ?? 0)] = (string) ($assignment['status'] ?? 'no_assignat');
}

$classYears = [];
foreach ($classes as $class) {
    $year = (string) ($class['academic_year_name'] ?? '');
    if ($year !== '') {
        $classYears[$year] = $year;
    }
}

$renderRoleChoices = static function (array $selectedRoles = []) use ($roles): void {
    foreach ($roles as $role) {
        $roleName = (string) ($role['name'] ?? '');
        ?>
        <label class="form__choice">
            <input type="checkbox" name="roles[]" value="<?= (int) ($role['id'] ?? 0) ?>" <?= in_array($roleName, $selectedRoles, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>
        </label>
        <?php
    }
};

$renderClassOptions = static function (?int $selectedClassId = null) use ($classes): void {
    ?>
    <option value="">Sense classe</option>
    <?php foreach ($classes as $class): ?>
        <option value="<?= (int) ($class['id'] ?? 0) ?>" <?= $selectedClassId === (int) ($class['id'] ?? 0) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) ($class['code'] ?? $class['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach;
};

$renderTeacherClassChoices = static function (array $selectedClassIds = []) use ($classes): void {
    $selectedClassIds = array_map('intval', $selectedClassIds);
    foreach ($classes as $class) {
        $classId = (int) ($class['id'] ?? 0);
        ?>
        <label class="form__choice">
            <input type="checkbox" name="teacher_class_ids[]" value="<?= $classId ?>" <?= in_array($classId, $selectedClassIds, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars((string) ($class['code'] ?? $class['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <?php
    }
};

$renderTeamOptions = static function (?int $selectedTeamId = null, string $studentClassCode = '') use ($availableTeams): void {
    ?>
    <option value="">(Sense grup assignat)</option>
    <?php foreach ($availableTeams as $team): ?>
        <?php
        $teamId = (int) ($team['id'] ?? 0);
        $teamClassGroup = trim((string) ($team['class_group'] ?? ''));
        if ($studentClassCode !== '' && $teamClassGroup !== '' && $teamClassGroup !== $studentClassCode) {
            if ($selectedTeamId !== $teamId) {
                continue;
            }
        }
        ?>
        <option value="<?= $teamId ?>" <?= $selectedTeamId === $teamId ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) ($team['project_name'] ?? 'Projecte') . ' [' . ($team['academic_year_name'] ?? '') . '] - ' . ($team['team_name'] ?? $team['team_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach;
};

$renderProjectOptions = static function (?int $selectedProjectId = null) use ($projects): void {
    ?>
    <option value="">(General / Transversal)</option>
    <?php foreach ($projects as $proj): ?>
        <option value="<?= (int) ($proj['id'] ?? 0) ?>" <?= $selectedProjectId === (int) ($proj['id'] ?? 0) ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) ($proj['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach;
};

$renderObjectiveChoices = static function (array $selectedObjIds = [], ?int $editionProjectId = null) use ($objectives): void {
    $selectedObjIds = array_map('intval', $selectedObjIds);
    $filtered = array_filter($objectives, static function (array $obj) use ($editionProjectId): bool {
        $objProjId = isset($obj['project_id']) && $obj['project_id'] !== null ? (int) $obj['project_id'] : null;
        return $objProjId === null || $editionProjectId === null || $objProjId === $editionProjectId;
    });

    if ($filtered === []) {
        echo '<p class="muted">No hi ha objectius específics per a aquest projecte.</p>';
        return;
    }

    foreach ($filtered as $obj) {
        $objId = (int) ($obj['id'] ?? 0);
        $codi = (string) ($obj['codi'] ?? '');
        $titol = (string) ($obj['titol'] ?? '');
        ?>
        <label class="form__choice" style="align-items: flex-start; gap: .5rem; margin-bottom: .5rem;">
            <input type="checkbox" name="objective_ids[]" value="<?= $objId ?>" <?= in_array($objId, $selectedObjIds, true) ? 'checked' : '' ?> style="margin-top: .25rem;">
            <span><strong>[<?= htmlspecialchars($codi, ENT_QUOTES, 'UTF-8') ?>]</strong> <?= htmlspecialchars($titol, ENT_QUOTES, 'UTF-8') ?></span>
        </label>
        <?php
    }
};
?>
<div class="admin-layout">
    <aside class="admin-layout__sidebar">
        <div class="admin-layout__brand">
            <strong>Mediterrani</strong>
            <span>Administració</span>
        </div>
        <nav class="admin-layout__nav">
            <a class="active" href="#resum">Resum</a>
            <a href="#visites">Visites</a>
            <a href="#usuaris">Usuaris</a>
            <a href="#classes">Classes</a>
            <a href="#grups-alumnes">Grups</a>
            <a href="#classroom">Classroom</a>
            <a href="#projectes">Projectes</a>
            <div class="admin-layout__nav-group" data-nav-group>
                <button class="admin-layout__nav-toggle" type="button" data-nav-group-toggle="objectius-submenu" aria-expanded="false" aria-controls="objectius-submenu">
                    Objectius
                </button>
                <div class="admin-layout__submenu" id="objectius-submenu" hidden>
                    <a href="#objectius">Objectius</a>
                    <a href="#indicadors">Indicadors</a>
                </div>
            </div>
        </nav>
    </aside>

    <div class="admin-layout__content" id="panell">
        <?php if (!empty($message)): ?>
            <div class="flash-message <?= htmlspecialchars((string) $messageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($importSummary ?? null) && $importSummary !== []): ?>
            <div class="card admin-panel">
                <h2>Resultat de la importació</h2>
                <p class="muted">Creats: <?= (int) ($importSummary['created'] ?? 0) ?> · Actualitzats: <?= (int) ($importSummary['updated'] ?? 0) ?></p>
                <?php if (!empty($importSummary['generated_passwords'])): ?>
                    <div class="admin-table__wrapper">
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Email</th><th>Contrasenya temporal</th></tr></thead>
                            <tbody>
                                <?php foreach ($importSummary['generated_passwords'] as $generated): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($generated['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><code><?= htmlspecialchars((string) ($generated['password'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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

                <!-- Secció Classroom -->
                <div class="card admin-subpanel" style="padding: 1.25rem;">
                    <h3 style="margin-top: 0; color: var(--ink); font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: .4rem; margin-bottom: 1rem;">Google Classroom</h3>
                    <div class="admin-summary__section-grid admin-summary__section-grid--dashboard">
                        <div class="admin-summary__card"><div class="admin-summary__icon">🎓</div><div class="admin-summary__body"><span class="admin-summary__label">Classrooms</span><strong class="admin-summary__value"><?= count($classrooms) ?></strong><span class="admin-summary__desc"><?= $activeClassroomsCount ?> actius</span></div></div>
                        <div class="admin-summary__card"><div class="admin-summary__icon">📝</div><div class="admin-summary__body"><span class="admin-summary__label">Tasques Classroom</span><strong class="admin-summary__value">0</strong><span class="admin-summary__desc">Pròximament</span></div></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="visites" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Visites</h2>
                <div class="admin-actions">
                    <span class="status">Analítica bàsica</span>
                    <button class="collapse-toggle" type="button" data-collapse="visites-content">Mostrar</button>
                </div>
            </div>
            <div id="visites-content" class="admin-collapsible__content">
                <div class="admin-summary__section-grid">
                    <div class="admin-summary__card"><div class="admin-summary__icon">👁</div><div class="admin-summary__body"><span class="admin-summary__label">Visites totals</span><strong class="admin-summary__value"><?= (int) ($analytics['total_visits'] ?? 0) ?></strong><span class="admin-summary__desc">Activitat registrada al web</span></div></div>
                    <div class="admin-summary__card"><div class="admin-summary__icon">🔁</div><div class="admin-summary__body"><span class="admin-summary__label">Sessions úniques</span><strong class="admin-summary__value"><?= (int) ($analytics['unique_sessions'] ?? 0) ?></strong><span class="admin-summary__desc">Sessions diferenciades</span></div></div>
                    <div class="admin-summary__card"><div class="admin-summary__icon">👤</div><div class="admin-summary__body"><span class="admin-summary__label">Usuaris reconeguts</span><strong class="admin-summary__value"><?= (int) ($analytics['unique_users'] ?? 0) ?></strong><span class="admin-summary__desc">Usuaris amb visites identificades</span></div></div>
                </div>
                <?php $pageStats = is_array($analytics['page_stats'] ?? null) ? $analytics['page_stats'] : []; ?>
                <?php $classVisitStats = is_array($analytics['current_class_visit_stats'] ?? null) ? $analytics['current_class_visit_stats'] : []; ?>
                <h3>Visites per classe</h3>
                <?php if ($classVisitStats !== []): ?>
                    <div class="admin-table__wrapper">
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Classe</th><th>Alumnes</th><th>Visites</th><th>Alumnes amb visites</th><th>Alumnes sense visites</th></tr></thead>
                            <tbody>
                                <?php foreach ($classVisitStats as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($row['class_code'] ?: $row['class_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) ($row['total_students'] ?? 0) ?></td>
                                        <td><?= (int) ($row['page_visits'] ?? 0) ?></td>
                                        <td><?= (int) ($row['students_with_visits'] ?? 0) ?></td>
                                        <td><?= (int) ($row['students_without_visits'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Encara no hi ha dades de visites per classe.</p>
                <?php endif; ?>
                <div class="admin-summary__section-grid">
                    <section class="admin-subpanel">
                        <h3>Dispositius</h3>
                        <?php $deviceStats = is_array($analytics['device_stats'] ?? null) ? $analytics['device_stats'] : []; ?>
                        <?php foreach ($deviceStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['device_type'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?>
                    </section>
                    <section class="admin-subpanel">
                        <h3>Sistemes operatius</h3>
                        <?php $osStats = is_array($analytics['os_stats'] ?? null) ? $analytics['os_stats'] : []; ?>
                        <?php foreach ($osStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['os_family'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?>
                    </section>
                </div>
                <section class="admin-subpanel">
                    <h3>Mapa de visites</h3>
                    <div class="admin-geo-map" data-geo-map data-geo-points="<?= htmlspecialchars(json_encode($geoMapPoints ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>"></div>
                </section>
                <div class="admin-summary__section-grid">
                    <section class="admin-subpanel"><h3>Geografia</h3><?php foreach (($analytics['geo_stats'] ?? []) as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['country_code'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?></section>
                    <section class="admin-subpanel"><h3>Pàgines més vistes</h3><?php foreach ($pageStats as $row): ?><div class="admin-summary__breakdown-row admin-summary__project-row"><span class="admin-summary__breakdown-label"><?= htmlspecialchars((string) ($row['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span><span class="admin-summary__breakdown-count"><?= (int) ($row['total'] ?? 0) ?></span></div><?php endforeach; ?></section>
                </div>
                <?php if ($pageStats !== []): ?>
                    <div class="admin-table__wrapper">
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Pàgina</th><th>Visites</th></tr></thead>
                            <tbody>
                                <?php foreach ($pageStats as $row): ?>
                                    <tr><td><?= htmlspecialchars((string) ($row['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) ($row['total'] ?? 0) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Encara no hi ha visites registrades.</p>
                <?php endif; ?>
                <?php $recentVisits = is_array($analytics['recent_visits'] ?? null) ? $analytics['recent_visits'] : []; ?>
                <h3>Visites recents</h3>
                <?php if ($recentVisits !== []): ?>
                    <div class="admin-table__wrapper">
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Data</th><th>Pàgina</th><th>Usuari</th><th>País</th><th>Dispositiu</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentVisits as $visit): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($visit['visited_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($visit['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(trim((string) ($visit['name'] ?? '') . ' ' . (string) ($visit['surname'] ?? '')) ?: 'Visitant', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($visit['country_code'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($visit['device_type'] ?? 'Desconegut'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">Encara no hi ha visites recents.</p>
                <?php endif; ?>
            </div>
        </section>

        <section id="usuaris" class="card admin-panel admin-collapsible">
            <div class="admin-panel__header">
                <h2>Usuaris</h2>
                <div class="admin-actions">
                    <span class="status"><?= count($users) ?> usuaris</span>
                    <button class="collapse-toggle" type="button" data-collapse="usuaris-content">Amagar</button>
                </div>
            </div>
            <div id="usuaris-content" class="admin-collapsible__content">
                <div class="admin-panels admin-panels--stacked">
                    <section id="crear-usuari" class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Crear usuari</h3>
                            <button class="collapse-toggle" type="button" data-collapse="crear-usuari-content">Mostrar</button>
                        </div>
                        <div id="crear-usuari-content" class="admin-collapsible__content">
                            <form class="admin-form" method="post" action="<?= url('admin') ?>">
                                <input type="hidden" name="action" value="create_user">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form__grid form__grid--compact">
                                    <label>Nom<input type="text" name="name" required></label>
                                    <label>Cognoms<input type="text" name="surname"></label>
                                    <label>Email<input type="email" name="email" required></label>
                                    <label>Contrasenya<input type="text" name="password" required></label>
                                    <label>Classe<select name="class_id"><?php $renderClassOptions(null); ?></select></label>
                                </div>
                                <div class="form__group"><label>Rols</label><div class="form__choices"><?php $renderRoleChoices(['student']); ?></div></div>
                                <label class="form__check"><input type="checkbox" name="is_active" value="1" checked> Usuari actiu</label>
                                <button class="button" type="submit">Crear usuari</button>
                            </form>
                        </div>
                    </section>

                    <section id="importar-usuaris" class="card admin-subpanel admin-collapsible is-collapsed">
                        <div class="admin-panel__header">
                            <h3>Importar CSV</h3>
                            <button class="collapse-toggle" type="button" data-collapse="importar-usuaris-content">Mostrar</button>
                        </div>
                        <div id="importar-usuaris-content" class="admin-collapsible__content">
                            <p class="muted">Columnes recomanades: <code>name,surname,email,password,class_code,roles,is_active</code>.</p>
                            <form class="admin-form" method="post" action="<?= url('admin') ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_students">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <label>Fitxer CSV<input type="file" name="students_file" accept=".csv,text/csv" required></label>
                                <button class="button" type="submit">Importar CSV</button>
                            </form>
                        </div>
                    </section>
                </div>

                <section id="alumnes-seccio" class="card admin-subpanel admin-collapsible">
                    <div class="admin-panel__header">
                        <h3>Alumnes</h3>
                        <div class="admin-actions"><span id="alumnes-count" class="status"><?= count($studentUsers) ?> alumnes</span><button class="collapse-toggle" type="button" data-collapse="alumnes-content">Amagar</button></div>
                    </div>
                    <div id="alumnes-content" class="admin-collapsible__content">
                        <div class="admin-filters" data-user-filter="students-table" data-count-target="alumnes-count" data-count-label="alumnes">
                            <label>Cerca<input type="search" data-user-search placeholder="Nom o email"></label>
                            <label>Estat<select data-status-filter><option value="all">Tots</option><option value="active">Actius</option><option value="inactive">Inactius</option></select></label>
                            <button class="admin-filters__chip is-active" type="button" data-value="all">Totes</button>
                            <?php foreach ($classes as $class): ?>
                                <button class="admin-filters__chip" type="button" data-value="<?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-table__wrapper">
                            <table id="students-table" class="admin-table admin-table--compact" data-sortable-table>
                                <thead><tr><th data-sort-type="text">Nom</th><th>Email</th><th>Classe</th><th>Visites</th><th>Estat</th><th>Accions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($studentUsers as $user): ?>
                                        <?php $userId = (int) ($user['id'] ?? 0); $classCode = (string) ($user['class_code'] ?? ''); ?>
                                        <tr data-user-row data-class="<?= htmlspecialchars($classCode, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= ((int) ($user['is_active'] ?? 0) === 1) ? 'active' : 'inactive' ?>" data-search="<?= htmlspecialchars(strtolower(trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? '') . ' ' . (string) ($user['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>">
                                            <td><?= htmlspecialchars(trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($classCode !== '' ? $classCode : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (int) ($user['visit_count'] ?? 0) ?></td>
                                            <td><?= ((int) ($user['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td>
                                            <td>
                                                <div class="admin-row-actions">
                                                    <button class="button button--small" type="button" data-target="student-editor-<?= $userId ?>">Editar</button>
                                                    <form class="inline-form" method="post" action="<?= url('admin/impersonate-student') ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="student_id" value="<?= $userId ?>">
                                                        <button class="button button--small button--secondary" type="submit">Veure com alumne</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="student-editor-<?= $userId ?>" class="student-editor-row"><td colspan="6"><?php $editableUser = $user; include __DIR__ . '/partials/user-editor-form.php'; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="professors-seccio" class="card admin-subpanel admin-collapsible is-collapsed">
                    <div class="admin-panel__header">
                        <h3>Professors</h3>
                        <div class="admin-actions"><span class="status"><?= count($teacherUsers) ?> professors</span><button class="collapse-toggle" type="button" data-collapse="professors-content">Mostrar</button></div>
                    </div>
                    <div id="professors-content" class="admin-collapsible__content">
                        <div class="admin-table__wrapper">
                            <table class="admin-table admin-table--compact">
                                <thead><tr><th>Nom</th><th>Email</th><th>Rols</th><th>Classe</th><th>Estat</th><th>Accions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($teacherUsers as $teacher): ?>
                                        <?php $userId = (int) ($teacher['id'] ?? 0); ?>
                                        <tr>
                                            <td><?= htmlspecialchars(trim((string) ($teacher['name'] ?? '') . ' ' . (string) ($teacher['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($teacher['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(implode(', ', $teacher['roles'] ?? []), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars(($teacher['teacher_class_codes'] ?? []) !== [] ? implode(', ', $teacher['teacher_class_codes']) : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= ((int) ($teacher['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td>
                                            <td><button class="button button--small" type="button" data-target="teacher-editor-<?= $userId ?>">Editar</button></td>
                                        </tr>
                                        <tr id="teacher-editor-<?= $userId ?>" class="student-editor-row"><td colspan="6"><?php $editableUser = $teacher; include __DIR__ . '/partials/user-editor-form.php'; ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section id="classes" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header"><h2>Classes</h2><div class="admin-actions"><span class="status"><?= count($classes) ?> classes</span><button class="collapse-toggle" type="button" data-collapse="classes-content">Mostrar</button></div></div>
            <div id="classes-content" class="admin-collapsible__content"><div class="admin-table__wrapper"><table class="admin-table admin-table--compact"><thead><tr><th>Classe</th><th>Codi</th><th>Curs</th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><?= htmlspecialchars((string) ($class['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($class['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($class['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
        </section>

        <section id="grups-alumnes" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header">
                <h2>Grups d'alumnes i equips</h2>
                <div class="admin-actions"><span class="status"><?= count($studentsWithTeams) ?> alumnes</span><button class="collapse-toggle" type="button" data-collapse="grups-alumnes-content">Mostrar</button></div>
            </div>
            <div id="grups-alumnes-content" class="admin-collapsible__content">
                <p class="muted">Visualitza i assigna cada alumne al seu grup o equip de projecte respectiu.</p>
                <?php if ($studentsWithTeams !== []): ?>
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
                                    $stClassCode = (string) ($st['class_code'] ?? '');
                                    $stTeamCode = (string) ($st['team_code'] ?? '');
                                    $stTeamName = (string) ($st['team_name'] ?? '');
                                    $stTeamId = !empty($st['team_id']) ? (int) $st['team_id'] : null;
                                    $currentTeamLabel = $stTeamName !== '' ? $stTeamName : ($stTeamCode !== '' ? $stTeamCode : 'Sense grup');
                                    ?>
                                    <tr data-user-row data-class="<?= htmlspecialchars($stClassCode, ENT_QUOTES, 'UTF-8') ?>" data-status="active" data-search="<?= htmlspecialchars(strtolower(trim((string) ($st['name'] ?? '') . ' ' . (string) ($st['surname'] ?? '') . ' ' . (string) ($st['email'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>">
                                        <td><?= htmlspecialchars(trim((string) ($st['name'] ?? '') . ' ' . (string) ($st['surname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($st['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($stClassCode !== '' ? $stClassCode : 'Sense classe', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="pill"><?= htmlspecialchars($currentTeamLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td>
                                            <form class="admin-form admin-form--compact" method="post" action="<?= url('admin') ?>" style="display: flex; gap: .5rem; align-items: center; margin: 0;">
                                                <input type="hidden" name="action" value="update_student_team">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="user_id" value="<?= $stUserId ?>">
                                                <select name="team_id" style="background: white; border: 1px solid var(--border); border-radius: 6px; padding: .35rem .5rem; font: inherit;">
                                                    <?php $renderTeamOptions($stTeamId, $stClassCode); ?>
                                                </select>
                                                <button class="button button--small" type="submit">Desar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="muted">No hi ha cap alumne registrat al sistema.</p>
                <?php endif; ?>
            </div>
        </section>

        <section id="classroom" class="card admin-panel admin-collapsible is-collapsed">
            <div class="admin-panel__header"><h2>Classroom</h2><div class="admin-actions"><span class="status"><?= (int) ($classroomSummary['total'] ?? count($classrooms)) ?> classrooms</span><button class="collapse-toggle" type="button" data-collapse="classroom-content">Mostrar</button></div></div>
            <div id="classroom-content" class="admin-collapsible__content"><?php if ($classrooms !== []): ?><div class="admin-table__wrapper"><table class="admin-table admin-table--compact"><thead><tr><th>Nom</th><th>Clau</th><th>Curs</th><th>Estat</th></tr></thead><tbody><?php foreach ($classrooms as $classroom): ?><tr><td><?= htmlspecialchars((string) ($classroom['classroom_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($classroom['classroom_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($classroom['academic_year_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= ((int) ($classroom['is_active'] ?? 0) === 1) ? 'Actiu' : 'Inactiu' ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">Encara no hi ha cap Classroom carregat.</p><?php endif; ?></div>
        </section>

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
    </div>
</div>
<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
