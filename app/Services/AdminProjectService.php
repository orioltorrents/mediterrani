<?php

declare(strict_types=1);

class AdminProjectService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function toggleProject(array $input): array
    {
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        if ($projectId === null || $projectId === false) {
            return $this->message('Projecte no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT is_active FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project === false) {
            return $this->message('No s’ha trobat el projecte.', 'error');
        }

        $newState = ((int) $project['is_active'] === 1) ? 0 : 1;
        $updateStmt = $this->pdo->prepare('UPDATE projects SET is_active = :is_active WHERE id = :id');
        $updateStmt->execute(['is_active' => $newState, 'id' => $projectId]);

        return $this->message('Estat del projecte actualitzat.', 'success');
    }

    public function updateProjectOrder(array $input): array
    {
        $orders = $input['display_order'] ?? [];
        if (!is_array($orders)) {
            return $this->message('Ordre de projectes no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare('UPDATE projects SET display_order = :display_order WHERE id = :id');
        $updated = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($orders as $projectId => $displayOrder) {
                $id = filter_var($projectId, FILTER_VALIDATE_INT);
                if ($id === false || $id === null) {
                    continue;
                }

                $order = filter_var($displayOrder, FILTER_VALIDATE_INT);
                if ($order === false || $order === null || $order < 0) {
                    $order = 0;
                }

                $stmt->execute([
                    'display_order' => (int) $order,
                    'id' => (int) $id,
                ]);
                $updated++;
            }

            $this->pdo->commit();

            return $this->message('Ordre dels projectes actualitzat (' . $updated . ').', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut actualitzar l’ordre dels projectes.', 'error');
        }
    }

    public function assignProjectToClass(array $input): array
    {
        $classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $this->normalizeProjectAssignmentStatus((string) ($input['status'] ?? 'actiu'));
        $allowedStatuses = ['pendent', 'actiu', 'realitzat'];

        if ($classId === null || $classId === false || $projectId === null || $projectId === false) {
            return $this->message('Classe o projecte no vàlid.', 'error');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            return $this->message('Estat de l’assignació no vàlid.', 'error');
        }

        $projectAcademicYearStmt = $this->pdo->prepare(
            'SELECT pay.id
             FROM project_academic_years pay
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE pay.project_id = :project_id
             ORDER BY ay.id DESC
             LIMIT 1'
        );
        $projectAcademicYearStmt->execute(['project_id' => (int) $projectId]);
        $projectAcademicYearId = $projectAcademicYearStmt->fetchColumn();

        if ($projectAcademicYearId === false) {
            return $this->message('Aquest projecte no té cap edició acadèmica vinculada.', 'error');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO project_class_assignments (project_academic_year_id, class_id, status, created_at)
             VALUES (:project_academic_year_id, :class_id, :status, NOW())
             ON DUPLICATE KEY UPDATE status = VALUES(status), project_academic_year_id = VALUES(project_academic_year_id)'
        );
        $stmt->execute([
            'project_academic_year_id' => (int) $projectAcademicYearId,
            'class_id' => (int) $classId,
            'status' => $status,
        ]);

        return $this->message('Projecte assignat a la classe correctament.', 'success');
    }

    public function syncProjectClassAssignments(array $input): array
    {
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        $classStatusesInput = $input['class_statuses'] ?? [];
        $allowedInputs = ['pendent', 'actiu', 'realitzat', 'no_assignat'];

        if ($projectId === null || $projectId === false) {
            return $this->message('Projecte no vàlid.', 'error');
        }

        if (!is_array($classStatusesInput)) {
            $classStatusesInput = [];
        }

        $classStatuses = [];
        foreach ($classStatusesInput as $classId => $rawStatus) {
            $classId = (int) $classId;
            $status = $this->normalizeProjectAssignmentStatus((string) $rawStatus);
            if ($classId <= 0 || !in_array($status, $allowedInputs, true)) {
                return $this->message('Assignacions de projecte no vàlides.', 'error');
            }

            $classStatuses[$classId] = $status;
        }

        $projectStmt = $this->pdo->prepare('SELECT id, name FROM projects WHERE id = :id LIMIT 1');
        $projectStmt->execute(['id' => (int) $projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        if ($project === false) {
            return $this->message('No s’ha trobat el projecte.', 'error');
        }

        $classStmt = $this->pdo->prepare('SELECT id, academic_year_id, class_code FROM classes WHERE id = :id LIMIT 1');
        $projectYearStmt = $this->pdo->prepare(
            'SELECT id
               FROM project_academic_years
              WHERE project_id = :project_id
                AND academic_year_id = :academic_year_id
              LIMIT 1'
        );

        $assignmentsToInsert = [];
        $missingClassCodes = [];

        foreach ($classStatuses as $classId => $status) {
            $classStmt->execute(['id' => $classId]);
            $class = $classStmt->fetch(PDO::FETCH_ASSOC);
            if ($class === false) {
                continue;
            }

            $projectYearStmt->execute([
                'project_id' => (int) $projectId,
                'academic_year_id' => (int) $class['academic_year_id'],
            ]);
            $projectAcademicYearId = $projectYearStmt->fetchColumn();

            if ($projectAcademicYearId === false) {
                $missingClassCodes[] = (string) $class['class_code'];
                continue;
            }

            if ($status === 'no_assignat') {
                continue;
            }

            $assignmentsToInsert[] = [
                'project_academic_year_id' => (int) $projectAcademicYearId,
                'class_id' => $classId,
                'status' => $status,
            ];
        }

        if ($missingClassCodes !== []) {
            return $this->message('No s’han desat canvis. Aquest projecte no té edició per a: ' . implode(', ', $missingClassCodes) . '.', 'error');
        }

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare(
                'DELETE pca
                   FROM project_class_assignments pca
                   INNER JOIN project_academic_years pay ON pay.id = pca.project_academic_year_id
                  WHERE pay.project_id = :project_id'
            );
            $deleteStmt->execute(['project_id' => (int) $projectId]);

            $insertStmt = $this->pdo->prepare(
                'INSERT INTO project_class_assignments (project_academic_year_id, class_id, status, created_at)
                 VALUES (:project_academic_year_id, :class_id, :status, NOW())'
            );

            $assigned = 0;
            foreach ($assignmentsToInsert as $assignment) {
                $insertStmt->execute($assignment);
                $assigned++;
            }

            $this->pdo->commit();

            return $this->message('Assignacions de ' . (string) $project['name'] . ' actualitzades: ' . $assigned . ' classes.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’han pogut actualitzar les assignacions del projecte.', 'error');
        }
    }

    public function updateProjectAcademicYearStatuses(array $input): array
    {
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        $statusesInput = $input['project_academic_year_statuses'] ?? [];
        $allowedStatuses = ['pendent', 'actiu', 'realitzat', 'arxivat'];

        if ($projectId === null || $projectId === false) {
            return $this->message('Projecte no vàlid.', 'error');
        }

        if (!is_array($statusesInput)) {
            return $this->message('Estats d’edició no vàlids.', 'error');
        }

        $projectYearIds = [];
        foreach ($statusesInput as $projectAcademicYearId => $rawStatus) {
            $projectAcademicYearId = (int) $projectAcademicYearId;
            $status = $this->normalizeProjectAcademicYearStatus((string) $rawStatus);

            if ($projectAcademicYearId <= 0 || !in_array($status, $allowedStatuses, true)) {
                return $this->message('Estats d’edició no vàlids.', 'error');
            }

            $projectYearIds[$projectAcademicYearId] = $status;
        }

        if ($projectYearIds === []) {
            return $this->message('No hi ha edicions per actualitzar.', 'error');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE project_academic_years
             SET status = :status
             WHERE id = :id
               AND project_id = :project_id'
        );

        $updated = 0;
        foreach ($projectYearIds as $projectAcademicYearId => $status) {
            $stmt->execute([
                'status' => $status,
                'id' => $projectAcademicYearId,
                'project_id' => (int) $projectId,
            ]);
            $updated += $stmt->rowCount();
        }

        return $this->message('Estats d’edició actualitzats (' . $updated . ').', 'success');
    }

    public function updateProjectAssignmentStatus(array $input): array
    {
        $assignmentId = filter_var($input['assignment_id'] ?? null, FILTER_VALIDATE_INT);
        $status = $this->normalizeProjectAssignmentStatus((string) ($input['status'] ?? 'actiu'));
        $allowedStatuses = ['pendent', 'actiu', 'realitzat'];

        if ($assignmentId === null || $assignmentId === false) {
            return $this->message('Assignació no vàlida.', 'error');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            return $this->message('Estat de l’assignació no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT status FROM project_class_assignments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $assignmentId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($assignment === false) {
            return $this->message('No s’ha trobat l’assignació.', 'error');
        }

        $updateStmt = $this->pdo->prepare('UPDATE project_class_assignments SET status = :status WHERE id = :id');
        $updateStmt->execute([
            'status' => $status,
            'id' => (int) $assignmentId,
        ]);

        return $this->message('Estat de l’assignació actualitzat.', 'success');
    }

    public function deleteProjectAssignment(array $input): array
    {
        $assignmentId = filter_var($input['assignment_id'] ?? null, FILTER_VALIDATE_INT);

        if ($assignmentId === null || $assignmentId === false) {
            return $this->message('Assignació no vàlida.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT id FROM project_class_assignments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $assignmentId]);

        if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
            return $this->message('No s’ha trobat l’assignació.', 'error');
        }

        $deleteStmt = $this->pdo->prepare('DELETE FROM project_class_assignments WHERE id = :id');
        $deleteStmt->execute(['id' => (int) $assignmentId]);

        return $this->message('Assignació eliminada correctament.', 'success');
    }

    private function normalizeProjectAssignmentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'planned', 'previst', 'pendent' => 'pendent',
            'active', 'actiu' => 'actiu',
            'completed', 'completat', 'realitzat' => 'realitzat',
            default => $normalized,
        };
    }

    private function normalizeProjectAcademicYearStatus(string $status): string
    {
        $normalized = $this->normalizeProjectAssignmentStatus($status);

        return match ($normalized) {
            'archived', 'archive', 'arxiu', 'arxivat' => 'arxivat',
            default => $normalized,
        };
    }

    private function message(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
