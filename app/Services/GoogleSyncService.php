<?php

declare(strict_types=1);

class GoogleSyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function sourcesForProjectAcademicYear(int $projectAcademicYearId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, project_academic_year_id, source_type, google_file_id, google_file_url, sheet_name, range_name, language_code, content_type, visibility, sync_mode, is_active, last_synced_at
             FROM google_sources
             WHERE project_academic_year_id = :project_academic_year_id AND is_active = 1
             ORDER BY source_type, content_type, id'
        );
        $stmt->execute(['project_academic_year_id' => $projectAcademicYearId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncProjectAcademicYear(int $projectAcademicYearId, ?int $startedByUserId = null): array
    {
        $sources = $this->sourcesForProjectAcademicYear($projectAcademicYearId);

        return [
            'status' => 'pending',
            'project_academic_year_id' => $projectAcademicYearId,
            'started_by_user_id' => $startedByUserId,
            'sources_count' => count($sources),
            'sources' => $sources,
        ];
    }
}
