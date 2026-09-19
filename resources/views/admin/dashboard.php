<?php
ob_start();

// The controller prepares the complete view context; this file normalizes it and composes the dashboard.
/** @var mixed $csrfToken */
/** @var mixed $users */
/** @var mixed $roles */
/** @var mixed $classes */
/** @var mixed $classrooms */
/** @var mixed $projects */
/** @var mixed $projectAssignments */
/** @var mixed $analytics */
/** @var mixed $classroomSummary */
/** @var mixed $userAvatarPreview */
/** @var mixed $projectAcademicYears */
/** @var mixed $objectives */
/** @var mixed $indicators */
/** @var mixed $projectYearObjectivesMap */
/** @var mixed $studentsWithTeams */
/** @var mixed $availableTeams */
/** @var mixed $teamsWithMembers */
/** @var mixed $message */
/** @var mixed $messageType */
/** @var mixed $resetLink */
/** @var mixed $importSummary */
/** @var mixed $academicYears */
/** @var mixed $usersByAcademicYear */
/** @var mixed $activeUsersByAcademicYear */
/** @var mixed $projectRolesCount */
/** @var mixed $projectRolesByMember */
/** @var mixed $projectTeamsCount */
/** @var mixed $projectTeamsByClass */
/** @var mixed $evidenceSummary */
/** @var mixed $indicatorsCount */
/** @var mixed $geoMapPoints */
/** @var mixed $classroomMembers */

$csrfToken = htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8');
$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$classes = is_array($classes ?? null) ? $classes : [];
$classrooms = is_array($classrooms ?? null) ? $classrooms : [];
$projects = is_array($projects ?? null) ? $projects : [];
$projectAssignments = is_array($projectAssignments ?? null) ? $projectAssignments : [];
$analytics = is_array($analytics ?? null) ? $analytics : [];
$classroomSummary = is_array($classroomSummary ?? null) ? $classroomSummary : [];
$userAvatarPreview = is_array($userAvatarPreview ?? null) ? $userAvatarPreview : [];
$avatarMatches = is_array($userAvatarPreview['matches'] ?? null) ? $userAvatarPreview['matches'] : [];
$avatarAmbiguousFiles = is_array($userAvatarPreview['ambiguousFiles'] ?? null) ? $userAvatarPreview['ambiguousFiles'] : [];
$avatarUnmatchedFiles = is_array($userAvatarPreview['unmatchedFiles'] ?? null) ? $userAvatarPreview['unmatchedFiles'] : [];
$avatarUsersWithoutPhoto = is_array($userAvatarPreview['usersWithoutPhoto'] ?? null) ? $userAvatarPreview['usersWithoutPhoto'] : [];

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
$teamsWithMembers = is_array($teamsWithMembers ?? null) ? $teamsWithMembers : [];
$studentTeamLabels = [];
$studentTeamIds = [];
foreach ($studentsWithTeams as $studentTeam) {
    $studentId = (int) ($studentTeam['user_id'] ?? 0);
    $teamId = !empty($studentTeam['team_id']) ? (int) $studentTeam['team_id'] : null;
    $teamName = trim((string) ($studentTeam['team_name'] ?? ''));
    $teamCode = trim((string) ($studentTeam['team_code'] ?? ''));
    $teamLabel = $teamName !== '' ? $teamName : $teamCode;
    if ($studentId > 0 && $teamLabel !== '') {
        $studentTeamLabels[$studentId][$teamLabel] = $teamLabel;
    }
    if ($studentId > 0 && $teamId !== null) {
        $studentTeamIds[$studentId] = $teamId;
    }
}
$teamsByClass = [];
$teamSizeCounts = [];
foreach ($teamsWithMembers as $team) {
    $classKey = (string) ($team['class_code'] ?? '');
    $teamsByClass[$classKey !== '' ? $classKey : 'Sense classe'][] = $team;
    $memberCount = count($team['members'] ?? []);
    if ($memberCount > 0) {
        $teamSizeCounts[$memberCount] = ($teamSizeCounts[$memberCount] ?? 0) + 1;
    }
}
ksort($teamSizeCounts);

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

