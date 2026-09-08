<?php

declare(strict_types=1);

class AdminStudentImportService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function importUploadedFile(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return $this->result('No s’ha rebut cap fitxer CSV.', 'error');
        }

        $handle = fopen((string) $file['tmp_name'], 'rb');
        if ($handle === false) {
            return $this->result('No s’ha pogut llegir el fitxer CSV.', 'error');
        }

        $headers = fgetcsv($handle);
        if ($headers === false || $headers === [null]) {
            fclose($handle);
            return $this->result('El fitxer CSV està buit.', 'error');
        }

        $normalizedHeaders = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);
        $created = 0;
        $updated = 0;
        $teamAssignments = 0;
        $generatedPasswords = [];
        $errors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($row === [null] || $row === false) {
                continue;
            }

            $data = [];
            foreach ($normalizedHeaders as $index => $header) {
                $data[$header] = isset($row[$index]) ? (string) $row[$index] : '';
            }

            $name = trim($data['name'] ?? $data['nom'] ?? '');
            $surname = trim($data['surname'] ?? $data['cognoms'] ?? '');
            $email = strtolower(trim($data['email'] ?? ''));
            $password = trim($data['password'] ?? '');
            $classIdValue = trim($data['class_id'] ?? '');
            $classCode = trim($data['class_code'] ?? $data['classid'] ?? '');
            $className = trim($data['class'] ?? $data['classe'] ?? $data['class_name'] ?? $data['grup_classe'] ?? $data['grup_classes'] ?? '');
            $rolesInput = trim($data['roles'] ?? $data['role'] ?? 'student');
            $isActive = $this->parseImportBoolean($data['is_active'] ?? $data['active'] ?? $data['status'] ?? '');
            $projectAcademicYearIdValue = trim($data['project_academic_year_id'] ?? $data['project_year_id'] ?? '');
            $projectIdValue = trim($data['project_id'] ?? '');
            $projectSlug = trim($data['project_slug'] ?? $data['project'] ?? '');
            $academicYearName = trim($data['academic_year'] ?? $data['academic_year_name'] ?? '');
            $teamCode = trim($data['team_code'] ?? $data['team'] ?? '');
            $teamName = trim($data['team_name'] ?? '');
            $teamClassGroup = trim($data['class_group'] ?? '');
            $projectRoleName = trim($data['project_role'] ?? $data['project_role_name'] ?? '');
            if ($name === '' || $email === '') {
                $errors[] = 'Fila ' . $lineNumber . ': falta nom o email.';
                continue;
            }

            $existingUserStmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existingUserStmt->execute(['email' => $email]);
            $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);
            $generatedPassword = null;

            if ($existingUser === false && $password === '') {
                $generatedPassword = $this->generateTemporaryPassword();
                $password = $generatedPassword;
            }

            $this->pdo->beginTransaction();

            try {
                $userId = $this->findOrCreateUser($name, $surname, $email, $password);
                if ($isActive !== null) {
                    $this->updateUserActiveState($userId, $isActive);
                }

                $this->setRolesForUser($userId, $rolesInput);

                $classId = $this->resolveImportClassId($classIdValue, $classCode, $className, $academicYearName);
                if ($classId !== null) {
                    $this->syncClassAssignment($userId, $classId, 'import');
                }

                $projectAcademicYearId = $this->resolveProjectAcademicYearId($projectAcademicYearIdValue, $projectIdValue, $projectSlug, $academicYearName);
                if ($teamCode !== '') {
                    if ($projectAcademicYearId === null) {
                        throw new RuntimeException('Cal `project_academic_year_id` o bé `project_slug` + `academic_year` per importar `team_code`.');
                    }

                    $projectRoleIds = $this->resolveProjectRoleIds($projectRoleName);
                    if ($projectRoleName !== '' && $projectRoleIds === []) {
                        throw new RuntimeException('No s’ha pogut resoldre el rol de projecte.');
                    }

                    $this->syncProjectTeamMembership(
                        $userId,
                        (int) $projectAcademicYearId,
                        $classId,
                        $teamCode,
                        $teamName,
                        $projectRoleIds,
                        $teamClassGroup !== '' ? $teamClassGroup : ($className !== '' ? $className : $classCode)
                    );
                    $teamAssignments++;
                }

                $this->pdo->commit();

                if ($existingUser === false) {
                    $created++;
                    if ($generatedPassword !== null) {
                        $generatedPasswords[] = [
                            'email' => $email,
                            'password' => $generatedPassword,
                        ];
                    }
                } else {
                    $updated++;
                }
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $errors[] = 'Fila ' . $lineNumber . ': ' . $exception->getMessage();
            }
        }

        fclose($handle);

        if ($errors !== []) {
            return $this->result('Importació finalitzada amb errors: ' . implode(' | ', $errors), 'error');
        }

        return $this->result(
            'Importació completada: ' . $created . ' usuaris creats, ' . $updated . ' actualitzats, ' . $teamAssignments . ' equips sincronitzats i ' . count($generatedPasswords) . ' contrasenyes temporals generades.',
            'success',
            [
                'created' => $created,
                'updated' => $updated,
                'team_assignments' => $teamAssignments,
                'generated_passwords' => $generatedPasswords,
            ]
        );
    }

    private function findOrCreateUser(string $name, string $surname, string $email, string $password): int
    {
        $existingStmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existingStmt->execute(['email' => $email]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $userId = (int) $existing['id'];
            $updateStmt = $this->pdo->prepare('UPDATE users SET name = :name, surname = :surname, is_active = 1 WHERE id = :id');
            $updateStmt->execute(['name' => $name, 'surname' => $surname, 'id' => $userId]);
            return $userId;
        }

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO users (name, surname, email, password_hash, must_change_password, is_active, created_at)
             VALUES (:name, :surname, :email, :password_hash, 1, 1, NOW())'
        );
        $insertStmt->execute([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function generateTemporaryPassword(): string
    {
        return 'MED-' . bin2hex(random_bytes(8));
    }

    private function updateUserActiveState(int $userId, int $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $stmt->execute(['is_active' => $isActive, 'id' => $userId]);
    }

    private function setRolesForUser(int $userId, string $rolesInput): void
    {
        $deleteStmt = $this->pdo->prepare('DELETE FROM user_web_roles WHERE user_id = :user_id');
        $deleteStmt->execute(['user_id' => $userId]);

        $roles = array_filter(array_map('trim', preg_split('/[;,|]/', $rolesInput) ?: []));
        if ($roles === []) {
            $roles = ['student'];
        }

        $insertStmt = $this->pdo->prepare('INSERT INTO user_web_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        foreach ($roles as $roleName) {
            $roleId = $this->findOrCreateRole($roleName);
            if ($roleId !== null) {
                $insertStmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
            }
        }
    }

    private function syncClassAssignment(int $userId, ?int $classId, string $changeSource = 'import'): void
    {
        $currentStmt = $this->pdo->prepare(
            'SELECT cm.class_id, c.academic_year_id
             FROM class_members cm
             INNER JOIN classes c ON c.id = cm.class_id
             WHERE cm.user_id = :user_id
             LIMIT 1'
        );
        $currentStmt->execute(['user_id' => $userId]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        $currentClassId = $current !== false ? (int) $current['class_id'] : null;

        if ($currentClassId === $classId) {
            return;
        }

        $referenceClassId = $classId ?? $currentClassId;
        if ($referenceClassId !== null) {
            $academicYearStmt = $this->pdo->prepare('SELECT academic_year_id FROM classes WHERE id = :id LIMIT 1');
            $academicYearStmt->execute(['id' => $referenceClassId]);
            $academicYearId = $academicYearStmt->fetchColumn();

            if ($academicYearId !== false) {
                try {
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
                    // History is optional in the minimal Mediterrani setup.
                }
            }
        }

        $deleteStmt = $this->pdo->prepare('DELETE FROM class_members WHERE user_id = :user_id');
        $deleteStmt->execute(['user_id' => $userId]);

        if ($classId !== null && $classId > 0) {
            $insertStmt = $this->pdo->prepare('INSERT INTO class_members (class_id, user_id) VALUES (:class_id, :user_id)');
            $insertStmt->execute(['class_id' => $classId, 'user_id' => $userId]);
        }
    }

    private function resolveImportClassId(string $classIdValue, string $classCode, string $className, string $academicYearName): ?int
    {
        if ($classIdValue !== '' && filter_var($classIdValue, FILTER_VALIDATE_INT) !== false) {
            $stmt = $this->pdo->prepare('SELECT id FROM classes WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $classIdValue]);
            $classId = $stmt->fetchColumn();
            if ($classId !== false) {
                return (int) $classId;
            }
        }

        $normalizedCode = trim($classCode);
        $normalizedName = trim($className);
        $normalizedYearNames = array_values(array_filter(array_unique([trim($academicYearName), $this->normalizeAcademicYearLabel($academicYearName)])));

        if ($normalizedCode !== '' && $normalizedYearNames !== []) {
            $yearPlaceholders = [];
            $yearParams = [];
            foreach ($normalizedYearNames as $index => $yearName) {
                $placeholder = ':year_' . $index;
                $yearPlaceholders[] = $placeholder;
                $yearParams['year_' . $index] = $yearName;
            }

            $stmt = $this->pdo->prepare(
                'SELECT c.id
                 FROM classes c
                 INNER JOIN academic_years ay ON ay.id = c.academic_year_id
                 WHERE ay.name IN (' . implode(',', $yearPlaceholders) . ')
                   AND (c.class_name = :class_name OR c.class_code = :class_code OR c.class_code = :built_code)
                 ORDER BY c.id ASC
                 LIMIT 1'
            );
            $stmt->execute($yearParams + [
                'class_name' => $normalizedCode,
                'class_code' => $normalizedCode,
                'built_code' => $this->buildClassCode($normalizedYearNames[0], $normalizedCode),
            ]);
            $classId = $stmt->fetchColumn();
            if ($classId !== false) {
                return (int) $classId;
            }
        }

        if ($normalizedCode !== '') {
            $stmt = $this->pdo->prepare('SELECT id FROM classes WHERE class_code = :class_code LIMIT 1');
            $stmt->execute(['class_code' => $normalizedCode]);
            $classId = $stmt->fetchColumn();
            if ($classId !== false) {
                return (int) $classId;
            }
        }

        if ($normalizedName !== '') {
            $stmt = $this->pdo->prepare('SELECT id FROM classes WHERE class_name = :class_name LIMIT 1');
            $stmt->execute(['class_name' => $normalizedName]);
            $classId = $stmt->fetchColumn();
            if ($classId !== false) {
                return (int) $classId;
            }
        }

        if ($normalizedCode === '' && $normalizedName === '') {
            return null;
        }

        $yearStmt = $this->pdo->query('SELECT id, name FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1');
        $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
        $academicYearId = $year !== false ? (int) $year['id'] : 1;
        $academicYearName = $year !== false ? (string) $year['name'] : '';
        $classNameToInsert = $normalizedName !== '' ? $normalizedName : $normalizedCode;
        $classCodeToInsert = $normalizedCode !== '' ? $normalizedCode : $this->buildClassCode($academicYearName, $classNameToInsert);

        $insertStmt = $this->pdo->prepare('INSERT INTO classes (academic_year_id, class_name, class_code) VALUES (:academic_year_id, :class_name, :class_code)');
        $insertStmt->execute(['academic_year_id' => $academicYearId, 'class_name' => $classNameToInsert, 'class_code' => $classCodeToInsert]);

        return (int) $this->pdo->lastInsertId();
    }

    private function resolveProjectAcademicYearId(string $projectAcademicYearIdValue, string $projectIdValue, string $projectSlug, string $academicYearName): ?int
    {
        if ($projectAcademicYearIdValue !== '' && filter_var($projectAcademicYearIdValue, FILTER_VALIDATE_INT) !== false) {
            $stmt = $this->pdo->prepare('SELECT id FROM project_academic_years WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $projectAcademicYearIdValue]);
            $projectAcademicYearId = $stmt->fetchColumn();
            if ($projectAcademicYearId !== false) {
                return (int) $projectAcademicYearId;
            }
        }

        $normalizedYearNames = array_values(array_filter(array_unique([trim($academicYearName), $this->normalizeAcademicYearLabel($academicYearName)])));
        if ($projectSlug === '' || $normalizedYearNames === []) {
            return null;
        }

        $yearPlaceholders = [];
        $yearParams = [];
        foreach ($normalizedYearNames as $index => $yearName) {
            $placeholder = ':year_' . $index;
            $yearPlaceholders[] = $placeholder;
            $yearParams['year_' . $index] = $yearName;
        }

        $projectStmt = $this->pdo->prepare(
            'SELECT pay.id
             FROM project_academic_years pay
             INNER JOIN projects p ON p.id = pay.project_id
             INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
             WHERE p.slug = :project_slug
               AND ay.name IN (' . implode(',', $yearPlaceholders) . ')
             LIMIT 1'
        );
        $projectStmt->execute($yearParams + ['project_slug' => $projectSlug]);
        $projectAcademicYearId = $projectStmt->fetchColumn();
        if ($projectAcademicYearId !== false) {
            return (int) $projectAcademicYearId;
        }

        if ($projectIdValue !== '' && filter_var($projectIdValue, FILTER_VALIDATE_INT) !== false) {
            $projectStmt = $this->pdo->prepare(
                'SELECT pay.id
                 FROM project_academic_years pay
                 INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                 WHERE pay.project_id = :project_id
                   AND ay.name IN (' . implode(',', $yearPlaceholders) . ')
                 LIMIT 1'
            );
            $projectStmt->execute($yearParams + ['project_id' => (int) $projectIdValue]);
            $projectAcademicYearId = $projectStmt->fetchColumn();
            if ($projectAcademicYearId !== false) {
                return (int) $projectAcademicYearId;
            }
        }

        return null;
    }

    private function syncProjectTeamMembership(int $userId, int $projectAcademicYearId, ?int $classId, string $teamCode, ?string $teamName, array $projectRoleIds, ?string $classGroup): int
    {
        if ($classId !== null) {
            $classYearStmt = $this->pdo->prepare(
                'SELECT 1
                 FROM classes c
                 INNER JOIN project_academic_years pay ON pay.academic_year_id = c.academic_year_id
                 WHERE c.id = :class_id
                   AND pay.id = :project_academic_year_id
                 LIMIT 1'
            );
            $classYearStmt->execute(['class_id' => $classId, 'project_academic_year_id' => $projectAcademicYearId]);
            if ($classYearStmt->fetchColumn() === false) {
                throw new RuntimeException('La classe de l’alumne no correspon a l’any acadèmic del projecte.');
            }

            $projectClassAssignmentStmt = $this->pdo->prepare(
                'SELECT p.name AS project_name, ay.name AS academic_year_name, c.class_code
                 FROM project_academic_years pay
                 INNER JOIN projects p ON p.id = pay.project_id
                 INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                 INNER JOIN classes c ON c.id = :class_id
                 INNER JOIN project_class_assignments pca ON pca.project_academic_year_id = pay.id AND pca.class_id = c.id
                 WHERE pay.id = :project_academic_year_id
                 LIMIT 1'
            );
            $projectClassAssignmentStmt->execute(['class_id' => $classId, 'project_academic_year_id' => $projectAcademicYearId]);
            if ($projectClassAssignmentStmt->fetch(PDO::FETCH_ASSOC) === false) {
                $contextStmt = $this->pdo->prepare(
                    'SELECT p.name AS project_name, ay.name AS academic_year_name, c.class_code
                     FROM project_academic_years pay
                     INNER JOIN projects p ON p.id = pay.project_id
                     INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
                     INNER JOIN classes c ON c.id = :class_id
                     WHERE pay.id = :project_academic_year_id
                     LIMIT 1'
                );
                $contextStmt->execute(['class_id' => $classId, 'project_academic_year_id' => $projectAcademicYearId]);
                $context = $contextStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                throw new RuntimeException(
                    'El projecte ' . (string) ($context['project_name'] ?? '')
                    . ' (' . (string) ($context['academic_year_name'] ?? '') . ') no està assignat a la classe '
                    . (string) ($context['class_code'] ?? '') . '.'
                );
            }
        }

        $teamId = $this->findOrCreateProjectTeam($projectAcademicYearId, $teamCode, $teamName, $classGroup);
        $deleteStmt = $this->pdo->prepare(
            'DELETE ptm
             FROM project_team_members ptm
             INNER JOIN project_teams pt ON pt.id = ptm.project_team_id
             WHERE pt.project_academic_year_id = :project_academic_year_id
               AND ptm.user_id = :user_id'
        );
        $deleteStmt->execute(['project_academic_year_id' => $projectAcademicYearId, 'user_id' => $userId]);

        $primaryProjectRoleId = $projectRoleIds[0] ?? null;
        $insertStmt = $this->pdo->prepare('INSERT INTO project_team_members (project_team_id, user_id, class_id, project_role_id) VALUES (:project_team_id, :user_id, :class_id, :project_role_id)');
        $insertStmt->execute(['project_team_id' => $teamId, 'user_id' => $userId, 'class_id' => $classId, 'project_role_id' => $primaryProjectRoleId]);
        $projectTeamMemberId = (int) $this->pdo->lastInsertId();
        $this->syncProjectTeamMemberRoles($projectTeamMemberId, $projectRoleIds);

        return $teamId;
    }

    private function findOrCreateProjectTeam(int $projectAcademicYearId, string $teamCode, ?string $teamName, ?string $classGroup): int
    {
        $normalizedTeamCode = trim($teamCode);
        if ($normalizedTeamCode === '') {
            throw new RuntimeException('El codi d’equip és obligatori.');
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM project_teams WHERE project_academic_year_id = :project_academic_year_id AND team_code = :team_code LIMIT 1');
        $existingStmt->execute(['project_academic_year_id' => $projectAcademicYearId, 'team_code' => $normalizedTeamCode]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $normalizedTeamName = trim((string) $teamName);
        $normalizedClassGroup = trim((string) $classGroup);

        if ($existing !== false) {
            $updateStmt = $this->pdo->prepare(
                'UPDATE project_teams
                 SET team_name = COALESCE(NULLIF(:team_name, ""), team_name),
                     class_group = COALESCE(NULLIF(:class_group, ""), class_group)
                 WHERE id = :id'
            );
            $updateStmt->execute(['team_name' => $normalizedTeamName, 'class_group' => $normalizedClassGroup, 'id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO project_teams (project_academic_year_id, team_code, team_name, class_group, display_order, is_active)
             VALUES (:project_academic_year_id, :team_code, :team_name, :class_group, 0, 1)'
        );
        $insertStmt->execute([
            'project_academic_year_id' => $projectAcademicYearId,
            'team_code' => $normalizedTeamCode,
            'team_name' => $normalizedTeamName !== '' ? $normalizedTeamName : $normalizedTeamCode,
            'class_group' => $normalizedClassGroup !== '' ? $normalizedClassGroup : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function syncProjectTeamMemberRoles(int $projectTeamMemberId, array $projectRoleIds): void
    {
        $deleteStmt = $this->pdo->prepare('DELETE FROM project_team_member_roles WHERE project_team_member_id = :project_team_member_id');
        $deleteStmt->execute(['project_team_member_id' => $projectTeamMemberId]);

        if ($projectRoleIds === []) {
            return;
        }

        $insertStmt = $this->pdo->prepare('INSERT IGNORE INTO project_team_member_roles (project_team_member_id, project_role_id) VALUES (:project_team_member_id, :project_role_id)');
        foreach (array_values(array_unique($projectRoleIds)) as $projectRoleId) {
            $insertStmt->execute(['project_team_member_id' => $projectTeamMemberId, 'project_role_id' => (int) $projectRoleId]);
        }
    }

    private function resolveProjectRoleIds(string $roleInput): array
    {
        $roleInput = trim($roleInput);
        if ($roleInput === '') {
            return [];
        }

        $exactRoleId = $this->findProjectRoleId($roleInput);
        if ($exactRoleId !== null) {
            return [$exactRoleId];
        }

        if (preg_match('/[;,|]/', $roleInput) === 1) {
            $roleNames = preg_split('/\s*[;,|]\s*/', $roleInput) ?: [];
        } else {
            $roleNames = $this->splitProjectRolesByKnownNames($roleInput) ?? [$roleInput];
        }

        $projectRoleIds = [];
        foreach ($roleNames as $roleName) {
            $projectRoleId = $this->findOrCreateProjectRole((string) $roleName);
            if ($projectRoleId !== null) {
                $projectRoleIds[$projectRoleId] = $projectRoleId;
            }
        }

        return array_values($projectRoleIds);
    }

    private function findProjectRoleId(string $roleName): ?int
    {
        $normalized = trim($roleName);
        if ($normalized === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM project_roles WHERE LOWER(name) = :name LIMIT 1');
        $stmt->execute(['name' => mb_strtolower($normalized)]);
        $projectRoleId = $stmt->fetchColumn();

        return $projectRoleId !== false ? (int) $projectRoleId : null;
    }

    private function findOrCreateProjectRole(string $roleName): ?int
    {
        $normalized = trim($roleName);
        if ($normalized === '') {
            return null;
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM project_roles WHERE LOWER(name) = :name LIMIT 1');
        $existingStmt->execute(['name' => mb_strtolower($normalized)]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
            return (int) $existing['id'];
        }

        $insertStmt = $this->pdo->prepare('INSERT INTO project_roles (name, created_at) VALUES (:name, NOW())');
        $insertStmt->execute(['name' => $normalized]);

        return (int) $this->pdo->lastInsertId();
    }

    private function splitProjectRolesByKnownNames(string $roleInput): ?array
    {
        $stmt = $this->pdo->query('SELECT name FROM project_roles ORDER BY CHAR_LENGTH(name) DESC, name ASC');
        $knownRoles = array_map(static fn (array $row): string => (string) $row['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
        $remaining = trim($roleInput);
        $matchedRoles = [];

        while ($remaining !== '') {
            $matchedRole = null;
            foreach ($knownRoles as $knownRole) {
                if ($knownRole === '') {
                    continue;
                }

                $pattern = '/^' . preg_quote($knownRole, '/') . '(?:\s+|$)/iu';
                if (preg_match($pattern, $remaining) === 1) {
                    $matchedRole = $knownRole;
                    break;
                }
            }

            if ($matchedRole === null) {
                return null;
            }

            $matchedRoles[] = $matchedRole;
            $remaining = trim((string) preg_replace('/^' . preg_quote($matchedRole, '/') . '\s*/iu', '', $remaining, 1));
        }

        return $matchedRoles !== [] ? $matchedRoles : null;
    }

    private function findOrCreateRole(string $roleName): ?int
    {
        $normalized = trim(strtolower($roleName));
        if ($normalized === '') {
            return null;
        }

        $existingStmt = $this->pdo->prepare('SELECT id FROM web_roles WHERE LOWER(name) = :name LIMIT 1');
        $existingStmt->execute(['name' => $normalized]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
            return (int) $existing['id'];
        }

        $insertStmt = $this->pdo->prepare('INSERT INTO web_roles (name, created_at) VALUES (:name, NOW())');
        $insertStmt->execute(['name' => $normalized]);

        return (int) $this->pdo->lastInsertId();
    }

    private function normalizeAcademicYearLabel(string $academicYearName): string
    {
        $academicYearName = trim($academicYearName);
        if ($academicYearName === '') {
            return '';
        }

        if (preg_match('/^(\d{2})-(\d{2})$/', $academicYearName, $matches) === 1) {
            $startYear = (int) $matches[1];
            $endYear = (int) $matches[2];
            $prefixStart = $startYear >= 70 ? 1900 : 2000;
            $prefixEnd = $endYear < $startYear ? $prefixStart + 100 : $prefixStart;

            return sprintf('%04d-%04d', $prefixStart + $startYear, $prefixEnd + $endYear);
        }

        return $academicYearName;
    }

    private function buildClassCode(string $academicYearName, string $className): string
    {
        $yearCode = $academicYearName;
        if (preg_match('/^(\d{4})-(\d{4})$/', $academicYearName, $matches) === 1) {
            $yearCode = substr($matches[1], -2) . '-' . substr($matches[2], -2);
        }

        $classCode = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $className) ?? '');

        return $yearCode . '_' . $classCode;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';

        return trim($normalized, '_');
    }

    private function parseImportBoolean(string $value): ?int
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            '1', 'true', 'yes', 'si', 'sí', 's', 'active', 'actiu', 'activa' => 1,
            '0', 'false', 'no', 'n', 'inactive', 'inactiu', 'inactiva' => 0,
            default => null,
        };
    }

    private function result(string $message, string $type, array $summary = []): array
    {
        return [
            'message' => $message,
            'type' => $type,
            'summary' => $summary,
        ];
    }
}
