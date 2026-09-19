<?php

declare(strict_types=1);

class AdminEvidenceService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function summary(): array
    {
        $categories = $this->pdo->query(
            'SELECT ec.id, ec.nom, ec.descripcio, ec.color_code,
                    COUNT(DISTINCT et.id) AS type_count,
                    COUNT(DISTINCT ea.id) AS evidence_count
               FROM evidencies_categoria ec
          LEFT JOIN evidencies_tipus et ON et.evidencia_categoria_id = ec.id
          LEFT JOIN evidencies_alumnes ea ON ea.evidencia_categoria_id = ec.id
           GROUP BY ec.id, ec.nom, ec.descripcio, ec.color_code
           ORDER BY ec.id'
        )->fetchAll(PDO::FETCH_ASSOC);

        $types = $this->pdo->query(
            'SELECT et.id, et.evidencia_categoria_id, et.tipus_evidencia, et.titol, et.descripcio,
                    ec.nom AS category_name, ec.color_code,
                    COUNT(ea.id) AS evidence_count
               FROM evidencies_tipus et
               INNER JOIN evidencies_categoria ec ON ec.id = et.evidencia_categoria_id
          LEFT JOIN evidencies_alumnes ea ON ea.evidencia_tipus_id = et.id
           GROUP BY et.id, et.evidencia_categoria_id, et.tipus_evidencia, et.titol,
                    et.descripcio, ec.nom, ec.color_code
           ORDER BY ec.id, et.titol'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as &$category) {
            $category['type_count'] = (int) $category['type_count'];
            $category['evidence_count'] = (int) $category['evidence_count'];
        }
        unset($category);

        foreach ($types as &$type) {
            $type['evidence_count'] = (int) $type['evidence_count'];
        }
        unset($type);

        $studentBreakdowns = [
            'projects' => $this->breakdown(
                'SELECT CONCAT(p.name, \' · \', ay.name) AS label, COUNT(*) AS evidence_count
                   FROM evidencies_alumnes ea
                   INNER JOIN project_academic_years pay ON pay.id = ea.project_academic_year_id
                   INNER JOIN projects p ON p.id = pay.project_id
                   INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
               GROUP BY pay.id, p.name, ay.name
               ORDER BY p.name, ay.name'
            ),
            'types' => $this->breakdown(
                'SELECT COALESCE(et.titol, \'Sense tipus\') AS label, COUNT(*) AS evidence_count
                   FROM evidencies_alumnes ea
              LEFT JOIN evidencies_tipus et ON et.id = ea.evidencia_tipus_id
               GROUP BY ea.evidencia_tipus_id, et.titol
               ORDER BY et.titol'
            ),
            'categories' => $this->breakdown(
                'SELECT COALESCE(ec.nom, \'Sense categoria\') AS label, COUNT(*) AS evidence_count,
                        COALESCE(ec.color_code, \'#94a3b8\') AS color_code
                   FROM evidencies_alumnes ea
              LEFT JOIN evidencies_tipus et ON et.id = ea.evidencia_tipus_id
              LEFT JOIN evidencies_categoria ec
                     ON ec.id = COALESCE(ea.evidencia_categoria_id, et.evidencia_categoria_id)
               GROUP BY ec.id, ec.nom, ec.color_code
               ORDER BY ec.id'
            ),
            'classes' => $this->breakdown(
                'SELECT COALESCE(c.class_name, \'Sense classe\') AS label, COUNT(*) AS evidence_count
                   FROM evidencies_alumnes ea
              LEFT JOIN class_members cm ON cm.user_id = ea.user_id
              LEFT JOIN classes c ON c.id = cm.class_id
               GROUP BY c.id, c.class_name
               ORDER BY c.class_name'
            ),
        ];

        return [
            'count' => (int) $this->pdo->query('SELECT COUNT(*) FROM evidencies_alumnes')->fetchColumn(),
            'types_count' => count($types),
            'categories' => $categories,
            'types' => $types,
            'student_breakdowns' => $studentBreakdowns,
        ];
    }

    public function updateCategory(array $post): array
    {
        $categoryId = (int) ($post['category_id'] ?? 0);
        $name = trim((string) ($post['name'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        $colorCode = trim((string) ($post['color_code'] ?? ''));

        if ($categoryId <= 0 || $name === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $colorCode)) {
            return $this->result('Revisa el nom i el color de la categoria.', 'error');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE evidencies_categoria
                SET nom = :name, descripcio = :description, color_code = :color_code
              WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'color_code' => strtolower($colorCode),
            'id' => $categoryId,
        ]);

        return $this->result('Categoria d’evidència actualitzada.', 'success');
    }

    public function createStudentEvidence(array $post): array
    {
        $userId = (int) ($post['user_id'] ?? 0);
        $projectAcademicYearId = (int) ($post['project_academic_year_id'] ?? 0);
        $objectiveId = (int) ($post['objective_id'] ?? 0);
        $typeId = (int) ($post['type_id'] ?? 0);
        $observation = trim((string) ($post['observation'] ?? ''));

        if ($userId <= 0 || $projectAcademicYearId <= 0 || $objectiveId <= 0 || $typeId <= 0 || $observation === '') {
            return $this->result('Completa tots els camps de l’evidència.', 'error');
        }

        $studentStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM users u
               INNER JOIN user_web_roles uwr ON uwr.user_id = u.id
               INNER JOIN web_roles wr ON wr.id = uwr.role_id
              WHERE u.id = :user_id AND u.is_active = 1 AND wr.name = \'student\''
        );
        $studentStmt->execute(['user_id' => $userId]);
        if ((int) $studentStmt->fetchColumn() === 0) {
            return $this->result('L’alumne seleccionat no existeix o no està actiu.', 'error');
        }

        $objectiveStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM project_academic_year_objectius
              WHERE project_academic_year_id = :project_year_id
                AND objectiu_id = :objective_id'
        );
        $objectiveStmt->execute([
            'project_year_id' => $projectAcademicYearId,
            'objective_id' => $objectiveId,
        ]);
        if ((int) $objectiveStmt->fetchColumn() === 0) {
            return $this->result('L’objectiu no està assignat a l’edició seleccionada.', 'error');
        }

        $typeStmt = $this->pdo->prepare(
            'SELECT evidencia_categoria_id FROM evidencies_tipus WHERE id = :id LIMIT 1'
        );
        $typeStmt->execute(['id' => $typeId]);
        $categoryId = $typeStmt->fetchColumn();
        if ($categoryId === false) {
            return $this->result('El tipus d’evidència seleccionat no existeix.', 'error');
        }

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO evidencies_alumnes
                (user_id, project_academic_year_id, objectiu_id, evidencia_categoria_id, evidencia_tipus_id, observacio)
             VALUES
                (:user_id, :project_year_id, :objective_id, :category_id, :type_id, :observation)'
        );
        $insertStmt->execute([
            'user_id' => $userId,
            'project_year_id' => $projectAcademicYearId,
            'objective_id' => $objectiveId,
            'category_id' => (int) $categoryId,
            'type_id' => $typeId,
            'observation' => $observation,
        ]);

        return $this->result('Evidència d’alumne registrada correctament.', 'success');
    }

    public function updateType(array $post): array
    {
        $typeId = (int) ($post['type_id'] ?? 0);
        $categoryId = (int) ($post['category_id'] ?? 0);
        $typeKey = trim((string) ($post['type_key'] ?? ''));
        $title = trim((string) ($post['title'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));

        if ($typeId <= 0 || $categoryId <= 0 || $title === '' || !preg_match('/^[a-z0-9_]+$/', $typeKey)) {
            return $this->result('Revisa la categoria, la clau i el títol del tipus d’evidència.', 'error');
        }

        $categoryStmt = $this->pdo->prepare('SELECT COUNT(*) FROM evidencies_categoria WHERE id = :id');
        $categoryStmt->execute(['id' => $categoryId]);
        if ((int) $categoryStmt->fetchColumn() === 0) {
            return $this->result('La categoria seleccionada no existeix.', 'error');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE evidencies_tipus
                SET evidencia_categoria_id = :category_id,
                    tipus_evidencia = :type_key,
                    titol = :title,
                    descripcio = :description
              WHERE id = :id'
        );
        $stmt->execute([
            'category_id' => $categoryId,
            'type_key' => $typeKey,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'id' => $typeId,
        ]);

        return $this->result('Tipus d’evidència actualitzat.', 'success');
    }

    private function result(string $message, string $type): array
    {
        return ['message' => $message, 'type' => $type];
    }

    private function breakdown(string $sql): array
    {
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['evidence_count'] = (int) $row['evidence_count'];
        }
        unset($row);

        return $rows;
    }
}
