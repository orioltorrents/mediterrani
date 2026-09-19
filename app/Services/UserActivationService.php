<?php

declare(strict_types=1);

class UserActivationService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $deleteStmt = $this->pdo->prepare(
            'DELETE FROM user_activation_tokens
              WHERE user_id = :user_id
                AND used_at IS NULL'
        );
        $deleteStmt->execute(['user_id' => $userId]);

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO user_activation_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 48 HOUR), NOW())'
        );
        $insertStmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);

        return $token;
    }

    public function isValid(string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM user_activation_tokens
              WHERE token_hash = :token_hash
                AND used_at IS NULL
                AND expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);

        return $stmt->fetchColumn() !== false;
    }

    public function activate(string $token, string $password, string $confirmation): ?string
    {
        if (!$this->isValid($token)) {
            return 'L’enllaç d’activació no és vàlid o ha caducat.';
        }

        if (strlen($password) < 8) {
            return 'La contrasenya ha de tenir almenys 8 caràcters.';
        }

        if ($password !== $confirmation) {
            return 'Les contrasenyes no coincideixen.';
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT token.id, token.user_id
                   FROM user_activation_tokens token
                   INNER JOIN users u ON u.id = token.user_id
                  WHERE token.token_hash = :token_hash
                    AND token.used_at IS NULL
                    AND token.expires_at > NOW()
                    AND u.is_active = 1
                  LIMIT 1
                  FOR UPDATE'
            );
            $stmt->execute(['token_hash' => hash('sha256', $token)]);
            $activation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($activation === false) {
                $this->pdo->rollBack();

                return 'L’enllaç d’activació no és vàlid o ha caducat.';
            }

            $updateUser = $this->pdo->prepare(
                'UPDATE users
                    SET password_hash = :password_hash,
                        must_change_password = 0,
                        password_changed_at = CURRENT_TIMESTAMP
                  WHERE id = :user_id'
            );
            $updateUser->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'user_id' => (int) $activation['user_id'],
            ]);

            $markToken = $this->pdo->prepare(
                'UPDATE user_activation_tokens
                    SET used_at = CURRENT_TIMESTAMP
                  WHERE id = :id'
            );
            $markToken->execute(['id' => (int) $activation['id']]);

            $this->pdo->commit();

            return null;
        } catch (Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 'No s’ha pogut activar el compte.';
        }
    }
}
