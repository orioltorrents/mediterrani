<?php

declare(strict_types=1);

class AdminDashboardService
{
    public function __construct(
        private PDO $pdo,
        private AdminDashboardUserService $userService,
        private AdminDashboardProjectService $projectService,
        private AdminDashboardClassroomService $classroomService,
        private AdminDashboardAssessmentService $assessmentService,
    ) {
    }

    public function dashboardData(): array
    {
        $users = $this->userService->users();
        $userRoles = $this->userService->userRoles();
        $classMemberships = $this->userService->classMemberships();
        $classTeachers = $this->userService->classTeachers();
        $projects = $this->projectService->projects();
        $projectAssignments = $this->projectService->projectAssignments();
        $classrooms = $this->classroomService->classrooms();
        $classroomSummary = $this->classroomService->classroomSummary();
        $roles = $this->roles();
        $classes = $this->classes();
        $academicYears = $this->academicYears();
        $projectAcademicYears = $this->projectAcademicYears();
        $objectivesService = new AdminObjectivesService($this->pdo);
        $objectives = $objectivesService->objectives();
        $projectYearObjectivesMap = $objectivesService->projectYearObjectivesMap();
        $indicators = $objectivesService->indicators();
        $teamService = new AdminTeamService($this->pdo);
        $studentsWithTeams = $teamService->studentsWithTeams();
        $availableTeams = $teamService->availableTeams();
        $teamsWithMembers = $teamService->teamsWithMembers();
        $evidenceSummary = (new AdminEvidenceService($this->pdo))->summary();

        $roleMap = [];
        foreach ($userRoles as $row) {
            $roleMap[(int) $row['user_id']][] = (string) $row['role_name'];
        }

        $userClassMap = [];
        $userClassGroupMap = [];
        $userClassCodeMap = [];
        $userAcademicYearMap = [];
        foreach ($classMemberships as $membership) {
            $userClassMap[(int) $membership['user_id']] = (int) $membership['class_id'];
            $userClassGroupMap[(int) $membership['user_id']] = (string) $membership['class_name'];
            $userClassCodeMap[(int) $membership['user_id']] = (string) $membership['class_code'];
            $userAcademicYearMap[(int) $membership['user_id']] = [
                'id' => (int) $membership['academic_year_id'],
                'name' => (string) $membership['academic_year_name'],
            ];
        }

        $classTeachersMap = [];
        $teacherClassMap = [];
        foreach ($classTeachers as $teacherAssignment) {
            $classId = (int) $teacherAssignment['class_id'];
            $teacherId = (int) $teacherAssignment['user_id'];
            $classTeachersMap[$classId][] = [
                'id' => $teacherId,
                'name' => trim((string) $teacherAssignment['name'] . ' ' . (string) $teacherAssignment['surname']),
            ];
            $teacherClassMap[$teacherId][] = [
                'id' => $classId,
                'name' => (string) $teacherAssignment['class_name'],
                'code' => (string) $teacherAssignment['class_code'],
            ];
        }

        foreach ($users as &$user) {
            $userId = (int) $user['id'];
            $teacherClasses = $teacherClassMap[$userId] ?? [];
            $user['roles'] = $roleMap[$userId] ?? [];
            $user['status'] = ((int) $user['is_active'] === 1) ? 'Actiu' : 'Inactiu';
            $user['class_id'] = $userClassMap[$userId] ?? null;
            $user['class_group'] = $userClassGroupMap[$userId] ?? null;
            $user['class_code'] = $userClassCodeMap[$userId] ?? null;
            $user['teacher_classes'] = $teacherClasses;
            $user['teacher_class_ids'] = array_map(static fn (array $class): int => (int) $class['id'], $teacherClasses);
            $user['teacher_class_codes'] = array_map(static fn (array $class): string => (string) $class['code'], $teacherClasses);
            $user['academic_year'] = $userAcademicYearMap[$userId] ?? null;
        }
        unset($user);

        $studentUsers = array_values(array_filter(
            $users,
            static fn (array $user): bool => in_array('student', $user['roles'], true)
        ));

        $teacherUsers = array_values(array_filter(
            $users,
            static fn (array $user): bool => in_array('teacher', $user['roles'], true)
        ));

        $usersByAcademicYear = [];
        $activeUsersByAcademicYear = [];
        foreach ($users as $user) {
            $academicYearName = (string) (($user['academic_year']['name'] ?? '') ?: 'Sense any acadèmic');
            $usersByAcademicYear[$academicYearName] = ($usersByAcademicYear[$academicYearName] ?? 0) + 1;
            if ((int) ($user['is_active'] ?? 0) === 1) {
                $activeUsersByAcademicYear[$academicYearName] = ($activeUsersByAcademicYear[$academicYearName] ?? 0) + 1;
            }
        }

        $analytics = (new AnalyticsService())->getDashboardStats($this->pdo);

        $projectRolesCount = 0;
        $projectTeamsCount = 0;
        $projectTeamsByClass = [];
        $projectRolesByMember = [];
        $indicatorsCount = 0;
        try {
            $projectRolesCount = (int) $this->pdo->query('SELECT COUNT(*) FROM project_roles')->fetchColumn();
            $projectRoleStmt = $this->pdo->query(
                'SELECT pr.name, COUNT(DISTINCT ptmr.project_team_member_id) AS member_count
                   FROM project_roles pr
              LEFT JOIN project_team_member_roles ptmr ON ptmr.project_role_id = pr.id
               GROUP BY pr.id, pr.name
               ORDER BY pr.name'
            );
            $projectRolesByMember = $projectRoleStmt->fetchAll(PDO::FETCH_ASSOC);
            $projectTeamsCount = (int) $this->pdo->query('SELECT COUNT(*) FROM project_teams')->fetchColumn();
            $teamClassStmt = $this->pdo->query(
                'SELECT COALESCE(NULLIF(TRIM(class_group), \'\'), \'Sense classe\') AS class_name, COUNT(*) AS team_count
                   FROM project_teams
                  GROUP BY COALESCE(NULLIF(TRIM(class_group), \'\'), \'Sense classe\')
                  ORDER BY class_name'
            );
            foreach ($teamClassStmt->fetchAll(PDO::FETCH_ASSOC) as $teamClass) {
                $projectTeamsByClass[] = [
                    'name' => (string) $teamClass['class_name'],
                    'count' => (int) $teamClass['team_count'],
                ];
            }
            $indicatorsCount = (int) $this->pdo->query('SELECT COUNT(*) FROM indicadors_assoliment')->fetchColumn();
        } catch (Throwable) {
            // tables might not exist yet
        }

        return [
            'users' => $users,
            'roles' => $roles,
            'projects' => $projects,
            'classes' => $classes,
            'academicYears' => $academicYears,
            'projectAcademicYears' => $projectAcademicYears,
            'studentUsers' => $studentUsers,
            'teacherUsers' => $teacherUsers,
            'usersByAcademicYear' => $usersByAcademicYear,
            'activeUsersByAcademicYear' => $activeUsersByAcademicYear,
            'classTeachersMap' => $classTeachersMap,
            'teacherClassMap' => $teacherClassMap,
            'projectAssignments' => $projectAssignments,
            'projectRolesCount' => $projectRolesCount,
            'projectTeamsCount' => $projectTeamsCount,
            'projectTeamsByClass' => $projectTeamsByClass,
            'projectRolesByMember' => $projectRolesByMember,
            'indicatorsCount' => $indicatorsCount,
            'projectTeams' => [],
            'projectRoleGroups' => [],
            'sitePages' => [],
            'classrooms' => $classrooms,
            'classroomSummary' => $classroomSummary,
            'assessmentSummary' => [],
            'projectRoles' => [],
            'projectMembersWithoutRole' => 0,
            'projectTeamMembershipCount' => 0,
            'assessmentStructure' => [],
            'roleMap' => $roleMap,
            'analytics' => $analytics,
            'objectives' => $objectives,
            'projectYearObjectivesMap' => $projectYearObjectivesMap,
            'indicators' => $indicators,
            'studentsWithTeams' => $studentsWithTeams,
            'availableTeams' => $availableTeams,
            'teamsWithMembers' => $teamsWithMembers,
            'evidenceSummary' => $evidenceSummary,
            'geoMapPoints' => $this->userService->buildGeoMapPoints($analytics['geo_stats'] ?? []),
        ];
    }

    private function roles(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT r.id, r.name, COUNT(ur.user_id) AS user_count
                 FROM web_roles r
                 LEFT JOIN user_web_roles ur ON ur.role_id = r.id
                 GROUP BY r.id, r.name
                 ORDER BY r.name'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function classes(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT c.id, c.class_name AS name, c.class_code AS code, c.academic_year_id, ay.name AS academic_year_name,
                        COUNT(DISTINCT cm.user_id) AS student_count,
                        COUNT(DISTINCT pt.id) AS team_count,
                        COALESCE(tc.teams_of_3, 0) AS teams_of_3,
                        COALESCE(tc.teams_of_4, 0) AS teams_of_4
                   FROM classes c
                   INNER JOIN academic_years ay ON ay.id = c.academic_year_id
                   LEFT JOIN class_members cm ON cm.class_id = c.id
                   LEFT JOIN project_teams pt ON pt.class_id = c.id
                   LEFT JOIN (
                       SELECT pt2.class_id,
                              SUM(CASE WHEN COALESCE(tm.member_count, 0) = 3 THEN 1 ELSE 0 END) AS teams_of_3,
                              SUM(CASE WHEN COALESCE(tm.member_count, 0) = 4 THEN 1 ELSE 0 END) AS teams_of_4
                         FROM project_teams pt2
                         LEFT JOIN (
                             SELECT project_team_id, COUNT(*) AS member_count
                               FROM project_team_members
                              GROUP BY project_team_id
                         ) tm ON tm.project_team_id = pt2.id
                        GROUP BY pt2.class_id
                   ) tc ON tc.class_id = c.id
                  GROUP BY c.id, c.class_name, c.class_code, c.academic_year_id, ay.name
                  ORDER BY ay.start_year ASC, c.class_code ASC'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function academicYears(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT id, name, start_year, end_year, is_current FROM academic_years ORDER BY start_year ASC, end_year ASC');

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function projectAcademicYears(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT pay.id, pay.project_id, pay.academic_year_id, ay.name AS academic_year_name, pay.status, p.name AS project_name
                   FROM project_academic_years pay
                   INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                   INNER JOIN projects p ON p.id = pay.project_id
                  ORDER BY ay.start_year ASC, p.display_order ASC, p.name ASC'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }
}
