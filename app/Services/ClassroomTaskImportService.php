<?php

declare(strict_types=1);

class ClassroomTaskImportService
{
    public function __construct(
        private PDO $pdo,
        private ClassroomCsvImportService $csvImportService,
        private ClassroomLookupService $lookupService,
    ) {
    }

    public function importTaskLinksUploadedFile(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return AppHelper::result('No s\'ha rebut cap fitxer CSV de tasques de Classroom.', 'error');
        }

        try {
            $csv = $this->csvImportService->readCsvWithHeaders((string) $file['tmp_name']);
            $this->csvImportService->validateHeaders(
                $csv['headers'],
                ['academic_year', 'classroom_key', 'project_slug', 'phase_key', 'phase_title', 'task_key', 'task_title', 'task_url'],
                'classroom_task_links'
            );
        } catch (Throwable $throwable) {
            return AppHelper::result('No s\'ha pogut llegir el CSV: ' . $throwable->getMessage(), 'error');
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($csv['rows'] as $rowNumber => $row) {
            try {
                $this->pdo->beginTransaction();
                $wasCreated = $this->importTaskLinkRowData($row);
                $this->pdo->commit();

                if ($wasCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $throwable) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $errors[] = 'Fila ' . $rowNumber . ': ' . $throwable->getMessage();
            }
        }

        $message = 'Importació de tasques Classroom: ' . $created . ' enllaços creats i ' . $updated . ' actualitzats.';
        if ($errors !== []) {
            $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
            return AppHelper::result($message, 'error');
        }

        return AppHelper::result($message, 'success');
    }

    public function importTaskLinkRowData(array $row): bool
    {
        $academicYear = trim((string) ($row['academic_year'] ?? ''));
        $classroomKey = trim((string) ($row['classroom_key'] ?? ''));
        $classroomName = trim((string) ($row['classroom_name'] ?? ''));
        $classroomUrl = trim((string) ($row['classroom_url'] ?? ''));
        $googleClassroomId = trim((string) ($row['google_classroom_id'] ?? ''));
        $projectSlug = trim((string) ($row['project_slug'] ?? ''));
        $phaseKey = trim((string) ($row['phase_key'] ?? ''));
        $phaseTitle = trim((string) ($row['phase_title'] ?? ''));
        $taskKey = trim((string) ($row['task_key'] ?? ''));
        $taskTitle = trim((string) ($row['task_title'] ?? ''));
        $taskUrl = trim((string) ($row['task_url'] ?? ''));
        $googleCourseWorkId = trim((string) ($row['google_course_work_id'] ?? ''));
        $googleRubricId = trim((string) ($row['google_rubric_id'] ?? ''));
        $roleFilter = trim((string) ($row['role_filter'] ?? ''));

        if ($academicYear === '' || $classroomKey === '' || $projectSlug === '' || $phaseKey === '' || $phaseTitle === '' || $taskKey === '' || $taskTitle === '' || $taskUrl === '') {
            throw new RuntimeException('academic_year, classroom_key, project_slug, phase_key, phase_title, task_key, task_title i task_url son obligatoris.');
        }

        $taskUrlScheme = strtolower((string) parse_url($taskUrl, PHP_URL_SCHEME));
        if (filter_var($taskUrl, FILTER_VALIDATE_URL) === false || !in_array($taskUrlScheme, ['http', 'https'], true)) {
            throw new RuntimeException('task_url no és una URL http/https vàlida.');
        }

        $academicYearId = $this->lookupService->academicYearId($academicYear);
        $projectAcademicYear = $this->lookupService->projectAcademicYear($academicYear, $projectSlug);
        $projectAcademicYearId = (int) $projectAcademicYear['id'];
        $projectId = (int) $projectAcademicYear['project_id'];
        $classroom = $this->lookupService->findOrCreateClassroom($academicYearId, $projectAcademicYearId, $classroomKey, $classroomName, $classroomUrl, $googleClassroomId);
        $this->lookupService->upsertProjectLink((int) $classroom['id'], $projectAcademicYearId, true);

        $phaseId = $this->upsertAssessmentPhase($projectId, $phaseKey, $phaseTitle);
        $projectAcademicYearPhaseId = $this->upsertProjectAcademicYearPhase($projectAcademicYearId, $phaseId);
        $taskId = $this->upsertAssessmentTask($phaseId, $taskKey, $taskTitle, $roleFilter);
        $projectAcademicYearPhaseTaskId = $this->upsertProjectAcademicYearPhaseTask($projectAcademicYearPhaseId, $taskId);

        return $this->upsertTaskClassroomLink($projectAcademicYearPhaseTaskId, (int) $classroom['id'], $taskUrl, $googleCourseWorkId, $googleRubricId);
    }

    private function upsertAssessmentPhase(int $projectId, string $phaseKey, string $phaseTitle): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM assessment_phases WHERE project_id = :project_id AND phase_key = :phase_key LIMIT 1');
        $stmt->execute([
            'project_id' => $projectId,
            'phase_key' => $phaseKey,
        ]);
        $phaseId = $stmt->fetchColumn();

