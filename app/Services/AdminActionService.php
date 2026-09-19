<?php

declare(strict_types=1);

class AdminActionService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handle(string $action, array $post, array $files): array
    {
        $projectHandlers = [
            'toggle_project' => 'toggleProject',
            'update_project_order' => 'updateProjectOrder',
            'assign_project_to_class' => 'assignProjectToClass',
            'sync_project_class_assignments' => 'syncProjectClassAssignments',
            'update_project_academic_year_statuses' => 'updateProjectAcademicYearStatuses',
            'update_project_assignment_status' => 'updateProjectAssignmentStatus',
            'delete_project_assignment' => 'deleteProjectAssignment',
        ];

        if (isset($projectHandlers[$action])) {
            $result = (new AdminProjectService($this->pdo))->{$projectHandlers[$action]}($post);
            $this->auditAdminAction($action);

            return $result;
        }

        $userHandlers = [
            'create_user' => 'createUser',
            'toggle_user' => 'toggleUser',
            'update_student' => 'updateUser',
        ];

        if (isset($userHandlers[$action])) {
            $result = (new AdminUserService($this->pdo))->{$userHandlers[$action]}($post);
            $this->auditAdminAction($action);

            return $result;
        }

        $classHandlers = [
            'sync_class_teachers' => 'syncClassTeachers',
            'sync_all_class_teachers' => 'syncAllClassTeachers',
        ];

        if (isset($classHandlers[$action])) {
            $result = (new AdminClassService($this->pdo))->{$classHandlers[$action]}($post);
            $this->auditAdminAction($action);

            return $result;
        }

        $objectiveHandlers = [
            'create_objective' => 'createObjective',
            'update_objective' => 'updateObjective',
            'sync_project_objectives' => 'syncProjectYearObjectives',
            'update_objective_indicators' => 'updateIndicators',
        ];

        if (isset($objectiveHandlers[$action])) {
            $result = (new AdminObjectivesService($this->pdo))->{$objectiveHandlers[$action]}($post);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'import_students') {
            $result = (new AdminStudentImportService($this->pdo))->importUploadedFile($files['students_file'] ?? []);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'sync_user_avatars') {
            $result = (new UserAvatarService($this->pdo))->syncMatches();
            $this->auditAdminAction($action, [
                'updated' => (int) ($result['summary']['updated'] ?? 0),
                'matches' => (int) ($result['summary']['matches'] ?? 0),
                'unmatched_files' => (int) ($result['summary']['unmatched_files'] ?? 0),
                'ambiguous_files' => (int) ($result['summary']['ambiguous_files'] ?? 0),
            ]);

            return $result;
        }

        if ($action === 'generate_student_password_reset') {
            $result = (new AdminUserService($this->pdo))->generateStudentPasswordResetLink($post);
            $this->auditAdminAction($action, [
                'target_student_id' => (int) ($post['student_id'] ?? 0),
                'target_email' => (string) ($result['reset_link']['email'] ?? ''),
            ]);

            return $result;
        }

        if ($action === 'update_student_team') {
            $result = (new AdminTeamService($this->pdo))->syncStudentTeam($post);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'toggle_classroom') {
            $result = (new AdminClassroomService($this->pdo))->toggleClassroom($post);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'import_classrooms') {
            $result = (new AdminClassroomService($this->pdo))->importUploadedFile($files['classrooms_file'] ?? []);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'import_classroom_members') {
            $result = (new AdminClassroomService($this->pdo))->importMembersUploadedFile($files['classroom_members_file'] ?? []);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'import_classroom_project_links') {
            $result = (new AdminClassroomService($this->pdo))->importProjectLinksUploadedFile($files['classroom_project_links_file'] ?? []);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'import_classroom_task_links') {
            $result = (new AdminClassroomService($this->pdo))->importTaskLinksUploadedFile($files['classroom_task_links_file'] ?? []);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'process_classroom_structure_snapshots') {
            $result = (new ClassroomStructureSnapshotProcessingService($this->pdo))->processPending();
            $this->auditAdminAction($action, [
                'processed' => (int) ($result['processed'] ?? 0),
                'errors' => (int) ($result['errors'] ?? 0),
            ]);

            return $result;
        }

        $assessmentHandlers = [
            'import_assessment_structure' => 'importAssessmentStructure',
            'toggle_assessment_phase' => 'toggleAssessmentPhase',
            'toggle_assessment_task' => 'toggleAssessmentTask',
        ];

        if (isset($assessmentHandlers[$action])) {
            $service = new AdminAssessmentStructureService($this->pdo);
            $method = $assessmentHandlers[$action];
            $result = $action === 'import_assessment_structure'
                ? $service->{$method}($files)
                : $service->{$method}($post);
            $this->auditAdminAction($action);

            return $result;
        }

        if ($action === 'sync_site_page') {
            $slug = trim((string) ($post['slug'] ?? ''));
            $languageCode = trim((string) ($post['language_code'] ?? 'ca'));

            if ($slug === '') {
                return [
                    'message' => 'No s’ha indicat cap pàgina pública per sincronitzar.',
                    'type' => 'error',
                ];
            }

            try {
                $result = (new SitePageService())->syncPage($slug, $languageCode !== '' ? $languageCode : 'ca');
                $this->auditAdminAction($action, [
                    'slug' => $slug,
                    'language_code' => $languageCode,
                    'characters' => (int) ($result['characters'] ?? 0),
                ]);

                return [
                    'message' => 'Pàgina pública sincronitzada correctament.',
                    'type' => 'success',
                ];
            } catch (Throwable $throwable) {
                $this->auditAdminAction($action . '_failed', [
                    'slug' => $slug,
                    'language_code' => $languageCode,
                    'error' => $throwable->getMessage(),
                ]);

                return [
                    'message' => 'No s’ha pogut sincronitzar la pàgina pública: ' . $throwable->getMessage(),
                    'type' => 'error',
                ];
            }
        }

        if ($action === 'update_site_page_google_file') {
            $slug = trim((string) ($post['slug'] ?? ''));
            $languageCode = trim((string) ($post['language_code'] ?? 'ca'));
            $googleFileId = trim((string) ($post['google_file_id'] ?? ''));

            try {
                $result = (new SitePageService())->updateGoogleFileId($slug, $languageCode !== '' ? $languageCode : 'ca', $googleFileId);
                $this->auditAdminAction($action, [
                    'slug' => $slug,
                    'language_code' => $languageCode,
                    'status' => (string) ($result['status'] ?? ''),
                ]);

                return [
                    'message' => ($result['status'] ?? '') === 'unchanged'
                        ? 'El Google Doc de la pàgina pública no ha canviat.'
                        : 'Google Doc de la pàgina pública actualitzat. Ara cal sincronitzar-la.',
                    'type' => 'success',
                ];
            } catch (Throwable $throwable) {
                $this->auditAdminAction($action . '_failed', [
                    'slug' => $slug,
                    'language_code' => $languageCode,
                    'error' => $throwable->getMessage(),
                ]);

                return [
                    'message' => 'No s’ha pogut actualitzar el Google Doc: ' . $throwable->getMessage(),
                    'type' => 'error',
                ];
            }
        }

        $this->auditAdminAction('unknown_action', ['action' => $action]);

        return [
            'message' => 'Acció d’administració no vàlida.',
            'type' => 'error',
        ];
    }

    public function auditAdminAction(string $action, array $context = []): void
    {
        $actor = (new AuthService())->actorUser();
        $actorId = $actor !== null ? (int) ($actor['id'] ?? 0) : 0;
        $actorEmail = $actor !== null ? (string) ($actor['email'] ?? '') : '';
        $parts = [
            'admin_action=' . $action,
            'actor_id=' . $actorId,
        ];

        if ($actorEmail !== '') {
            $parts[] = 'actor_email=' . $actorEmail;
        }

        foreach ($context as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $key);
            $parts[] = $safeKey . '=' . str_replace(["\r", "\n"], ' ', (string) $value);
        }

        (new LogService())->write(implode(' ', $parts));
    }
}
