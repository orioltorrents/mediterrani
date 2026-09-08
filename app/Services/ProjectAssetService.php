<?php

declare(strict_types=1);

class ProjectAssetService
{
    public function assetsByProjectIds(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));

        if ($projectIds === []) {
            return [];
        }

        $pdo = $this->pdo();

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    pal.project_id,
                    pa.id,
                    pa.slug,
                    pa.name,
                    pa.asset_type,
                    pa.logo_path,
                    pa.website_url,
                    pa.description,
                    pal.display_order
                 FROM project_asset_links pal
                 INNER JOIN project_assets pa ON pa.id = pal.asset_id
                 WHERE pal.project_id IN ({$placeholders})
                   AND pal.is_visible = 1
                   AND pa.is_active = 1
                 ORDER BY pal.project_id, pal.display_order, pa.display_order, pa.name"
            );
            $stmt->execute($projectIds);
        } catch (Throwable) {
            return [];
        }

        $assetsByProject = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $projectId = (int) $row['project_id'];

            $assetsByProject[$projectId][] = [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'asset_type' => (string) $row['asset_type'],
                'logo_path' => (string) ($row['logo_path'] ?? ''),
                'website_url' => $row['website_url'] !== null ? (string) $row['website_url'] : '',
                'description' => $row['description'] !== null ? (string) $row['description'] : '',
                'display_order' => (int) $row['display_order'],
            ];
        }

        return $assetsByProject;
    }

    public function logoAssetByProjectIds(array $projectIds): array
    {
        $assetsByProject = $this->assetsByProjectIds($projectIds);
        $logoAssets = [];

        foreach ($assetsByProject as $projectId => $assets) {
            $logoAssets[$projectId] = $this->pickLogoAsset($assets);
        }

        return $logoAssets;
    }

    public function assetsByProjectId(int $projectId): array
    {
        $assetsByProject = $this->assetsByProjectIds([$projectId]);

        return $assetsByProject[$projectId] ?? [];
    }

    public function logoAssetByProjectId(int $projectId): ?array
    {
        $logoAssets = $this->logoAssetByProjectIds([$projectId]);

        return $logoAssets[$projectId] ?? null;
    }

    private function pickLogoAsset(array $assets): ?array
    {
        $fallback = null;
        $fallbackWithLogo = null;

        foreach ($assets as $asset) {
            if ($fallback === null) {
                $fallback = $asset;
            }

            if ($fallbackWithLogo === null && !empty($asset['logo_path'])) {
                $fallbackWithLogo = $asset;
            }

            if (($asset['asset_type'] ?? '') === 'project' && !empty($asset['logo_path'])) {
                return $asset;
            }
        }

        return $fallbackWithLogo ?? $fallback;
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