$renderTeamOptions = static function (?int $selectedTeamId = null, ?int $studentClassId = null, string $studentClassCode = '') use ($availableTeams): void {
    ?>
    <option value="">(Sense grup assignat)</option>
    <?php foreach ($availableTeams as $team): ?>
        <?php
        $teamId = (int) ($team['id'] ?? 0);
        $teamClassId = !empty($team['class_id']) ? (int) $team['class_id'] : null;
        $teamClassGroup = trim((string) ($team['class_group'] ?? ''));
        $teamClassCode = trim((string) ($team['class_code'] ?? ''));
        $matchesStudentClass = true;
        if ($studentClassId !== null && $teamClassId !== null) {
            $matchesStudentClass = $teamClassId === $studentClassId;
        } elseif ($studentClassCode !== '' && ($teamClassCode !== '' || $teamClassGroup !== '')) {
            $matchesStudentClass = $teamClassCode === $studentClassCode || $teamClassGroup === $studentClassCode;
        }

        if (!$matchesStudentClass && $selectedTeamId !== $teamId) {
            continue;
        }
        $teamLabel = trim((string) ($team['team_name'] ?? ''));
        if ($teamLabel === '') {
            $teamLabel = trim((string) ($team['team_code'] ?? ''));
        }
        $teamContext = trim((string) ($team['project_name'] ?? 'Projecte') . ' [' . (string) ($team['academic_year_name'] ?? '') . ']');
        ?>
        <option value="<?= $teamId ?>" title="<?= htmlspecialchars($teamContext, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedTeamId === $teamId ? 'selected' : '' ?>>
            <?= htmlspecialchars($teamLabel !== '' ? $teamLabel : 'Equip ' . $teamId, ENT_QUOTES, 'UTF-8') ?>
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
            <div class="admin-layout__nav-group" data-nav-group>
                <button class="admin-layout__nav-toggle" type="button" data-nav-group-toggle="usuaris-submenu" aria-expanded="false" aria-controls="usuaris-submenu">
                    Usuaris
                </button>
                <div class="admin-layout__submenu" id="usuaris-submenu" hidden>
                    <a href="#crear-usuari">Crear usuari</a>
                    <a href="#importar-usuaris">Importar CSV</a>
                    <a href="#fotos-usuaris">Fotos</a>
                    <a href="#alumnes-seccio">Alumnes</a>
                    <a href="#professors-seccio">Professors</a>
                </div>
            </div>
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

        <?php if (is_array($resetLink ?? null) && $resetLink !== []): ?>
            <div class="card admin-panel">
                <h2>Enllaç de reset de contrasenya</h2>
                <p class="muted">Comparteix aquest enllaç només amb l’alumne. Caduca en 48 hores i només es pot utilitzar una vegada.</p>
                <p><strong><?= htmlspecialchars((string) ($resetLink['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p><a href="<?= htmlspecialchars((string) ($resetLink['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($resetLink['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></p>
            </div>
        <?php endif; ?>

        <?php if (is_array($importSummary ?? null) && $importSummary !== []): ?>
            <div class="card admin-panel">
                <h2>Resultat de la importació</h2>
                <p class="muted">Creats: <?= (int) ($importSummary['created'] ?? 0) ?> · Actualitzats: <?= (int) ($importSummary['updated'] ?? 0) ?></p>
                <?php if (!empty($importSummary['activation_links'])): ?>
                    <div class="admin-table__wrapper">
                        <table class="admin-table admin-table--compact">
                            <thead><tr><th>Email</th><th>Enllaç d’activació</th></tr></thead>
                            <tbody>
                                <?php foreach ($importSummary['activation_links'] as $activation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($activation['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><a href="<?= htmlspecialchars((string) ($activation['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Activar compte</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php /* Each partial renders one dashboard section and shares this prepared view context. */ ?>
        <?php include __DIR__ . '/partials/resum.php'; ?>

        <?php include __DIR__ . '/partials/visites.php'; ?>

        <?php include __DIR__ . '/partials/usuaris.php'; ?>

        <?php include __DIR__ . '/partials/classes.php'; ?>

        <?php include __DIR__ . '/partials/classroom.php'; ?>

        <?php include __DIR__ . '/partials/projectes.php'; ?>

        <?php include __DIR__ . '/partials/objectius.php'; ?>

        <?php include __DIR__ . '/partials/indicadors.php'; ?>

    </div>
</div>

<div class="avatar-lightbox" id="avatar-lightbox" hidden>
    <div class="avatar-lightbox__overlay" id="avatar-lightbox-overlay"></div>
    <div class="avatar-lightbox__content">
        <button type="button" class="avatar-lightbox__close" id="avatar-lightbox-close" aria-label="Tancar">&times;</button>
        <img class="avatar-lightbox__image" id="avatar-lightbox-img" src="" alt="">
        <p class="avatar-lightbox__caption" id="avatar-lightbox-caption"></p>
    </div>
</div>

<?php
$content = ob_get_clean();
include dirname(__DIR__) . '/layouts/app.php';
