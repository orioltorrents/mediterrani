<?php

declare(strict_types=1);

class DocumentImportService
{
    private ?PDO $connection = null;

    public function importPayload(array $payload): array
    {
        $documents = $payload['documents'] ?? null;
        $documentSources = $payload['document_sources'] ?? null;
        $documentFragments = $payload['document_fragments'] ?? null;

        if (!is_array($documents) || !is_array($documentSources) || !is_array($documentFragments)) {
            throw new InvalidArgumentException('El JSON ha de contenir documents, document_sources i document_fragments com a arrays.');
        }

        $pdo = $this->pdo();
        $this->connection = $pdo;
        $summary = [
            'documents_imported' => 0,
            'sources_imported' => 0,
            'fragments_imported' => 0,
            'rules_imported' => 0,
            'warnings' => [],
        ];

        $pdo->beginTransaction();

        try {
            $documentIds = [];

            foreach ($documents as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if ($this->isHeaderRow($row, ['project_slug', 'slug', 'title'])) {
                    continue;
                }

                $documentId = $this->upsertDocument($row);
                $documentIds[(string) $row['slug']] = $documentId;
                $summary['documents_imported']++;

                $defaultVisibilityValue = (string) ($row['default_visibility'] ?? 'public');
                $documentVisibility = $this->normalizeDocumentVisibility($defaultVisibilityValue);
                $this->upsertDocumentRule(
                    $documentId,
                    null,
                    $this->visibilityTypeFromVisibilityValue($documentVisibility),
                    $this->resolveWebRoleIdFromVisibilityValue($documentVisibility),
                    $this->resolveProjectRoleIdFromVisibilityValue($documentVisibility),
                    $this->resolveClassId($documentVisibility),
                    1,
                    0,
                    10
                );
                $summary['rules_imported']++;
            }

            foreach ($documentSources as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if ($this->isHeaderRow($row, ['document_slug', 'source_type', 'source_url'])) {
                    continue;
                }

                $documentSlug = trim((string) ($row['document_slug'] ?? ''));
                if ($documentSlug === '' || !isset($documentIds[$documentSlug])) {
                    $summary['warnings'][] = 'Source sense document vàlid: ' . $documentSlug;
                    continue;
                }

                $documentId = $documentIds[$documentSlug];
                $this->upsertDocumentSource($documentId, $row);
                $summary['sources_imported']++;
            }

            $fragmentIds = [];
            foreach ($documentFragments as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if ($this->isHeaderRow($row, ['document_slug', 'fragment_key', 'title'])) {
                    continue;
                }

                $documentSlug = trim((string) ($row['document_slug'] ?? ''));
                if ($documentSlug === '' || !isset($documentIds[$documentSlug])) {
                    $summary['warnings'][] = 'Fragment sense document vàlid: ' . $documentSlug;
                    continue;
                }

                $documentId = $documentIds[$documentSlug];
                $fragmentId = $this->upsertDocumentFragment($documentId, $row);
                $fragmentIds[$documentSlug . ':' . (string) ($row['fragment_key'] ?? '')] = $fragmentId;
                $summary['fragments_imported']++;

                $visibilityValue = trim((string) ($row['visibility'] ?? ''));
                if ($visibilityValue !== '') {
                    $visibilityType = $this->visibilityTypeFromVisibilityValue($visibilityValue);
                    $this->upsertDocumentRule(
                        $documentId,
                        $fragmentId,
                        $visibilityType,
                        $this->resolveWebRoleIdFromVisibilityValue($visibilityValue),
                        $this->resolveProjectRoleIdFromVisibilityValue($visibilityValue),
                        $this->resolveClassId($visibilityValue),
                        1,
                        0,
                        100
                    );
                    $summary['rules_imported']++;
                }
            }

            $pdo->commit();

            return $summary;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function upsertDocument(array $row): int
    {
        $projectSlug = trim((string) ($row['project_slug'] ?? ''));
        $slug = trim((string) ($row['slug'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));

        if ($projectSlug === '' || $slug === '' || $title === '') {
            throw new InvalidArgumentException('project_slug, slug i title son obligatoris a documents.');
        }

        $projectId = $this->projectIdBySlug($projectSlug);
        $projectAcademicYearId = $this->projectAcademicYearIdByProjectId($projectId);
        $existingId = $this->documentIdByProjectAcademicYearAndSlug($projectAcademicYearId, $slug);
        $params = [
            'project_academic_year_id' => $projectAcademicYearId,
            'slug' => $slug,
            'title' => $title,
            'doc_type' => $this->nonEmptyString($row['doc_type'] ?? 'markdown') ?? 'markdown',
            'default_visibility' => $this->normalizeDocumentVisibility((string) ($row['default_visibility'] ?? 'public')),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'is_active' => $this->toBooleanInt($row['is_active'] ?? 1),
            'display_order' => $this->toUnsignedInt($row['display_order'] ?? 0),
        ];

        if ($existingId !== null) {
            $stmt = $this->pdo()->prepare(
                'UPDATE documents
                 SET title = :title,
                     doc_type = :doc_type,
                     default_visibility = :default_visibility,
                     notes = :notes,
                     is_active = :is_active,
                     display_order = :display_order,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'title' => $params['title'],
                'doc_type' => $params['doc_type'],
                'default_visibility' => $params['default_visibility'],
                'notes' => $params['notes'],
                'is_active' => $params['is_active'],
                'display_order' => $params['display_order'],
                'id' => $existingId,
            ]);

            return $existingId;
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO documents (project_academic_year_id, slug, title, doc_type, default_visibility, notes, is_active, display_order)
             VALUES (:project_academic_year_id, :slug, :title, :doc_type, :default_visibility, :notes, :is_active, :display_order)'
        );
        $stmt->execute($params);

        return (int) $this->pdo()->lastInsertId();
    }

    private function upsertDocumentSource(int $documentId, array $row): void
    {
        $sourceType = $this->nonEmptyString($row['source_type'] ?? 'markdown') ?? 'markdown';
        $sourceFingerprint = $this->sourceFingerprint($row);
        $existingId = $this->documentSourceIdByFingerprint($documentId, $sourceFingerprint);
        $params = [
            'document_id' => $documentId,
            'source_fingerprint' => $sourceFingerprint,
            'source_type' => $sourceType,
            'source_url' => $this->nullableString($row['source_url'] ?? null),
            'external_id' => $this->nullableString($row['external_id'] ?? null),
            'sheet_name' => $this->nullableString($row['sheet_name'] ?? null),
            'range_name' => $this->nullableString($row['range_name'] ?? null),
            'sync_mode' => $this->normalizeSyncMode((string) ($row['sync_mode'] ?? 'manual')),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'is_active' => $this->toBooleanInt($row['is_active'] ?? 1),
        ];

        if ($existingId !== null) {
            $stmt = $this->pdo()->prepare(
                'UPDATE document_sources
                 SET source_type = :source_type,
                     source_url = :source_url,
                     external_id = :external_id,
                     sheet_name = :sheet_name,
                     range_name = :range_name,
                     sync_mode = :sync_mode,
                     notes = :notes,
                     is_active = :is_active,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'source_type' => $params['source_type'],
                'source_url' => $params['source_url'],
                'external_id' => $params['external_id'],
                'sheet_name' => $params['sheet_name'],
                'range_name' => $params['range_name'],
                'sync_mode' => $params['sync_mode'],
                'notes' => $params['notes'],
                'is_active' => $params['is_active'],
                'id' => $existingId,
            ]);
            return;
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO document_sources
                (document_id, source_fingerprint, source_type, source_url, external_id, sheet_name, range_name, sync_mode, notes, is_active)
             VALUES
                (:document_id, :source_fingerprint, :source_type, :source_url, :external_id, :sheet_name, :range_name, :sync_mode, :notes, :is_active)'
        );
        $stmt->execute($params);
    }

    private function upsertDocumentFragment(int $documentId, array $row): int
    {
        $fragmentKey = trim((string) ($row['fragment_key'] ?? ''));
        $title = trim((string) ($row['title'] ?? ''));

        if ($fragmentKey === '' || $title === '') {
            throw new InvalidArgumentException('fragment_key i title son obligatoris a document_fragments.');
        }

        if ($this->isHeaderRow($row, ['document_slug', 'fragment_key', 'title'])) {
            throw new InvalidArgumentException('Fila de capçalera detectada a document_fragments.');
        }

        $existingId = $this->documentFragmentIdByDocumentAndKey($documentId, $fragmentKey);
        $params = [
            'document_id' => $documentId,
            'fragment_key' => $fragmentKey,
            'title' => $title,
            'content' => $this->nullableString($row['content'] ?? null),
            'content_format' => $this->normalizeContentFormat((string) ($row['content_format'] ?? 'markdown')),
            'notes' => $this->nullableString($row['notes'] ?? null),
            'display_order' => $this->toUnsignedInt($row['display_order'] ?? 0),
            'is_active' => $this->toBooleanInt($row['is_active'] ?? 1),
        ];

        if ($existingId !== null) {
            $stmt = $this->pdo()->prepare(
                'UPDATE document_fragments
                 SET title = :title,
                     content = :content,
                     content_format = :content_format,
                     notes = :notes,
                     display_order = :display_order,
                     is_active = :is_active,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'title' => $params['title'],
                'content' => $params['content'],
                'content_format' => $params['content_format'],
                'notes' => $params['notes'],
                'display_order' => $params['display_order'],
                'is_active' => $params['is_active'],
                'id' => $existingId,
            ]);

            return $existingId;
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO document_fragments
                (document_id, fragment_key, title, content, content_format, notes, display_order, is_active)
             VALUES
                (:document_id, :fragment_key, :title, :content, :content_format, :notes, :display_order, :is_active)'
        );
        $stmt->execute($params);

        return (int) $this->pdo()->lastInsertId();
    }

