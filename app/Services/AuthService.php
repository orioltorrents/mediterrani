<?php

declare(strict_types=1);

class AuthService
{
    private const LOGIN_RATE_WINDOW = 900;
    private const LOGIN_RATE_IP_MAX = 5;
    private const LOGIN_RATE_EMAIL_MAX = 10;
    private const SESSION_REVALIDATION_INTERVAL = 300;

    private ?string $loginRateError = null;

    public function __construct()
    {
        startAppSession();
    }

    public function attemptLogin(string $email, string $password): bool
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            (new LogService())->write('auth=login_failed email=' . $email . ' reason=empty_fields');
            return false;
        }

        $rateError = $this->checkLoginRateLimit($email);
        if ($rateError !== null) {
            $this->loginRateError = $rateError;
            (new LogService())->write('auth=login_failed email=' . $email . ' reason=rate_limited');
            return false;
        }

        $user = $this->findActiveUserByEmail($email);

        if ($user === null || empty($user['password_hash'])) {
            $this->recordLoginAttempt($this->clientIp(), $email, false);
            (new LogService())->write('auth=login_failed email=' . $email . ' reason=user_not_found_or_inactive');
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->recordLoginAttempt($this->clientIp(), $email, false);
            (new LogService())->write('auth=login_failed email=' . $email . ' reason=invalid_password');
            return false;
        }

        $roles = $this->rolesForUser((int) $user['id']);

        if ($roles === []) {
            $this->recordLoginAttempt($this->clientIp(), $email, false);
            (new LogService())->write('auth=login_failed email=' . $email . ' reason=no_roles');
            return false;
        }

        $this->recordLoginAttempt($this->clientIp(), $email, true);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'surname' => (string) ($user['surname'] ?? ''),
            'email' => (string) $user['email'],
            'roles' => $roles,
            'must_change_password' => (int) ($user['must_change_password'] ?? 0) === 1,
        ];

        $this->markLastLogin((int) $user['id']);

        (new LogService())->write('auth=login user_id=' . (int) $user['id'] . ' email=' . $email . ' roles=' . implode(',', $roles));

        return true;
    }

    public function getLoginRateError(): ?string
    {
        return $this->loginRateError;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    public function check(): bool
    {
        $hasSession = isset($_SESSION['user']['id'], $_SESSION['user']['roles'])
            && is_array($_SESSION['user']['roles']);

        if ($hasSession) {
            $this->validateSession();

            return isset($_SESSION['user']['id'], $_SESSION['user']['roles'])
                && is_array($_SESSION['user']['roles']);
        }

        return false;
    }

    public function user(): ?array
    {
        if ($this->isImpersonating()) {
            return $_SESSION['impersonation']['target'];
        }

        return $this->check() ? $_SESSION['user'] : null;
    }

    public function actorUser(): ?array
    {
        return $this->check() ? $_SESSION['user'] : null;
    }

    private function validateSession(): void
    {
        $lastValidated = (int) ($_SESSION['user_last_validated'] ?? 0);
        if (time() - $lastValidated < self::SESSION_REVALIDATION_INTERVAL) {
            return;
        }

        $userId = (int) $_SESSION['user']['id'];

        $stmt = $this->pdo()->prepare(
            'SELECT is_active FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            unset($_SESSION['user'], $_SESSION['user_last_validated']);
            (new LogService())->write('auth=session_invalidated user_id=' . $userId . ' reason=inactive_or_deleted');

            return;
        }

        $currentRoles = $this->rolesForUser($userId);
        $sessionRoles = (array) ($_SESSION['user']['roles'] ?? []);

        if (array_diff($currentRoles, $sessionRoles) !== [] || array_diff($sessionRoles, $currentRoles) !== []) {
            $_SESSION['user']['roles'] = $currentRoles;
            (new LogService())->write('auth=roles_updated user_id=' . $userId . ' roles=' . implode(',', $currentRoles));
        }

        $_SESSION['user_last_validated'] = time();
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();

        return $user !== null && in_array($role, $user['roles'], true);
    }

    public function hasActorRole(string $role): bool
    {
        $user = $this->actorUser();

        return $user !== null && in_array($role, $user['roles'], true);
    }

    public function requireRole(string $role): void
    {
        if ($this->hasRole($role)) {
            return;
        }

        header('Location: ' . url('login'));
        exit;
    }

    public function requireActorRole(string $role): void
    {
        if ($this->hasActorRole($role)) {
            return;
        }

        header('Location: ' . url('login'));
        exit;
    }

    public function mustChangePassword(): bool
    {
        return $this->check()
            && !$this->isImpersonating()
            && (bool) ($_SESSION['user']['must_change_password'] ?? false);
    }

    public function requirePasswordChangeCompleted(): void
    {
        if (!$this->mustChangePassword()) {
            return;
        }

        header('Location: ' . url('canviar-contrasenya'));
        exit;
    }

    public function changeRequiredPassword(string $currentPassword, string $newPassword, string $confirmPassword): ?string
    {
        $user = $this->actorUser();

        if ($user === null) {
            return 'Cal iniciar sessió.';
        }

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=empty_fields');
            return 'Tots els camps són obligatoris.';
        }

        if ($newPassword !== $confirmPassword) {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=password_mismatch');
            return 'La nova contrasenya i la repetició no coincideixen.';
        }

        if (strlen($newPassword) < 8) {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=too_short');
            return 'La nova contrasenya ha de tenir com a mínim 8 caràcters.';
        }

        if ($currentPassword === $newPassword) {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=same_password');
            return 'La nova contrasenya ha de ser diferent de la contrasenya inicial.';
        }

        $storedUser = $this->findActiveUserWithPasswordById((int) $user['id']);

        if ($storedUser === null || empty($storedUser['password_hash'])) {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=user_not_found');
            return 'No s’ha pogut validar l’usuari.';
        }

        if (!password_verify($currentPassword, (string) $storedUser['password_hash'])) {
            (new LogService())->write('auth=change_password_failed user_id=' . (int) $user['id'] . ' reason=incorrect_current');
            return 'La contrasenya actual no és correcta.';
        }

        $stmt = $this->pdo()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                  must_change_password = 0,
                  password_changed_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

        $_SESSION['user']['must_change_password'] = false;

        (new LogService())->write('auth=change_password user_id=' . (int) $user['id']);

        return null;
    }

    public function isImpersonating(): bool
    {
        return $this->check()
            && isset($_SESSION['impersonation']['target']['id'], $_SESSION['impersonation']['target']['roles'])
            && is_array($_SESSION['impersonation']['target']['roles']);
    }

    public function impersonateStudent(int $studentId): bool
    {
        if (!$this->hasActorRole('admin')) {
            return false;
        }

        $student = $this->findActiveUserById($studentId);

        if ($student === null) {
            return false;
        }

        $roles = $this->rolesForUser((int) $student['id']);

        if (!in_array('student', $roles, true)) {
            return false;
        }

        $_SESSION['impersonation'] = [
            'target' => [
                'id' => (int) $student['id'],
                'name' => (string) $student['name'],
                'surname' => (string) ($student['surname'] ?? ''),
                'email' => (string) $student['email'],
                'roles' => $roles,
            ],
            'started_at' => time(),
        ];

        return true;
    }

    public function stopImpersonating(): void
    {
        unset($_SESSION['impersonation']);
    }

    public function redirectPathForCurrentUser(): string
    {
        if ($this->mustChangePassword()) {
            return 'canviar-contrasenya';
        }

        if ($this->hasRole('admin')) {
            return 'admin';
        }

        if ($this->hasRole('teacher')) {
            return 'professor';
        }

        if ($this->hasRole('student')) {
            return 'alumne';
        }

        return '';
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && hash_equals((string) $_SESSION['csrf_token'], $token);
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function cleanupOldLoginAttempts(): void
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM login_attempts
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :window SECOND)'
        );
        $stmt->execute(['window' => self::LOGIN_RATE_WINDOW]);
    }

    private function checkLoginRateLimit(string $email): ?string
    {
        $this->cleanupOldLoginAttempts();

        $ip = $this->clientIp();

        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip
             AND success = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)'
        );
        $stmt->execute(['ip' => $ip, 'window' => self::LOGIN_RATE_WINDOW]);
        $ipAttempts = (int) $stmt->fetchColumn();

        if ($ipAttempts >= self::LOGIN_RATE_IP_MAX) {
            return 'Massa intents fallits des d\'aquesta adreça. Torna-ho a provar d\'aquí a uns minuts.';
        }

        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email
             AND success = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)'
        );
        $stmt->execute(['email' => $email, 'window' => self::LOGIN_RATE_WINDOW]);
        $emailAttempts = (int) $stmt->fetchColumn();

        if ($emailAttempts >= self::LOGIN_RATE_EMAIL_MAX) {
            return 'Massa intents fallits per a aquest compte. Torna-ho a provar d\'aquí a uns minuts.';
        }

        return null;
    }

    private function recordLoginAttempt(string $ip, string $email, bool $success): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO login_attempts (ip_address, email, success, attempted_at)
             VALUES (:ip, :email, :success, NOW())'
        );
        $stmt->execute(['ip' => $ip, 'email' => $email, 'success' => $success ? 1 : 0]);
    }

    private function findActiveUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, surname, email, password_hash, is_active
                    , must_change_password
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        return $user;
    }

    private function findActiveUserWithPasswordById(int $userId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, password_hash, is_active
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        return $user;
    }

    private function findActiveUserById(int $userId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, surname, email, is_active
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        return $user;
    }

    private function rolesForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT web_roles.name
              FROM user_web_roles
              INNER JOIN web_roles ON web_roles.id = user_web_roles.role_id
              WHERE user_web_roles.user_id = :user_id
              ORDER BY web_roles.name'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function markLastLogin(int $userId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users
             SET last_login_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
