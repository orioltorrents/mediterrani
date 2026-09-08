<?php

declare(strict_types=1);

class ProjectService
{
    public function allActive(string $languageCode = 'ca'): array
    {
        $pdo = $this->pdo();

        $stmt = $pdo->prepare(
            'SELECT
                projects.id,
                projects.slug,
                projects.name,
                projects.display_order,
                projects.is_active,
                COALESCE(pt_ca.title, projects.name) AS title,
                project_translations.description
             FROM projects
             LEFT JOIN languages ON languages.code = :language_code
             LEFT JOIN project_translations
                ON project_translations.project_id = projects.id
                AND project_translations.language_id = languages.id
             LEFT JOIN languages AS lang_ca ON lang_ca.code = \'ca\'
             LEFT JOIN project_translations AS pt_ca
                ON pt_ca.project_id = projects.id
                AND pt_ca.language_id = lang_ca.id
             WHERE projects.is_active = 1
             ORDER BY projects.display_order, title'
        );
        $stmt->execute(['language_code' => $languageCode]);

        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->attachAssetsToProjects($projects);
    }

    public function findActiveBySlug(string $slug, string $languageCode = 'ca'): ?array
    {
        $pdo = $this->pdo();

        $stmt = $pdo->prepare(
            'SELECT
                projects.id,
                projects.slug,
                projects.name,
                projects.display_order,
                projects.is_active,
                COALESCE(pt_ca.title, projects.name) AS title,
                project_translations.description
             FROM projects
             LEFT JOIN languages ON languages.code = :language_code
             LEFT JOIN project_translations
                ON project_translations.project_id = projects.id
                AND project_translations.language_id = languages.id
             LEFT JOIN languages AS lang_ca ON lang_ca.code = \'ca\'
             LEFT JOIN project_translations AS pt_ca
                ON pt_ca.project_id = projects.id
                AND pt_ca.language_id = lang_ca.id
             WHERE projects.slug = :slug
                AND projects.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'language_code' => $languageCode,
            'slug' => $slug,
        ]);

        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project === false) {
            return null;
        }

        $projectAssetService = new ProjectAssetService();
        $project['assets'] = $projectAssetService->assetsByProjectId((int) $project['id']);
        $project['logo_asset'] = $projectAssetService->logoAssetByProjectId((int) $project['id']);

        return $project;
    }

    public function academicYearForProject(int $projectId, ?int $projectAcademicYearId = null): ?array
    {
        if ($projectAcademicYearId !== null && $projectAcademicYearId > 0) {
            $stmt = $this->pdo()->prepare(
                'SELECT pay.id, pay.project_id, pay.academic_year_id, pay.status, ay.name AS academic_year_name
                 FROM project_academic_years pay
                 INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                 WHERE pay.id = :id
                   AND pay.project_id = :project_id
                 LIMIT 1'
            );
            $stmt->execute([
                'id' => $projectAcademicYearId,
                'project_id' => $projectId,
            ]);
            $projectAcademicYear = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($projectAcademicYear !== false) {
                return $projectAcademicYear;
            }
        }

        $stmt = $this->pdo()->prepare(
            'SELECT pay.id, pay.project_id, pay.academic_year_id, pay.status, ay.name AS academic_year_name
             FROM project_academic_years pay
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE pay.project_id = :project_id
               AND ay.is_current = 1
             ORDER BY ay.id DESC
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $projectAcademicYear = $stmt->fetch(PDO::FETCH_ASSOC);

        return $projectAcademicYear !== false ? $projectAcademicYear : null;
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }

    private function attachAssetsToProjects(array $projects): array
    {
        $projectIds = array_map(static fn (array $project): int => (int) $project['id'], $projects);
        $assetsByProject = (new ProjectAssetService())->assetsByProjectIds($projectIds);

        foreach ($projects as &$project) {
            $project['assets'] = $assetsByProject[(int) $project['id']] ?? [];
        }
        unset($project);

        return $projects;
    }
}