    private function upsertDocumentRule(int $documentId, ?int $fragmentId, string $visibilityType, ?int $webRoleId, ?int $projectRoleId, ?int $classId, int $allowView, int $allowEdit, int $priority): void
    {
        $ruleFingerprint = sha1(implode('|', [
            (string) $documentId,
            (string) ($fragmentId ?? 0),
            $visibilityType,
            (string) ($webRoleId ?? 0),
            (string) ($projectRoleId ?? 0),
            (string) ($classId ?? 0),
            (string) $allowView,
            (string) $allowEdit,
            (string) $priority,
        ]));

        $existingId = $this->documentRuleIdByFingerprint($documentId, $ruleFingerprint);
        $params = [
            'document_id' => $documentId,
            'fragment_id' => $fragmentId,
            'rule_fingerprint' => $ruleFingerprint,
            'visibility_type' => $visibilityType,
            'role_id' => $webRoleId,
            'project_role_id' => $projectRoleId,
            'class_id' => $classId,
            'allow_view' => $allowView,
            'allow_edit' => $allowEdit,
            'priority' => $priority,
        ];

        if ($existingId !== null) {
            $stmt = $this->pdo()->prepare(
                'UPDATE document_visibility_rules
                 SET fragment_id = :fragment_id,
                      visibility_type = :visibility_type,
                      role_id = :role_id,
                      project_role_id = :project_role_id,
                      class_id = :class_id,
                      allow_view = :allow_view,
                      allow_edit = :allow_edit,
                     priority = :priority,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                'fragment_id' => $params['fragment_id'],
                'visibility_type' => $params['visibility_type'],
                'role_id' => $params['role_id'],
                'project_role_id' => $params['project_role_id'],
                'class_id' => $params['class_id'],
                'allow_view' => $params['allow_view'],
                'allow_edit' => $params['allow_edit'],
                'priority' => $params['priority'],
                'id' => $existingId,
            ]);
            return;
        }

