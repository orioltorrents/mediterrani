<?php

declare(strict_types=1);

class ProjectAssignmentService
{
    public function projectsForStudent(int $userId, string $languageCode = 'ca'): array
    {
        return $this->projectsForUserClasses($userId, $languageCode, 'class_members', ['actiu', 'realitzat']);
    }

    public function projectsForTeacher(int $userId, string $languageCode = 'ca'): array
    {
        return $this->projectsForUserClasses($userId, $languageCode, 'class_teachers', ['pendent', 'actiu', 'realitzat']);
    }

    private function projectsForUserClasses(int $userId, string $languageCode, string $membershipTable, array $visibleStatuses): array
    {
        if (!in_array($membershipTable, ['class_members', 'class_teachers'], true)) {
            throw new InvalidArgumentException('Taula de relacio no valida.');
        }

        $pdo = $this->pdo();

        $editionStatusPlaceholders = implode(',', array_map(static fn (int $index): string => ':edition_status_' . $index, array_keys($visibleStatuses)));
        $assignmentStatusPlaceholders = implode(',', array_map(static fn (int $index): string => ':assignment_status_' . $index, array_keys($visibleStatuses)));

        $sql = "
            SELECT
                classes.id AS class_id,
                classes.class_name AS class_name,
                classes.class_code AS class_code,
                project_academic_years.id AS project_academic_year_id,
                project_academic_years.status AS project_academic_year_status,
                projects.id AS project_id,
                projects.slug,
                projects.display_order,
                academic_years.name AS academic_year_name,
                 COALESCE(pt_ca.title, projects.name) AS title,
                project_translations.description,
                project_class_assignments.status AS assignment_status
             FROM {$membershipTable}
               INNER JOIN classes ON classes.id = {$membershipTable}.class_id
            INNER JOIN project_class_assignments ON project_class_assignments.class_id = classes.id
            INNER JOIN project_academic_years ON project_academic_years.id = project_class_assignments.project_academic_year_id
            INNER JOIN projects ON projects.id = project_academic_years.project_id
            INNER JOIN academic_years ON academic_years.id = project_academic_years.academic_year_id
             LEFT JOIN languages ON languages.code = :language_code
             LEFT JOIN project_translations
                 ON project_translations.project_id = projects.id
                 AND project_translations.language_id = languages.id
             LEFT JOIN languages AS lang_ca ON lang_ca.code = 'ca'
             LEFT JOIN project_translations AS pt_ca
                 ON pt_ca.project_id = projects.id
                 AND pt_ca.language_id = lang_ca.id
            WHERE {$membershipTable}.user_id = :user_id
                AND projects.is_active = 1
                AND academic_years.is_current = 1
                AND project_academic_years.status IN ({$editionStatusPlaceholders})
                AND project_class_assignments.status IN ({$assignmentStatusPlaceholders})
            ORDER BY classes.class_name, projects.display_order, title
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $params = [
                'language_code' => $languageCode,
                'user_id' => $userId,
            ];

            foreach (array_values($visibleStatuses) as $index => $status) {
                $params['edition_status_' . $index] = $status;
                $params['assignment_status_' . $index] = $status;
            }

            $stmt->execute($params);

            return $this->groupRowsByClass($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return [];
        }
    }

    private function groupRowsByClass(array $rows): array
    {
        $classes = [];

        foreach ($rows as $row) {
            $classId = (int) $row['class_id'];

            if (!isset($classes[$classId])) {
                $classes[$classId] = [
                    'id' => $classId,
                    'name' => (string) $row['class_name'],
                    'code' => (string) $row['class_code'],
                    'projects' => [],
                ];
            }

            $classes[$classId]['projects'][] = [
                'id' => (int) $row['project_id'],
                'project_academic_year_id' => (int) $row['project_academic_year_id'],
                'slug' => (string) $row['slug'],
                'display_order' => (int) $row['display_order'],
                'title' => (string) $row['title'],
                'description' => $row['description'] !== null ? (string) $row['description'] : '',
                'academic_year_name' => (string) $row['academic_year_name'],
                'project_academic_year_status' => (string) $row['project_academic_year_status'],
                'assignment_status' => (string) $row['assignment_status'],
                'status' => (string) $row['assignment_status'],
            ];
        }

        $classes = array_values($classes);
        $projectIds = [];
        foreach ($classes as $class) {
            foreach ($class['projects'] as $project) {
                $projectIds[] = (int) $project['id'];
            }
        }

        $assetsByProject = (new ProjectAssetService())->assetsByProjectIds($projectIds);

        foreach ($classes as &$class) {
            foreach ($class['projects'] as &$project) {
                $project['assets'] = $assetsByProject[(int) $project['id']] ?? [];
                $project['logo_asset'] = $this->pickLogoAsset($project['assets']);
            }
            unset($project);
        }
        unset($class);

        return $classes;
    }

    private function pickLogoAsset(array $assets): ?array
    {
        $fallback = null;

        foreach ($assets as $asset) {
            if ($fallback === null) {
                $fallback = $asset;
            }

            if (($asset['asset_type'] ?? '') === 'project' && !empty($asset['logo_path'])) {
                return $asset;
            }
        }

        return $fallback;
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }

}
