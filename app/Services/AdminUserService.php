<?php

declare(strict_types=1);

class AdminUserService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createUser(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $surname = trim((string) ($input['surname'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $isActive = isset($input['is_active']) ? 1 : 0;
        $roles = $input['roles'] ?? [];
        $classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedClassId = $classId === null || $classId === false ? null : (int) $classId;
        $teacherClassIds = $this->inputClassIds($input['teacher_class_ids'] ?? []);

        if ($name === '' || $email === '' || $password === '') {
            return $this->message('Nom, email i contrasenya són obligatoris.', 'error');
        }

        $existing = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        if ($existing->fetch()) {
            return $this->message('Ja existeix un usuari amb aquest email.', 'error');
        }

        $this->pdo->beginTransaction();

        try {
            $roleIds = [];
            if (!empty($roles) && is_array($roles)) {
                $roleIds = array_map('intval', $roles);
            }

            $mustChangePassword = $this->roleIdsContainRoleName($roleIds, 'student') ? 1 : 0;

            $stmt = $this->pdo->prepare(
                'INSERT INTO users (name, surname, email, password_hash, must_change_password, is_active, created_at)
                 VALUES (:name, :surname, :email, :password_hash, :must_change_password, :is_active, NOW())'
            );
            $stmt->execute([
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'must_change_password' => $mustChangePassword,
                'is_active' => $isActive,
            ]);

            $userId = (int) $this->pdo->lastInsertId();
            if ($roleIds !== []) {
                $insertRoleStmt = $this->pdo->prepare('INSERT INTO user_web_roles (user_id, role_id) VALUES (:user_id, :role_id)');
                foreach ($roleIds as $roleId) {
                    $insertRoleStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
                }
            }

            $this->syncClassAssignment($userId, $resolvedClassId, 'manual');
            if ($this->roleIdsContainRoleName($roleIds, 'teacher')) {
                $this->syncTeacherClassAssignments($userId, $teacherClassIds);
            }

            $this->pdo->commit();

            return $this->message('Usuari creat correctament.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut crear l’usuari.', 'error');
        }
    }

    public function toggleUser(array $input): array
    {
        $userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($userId === null || $userId === false) {
            return $this->message('Usuari no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT is_active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return $this->message('No s’ha trobat l’usuari.', 'error');
        }

        if ((int) $user['is_active'] === 1 && $this->userHasRole((int) $userId, 'admin')) {
            return $this->message('No es pot desactivar un usuari administrador.', 'error');
        }

        $newState = ((int) $user['is_active'] === 1) ? 0 : 1;
        $updateStmt = $this->pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $updateStmt->execute(['is_active' => $newState, 'id' => $userId]);

        return $this->message('Estat d’usuari actualitzat.', 'success');
    }

    public function updateUser(array $input): array
    {
        $userId = filter_var($input['student_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($input['name'] ?? ''));
        $surname = trim((string) ($input['surname'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($userId === null || $userId === false || $name === '' || $email === '') {
            return $this->message('Nom i email són obligatoris.', 'error');
        }

        $isActive = isset($input['is_active']) ? 1 : 0;
        if ($this->userHasRole((int) $userId, 'admin')) {
            $isActive = 1;
        }

        $classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedClassId = $classId === null || $classId === false ? null : (int) $classId;
        $roles = $input['roles'] ?? [];
        $roleIds = is_array($roles) ? array_map('intval', $roles) : [];
        $teacherClassIds = $this->inputClassIds($input['teacher_class_ids'] ?? []);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE users
                 SET name = :name,
                     surname = :surname,
                     email = :email,
                     is_active = :is_active
                  WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'is_active' => $isActive,
                'id' => $userId,
            ]);

            $this->syncClassAssignment((int) $userId, $resolvedClassId, 'manual');
            if (is_array($roles)) {
                $this->syncUserRoles((int) $userId, $roleIds);
            }

            $this->syncTeacherClassAssignments(
                (int) $userId,
                $this->roleIdsContainRoleName($roleIds, 'teacher') ? $teacherClassIds : []
            );

            $this->pdo->commit();

            return $this->message('Informació d’usuari actualitzada.', 'success');
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->message('No s’ha pogut actualitzar l’usuari.', 'error');
        }
    }

    private function inputClassIds(mixed $inputClassIds): array
    {
        if (!is_array($inputClassIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $inputClassIds),
            static fn (int $classId): bool => $classId > 0
        )));
    }

    private function userHasRole(int $userId, string $roleName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM user_web_roles ur
             INNER JOIN web_roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND r.name = :role_name
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'role_name' => $roleName,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    private function syncClassAssignment(int $userId, ?int $classId, string $changeSource): void
    {
        try {
            $currentStmt = $this->pdo->prepare('SELECT class_id FROM class_members WHERE user_id = :user_id LIMIT 1');
            $currentStmt->execute(['user_id' => $userId]);
            $currentClassId = $currentStmt->fetchColumn();
            $currentClassId = $currentClassId !== false ? (int) $currentClassId : null;

            if ($currentClassId === $classId) {
                return;
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM class_members WHERE user_id = :user_id');
            $deleteStmt->execute(['user_id' => $userId]);

            if ($classId !== null) {
                $insertStmt = $this->pdo->prepare('INSERT INTO class_members (class_id, user_id) VALUES (:class_id, :user_id)');
                $insertStmt->execute([
                    'class_id' => $classId,
                    'user_id' => $userId,
                ]);
            }

            $historyStmt = $this->pdo->prepare(
                'INSERT INTO class_member_history (user_id, from_class_id, to_class_id, change_source)
                 VALUES (:user_id, :from_class_id, :to_class_id, :change_source)'
            );
            $historyStmt->execute([
                'user_id' => $userId,
                'from_class_id' => $currentClassId,
                'to_class_id' => $classId,
                'change_source' => $changeSource,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    private function syncTeacherClassAssignments(int $userId, array $classIds): void
    {
        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM class_teachers WHERE user_id = :user_id');
            $deleteStmt->execute(['user_id' => $userId]);

            if ($classIds === []) {
                return;
            }

            $insertStmt = $this->pdo->prepare(
                'INSERT IGNORE INTO class_teachers (class_id, user_id)
                 VALUES (:class_id, :user_id)'
            );

            foreach ($classIds as $classId) {
                $insertStmt->execute([
                    'class_id' => $classId,
                    'user_id' => $userId,
                ]);
            }
        } catch (Throwable) {
            return;
        }
    }

    private function syncUserRoles(int $userId, array $roleIds): void
    {
        $roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $roleId): bool => $roleId > 0)));
        $deleteStmt = $this->pdo->prepare('DELETE FROM user_web_roles WHERE user_id = :user_id');
        $deleteStmt->execute(['user_id' => $userId]);

        if ($roleIds === []) {
            return;
        }

        $insertStmt = $this->pdo->prepare('INSERT IGNORE INTO user_web_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        foreach ($roleIds as $roleId) {
            $insertStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
        }
    }

    private function roleIdsContainRoleName(array $roleIds, string $roleName): bool
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $roleId): bool => $roleId > 0)));
        if ($roleIds === []) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare("SELECT 1 FROM web_roles WHERE id IN ({$placeholders}) AND name = ? LIMIT 1");
        $stmt->execute([...$roleIds, $roleName]);

        return $stmt->fetchColumn() !== false;
    }

    private function message(string $message, string $type): array
    {
        return [
            'message' => $message,
            'type' => $type,
        ];
    }
}
