<?php

declare(strict_types=1);

class AdminDashboardProjectService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function projects(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.id, p.name, p.slug, p.display_order, p.is_active, p.created_at,
                    COALESCE(pt.description, "") AS description
              FROM projects p
             LEFT JOIN languages l ON l.code = "ca"
             LEFT JOIN project_translations pt ON pt.project_id = p.id AND pt.language_id = l.id
             ORDER BY p.display_order, p.name'
        );
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $projectIds = array_map(static fn (array $project): int => (int) $project['id'], $projects);
        $projectAssetService = new ProjectAssetService();
        $assetsByProject = $projectAssetService->assetsByProjectIds($projectIds);
        $logoAssetsByProject = $projectAssetService->logoAssetByProjectIds($projectIds);

        foreach ($projects as &$project) {
            $project['assets'] = $assetsByProject[(int) $project['id']] ?? [];
            $project['logo_asset'] = $logoAssetsByProject[(int) $project['id']] ?? null;
        }
        unset($project);

        return $projects;
    }

    public function projectAssignments(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT pg.id, pg.class_id, p.id AS project_id, ay.name AS academic_year_name, pg.status, pg.created_at,
                        c.class_name AS class_name,
                        c.class_code AS class_code,
                        p.name AS project_name,
                        p.slug AS project_slug
                  FROM project_class_assignments pg
                  INNER JOIN classes c ON c.id = pg.class_id
                  INNER JOIN project_academic_years pay ON pay.id = pg.project_academic_year_id
                  INNER JOIN projects p ON p.id = pay.project_id
                  INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                  ORDER BY c.class_name, ay.id, p.display_order, p.name'
            );

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function projectTeamData(array $typicalProjectRoleNames): array
    {
        try {
            $stmt = $this->pdo->query(
            'SELECT pt.id AS project_team_id,
                    pt.project_academic_year_id,
                    pt.team_code,
                    pt.team_name,
                    pt.class_group,
                    pt.display_order,
                    pt.is_active,
                    p.name AS project_name,
                    p.slug AS project_slug,
                    ay.name AS academic_year_name,
                    ptm.user_id,
                    ptm.class_id AS member_class_id,
                    u.name AS member_name,
                    u.surname AS member_surname,
                    u.email AS member_email,
                    mc.class_code AS member_class_code,
                    GROUP_CONCAT(DISTINCT pr.name ORDER BY pr.name SEPARATOR "||") AS project_role_names
               FROM project_teams pt
               INNER JOIN project_academic_years pay ON pay.id = pt.project_academic_year_id
               INNER JOIN projects p ON p.id = pay.project_id
               INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
               LEFT JOIN project_team_members ptm ON ptm.project_team_id = pt.id
               LEFT JOIN users u ON u.id = ptm.user_id
               LEFT JOIN classes mc ON mc.id = ptm.class_id
               LEFT JOIN project_team_member_roles ptmr ON ptmr.project_team_member_id = ptm.id
               LEFT JOIN project_roles pr ON pr.id = ptmr.project_role_id
              GROUP BY pt.id, pt.project_academic_year_id, pt.team_code, pt.team_name, pt.class_group,
                       pt.display_order, pt.is_active, p.name, p.slug, ay.name, ptm.id, ptm.user_id,
                       ptm.class_id, u.name, u.surname, u.email, mc.class_code
              ORDER BY ay.id, p.display_order, p.name, pt.display_order, pt.team_code, u.surname, u.name'
            );
        } catch (Throwable) {
            return [
                'projectTeams' => [],
                'projectRoleGroups' => [],
            ];
        }
        $projectTeams = [];
        $projectRoleGroups = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $projectTeamId = (int) $row['project_team_id'];
            $roleNames = array_values(array_intersect(
                $this->splitStoredProjectRoleNames((string) ($row['project_role_names'] ?? '')),
                $typicalProjectRoleNames
            ));
            if (!isset($projectTeams[$projectTeamId])) {
                $projectTeams[$projectTeamId] = [
                    'id' => $projectTeamId,
                    'project_academic_year_id' => (int) $row['project_academic_year_id'],
                    'team_code' => (string) $row['team_code'],
                    'team_name' => (string) ($row['team_name'] ?? ''),
                    'class_group' => (string) ($row['class_group'] ?? ''),
                    'display_order' => (int) $row['display_order'],
                    'is_active' => (int) $row['is_active'],
                    'project_name' => (string) $row['project_name'],
                    'project_slug' => (string) $row['project_slug'],
                    'academic_year_name' => (string) $row['academic_year_name'],
                    'members' => [],
                ];
            }

            if (!empty($row['user_id'])) {
                $member = [
                    'id' => (int) $row['user_id'],
                    'name' => trim((string) $row['member_name'] . ' ' . (string) $row['member_surname']),
                    'email' => (string) ($row['member_email'] ?? ''),
                    'class_id' => !empty($row['member_class_id']) ? (int) $row['member_class_id'] : null,
                    'class_code' => (string) ($row['member_class_code'] ?? ''),
                    'project_role_names' => $roleNames,
                    'team_code' => (string) $row['team_code'],
                    'team_name' => (string) ($row['team_name'] ?? ''),
                    'class_group' => (string) ($row['class_group'] ?? ''),
                    'project_name' => (string) $row['project_name'],
                    'academic_year_name' => (string) $row['academic_year_name'],
                    'project_slug' => (string) $row['project_slug'],
                ];

                $projectTeams[$projectTeamId]['members'][] = [
                    'id' => $member['id'],
                    'name' => $member['name'],
                    'email' => $member['email'],
                    'class_code' => $member['class_code'],
                    'project_role_names' => $member['project_role_names'],
                ];

                $roleGroupNames = $roleNames !== [] ? $roleNames : ['Sense rol'];
                foreach ($roleGroupNames as $roleGroupName) {
                    if (!isset($projectRoleGroups[$roleGroupName])) {
                        $projectRoleGroups[$roleGroupName] = [
                            'name' => $roleGroupName,
                            'members' => [],
                        ];
                    }

                    $projectRoleGroups[$roleGroupName]['members'][] = $member;
                }
            }
        }

        return [
            'projectTeams' => $projectTeams,
            'projectRoleGroups' => $projectRoleGroups,
        ];
    }

    public function projectRoles(array $typicalProjectRoleNames): array
    {
        $placeholders = implode(',', array_fill(0, count($typicalProjectRoleNames), '?'));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT pr.id, pr.name, COUNT(DISTINCT ptmr.project_team_member_id) AS member_count
                   FROM project_roles pr
                   LEFT JOIN project_team_member_roles ptmr ON ptmr.project_role_id = pr.id
                  WHERE pr.name IN (' . $placeholders . ')
                  GROUP BY pr.id, pr.name
                  ORDER BY CASE pr.name
                      WHEN "coordinador/a" THEN 1
                      WHEN "informàtic/a" THEN 2
                      WHEN "cartògraf/a" THEN 3
                      WHEN "científic/a" THEN 4
                      ELSE 99
                  END, pr.name'
            );
            $stmt->execute($typicalProjectRoleNames);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function projectMembersWithoutRole(array $typicalProjectRoleNames): int
    {
        $placeholders = implode(',', array_fill(0, count($typicalProjectRoleNames), '?'));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                   FROM project_team_members ptm
                   LEFT JOIN project_team_member_roles ptmr ON ptmr.project_team_member_id = ptm.id
                   LEFT JOIN project_roles pr ON pr.id = ptmr.project_role_id
                    AND pr.name IN (' . $placeholders . ')
                  GROUP BY ptm.id
                 HAVING COUNT(pr.id) = 0'
            );
            $stmt->execute($typicalProjectRoleNames);

            return count($stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable) {
            return 0;
        }
    }

    private function splitStoredProjectRoleNames(string $storedRoleNames): array
    {
        $roleNames = array_filter(array_map('trim', explode('||', $storedRoleNames)), static fn (string $roleName): bool => $roleName !== '');

        return array_values(array_unique($roleNames));
    }
}
