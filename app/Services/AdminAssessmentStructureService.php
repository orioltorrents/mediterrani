<?php

declare(strict_types=1);

class AdminAssessmentStructureService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function assessmentStructure(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                pay.id AS project_academic_year_id,
                p.id AS project_id,
                p.name AS project_name,
                p.slug AS project_slug,
                ay.name AS academic_year_name,
                pay.status AS project_academic_year_status,
                ap.id AS phase_id,
                ap.phase_key,
                ap.title AS phase_title,
                ap.section_type,
                payp.display_order AS phase_order,
                payp.is_active,
                payp.id AS project_academic_year_phase_id,
                at.id AS task_id,
                at.source_column,
                at.title AS task_title,
                at.weight_label,
                at.role_filter,
                paypt.display_order AS task_order,
                paypt.is_visible,
                paypt.id AS project_academic_year_phase_task_id
             FROM project_academic_year_phases payp
             INNER JOIN project_academic_years pay ON pay.id = payp.project_academic_year_id
             INNER JOIN projects p ON p.id = pay.project_id
              INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
              INNER JOIN assessment_phases ap ON ap.id = payp.assessment_phase_id
              LEFT JOIN project_academic_year_phase_tasks paypt ON paypt.project_academic_year_phase_id = payp.id
              LEFT JOIN assessment_tasks at ON at.id = paypt.assessment_task_id
              WHERE ay.is_current = 1
                AND pay.status IN ("pendent", "actiu")
              ORDER BY p.display_order, p.name, ay.id, payp.display_order, ap.id, paypt.display_order, at.id'
        );

        $projects = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $projectAcademicYearId = (int) $row['project_academic_year_id'];
            $phaseId = (int) $row['phase_id'];

            if (!isset($projects[$projectAcademicYearId])) {
                $projects[$projectAcademicYearId] = [
                    'id' => $projectAcademicYearId,
                    'project_id' => (int) $row['project_id'],
                    'name' => (string) $row['project_name'],
                    'slug' => (string) $row['project_slug'],
                    'academic_year_name' => (string) $row['academic_year_name'],
                    'status' => (string) $row['project_academic_year_status'],
                    'phases' => [],
                ];
            }

            if (!isset($projects[$projectAcademicYearId]['phases'][$phaseId])) {
                $projects[$projectAcademicYearId]['phases'][$phaseId] = [
                    'id' => $phaseId,
                    'project_academic_year_phase_id' => (int) $row['project_academic_year_phase_id'],
                    'phase_key' => (string) $row['phase_key'],
                    'title' => (string) $row['phase_title'],
                    'section_type' => (string) $row['section_type'],
                    'display_order' => (int) $row['phase_order'],
                    'is_active' => (int) $row['is_active'],
                    'tasks' => [],
                ];
            }

            if ($row['task_id'] !== null) {
                $projects[$projectAcademicYearId]['phases'][$phaseId]['tasks'][] = [
                    'id' => (int) $row['task_id'],
                    'project_academic_year_phase_task_id' => (int) $row['project_academic_year_phase_task_id'],
                    'source_column' => (string) $row['source_column'],
                    'title' => (string) $row['task_title'],
                    'weight_label' => $row['weight_label'] !== null ? (string) $row['weight_label'] : '',
                    'role_filter' => $row['role_filter'] !== null ? (string) $row['role_filter'] : '',
                    'display_order' => (int) $row['task_order'],
                    'is_visible' => (int) $row['is_visible'],
                ];
            }
        }

        foreach ($projects as &$project) {
            $project['phases'] = array_values($project['phases']);
        }
        unset($project);

        return array_values($projects);
    }

    public function importAssessmentStructure(array $files): array
    {
        if (!isset($files['phases_file'], $files['tasks_file'])
            || !is_uploaded_file((string) ($files['phases_file']['tmp_name'] ?? ''))
            || !is_uploaded_file((string) ($files['tasks_file']['tmp_name'] ?? ''))
        ) {
            return $this->result('Cal pujar els CSV assessment_phases i assessment_tasks.', 'error');
        }

        try {
            $importer = new AssessmentStructureImportService($this->pdo);
            $result = $importer->importFromCsv(
                (string) $files['phases_file']['tmp_name'],
                (string) $files['tasks_file']['tmp_name']
            );
        } catch (Throwable $e) {
            return $this->result('No s’ha pogut importar l’estructura d’avaluació: ' . $e->getMessage(), 'error');
        }

        $message = 'Estructura importada: '
            . (int) $result['phases_imported'] . ' fases i '
            . (int) $result['tasks_imported'] . ' tasques.';

        if (!empty($result['errors'])) {
            $message .= ' Errors: ' . implode(' | ', array_slice($result['errors'], 0, 5));
            return $this->result($message, 'error');
        }

        return $this->result($message, 'success');
    }

    public function toggleAssessmentPhase(array $input): array
    {
        $projectAcademicYearPhaseId = filter_var($input['project_academic_year_phase_id'] ?? null, FILTER_VALIDATE_INT);
        if ($projectAcademicYearPhaseId === false || $projectAcademicYearPhaseId === null) {
            return $this->result('Fase no vàlida.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT is_active FROM project_academic_year_phases WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $projectAcademicYearPhaseId]);
        $phase = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($phase === false) {
            return $this->result('No s’ha trobat la fase.', 'error');
        }

        $newState = ((int) $phase['is_active'] === 1) ? 0 : 1;
        $update = $this->pdo->prepare('UPDATE project_academic_year_phases SET is_active = :is_active WHERE id = :id');
        $update->execute(['is_active' => $newState, 'id' => (int) $projectAcademicYearPhaseId]);

        return $this->result('Estat de la fase actualitzat.', 'success');
    }

    public function toggleAssessmentTask(array $input): array
    {
        $projectAcademicYearPhaseTaskId = filter_var($input['project_academic_year_phase_task_id'] ?? null, FILTER_VALIDATE_INT);
        if ($projectAcademicYearPhaseTaskId === false || $projectAcademicYearPhaseTaskId === null) {
            return $this->result('Tasca no vàlida.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT is_visible FROM project_academic_year_phase_tasks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $projectAcademicYearPhaseTaskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($task === false) {
            return $this->result('No s’ha trobat la tasca.', 'error');
        }

        $newState = ((int) $task['is_visible'] === 1) ? 0 : 1;
        $update = $this->pdo->prepare('UPDATE project_academic_year_phase_tasks SET is_visible = :is_visible WHERE id = :id');
        $update->execute(['is_visible' => $newState, 'id' => (int) $projectAcademicYearPhaseTaskId]);

        return $this->result('Visibilitat de la tasca actualitzada.', 'success');
    }

    private function result(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
