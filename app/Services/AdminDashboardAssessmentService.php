<?php

declare(strict_types=1);

class AdminDashboardAssessmentService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assessmentSummary(): array
    {
        try {
            $phaseStmt = $this->pdo->query(
                'SELECT
                    COUNT(DISTINCT id) AS total,
                    COUNT(DISTINCT CASE WHEN is_active = 1 THEN id END) AS active
                 FROM project_academic_year_phases'
            );
            $phaseSummary = $phaseStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $taskStmt = $this->pdo->query(
                'SELECT
                    COUNT(DISTINCT id) AS total,
                    COUNT(DISTINCT CASE WHEN is_visible = 1 THEN id END) AS visible
                 FROM project_academic_year_phase_tasks'
            );
            $taskSummary = $taskStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $byProjectYear = $this->assessmentProjectYearSummary();
        } catch (Throwable) {
            $phaseSummary = [];
            $taskSummary = [];
            $byProjectYear = [];
        }

        return [
            'phases_total' => (int) ($phaseSummary['total'] ?? 0),
            'phases_active' => (int) ($phaseSummary['active'] ?? 0),
            'tasks_total' => (int) ($taskSummary['total'] ?? 0),
            'tasks_visible' => (int) ($taskSummary['visible'] ?? 0),
            'by_project_year' => $byProjectYear,
        ];
    }

    private function assessmentProjectYearSummary(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    p.name AS project_name,
                    p.slug AS project_slug,
                    ay.name AS academic_year_name,
                    COUNT(DISTINCT payp.id) AS phase_count,
                    COUNT(DISTINCT paypt.id) AS task_count
                 FROM project_academic_years pay
                 INNER JOIN projects p ON p.id = pay.project_id
                 INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                 LEFT JOIN project_academic_year_phases payp ON payp.project_academic_year_id = pay.id
                 LEFT JOIN project_academic_year_phase_tasks paypt ON paypt.project_academic_year_phase_id = payp.id
                 GROUP BY p.id, p.name, p.slug, p.display_order, ay.id, ay.name, ay.start_year
                 HAVING phase_count > 0 OR task_count > 0
                 ORDER BY ay.start_year DESC, p.display_order, p.name'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }
}