        $stmt = $this->pdo()->prepare(
            'INSERT INTO document_visibility_rules
                (document_id, fragment_id, rule_fingerprint, visibility_type, role_id, project_role_id, class_id, allow_view, allow_edit, priority)
              VALUES
                (:document_id, :fragment_id, :rule_fingerprint, :visibility_type, :role_id, :project_role_id, :class_id, :allow_view, :allow_edit, :priority)'
        );
        $stmt->execute($params);
    }

    private function projectIdBySlug(string $slug): int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM projects WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $projectId = $stmt->fetchColumn();

        if ($projectId === false) {
            throw new RuntimeException('No existeix cap projecte amb slug ' . $slug . '.');
        }

        return (int) $projectId;
    }

    private function documentIdByProjectAcademicYearAndSlug(int $projectAcademicYearId, string $slug): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM documents WHERE project_academic_year_id = :project_academic_year_id AND slug = :slug LIMIT 1');
        $stmt->execute(['project_academic_year_id' => $projectAcademicYearId, 'slug' => $slug]);
        $documentId = $stmt->fetchColumn();

        return $documentId === false ? null : (int) $documentId;
    }

    private function projectAcademicYearIdByProjectId(int $projectId): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT pay.id
             FROM project_academic_years pay
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE pay.project_id = :project_id
             ORDER BY ay.id DESC
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $projectAcademicYearId = $stmt->fetchColumn();

        if ($projectAcademicYearId === false) {
            throw new RuntimeException('No hi ha cap edició acadèmica per al projecte amb id ' . $projectId . '.');
        }

        return (int) $projectAcademicYearId;
    }

    private function documentSourceIdByFingerprint(int $documentId, string $fingerprint): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM document_sources WHERE document_id = :document_id AND source_fingerprint = :source_fingerprint LIMIT 1');
        $stmt->execute(['document_id' => $documentId, 'source_fingerprint' => $fingerprint]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function documentFragmentIdByDocumentAndKey(int $documentId, string $fragmentKey): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM document_fragments WHERE document_id = :document_id AND fragment_key = :fragment_key LIMIT 1');
        $stmt->execute(['document_id' => $documentId, 'fragment_key' => $fragmentKey]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function documentRuleIdByFingerprint(int $documentId, string $fingerprint): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM document_visibility_rules WHERE document_id = :document_id AND rule_fingerprint = :rule_fingerprint LIMIT 1');
        $stmt->execute(['document_id' => $documentId, 'rule_fingerprint' => $fingerprint]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function resolveWebRoleIdFromVisibilityValue(string $value): ?int
    {
        $value = strtolower(trim($value));

        if (!in_array($value, ['student', 'teacher', 'admin'], true)) {
            return null;
        }

        $stmt = $this->pdo()->prepare('SELECT id FROM web_roles WHERE LOWER(name) = :name LIMIT 1');
        $stmt->execute(['name' => $value]);
        $roleId = $stmt->fetchColumn();

        return $roleId === false ? null : (int) $roleId;
    }

    private function resolveProjectRoleIdFromVisibilityValue(string $value): ?int
    {
        $value = strtolower(trim($value));

        if (str_starts_with($value, 'project_role:')) {
            $value = substr($value, strlen('project_role:'));
        } elseif (str_starts_with($value, 'project:')) {
            $value = substr($value, strlen('project:'));
        } elseif (str_starts_with($value, 'academic_role:')) {
            $value = substr($value, strlen('academic_role:'));
        } elseif (str_starts_with($value, 'academic:')) {
            $value = substr($value, strlen('academic:'));
        } else {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $stmt = $this->pdo()->prepare('SELECT id FROM project_roles WHERE LOWER(name) = :name LIMIT 1');
        $stmt->execute(['name' => $value]);
        $roleId = $stmt->fetchColumn();

        if ($roleId !== false) {
            return (int) $roleId;
        }

        $insertStmt = $this->pdo()->prepare('INSERT INTO project_roles (name, created_at) VALUES (:name, NOW())');
        $insertStmt->execute(['name' => $value]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function resolveClassId(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (stripos($value, 'class:') === 0) {
            $value = substr($value, 6);
        }

        $normalizedValue = $this->normalizeIdentifier($value);

        $stmt = $this->pdo()->query('SELECT id, class_name, class_code FROM classes ORDER BY class_name');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $classRow) {
            if ($this->normalizeIdentifier((string) $classRow['class_name']) === $normalizedValue || $this->normalizeIdentifier((string) $classRow['class_code']) === $normalizedValue) {
                return (int) $classRow['id'];
            }
        }

        return null;
    }

    private function normalizeDocumentVisibility(string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === 'public' || $value === '') {
            return 'public';
        }

        if (str_starts_with($value, 'project_role:') || str_starts_with($value, 'project:') || str_starts_with($value, 'academic_role:') || str_starts_with($value, 'academic:')) {
            return $value;
        }

        if (in_array($value, ['student', 'teacher', 'admin', 'assigned_teacher'], true)) {
            return $value;
        }

        return 'public';
    }

    private function visibilityTypeFromVisibilityValue(string $value): string
    {
        $value = strtolower(trim($value));

        if ($value === 'public' || $value === '') {
            return 'public';
        }

        if (str_starts_with($value, 'project_role:') || str_starts_with($value, 'project:') || str_starts_with($value, 'academic_role:') || str_starts_with($value, 'academic:')) {
            return 'project_role';
        }

        if (in_array($value, ['student', 'teacher', 'admin'], true)) {
            return 'role';
        }

        if (stripos($value, 'class:') === 0) {
            return 'class';
        }

        if ($value === 'assigned_teacher') {
            return 'assigned_teacher';
        }

        return 'public';
    }

    private function normalizeSyncMode(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['manual', 'automatic', 'disabled'], true) ? $value : 'manual';
    }

    private function normalizeContentFormat(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['markdown', 'html', 'text'], true) ? $value : 'markdown';
    }

    private function sourceFingerprint(array $row): string
    {
        return sha1(implode('|', [
            strtolower(trim((string) ($row['document_slug'] ?? ''))),
            strtolower(trim((string) ($row['source_type'] ?? 'markdown'))),
            strtolower(trim((string) ($row['source_url'] ?? ''))),
            strtolower(trim((string) ($row['external_id'] ?? ''))),
            strtolower(trim((string) ($row['sheet_name'] ?? ''))),
            strtolower(trim((string) ($row['range_name'] ?? ''))),
        ]));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function toUnsignedInt(mixed $value): int
    {
        return max(0, (int) $value);
    }

    private function toBooleanInt(mixed $value): int
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'si', 'sí', 'yes', 'actiu', 'visible'], true) ? 1 : 0;
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
    }

    private function isHeaderRow(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($row[$key])) {
                return false;
            }

            if (strtolower(trim((string) $row[$key])) !== strtolower($key)) {
                return false;
            }
        }

        return true;
    }

    private function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $this->connection = require dirname(__DIR__, 2) . '/config/database.php';

        return $this->connection;
    }
}
