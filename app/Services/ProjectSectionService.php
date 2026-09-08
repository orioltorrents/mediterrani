<?php

declare(strict_types=1);

class ProjectSectionService
{
    public function visibleSectionsForProject(string $projectSlug, ?array $currentUser = null, ?int $projectAcademicYearId = null): array
    {
        $pdo = $this->pdo();
        $project = $this->projectBySlug($projectSlug);

        if ($project === null) {
            return [
                'project' => null,
                'sections' => [],
                'context' => $this->buildContext($currentUser, null),
            ];
        }

        $projectId = (int) $project['id'];
        $sections = $this->fetchSections($projectId);
        $sectionIds = array_map(static fn (array $section): int => (int) $section['id'], $sections);
        $rolesBySection = $this->groupRolesBySectionId($this->fetchSectionRoles($sectionIds));
        $context = $this->buildContext($currentUser, $projectId, $projectAcademicYearId);

        foreach ($sections as &$section) {
            $sectionId = (int) $section['id'];
            $section['roles'] = $rolesBySection[$sectionId] ?? [];
            $section['is_visible'] = $this->canViewSection($section, $context);
            $section['summary'] = $this->buildSectionSummary($section);
        }
        unset($section);

        $sections = array_values(array_filter($sections, static fn (array $section): bool => !empty($section['is_visible'])));

        return [
            'project' => $project,
            'sections' => $sections,
            'context' => $context,
        ];
    }

    private function buildContext(?array $currentUser, ?int $projectId, ?int $projectAcademicYearId = null): array
    {
        $context = [
            'user' => $currentUser,
            'roles' => [],
            'class_ids' => [],
            'teacher_class_ids' => [],
            'project_class_ids' => [],
            'is_admin' => false,
            'is_teacher' => false,
            'is_student' => false,
            'is_assigned_teacher' => false,
        ];

        if ($currentUser === null) {
            return $context;
        }

        $context['roles'] = array_values(array_map('strval', $currentUser['roles'] ?? []));
        $context['is_admin'] = in_array('admin', $context['roles'], true);
        $context['is_teacher'] = in_array('teacher', $context['roles'], true);
        $context['is_student'] = in_array('student', $context['roles'], true);
        $context['class_ids'] = $this->userClassIds((int) $currentUser['id']);
        $context['teacher_class_ids'] = $this->userTeacherClassIds((int) $currentUser['id']);
        $context['project_class_ids'] = $projectId !== null ? $this->projectClassIds($projectId, $projectAcademicYearId) : [];
        $context['is_assigned_teacher'] = $context['is_teacher'] && array_intersect($context['teacher_class_ids'], $context['project_class_ids']) !== [];

        return $context;
    }

    private function canViewSection(array $section, array $context): bool
    {
        if (($section['section_key'] ?? '') === 'notes' && !$context['is_student']) {
            return false;
        }

        if ($context['is_admin']) {
            return true;
        }

        $visibilityType = (string) ($section['visibility_type'] ?? 'public');

        if ($visibilityType === 'public') {
            return true;
        }

        if ($visibilityType === 'role') {
            foreach ($section['roles'] as $role) {
                if (in_array((string) $role['name'], $context['roles'], true)) {
                    return true;
                }
            }

            return false;
        }

        if ($visibilityType === 'class') {
            $classId = (int) ($section['class_id'] ?? 0);

            return $classId > 0 && in_array($classId, $context['class_ids'], true);
        }

        if ($visibilityType === 'assigned_teacher') {
            return (bool) $context['is_assigned_teacher'];
        }

        return false;
    }

    private function buildSectionSummary(array $section): string
    {
        $visibilityType = (string) ($section['visibility_type'] ?? 'public');

        if ($visibilityType === 'public') {
            return 'Pública';
        }

        if ($visibilityType === 'role') {
            $roleNames = array_map(static fn (array $role): string => (string) $role['name'], $section['roles'] ?? []);

            return $roleNames !== [] ? 'Rols: ' . implode(', ', $roleNames) : 'Per rol';
        }

        if ($visibilityType === 'class') {
            if (!empty($section['class_name'])) {
                return 'Classe: ' . (string) $section['class_name'];
            }

            return 'Per classe';
        }

        if ($visibilityType === 'assigned_teacher') {
            return 'Professorat assignat';
        }

        return 'Personalitzada';
    }

    private function fetchSections(int $projectId): array
    {
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT id, project_id, section_key, title, section_type, display_order, visibility_type, role_id, class_id, is_active, config_json
                 FROM project_sections
                 WHERE project_id = :project_id AND is_active = 1
                 ORDER BY display_order, title'
            );
            $stmt->execute(['project_id' => $projectId]);

            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sections as &$section) {
                $section['config'] = $this->decodeJson((string) ($section['config_json'] ?? ''));
                if (isset($section['config']['summary']) && is_string($section['config']['summary'])) {
                    $section['summary'] = $section['config']['summary'];
                }
            }
            unset($section);

            return $sections;
        } catch (Throwable) {
            return [];
        }
    }

    private function fetchSectionRoles(array $sectionIds): array
    {
        if ($sectionIds === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
            $stmt = $this->pdo()->prepare(
                "SELECT psr.project_section_id, r.id AS role_id, r.name
                  FROM project_section_roles psr
                  INNER JOIN web_roles r ON r.id = psr.role_id
                  WHERE psr.project_section_id IN ({$placeholders}) AND psr.allow_view = 1
                  ORDER BY r.name"
            );
            $stmt->execute($sectionIds);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function groupRolesBySectionId(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['project_section_id']][] = $row;
        }

        return $grouped;
    }

    private function projectBySlug(string $projectSlug): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, slug, name FROM projects WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $projectSlug]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        return $project === false ? null : $project;
    }

    private function userClassIds(int $userId): array
    {
        $stmt = $this->pdo()->prepare('SELECT class_id FROM class_members WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function userTeacherClassIds(int $userId): array
    {
        $stmt = $this->pdo()->prepare('SELECT class_id FROM class_teachers WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function projectClassIds(int $projectId, ?int $projectAcademicYearId = null): array
    {
        if ($projectAcademicYearId !== null && $projectAcademicYearId > 0) {
            $stmt = $this->pdo()->prepare(
                'SELECT project_class_assignments.class_id
                 FROM project_class_assignments
                 INNER JOIN project_academic_years ON project_academic_years.id = project_class_assignments.project_academic_year_id
                 WHERE project_academic_years.project_id = :project_id
                   AND project_academic_years.id = :project_academic_year_id'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'project_academic_year_id' => $projectAcademicYearId,
            ]);

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $stmt = $this->pdo()->prepare(
            'SELECT project_class_assignments.class_id
             FROM project_class_assignments
             INNER JOIN project_academic_years ON project_academic_years.id = project_class_assignments.project_academic_year_id
             WHERE project_academic_years.project_id = :project_id'
        );
        $stmt->execute(['project_id' => $projectId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
