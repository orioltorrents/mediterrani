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
        $roleIds = is_array($roles) ? array_values(array_unique(array_map('intval', $roles))) : [];

        if ($name === '' || $email === '' || $password === '') {
            return $this->message('Nom, email i contrasenya són obligatoris.', 'error');
        }

        if (!is_array($roles) || count($this->validWebRoleIds($roleIds)) !== count($roleIds)) {
            return $this->message('Els rols seleccionats no són vàlids.', 'error');
        }

        if ($resolvedClassId !== null && !$this->classExists($resolvedClassId)) {
            return $this->message('La classe seleccionada no existeix.', 'error');
        }

        if (!$this->classesExist($teacherClassIds)) {
            return $this->message('Una o més classes del professor no existeixen.', 'error');
        }

        $existing = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        if ($existing->fetch()) {
            return $this->message('Ja existeix un usuari amb aquest email.', 'error');
        }

        $this->pdo->beginTransaction();

        try {
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

    public function generateStudentPasswordResetLink(array $input): array
    {
        $userId = filter_var($input['student_id'] ?? null, FILTER_VALIDATE_INT);
        if ($userId === null || $userId === false || $userId <= 0) {
            return $this->message('Alumne no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email
               FROM users u
              WHERE u.id = :id
                AND u.is_active = 1
                AND EXISTS (
                    SELECT 1
                      FROM user_web_roles uwr
                      INNER JOIN web_roles wr ON wr.id = uwr.role_id
                     WHERE uwr.user_id = u.id
                       AND wr.name = "student"
                )
              LIMIT 1'
        );
        $stmt->execute(['id' => (int) $userId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student === false) {
            return $this->message('No s’ha trobat un alumne actiu amb aquest identificador.', 'error');
        }

        $token = (new UserActivationService($this->pdo))->createToken((int) $student['id']);

        return [
            'message' => 'Enllaç de reset generat correctament.',
            'type' => 'success',
            'reset_link' => [
                'email' => (string) $student['email'],
                'url' => url('activar-compte') . '?token=' . rawurlencode($token),
            ],
        ];
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

        if (!$this->userExists((int) $userId)) {
            return $this->message('No s’ha trobat l’usuari.', 'error');
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
        $teamId = filter_var($input['team_id'] ?? null, FILTER_VALIDATE_INT);
        $resolvedTeamId = $teamId === null || $teamId === false || $teamId <= 0 ? null : (int) $teamId;

        if (is_array($roles) && count($this->validWebRoleIds($roleIds)) !== count(array_unique($roleIds))) {
            return $this->message('Els rols seleccionats no són vàlids.', 'error');
        }

        $actor = (new AuthService())->actorUser();
        $actorId = $actor !== null ? (int) ($actor['id'] ?? 0) : 0;
        if ($actorId === (int) $userId && is_array($roles) && !$this->roleIdsContainRoleName($roleIds, 'admin')) {
            return $this->message('No et pots treure el rol d’administrador a tu mateix.', 'error');
        }

        if ($resolvedClassId !== null && !$this->classExists($resolvedClassId)) {
            return $this->message('La classe seleccionada no existeix.', 'error');
        }

        if (!$this->classesExist($teacherClassIds)) {
            return $this->message('Una o més classes del professor no existeixen.', 'error');
        }

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

            if (array_key_exists('team_id', $input)) {
                $this->syncStudentTeamAssignment(
                    (int) $userId,
                    $this->roleIdsContainRoleName($roleIds, 'student') ? $resolvedTeamId : null,
                    $resolvedClassId
                );
            }

            $this->pdo->commit();

            return $this->message('Informació d’usuari actualitzada.', 'success');
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($throwable instanceof RuntimeException && $throwable->getMessage() !== '') {
                return $this->message($throwable->getMessage(), 'error');
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

    private function syncStudentTeamAssignment(int $userId, ?int $teamId, ?int $classId): void
    {
        if ($teamId === null) {
            $editionStmt = $this->pdo->prepare(
                'SELECT DISTINCT pt.project_academic_year_id
                   FROM project_team_members ptm
                   INNER JOIN project_teams pt ON pt.id = ptm.project_team_id
                  WHERE ptm.user_id = :user_id'
            );
            $editionStmt->execute(['user_id' => $userId]);
            $editionIds = array_map('intval', $editionStmt->fetchAll(PDO::FETCH_COLUMN));

            if (count($editionIds) > 1) {
                throw new RuntimeException('Cal indicar el projecte abans de treure una assignació d’equip.');
            }

            if ($editionIds === []) {
                return;
            }

            $deleteStmt = $this->pdo->prepare(
                'DELETE ptm
                   FROM project_team_members ptm
                   INNER JOIN project_teams pt ON pt.id = ptm.project_team_id
                  WHERE ptm.user_id = :user_id
                    AND pt.project_academic_year_id = :project_academic_year_id'
            );
            $deleteStmt->execute([
                'user_id' => $userId,
                'project_academic_year_id' => $editionIds[0],
            ]);

            return;
        }

        if ($classId === null) {
            throw new RuntimeException('Cal assignar una classe abans de seleccionar un equip.');
        }

        if (!$this->teamBelongsToClass($teamId, $classId)) {
            throw new RuntimeException('Aquest equip no pertany a la classe de l’alumne.');
        }

        $teamEditionStmt = $this->pdo->prepare('SELECT project_academic_year_id FROM project_teams WHERE id = :team_id LIMIT 1');
        $teamEditionStmt->execute(['team_id' => $teamId]);
        $projectAcademicYearId = $teamEditionStmt->fetchColumn();
        if ($projectAcademicYearId === false) {
            throw new RuntimeException('No s’ha trobat l’equip seleccionat.');
        }

        $deleteStmt = $this->pdo->prepare(
            'DELETE ptm
               FROM project_team_members ptm
               INNER JOIN project_teams pt ON pt.id = ptm.project_team_id
              WHERE ptm.user_id = :user_id
                AND pt.project_academic_year_id = :project_academic_year_id'
        );
        $deleteStmt->execute([
            'user_id' => $userId,
            'project_academic_year_id' => (int) $projectAcademicYearId,
        ]);

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO project_team_members (project_team_id, user_id, class_id, created_at)
             VALUES (:team_id, :user_id, :class_id, NOW())'
        );
        $insertStmt->execute([
            'team_id' => $teamId,
            'user_id' => $userId,
            'class_id' => $classId,
        ]);
    }

    private function teamBelongsToClass(int $teamId, int $classId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT pt.class_id, pt.class_group, c.class_code
               FROM project_teams pt
               LEFT JOIN classes c ON c.id = :class_id
              WHERE pt.id = :team_id
              LIMIT 1'
        );
        $stmt->execute([
            'class_id' => $classId,
            'team_id' => $teamId,
        ]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($team === false) {
            return false;
        }

        $teamClassId = !empty($team['class_id']) ? (int) $team['class_id'] : null;
        if ($teamClassId !== null) {
            return $teamClassId === $classId;
        }

        $teamClassGroup = trim((string) ($team['class_group'] ?? ''));
        $classCode = trim((string) ($team['class_code'] ?? ''));

        return $teamClassGroup !== '' && $classCode !== '' && $teamClassGroup === $classCode;
    }

    private function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    private function classExists(int $classId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM classes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $classId]);

        return $stmt->fetchColumn() !== false;
    }

    private function classesExist(array $classIds): bool
    {
        foreach ($classIds as $classId) {
            if (!$this->classExists((int) $classId)) {
                return false;
            }
        }

        return true;
    }

    private function validWebRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $roleId): bool => $roleId > 0)));
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM web_roles WHERE id IN ($placeholders)");
        $stmt->execute($roleIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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
