<?php

declare(strict_types=1);

class AdminDashboardClassroomService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function classrooms(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    c.id,
                    c.classroom_key,
                    c.classroom_name,
                    c.classroom_url,
                    c.google_classroom_id,
                    c.is_active,
                    p.name AS project_name,
                    p.slug AS project_slug,
                    ay.name AS academic_year_name,
                    ay.is_current AS academic_year_is_current,
                     0 AS member_count,
                    0 AS task_link_count
                  FROM classrooms c
                   INNER JOIN academic_years ay ON ay.id = c.academic_year_id
                   INNER JOIN project_academic_years pay ON pay.id = c.project_academic_year_id
                   LEFT JOIN projects p ON p.id = pay.project_id
                    GROUP BY c.id, c.classroom_key, c.classroom_name, c.classroom_url, c.google_classroom_id, c.is_active,
                             ay.name, ay.is_current, ay.start_year, p.name, p.slug
                   ORDER BY ay.is_current DESC, ay.start_year DESC, c.is_active DESC, c.classroom_name'
            );

            $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            try {
                $memberStmt = $this->pdo->query(
                    'SELECT classroom_id, COUNT(*) AS member_count
                       FROM classroom_members
                      WHERE is_active = 1
                      GROUP BY classroom_id'
                );
                $memberCounts = [];
                foreach ($memberStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $memberCounts[(int) $row['classroom_id']] = (int) $row['member_count'];
                }
                foreach ($classrooms as &$classroom) {
                    $classroom['member_count'] = $memberCounts[(int) $classroom['id']] ?? 0;
                }
                unset($classroom);
            } catch (Throwable) {
                // Els Classrooms es poden mostrar encara que la taula de membres no estigui disponible.
            }

            return $classrooms;
        } catch (Throwable) {
            return [];
        }
    }

    public function classroomSummary(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    COUNT(DISTINCT c.id) AS total,
                    COUNT(DISTINCT CASE WHEN c.is_active = 1 THEN c.id END) AS active,
                     COUNT(DISTINCT CASE WHEN c.is_active = 0 THEN c.id END) AS archived,
                     0 AS members
                   FROM classrooms c
                   '
            );
            $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            try {
                $summary['members'] = (int) $this->pdo->query(
                    'SELECT COUNT(*) FROM classroom_members WHERE is_active = 1'
                )->fetchColumn();
            } catch (Throwable) {
                $summary['members'] = 0;
            }
            $projectRows = $this->classroomProjectSummary();
        } catch (Throwable) {
            $summary = [];
            $projectRows = [];
        }

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'active' => (int) ($summary['active'] ?? 0),
            'archived' => (int) ($summary['archived'] ?? 0),
            'members' => (int) ($summary['members'] ?? 0),
            'project_count' => count($projectRows),
            'projects' => $projectRows,
        ];
    }

    public function classroomMembers(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT cm.classroom_id, cm.google_user_id, cm.is_active,
                        c.classroom_name, c.classroom_key,
                        u.name, u.surname, u.email
                   FROM classroom_members cm
                   INNER JOIN classrooms c ON c.id = cm.classroom_id
                   LEFT JOIN users u ON u.id = cm.user_id
                  ORDER BY c.classroom_name, u.surname, u.name, u.email'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function classroomProjectSummary(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    p.name,
                    p.slug,
                    COUNT(DISTINCT c.id) AS classroom_count
                  FROM classrooms c
                  INNER JOIN project_academic_years pay ON pay.id = c.project_academic_year_id
                  INNER JOIN projects p ON p.id = pay.project_id
                  WHERE c.is_active = 1
                 GROUP BY p.id, p.name, p.slug, p.display_order
                 ORDER BY p.display_order, p.name'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }
}
