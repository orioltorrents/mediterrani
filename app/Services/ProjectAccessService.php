<?php

declare(strict_types=1);

class ProjectAccessService
{
    public function canAccessProjectAcademicYear(?array $user, int $projectId, int $projectAcademicYearId): bool
    {
        if ($projectAcademicYearId <= 0 || !$this->projectAcademicYearBelongsToProject($projectId, $projectAcademicYearId)) {
            return false;
        }

        if ($user === null) {
            return false;
        }

        $roles = array_values(array_map('strval', $user['roles'] ?? []));
        if (in_array('admin', $roles, true) || in_array('coordinator', $roles, true)) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (in_array('student', $roles, true) && $this->studentCanAccess($userId, $projectAcademicYearId)) {
            return true;
        }

        return in_array('teacher', $roles, true) && $this->teacherCanAccess($userId, $projectAcademicYearId);
    }

    private function projectAcademicYearBelongsToProject(int $projectId, int $projectAcademicYearId): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1
             FROM project_academic_years
             WHERE id = :project_academic_year_id
               AND project_id = :project_id
             LIMIT 1'
        );
        $stmt->execute([
            'project_academic_year_id' => $projectAcademicYearId,
            'project_id' => $projectId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function studentCanAccess(int $userId, int $projectAcademicYearId): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1
             FROM class_members cm
             INNER JOIN project_class_assignments pca ON pca.class_id = cm.class_id
             INNER JOIN project_academic_years pay ON pay.id = pca.project_academic_year_id
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE cm.user_id = :user_id
               AND pca.project_academic_year_id = :project_academic_year_id
               AND ay.is_current = 1
               AND pay.status IN ("actiu", "realitzat")
               AND pca.status IN ("actiu", "realitzat")
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'project_academic_year_id' => $projectAcademicYearId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function teacherCanAccess(int $userId, int $projectAcademicYearId): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1
             FROM class_teachers ct
             INNER JOIN project_class_assignments pca ON pca.class_id = ct.class_id
             INNER JOIN project_academic_years pay ON pay.id = pca.project_academic_year_id
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE ct.user_id = :user_id
               AND pca.project_academic_year_id = :project_academic_year_id
               AND ay.is_current = 1
               AND pay.status IN ("pendent", "actiu", "realitzat")
               AND pca.status IN ("pendent", "actiu", "realitzat")
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'project_academic_year_id' => $projectAcademicYearId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
