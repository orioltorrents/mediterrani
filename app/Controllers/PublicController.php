<?php

declare(strict_types=1);

class PublicController
{
    public function __construct(
        private ProjectService $projectService,
        private AuthService $authService,
        private AssessmentService $assessmentService,
        private DocumentService $documentService,
        private ProjectSectionService $projectSectionService,
        private ProjectAccessService $projectAccessService,
        private SitePageService $sitePageService
    ) {
    }

    public function home(): string
    {
        return view('public.home', [
            'title' => trans('home_title'),
        ]);
    }

    public function about(): string
    {
        return view('public.about', [
            'title' => 'Què és Mediterrani',
            'aboutContent' => $this->sitePageService->aboutContent(),
        ]);
    }

    public function projects(): string
    {
        return view('public.projects', [
            'title' => trans('projects_title'),
            'projects' => $this->projectService->allActive(getLanguage()),
        ]);
    }

    public function projectDetail(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-detail', [
                'title' => 'Projecte no trobat',
                'project' => null,
            ]);
        }

        $currentUser = $this->authService->user();
        $projectClass = null;
        $selectedClassId = isset($_GET['classe']) ? (int) $_GET['classe'] : 0;
        if ($selectedClassId > 0) {
            $pdo = require dirname(__DIR__, 2) . '/config/database.php';
            $classStmt = $pdo->prepare('SELECT id, class_name, class_code FROM classes WHERE id = :class_id LIMIT 1');
            $classStmt->execute(['class_id' => $selectedClassId]);
            $projectClass = $classStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $projectSectionsData = $this->projectSectionService->visibleSectionsForProject($slug, $currentUser, $projectAcademicYearId);

        return view('public.project-detail', [
            'title' => (string) $project['title'],
            'project' => $project,
            'projectSections' => $projectSectionsData['sections'] ?? [],
            'projectSectionsContext' => $projectSectionsData['context'] ?? [],
            'currentUser' => $currentUser,
            'projectAcademicYearId' => $projectAcademicYearId,
            'projectAcademicYear' => $projectAcademicYear,
            'projectClass' => $projectClass,
        ]);
    }

    public function projectTasks(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-tasks', [
                'title' => 'Tasques no trobades',
                'project' => null,
                'tasks' => [],
                'context' => [],
                'currentUser' => $this->authService->user(),
            ]);
        }

        $currentUser = $this->authService->user();
        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $tasksData = $this->assessmentService->visibleTaskSectionsForProject($slug, $currentUser, $projectAcademicYearId);

        return view('public.project-tasks', [
            'title' => 'Tasques de ' . (string) $project['title'],
            'project' => $project,
            'tasks' => $tasksData['sections'] ?? [],
            'context' => $tasksData['context'] ?? [],
            'currentUser' => $currentUser,
            'projectAcademicYearId' => $projectAcademicYearId,
            'projectAcademicYear' => $tasksData['projectAcademicYear'] ?? $projectAcademicYear,
        ]);
    }

    public function projectNotes(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-notes', [
                'title' => 'Notes no trobades',
                'project' => null,
                'notes' => null,
                'currentUser' => $this->authService->user(),
                'accessDenied' => false,
            ]);
        }

        $currentUser = $this->authService->user();
        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $notes = $this->resolveProjectNotes($slug, $currentUser, $projectAcademicYearId);

        if ($notes === null) {
            http_response_code(403);

            return view('public.project-notes', [
                'title' => 'Accés restringit',
                'project' => $project,
                'notes' => null,
                'currentUser' => $currentUser,
                'accessDenied' => true,
                'projectAcademicYearId' => $projectAcademicYearId,
            ]);
        }

        return view('public.project-notes', [
            'title' => 'Notes de ' . (string) $project['title'],
            'project' => $project,
            'notes' => $notes,
            'currentUser' => $currentUser,
            'accessDenied' => false,
            'projectAcademicYearId' => $projectAcademicYearId,
        ]);
    }

    public function projectDocuments(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-documents', [
                'title' => 'Documents no trobats',
                'project' => null,
                'projectAcademicYear' => null,
                'documents' => [],
                'context' => [],
            ]);
        }

        $currentUser = $this->authService->user();
        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $documentsData = $this->documentService->projectDocuments($slug, $currentUser, $projectAcademicYearId);

        return view('public.project-documents', [
            'title' => 'Documents de ' . (string) $project['title'],
            'project' => $documentsData['project'] ?? $project,
            'projectAcademicYear' => $documentsData['projectAcademicYear'] ?? null,
            'documents' => $documentsData['documents'] ?? [],
            'context' => $documentsData['context'] ?? [],
            'projectAcademicYearId' => $projectAcademicYearId,
        ]);
    }

    public function projectGroups(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-groups', [
                'title' => 'Grups no trobats',
                'project' => null,
                'team' => null,
                'currentUser' => $this->authService->user(),
            ]);
        }

        $currentUser = $this->authService->user();
        if ($currentUser === null) {
            header('Location: ' . url('login'));
            exit;
        }

        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $editionId = $projectAcademicYear !== null ? (int) $projectAcademicYear['id'] : 0;
        $team = null;
        $teams = [];

        if ($editionId > 0) {
            $pdo = require dirname(__DIR__, 2) . '/config/database.php';
            try {
                $roles = array_values(array_map('strval', $currentUser['roles'] ?? []));
                $canViewAllTeams = in_array('teacher', $roles, true)
                    || in_array('guest_teacher', $roles, true)
                    || in_array('coordinator', $roles, true)
                    || in_array('admin', $roles, true);
                $isTeacher = in_array('teacher', $roles, true) || in_array('guest_teacher', $roles, true);
                $selectedClassId = isset($_GET['classe']) ? (int) $_GET['classe'] : 0;
                $teamFilter = $isTeacher
                    ? 'pt.project_academic_year_id = :edition_id
                       AND pt.class_group IN (
                           SELECT c.class_code
                           FROM class_teachers ct
                           INNER JOIN classes c ON c.id = ct.class_id
                           WHERE ct.user_id = :teacher_id
                       )'
                     : ($canViewAllTeams
                     ? 'pt.project_academic_year_id = :edition_id'
                     : 'pt.project_academic_year_id = :edition_id_outer
                       AND pt.id IN (
                           SELECT inner_pt.id
                           FROM project_teams inner_pt
                           INNER JOIN project_team_members inner_ptm ON inner_ptm.project_team_id = inner_pt.id
                           WHERE inner_pt.project_academic_year_id = :edition_id_inner
                             AND inner_ptm.user_id = :user_id
                         )');
                if ($selectedClassId > 0 && ($isTeacher || $canViewAllTeams)) {
                    $teamFilter .= ' AND pt.class_group = (SELECT class_code FROM classes WHERE id = :selected_class_id)';
                }
                $stmt = $pdo->prepare(
                    'SELECT pt.id AS team_id,
                            pt.team_code,
                            pt.team_name,
                            pt.class_group,
                            ptm.user_id,
                            u.name AS member_name,
                            u.surname AS member_surname,
                            u.email AS member_email,
                            GROUP_CONCAT(DISTINCT pr.name ORDER BY pr.name SEPARATOR ", ") AS role_names
                        FROM project_teams pt
                       LEFT JOIN project_team_members ptm ON ptm.project_team_id = pt.id
                       LEFT JOIN users u ON u.id = ptm.user_id
                       LEFT JOIN project_team_member_roles ptmr ON ptmr.project_team_member_id = ptm.id
                       LEFT JOIN project_roles pr ON pr.id = ptmr.project_role_id
                      WHERE ' . $teamFilter . '
                      GROUP BY pt.id, pt.team_code, pt.team_name, pt.class_group, ptm.user_id, u.name, u.surname, u.email
                      ORDER BY pt.display_order, pt.team_code, u.surname, u.name'
                 );
                $params = $isTeacher
                    ? ['edition_id' => $editionId, 'teacher_id' => (int) $currentUser['id']]
                    : ($canViewAllTeams
                    ? ['edition_id' => $editionId]
                     : ['edition_id_outer' => $editionId, 'edition_id_inner' => $editionId, 'user_id' => (int) $currentUser['id']]);
                if ($selectedClassId > 0 && ($isTeacher || $canViewAllTeams)) {
                    $params['selected_class_id'] = $selectedClassId;
                }
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $teamId = (int) $row['team_id'];
                    if (!isset($teams[$teamId])) {
                        $teams[$teamId] = [
                            'id' => $teamId,
                            'team_code' => (string) $row['team_code'],
                            'team_name' => (string) ($row['team_name'] ?? ''),
                            'class_group' => (string) ($row['class_group'] ?? ''),
                            'members' => [],
                        ];
                    }
                    if (!empty($row['user_id'])) {
                        $teams[$teamId]['members'][] = [
                            'user_id' => (int) $row['user_id'],
                            'name' => trim((string) $row['member_name'] . ' ' . (string) $row['member_surname']),
                            'email' => (string) ($row['member_email'] ?? ''),
                            'role_names' => (string) ($row['role_names'] ?? ''),
                        ];
                    }
                }
                $teams = array_values($teams);
                if (!$canViewAllTeams && $teams !== []) {
                    $team = $teams[0];
                }
            } catch (Throwable) {
                $team = null;
                $teams = [];
            }
        }

        return view('public.project-groups', [
            'title' => 'El teu grup - ' . (string) $project['title'],
            'project' => $project,
            'team' => $team,
            'teams' => $teams,
            'currentUser' => $currentUser,
            'projectAcademicYearId' => $projectAcademicYearId,
            'projectAcademicYear' => $projectAcademicYear,
        ]);
    }

    public function projectStudents(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());
        $currentUser = $this->authService->user();
        $classId = isset($_GET['classe']) ? (int) $_GET['classe'] : 0;
        $students = [];
        $edition = $project !== null ? $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId) : null;
        if ($project !== null && $this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $edition)) {
            return $this->accessDenied();
        }
        if ($project !== null && $edition !== null && $classId > 0) {
            $pdo = require dirname(__DIR__, 2) . '/config/database.php';
            $stmt = $pdo->prepare('SELECT u.name, u.surname, u.email FROM class_members cm INNER JOIN users u ON u.id = cm.user_id INNER JOIN project_class_assignments pca ON pca.class_id = cm.class_id WHERE cm.class_id = :class_id AND pca.project_academic_year_id = :edition_id ORDER BY u.surname, u.name');
            $stmt->execute(['class_id' => $classId, 'edition_id' => (int) $edition['id']]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return view('public.project-students', ['title' => 'Alumnes', 'project' => $project, 'students' => $students, 'currentUser' => $currentUser, 'projectAcademicYearId' => $projectAcademicYearId]);
    }

    public function projectObjectives(string $slug, ?int $projectAcademicYearId = null): string
    {
        $project = $this->projectService->findActiveBySlug($slug, getLanguage());

        if ($project === null) {
            http_response_code(404);

            return view('public.project-objectives', [
                'title' => 'Objectius no trobats',
                'project' => null,
                'objectives' => [],
                'currentUser' => $this->authService->user(),
            ]);
        }

        $currentUser = $this->authService->user();
        $projectAcademicYear = $this->projectService->academicYearForProject((int) $project['id'], $projectAcademicYearId);
        if ($this->shouldEnforceEditionAccess($currentUser) && !$this->canAccessEdition($currentUser, $project, $projectAcademicYear)) {
            return $this->accessDenied();
        }

        $pdo = require dirname(__DIR__, 2) . '/config/database.php';
        $objectives = [];
        $editionId = $projectAcademicYear !== null ? (int) $projectAcademicYear['id'] : 0;
        try {
            if ($editionId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT oa.id, oa.codi, oa.titol AS description, payo.display_order
                     FROM project_academic_year_objectius payo
                     INNER JOIN objectius_aprenentatge oa ON oa.id = payo.objectiu_id
                     WHERE payo.project_academic_year_id = :edition_id
                     ORDER BY payo.display_order ASC, oa.codi ASC'
                );
                $stmt->execute(['edition_id' => $editionId]);
                $objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query('SELECT id, codi, titol AS description, 0 AS display_order FROM objectius_aprenentatge ORDER BY codi ASC');
                $objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($objectives !== []) {
                $objIds = array_map(static fn (array $o): int => (int) $o['id'], $objectives);
                $placeholders = implode(',', array_fill(0, count($objIds), '?'));
                $indStmt = $pdo->prepare("SELECT objectiu_id, color_semafor, descriptor FROM indicadors_assoliment WHERE objectiu_id IN ({$placeholders})");
                $indStmt->execute($objIds);
                $indicatorsByObj = [];
                foreach ($indStmt->fetchAll(PDO::FETCH_ASSOC) as $ind) {
                    $indicatorsByObj[(int) $ind['objectiu_id']][(string) $ind['color_semafor']] = (string) $ind['descriptor'];
                }

                foreach ($objectives as &$obj) {
                    $obj['indicators'] = $indicatorsByObj[(int) $obj['id']] ?? [];
                }
                unset($obj);

                $studentEvaluations = [];
                if ($currentUser !== null && in_array('student', (array) ($currentUser['roles'] ?? []), true)) {
                    $evaluationStmt = $pdo->prepare(
                        'SELECT objectiu_id, color_semafor
                           FROM student_indicador_assoliment
                          WHERE user_id = ?
                            AND project_academic_year_id = ?
                            AND objectiu_id IN (' . $placeholders . ')'
                    );
                    $evaluationStmt->execute(array_merge(
                        [(int) $currentUser['id'], $editionId],
                        $objIds
                    ));
                    foreach ($evaluationStmt->fetchAll(PDO::FETCH_ASSOC) as $evaluation) {
                        $studentEvaluations[(int) $evaluation['objectiu_id']] = (string) $evaluation['color_semafor'];
                    }
                }
            } else {
                $studentEvaluations = [];
            }
        } catch (Throwable) {
            $objectives = [];
            $studentEvaluations = [];
        }

        return view('public.project-objectives', [
            'title' => 'Objectius d\'aprenentatge - ' . (string) $project['title'],
            'project' => $project,
            'objectives' => $objectives,
            'studentEvaluations' => $studentEvaluations,
            'currentUser' => $currentUser,
            'projectAcademicYearId' => $projectAcademicYearId,
            'projectAcademicYear' => $projectAcademicYear,
        ]);
    }

    private function resolveProjectNotes(string $slug, ?array $currentUser, ?int $projectAcademicYearId = null): ?array
    {
        if ($currentUser === null) {
            return null;
        }

        $hasStudentRole = $this->authService->hasRole('student');

        if (!$hasStudentRole) {
            return null;
        }

        return $this->assessmentService->gradesForStudentProject((int) $currentUser['id'], $slug, $projectAcademicYearId);
    }

    private function canAccessEdition(?array $currentUser, array $project, ?array $projectAcademicYear): bool
    {
        if ($projectAcademicYear === null) {
            return false;
        }

        $requestedProjectAcademicYearId = isset($_GET['edicio']) ? (int) $_GET['edicio'] : 0;
        if ($requestedProjectAcademicYearId > 0 && (int) $projectAcademicYear['id'] !== $requestedProjectAcademicYearId) {
            return false;
        }

        return $this->projectAccessService->canAccessProjectAcademicYear(
            $currentUser,
            (int) $project['id'],
            (int) $projectAcademicYear['id']
        );
    }

    private function shouldEnforceEditionAccess(?array $currentUser): bool
    {
        if ($currentUser === null) {
            return false;
        }

        $roles = array_values(array_map('strval', $currentUser['roles'] ?? []));
        if (in_array('admin', $roles, true) || in_array('coordinator', $roles, true)) {
            return false;
        }

        return in_array('student', $roles, true) || in_array('teacher', $roles, true) || in_array('guest_teacher', $roles, true);
    }

    private function accessDenied(): string
    {
        http_response_code(403);

        return view('public.access-denied', [
            'title' => 'Accés restringit',
        ]);
    }
}
