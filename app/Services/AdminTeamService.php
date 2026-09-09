<?php

declare(strict_types=1);

class AdminTeamService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function studentsWithTeams(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT u.id AS user_id, u.name, u.surname, u.email, u.is_active,
                        c.id AS class_id, c.class_name, c.class_code,
                        pt.id AS team_id, pt.team_code, pt.team_name, pt.class_group
                 FROM users u
                 INNER JOIN user_web_roles uwr ON uwr.user_id = u.id
                 INNER JOIN web_roles wr ON wr.id = uwr.role_id AND wr.name = "student"
                 LEFT JOIN class_members cm ON cm.user_id = u.id
                 LEFT JOIN classes c ON c.id = cm.class_id
                 LEFT JOIN project_team_members ptm ON ptm.user_id = u.id
                 LEFT JOIN project_teams pt ON pt.id = ptm.project_team_id
                 GROUP BY u.id, u.name, u.surname, u.email, u.is_active, c.id, c.class_name, c.class_code, pt.id, pt.team_code, pt.team_name, pt.class_group
                 ORDER BY c.class_code, u.surname, u.name'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function availableTeams(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT pt.id, pt.team_code, pt.team_name, pt.class_group,
                        p.name AS project_name, ay.name AS academic_year_name, pay.id AS project_academic_year_id
                 FROM project_teams pt
                 INNER JOIN project_academic_years pay ON pay.id = pt.project_academic_year_id
                 INNER JOIN projects p ON p.id = pay.project_id
                 INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                 ORDER BY ay.start_year DESC, p.display_order, pt.class_group, pt.team_name'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function teamsWithMembers(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT pt.id, pt.team_code, pt.team_name, pt.class_id,
                        c.class_code, p.name AS project_name, ay.name AS academic_year_name,
                        u.id AS user_id, u.name, u.surname, u.email
                   FROM project_teams pt
                   INNER JOIN project_academic_years pay ON pay.id = pt.project_academic_year_id
                   INNER JOIN projects p ON p.id = pay.project_id
                   INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                   LEFT JOIN classes c ON c.id = pt.class_id
                   LEFT JOIN project_team_members ptm ON ptm.project_team_id = pt.id
                   LEFT JOIN users u ON u.id = ptm.user_id
               ORDER BY ay.start_year DESC, p.display_order, c.class_code, pt.team_code, u.surname, u.name'
            );

            $teams = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $teamId = (int) $row['id'];
                if (!isset($teams[$teamId])) {
                    $teams[$teamId] = [
                        'id' => $teamId,
                        'team_code' => (string) $row['team_code'],
                        'team_name' => (string) ($row['team_name'] ?? ''),
                        'class_code' => (string) ($row['class_code'] ?? ''),
                        'project_name' => (string) $row['project_name'],
                        'academic_year_name' => (string) $row['academic_year_name'],
                        'members' => [],
                    ];
                }

                if ($row['user_id'] !== null) {
                    $teams[$teamId]['members'][] = [
                        'id' => (int) $row['user_id'],
                        'name' => trim((string) $row['name'] . ' ' . (string) $row['surname']),
                        'email' => (string) $row['email'],
                    ];
                }
            }

            return array_values($teams);
        } catch (Throwable) {
            return [];
        }
    }

    public function syncStudentTeam(array $input): array
    {
        $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
        $teamId = filter_var($input['team_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedTeamId = $teamId === null || $teamId === false || $teamId <= 0 ? null : (int) $teamId;

        if ($userId === null || $userId === false) {
            return $this->message('Usuari no vàlid.', 'error');
        }

        $this->pdo->beginTransaction();

        try {
            // Remove existing team memberships for this student
            $deleteStmt = $this->pdo->prepare('DELETE FROM project_team_members WHERE user_id = :user_id');
            $deleteStmt->execute(['user_id' => (int) $userId]);

            if ($resolvedTeamId !== null) {
                // Find class_id of the student if assigned
                $classStmt = $this->pdo->prepare('SELECT class_id FROM class_members WHERE user_id = :user_id LIMIT 1');
                $classStmt->execute(['user_id' => (int) $userId]);
                $classId = $classStmt->fetchColumn();
                $resolvedClassId = $classId !== false ? (int) $classId : null;

                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO project_team_members (project_team_id, user_id, class_id, created_at)
                     VALUES (:team_id, :user_id, :class_id, NOW())'
                );
                $insertStmt->execute([
                    'team_id' => $resolvedTeamId,
                    'user_id' => (int) $userId,
                    'class_id' => $resolvedClassId,
                ]);
            }

            $this->pdo->commit();

            return $this->message('Assignació de grup actualitzada correctament.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut actualitzar el grup de l’alumne.', 'error');
        }
    }

    private function message(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
