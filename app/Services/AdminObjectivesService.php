<?php

declare(strict_types=1);

class AdminObjectivesService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function objectives(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT id, project_id, codi, titol, creat_el, updated_at FROM objectius_aprenentatge ORDER BY codi ASC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public function projectYearObjectivesMap(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT project_academic_year_id, objectiu_id FROM project_academic_year_objectius');
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['project_academic_year_id']][] = (int) $row['objectiu_id'];
            }
            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    public function createObjective(array $input): array
    {
        $codi = trim((string) ($input['codi'] ?? ''));
        $titol = trim((string) ($input['titol'] ?? ''));
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedProjectId = $projectId === null || $projectId === false || $projectId <= 0 ? null : (int) $projectId;

        if ($codi === '' || $titol === '') {
            return $this->message('El codi i el títol de l’objectiu són obligatoris.', 'error');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO objectius_aprenentatge (project_id, codi, titol, creat_el, updated_at)
                 VALUES (:project_id, :codi, :titol, NOW(), NOW())'
            );
            $stmt->execute([
                'project_id' => $resolvedProjectId,
                'codi' => $codi,
                'titol' => $titol,
            ]);

            return $this->message('Objectiu creat correctament.', 'success');
        } catch (Throwable) {
            return $this->message('No s’ha pogut crear l’objectiu.', 'error');
        }
    }

    public function updateObjective(array $input): array
    {
        $objectiveId = filter_var($input['objective_id'] ?? null, FILTER_VALIDATE_INT);
        $codi = trim((string) ($input['codi'] ?? ''));
        $titol = trim((string) ($input['titol'] ?? ''));
        $projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedProjectId = $projectId === null || $projectId === false || $projectId <= 0 ? null : (int) $projectId;

        if ($objectiveId === null || $objectiveId === false || $codi === '' || $titol === '') {
            return $this->message('Dades d’objectiu no vàlides.', 'error');
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE objectius_aprenentatge
                 SET project_id = :project_id,
                     codi = :codi,
                     titol = :titol,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'project_id' => $resolvedProjectId,
                'codi' => $codi,
                'titol' => $titol,
                'id' => (int) $objectiveId,
            ]);

            return $this->message('Objectiu actualitzat correctament.', 'success');
        } catch (Throwable) {
            return $this->message('No s’ha pogut actualitzar l’objectiu.', 'error');
        }
    }

    public function syncProjectYearObjectives(array $input): array
    {
        $projectAcademicYearId = filter_var($input['project_academic_year_id'] ?? null, FILTER_VALIDATE_INT);
        $objectiveIdsInput = $input['objective_ids'] ?? [];

        if ($projectAcademicYearId === null || $projectAcademicYearId === false) {
            return $this->message('Edició acadèmica de projecte no vàlida.', 'error');
        }

        if (!is_array($objectiveIdsInput)) {
            $objectiveIdsInput = [];
        }

        $objectiveIds = array_values(array_unique(array_filter(
            array_map('intval', $objectiveIdsInput),
            static fn (int $id): bool => $id > 0
        )));

        $editionStmt = $this->pdo->prepare('SELECT project_id FROM project_academic_years WHERE id = :id LIMIT 1');
        $editionStmt->execute(['id' => (int) $projectAcademicYearId]);
        $editionProjectId = $editionStmt->fetchColumn();
        if ($editionProjectId === false) {
            return $this->message('Edició acadèmica no trobada.', 'error');
        }
        $editionProjectId = (int) $editionProjectId;

        if ($objectiveIds !== []) {
            $placeholders = implode(',', array_fill(0, count($objectiveIds), '?'));
            $valStmt = $this->pdo->prepare("SELECT id FROM objectius_aprenentatge WHERE id IN ({$placeholders}) AND (project_id IS NULL OR project_id = ?)");
            $valStmt->execute([...$objectiveIds, $editionProjectId]);
            $validObjIds = array_map('intval', $valStmt->fetchAll(PDO::FETCH_COLUMN));
            $objectiveIds = array_values(array_intersect($objectiveIds, $validObjIds));
        }

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM project_academic_year_objectius WHERE project_academic_year_id = :edition_id');
            $deleteStmt->execute(['edition_id' => (int) $projectAcademicYearId]);

            if ($objectiveIds !== []) {
                $insertStmt = $this->pdo->prepare(
                    'INSERT INTO project_academic_year_objectius (project_academic_year_id, objectiu_id, display_order)
                     VALUES (:edition_id, :objectiu_id, :display_order)'
                );
                foreach ($objectiveIds as $index => $objId) {
                    $insertStmt->execute([
                        'edition_id' => (int) $projectAcademicYearId,
                        'objectiu_id' => $objId,
                        'display_order' => $index + 1,
                    ]);
                }
            }

            $this->pdo->commit();

            return $this->message('Objectius de l’edició actualitzats correctament.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’han pogut actualitzar els objectius de l’edició.', 'error');
        }
    }

    public function indicators(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT id, objectiu_id, color_semafor, descriptor FROM indicadors_assoliment');
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['objectiu_id']][(string) $row['color_semafor']] = (string) $row['descriptor'];
            }
            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    public function updateIndicators(array $input): array
    {
        $objectiveId = filter_var($input['objective_id'] ?? null, FILTER_VALIDATE_INT);
        $descriptors = $input['descriptors'] ?? [];

        if ($objectiveId === null || $objectiveId === false || !is_array($descriptors)) {
            return $this->message('Dades d’indicadors no vàlides.', 'error');
        }

        $this->pdo->beginTransaction();

        try {
            $upsertStmt = $this->pdo->prepare(
                'INSERT INTO indicadors_assoliment (objectiu_id, color_semafor, descriptor, created_at, updated_at)
                 VALUES (:objectiu_id, :color_semafor, :descriptor, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE descriptor = VALUES(descriptor), updated_at = NOW()'
            );

            foreach (['vermell', 'groc', 'verd_clar', 'verd_fosc'] as $color) {
                $desc = trim((string) ($descriptors[$color] ?? ''));
                if ($desc !== '') {
                    $upsertStmt->execute([
                        'objectiu_id' => (int) $objectiveId,
                        'color_semafor' => $color,
                        'descriptor' => $desc,
                    ]);
                }
            }

            $this->pdo->commit();

            return $this->message('Indicadors d’assoliment actualitzats correctament.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’han pogut actualitzar els indicadors.', 'error');
        }
    }
}