        if ($phaseId !== false) {
            $update = $this->pdo->prepare(
                'UPDATE assessment_phases
                 SET title = :title,
                     is_active = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $update->execute([
                'title' => $phaseTitle,
                'id' => (int) $phaseId,
            ]);

            return (int) $phaseId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO assessment_phases (project_id, phase_key, title, display_order, is_active)
             VALUES (:project_id, :phase_key, :title, :display_order, 1)'
        );
        $insert->execute([
            'project_id' => $projectId,
            'phase_key' => $phaseKey,
            'title' => $phaseTitle,
            'display_order' => $this->nextPhaseDisplayOrder($projectId),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertProjectAcademicYearPhase(int $projectAcademicYearId, int $phaseId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM project_academic_year_phases
             WHERE project_academic_year_id = :project_academic_year_id
               AND assessment_phase_id = :assessment_phase_id
             LIMIT 1'
        );
        $stmt->execute([
            'project_academic_year_id' => $projectAcademicYearId,
            'assessment_phase_id' => $phaseId,
        ]);
        $projectAcademicYearPhaseId = $stmt->fetchColumn();

        if ($projectAcademicYearPhaseId !== false) {
            $update = $this->pdo->prepare('UPDATE project_academic_year_phases SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute(['id' => (int) $projectAcademicYearPhaseId]);

            return (int) $projectAcademicYearPhaseId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO project_academic_year_phases (project_academic_year_id, assessment_phase_id, display_order, is_active)
             SELECT :project_academic_year_id, ap.id, ap.display_order, 1
             FROM assessment_phases ap
             WHERE ap.id = :assessment_phase_id'
        );
        $insert->execute([
            'project_academic_year_id' => $projectAcademicYearId,
            'assessment_phase_id' => $phaseId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertAssessmentTask(int $phaseId, string $taskKey, string $taskTitle, string $roleFilter): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM assessment_tasks WHERE phase_id = :phase_id AND source_column = :source_column LIMIT 1');
        $stmt->execute([
            'phase_id' => $phaseId,
            'source_column' => $taskKey,
        ]);
        $taskId = $stmt->fetchColumn();
        $roleFilter = $roleFilter !== '' ? $roleFilter : null;

        if ($taskId !== false) {
            $update = $this->pdo->prepare(
                'UPDATE assessment_tasks
                 SET title = :title,
                     role_filter = :role_filter,
                     is_visible = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $update->execute([
                'title' => $taskTitle,
                'role_filter' => $roleFilter,
                'id' => (int) $taskId,
            ]);

            return (int) $taskId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO assessment_tasks (phase_id, source_column, title, role_filter, display_order, is_visible)
             VALUES (:phase_id, :source_column, :title, :role_filter, :display_order, 1)'
        );
        $insert->execute([
            'phase_id' => $phaseId,
            'source_column' => $taskKey,
            'title' => $taskTitle,
            'role_filter' => $roleFilter,
            'display_order' => $this->nextTaskDisplayOrder($phaseId),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertProjectAcademicYearPhaseTask(int $projectAcademicYearPhaseId, int $taskId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM project_academic_year_phase_tasks
             WHERE project_academic_year_phase_id = :project_academic_year_phase_id
               AND assessment_task_id = :assessment_task_id
             LIMIT 1'
        );
        $stmt->execute([
            'project_academic_year_phase_id' => $projectAcademicYearPhaseId,
            'assessment_task_id' => $taskId,
        ]);
        $projectAcademicYearPhaseTaskId = $stmt->fetchColumn();

        if ($projectAcademicYearPhaseTaskId !== false) {
            $update = $this->pdo->prepare('UPDATE project_academic_year_phase_tasks SET is_visible = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $update->execute(['id' => (int) $projectAcademicYearPhaseTaskId]);

            return (int) $projectAcademicYearPhaseTaskId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO project_academic_year_phase_tasks (project_academic_year_phase_id, assessment_task_id, display_order, is_visible)
             SELECT :project_academic_year_phase_id, at.id, at.display_order, 1
             FROM assessment_tasks at
             WHERE at.id = :assessment_task_id'
        );
        $insert->execute([
            'project_academic_year_phase_id' => $projectAcademicYearPhaseId,
            'assessment_task_id' => $taskId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertTaskClassroomLink(int $projectAcademicYearPhaseTaskId, int $classroomId, string $taskUrl, string $googleCourseWorkId, string $googleRubricId): bool
    {
        $existsStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM assessment_task_classroom_links
             WHERE project_academic_year_phase_task_id = :project_academic_year_phase_task_id
               AND classroom_id = :classroom_id'
        );
        $existsStmt->execute([
            'project_academic_year_phase_task_id' => $projectAcademicYearPhaseTaskId,
            'classroom_id' => $classroomId,
        ]);
        $exists = (int) $existsStmt->fetchColumn() > 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO assessment_task_classroom_links
                (project_academic_year_phase_task_id, classroom_id, task_url, google_course_work_id, google_rubric_id, is_visible)
             VALUES
                (:project_academic_year_phase_task_id, :classroom_id, :task_url, :google_course_work_id, :google_rubric_id, 1)
             ON DUPLICATE KEY UPDATE
                task_url = VALUES(task_url),
                google_course_work_id = VALUES(google_course_work_id),
                google_rubric_id = VALUES(google_rubric_id),
                is_visible = 1,
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'project_academic_year_phase_task_id' => $projectAcademicYearPhaseTaskId,
            'classroom_id' => $classroomId,
            'task_url' => $taskUrl,
            'google_course_work_id' => $googleCourseWorkId !== '' ? $googleCourseWorkId : null,
            'google_rubric_id' => $googleRubricId !== '' ? $googleRubricId : null,
        ]);

        return !$exists;
    }

    private function nextPhaseDisplayOrder(int $projectId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(display_order), 0) + 10 FROM assessment_phases WHERE project_id = :project_id');
        $stmt->execute(['project_id' => $projectId]);

        return (int) $stmt->fetchColumn();
    }

    private function nextTaskDisplayOrder(int $phaseId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(display_order), 0) + 10 FROM assessment_tasks WHERE phase_id = :phase_id');
        $stmt->execute(['phase_id' => $phaseId]);

        return (int) $stmt->fetchColumn();
    }
}
