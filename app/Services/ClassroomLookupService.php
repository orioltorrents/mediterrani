<?php

declare(strict_types=1);

class ClassroomLookupService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function academicYearId(string $academicYear): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM academic_years WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $academicYear]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('No existeix el curs ' . $academicYear . '.');
        }

        return (int) $id;
    }

    public function projectAcademicYearId(string $academicYear, string $projectSlug): int
    {
        return (int) $this->projectAcademicYear($academicYear, $projectSlug)['id'];
    }

    public function projectAcademicYear(string $academicYear, string $projectSlug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pay.id, pay.project_id
             FROM project_academic_years pay
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             INNER JOIN projects p ON p.id = pay.project_id
             WHERE ay.name = :academic_year
               AND p.slug = :project_slug
             LIMIT 1'
        );
        $stmt->execute([
            'academic_year' => $academicYear,
            'project_slug' => $projectSlug,
        ]);
        $projectAcademicYear = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($projectAcademicYear === false) {
            throw new RuntimeException('No existeix l\'edició ' . $academicYear . ' / ' . $projectSlug . '.');
        }

        return $projectAcademicYear;
    }

    public function findOrCreateClassroom(int $academicYearId, ?int $projectAcademicYearId, string $classroomKey, string $classroomName, string $classroomUrl, string $googleClassroomId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, google_classroom_id
             FROM classrooms
             WHERE academic_year_id = :academic_year_id
               AND classroom_key = :classroom_key
             LIMIT 1'
        );
        $stmt->execute([
            'academic_year_id' => $academicYearId,
            'classroom_key' => $classroomKey,
        ]);
        $classroom = $stmt->fetch(PDO::FETCH_ASSOC);

        $classroomNameInput = $classroomName;
        $classroomName = $classroomName !== '' ? $classroomName : $classroomKey;
        $classroomUrl = $classroomUrl !== '' ? $classroomUrl : null;
        $googleClassroomId = $googleClassroomId !== '' ? $googleClassroomId : null;

        if ($classroom !== false) {
            $update = $this->pdo->prepare(
                "UPDATE classrooms
                  SET classroom_name = CASE WHEN :classroom_name_input = '' THEN classroom_name ELSE :classroom_name END,
                     project_academic_year_id = COALESCE(project_academic_year_id, :project_academic_year_id),
                      classroom_url = COALESCE(:classroom_url, classroom_url),
                     google_classroom_id = COALESCE(:google_classroom_id, google_classroom_id),
                     is_active = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id"
            );
            $update->execute([
                'classroom_name' => $classroomName,
                'classroom_name_input' => $classroomNameInput,
                'project_academic_year_id' => $projectAcademicYearId,
                'classroom_url' => $classroomUrl,
                'google_classroom_id' => $googleClassroomId,
                'id' => (int) $classroom['id'],
            ]);

            return [
                'id' => (int) $classroom['id'],
                'google_classroom_id' => trim((string) ($classroom['google_classroom_id'] ?? '')) !== '' ? (string) $classroom['google_classroom_id'] : ($googleClassroomId ?? ''),
            ];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO classrooms
                (academic_year_id, project_academic_year_id, classroom_key, classroom_name, classroom_url, google_classroom_id, is_active)
             VALUES
                (:academic_year_id, :project_academic_year_id, :classroom_key, :classroom_name, :classroom_url, :google_classroom_id, 1)'
        );
        $insert->execute([
            'academic_year_id' => $academicYearId,
            'project_academic_year_id' => $projectAcademicYearId,
            'classroom_key' => $classroomKey,
            'classroom_name' => $classroomName,
            'classroom_url' => $classroomUrl,
            'google_classroom_id' => $googleClassroomId,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'google_classroom_id' => $googleClassroomId ?? '',
        ];
    }

    public function upsertProjectLink(int $classroomId, int $projectAcademicYearId, bool $isActive): bool
    {
        $exists = $this->classroomProjectLinkExists($classroomId, $projectAcademicYearId);
        $stmt = $this->pdo->prepare(
            'INSERT INTO classroom_project_academic_years
                (classroom_id, project_academic_year_id, is_active)
             VALUES
                (:classroom_id, :project_academic_year_id, :is_active)
             ON DUPLICATE KEY UPDATE
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'classroom_id' => $classroomId,
            'project_academic_year_id' => $projectAcademicYearId,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return !$exists;
    }

    public function userByEmail(string $email): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, surname, email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            throw new RuntimeException('No existeix cap usuari amb email ' . $email . '.');
        }

        return $user;
    }

    public function userHasRole(int $userId, string $roleName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM user_web_roles ur
             INNER JOIN web_roles wr ON wr.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND wr.name = :role_name'
        );
        $stmt->execute([
            'user_id' => $userId,
            'role_name' => $roleName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function classroomMembershipExists(int $classroomId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM classroom_members WHERE classroom_id = :classroom_id AND user_id = :user_id');
        $stmt->execute([
            'classroom_id' => $classroomId,
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function classroomProjectLinkExists(int $classroomId, int $projectAcademicYearId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM classroom_project_academic_years
             WHERE classroom_id = :classroom_id
               AND project_academic_year_id = :project_academic_year_id'
        );
        $stmt->execute([
            'classroom_id' => $classroomId,
            'project_academic_year_id' => $projectAcademicYearId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
