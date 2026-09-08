<?php

declare(strict_types=1);

class AdminClassService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function syncClassTeachers(array $input): array
    {
        $classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
        $teacherIds = $input['teacher_ids'] ?? [];

        if ($classId === null || $classId === false) {
            return $this->message('Classe no vàlida.', 'error');
        }

        if (!is_array($teacherIds)) {
            $teacherIds = [];
        }

        $teacherIds = $this->normalizeTeacherIds($teacherIds);

        $classStmt = $this->pdo->prepare('SELECT id, class_name AS name FROM classes WHERE id = :id LIMIT 1');
        $classStmt->execute(['id' => (int) $classId]);
        $class = $classStmt->fetch(PDO::FETCH_ASSOC);

        if ($class === false) {
            return $this->message('No s’ha trobat la classe.', 'error');
        }

        $validTeacherIds = $this->validTeacherIds($teacherIds);
        $this->pdo->beginTransaction();

        try {
            $this->replaceClassTeachers((int) $classId, $validTeacherIds);
            $this->pdo->commit();
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut actualitzar el professorat de la classe.', 'error');
        }

        return $this->message('Professorat actualitzat per a ' . (string) $class['name'] . '.', 'success');
    }

    public function syncAllClassTeachers(array $input): array
    {
        $assignments = $input['teacher_ids_by_class'] ?? [];
        if (!is_array($assignments)) {
            $assignments = [];
        }

        $classRows = $this->pdo->query('SELECT id FROM classes')->fetchAll(PDO::FETCH_ASSOC);
        $classIds = array_map(static fn (array $row): int => (int) $row['id'], $classRows);
        $validTeacherIds = array_flip($this->allTeacherIds());

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM class_teachers WHERE class_id = :class_id');
            $insertStmt = $this->pdo->prepare('INSERT INTO class_teachers (class_id, user_id) VALUES (:class_id, :user_id)');
            $savedClasses = 0;
            $savedAssignments = 0;

            foreach ($classIds as $classId) {
                $rawTeacherIds = $assignments[(string) $classId] ?? $assignments[$classId] ?? [];
                if (!is_array($rawTeacherIds)) {
                    $rawTeacherIds = [];
                }

                $teacherIds = array_values(array_filter(
                    $this->normalizeTeacherIds($rawTeacherIds),
                    static fn (int $teacherId): bool => isset($validTeacherIds[$teacherId])
                ));

                $deleteStmt->execute(['class_id' => $classId]);
                foreach ($teacherIds as $teacherId) {
                    $insertStmt->execute([
                        'class_id' => $classId,
                        'user_id' => $teacherId,
                    ]);
                    $savedAssignments++;
                }

                $savedClasses++;
            }

            $this->pdo->commit();

            return $this->message('Professorat actualitzat per a ' . $savedClasses . ' classes amb ' . $savedAssignments . ' assignacions.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut actualitzar el professorat de totes les classes.', 'error');
        }
    }

    private function replaceClassTeachers(int $classId, array $teacherIds): void
    {
        $deleteStmt = $this->pdo->prepare('DELETE FROM class_teachers WHERE class_id = :class_id');
        $deleteStmt->execute(['class_id' => $classId]);

        if ($teacherIds === []) {
            return;
        }

        $insertStmt = $this->pdo->prepare('INSERT INTO class_teachers (class_id, user_id) VALUES (:class_id, :user_id)');
        foreach ($teacherIds as $teacherId) {
            $insertStmt->execute([
                'class_id' => $classId,
                'user_id' => $teacherId,
            ]);
        }
    }

    private function validTeacherIds(array $teacherIds): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($teacherIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_web_roles ur ON ur.user_id = u.id
             INNER JOIN web_roles r ON r.id = ur.role_id
             WHERE r.name = 'teacher'
               AND u.id IN ($placeholders)"
        );
        $stmt->execute($teacherIds);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function allTeacherIds(): array
    {
        $rows = $this->pdo->query(
            "SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_web_roles ur ON ur.user_id = u.id
             INNER JOIN web_roles r ON r.id = ur.role_id
             WHERE r.name = 'teacher'"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function normalizeTeacherIds(array $teacherIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($teacherId): int => (int) $teacherId,
            $teacherIds
        ), static fn (int $teacherId): bool => $teacherId > 0)));
    }

    private function message(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
