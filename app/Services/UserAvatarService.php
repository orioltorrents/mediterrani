<?php

declare(strict_types=1);

class UserAvatarService
{
    private const RELATIVE_DIRECTORY = 'user-avatars/originals';

    /** @var array<string, string> */
    private array $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function preview(): array
    {
        $users = $this->users();
        $files = $this->avatarFiles();
        $usersByKey = [];
        $matchedUserIds = [];
        $matches = [];
        $ambiguousFiles = [];
        $unmatchedFiles = [];

        foreach ($users as $user) {
            $key = $this->userKey($user);
            if ($key === '') {
                continue;
            }

            $usersByKey[$key][] = $user;
        }

        foreach ($files as $file) {
            $key = $this->fileKey((string) $file['filename']);
            $candidateUsers = $key !== '' ? ($usersByKey[$key] ?? []) : [];

            if (count($candidateUsers) === 1) {
                $user = $candidateUsers[0];
                $userId = (int) $user['id'];
                $matchedUserIds[$userId] = true;
                $matches[] = [
                    'user' => $user,
                    'file' => $file,
                    'current_avatar_url' => (string) ($user['avatar_url'] ?? ''),
                    'will_update' => (string) ($user['avatar_url'] ?? '') !== (string) $file['relative_path'],
                ];
                continue;
            }

            if (count($candidateUsers) > 1) {
                $ambiguousFiles[] = [
                    'file' => $file,
                    'users' => $candidateUsers,
                ];
                continue;
            }

            $unmatchedFiles[] = $file;
        }

        $usersWithoutPhoto = array_values(array_filter(
            $users,
            static fn (array $user): bool => empty($matchedUserIds[(int) $user['id']]) && trim((string) ($user['avatar_url'] ?? '')) === ''
        ));

        return [
            'directory' => $this->absoluteDirectory(),
            'relativeDirectory' => self::RELATIVE_DIRECTORY,
            'files' => $files,
            'matches' => $matches,
            'ambiguousFiles' => $ambiguousFiles,
            'unmatchedFiles' => $unmatchedFiles,
            'usersWithoutPhoto' => $usersWithoutPhoto,
            'totalFiles' => count($files),
            'totalMatches' => count($matches),
            'totalUpdates' => count(array_filter($matches, static fn (array $match): bool => (bool) $match['will_update'])),
        ];
    }

    public function syncMatches(): array
    {
        $preview = $this->preview();
        $updated = 0;

        $stmt = $this->pdo->prepare('UPDATE users SET avatar_url = :avatar_url WHERE id = :id');
        foreach ($preview['matches'] as $match) {
            if (!(bool) ($match['will_update'] ?? false)) {
                continue;
            }

            $stmt->execute([
                'avatar_url' => (string) $match['file']['relative_path'],
                'id' => (int) $match['user']['id'],
            ]);
            $updated++;
        }

        return [
            'message' => 'Fotos vinculades: ' . $updated . '. Coincidències segures trobades: ' . (int) $preview['totalMatches'] . '.',
            'type' => 'success',
            'summary' => [
                'updated' => $updated,
                'matches' => (int) $preview['totalMatches'],
                'unmatched_files' => count($preview['unmatchedFiles']),
                'ambiguous_files' => count($preview['ambiguousFiles']),
            ],
        ];
    }

    public function avatarResponse(int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT avatar_url FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $avatarUrl = $stmt->fetchColumn();

        if ($avatarUrl === false || trim((string) $avatarUrl) === '') {
            http_response_code(404);
            echo 'Foto no trobada';
            return;
        }

        $filePath = $this->absolutePathForRelativePath((string) $avatarUrl);
        if ($filePath === null || !is_file($filePath)) {
            http_response_code(404);
            echo 'Foto no trobada';
            return;
        }

        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $this->mimeTypes[$extension] ?? null;
        if ($mimeType === null) {
            http_response_code(415);
            echo 'Tipus de fitxer no suportat';
            return;
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
    }

    private function users(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.name, u.surname, u.email, u.avatar_url
               FROM users u
         INNER JOIN user_web_roles ur ON ur.user_id = u.id
         INNER JOIN web_roles r ON r.id = ur.role_id
              WHERE r.name = \'student\'
           ORDER BY u.surname, u.name, u.email'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function avatarFiles(): array
    {
        $directory = $this->absoluteDirectory();
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator($directory);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (!isset($this->mimeTypes[$extension])) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            $files[] = [
                'filename' => $filename,
                'relative_path' => self::RELATIVE_DIRECTORY . '/' . $filename,
                'size' => $fileInfo->getSize(),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcmp((string) $a['filename'], (string) $b['filename']));

        return $files;
    }

    private function userKey(array $user): string
    {
        return $this->normalizeName(trim((string) ($user['surname'] ?? '') . ', ' . (string) ($user['name'] ?? '')));
    }

    private function fileKey(string $filename): string
    {
        return $this->normalizeName((string) pathinfo($filename, PATHINFO_FILENAME));
    }

    private function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists('Transliterator')) {
            $transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($transliterator !== null) {
                $value = $transliterator->transliterate($value);
            }
        } else {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9,]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s*,\s*/', ',', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function absoluteDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/' . self::RELATIVE_DIRECTORY;
    }

    private function absolutePathForRelativePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $baseDirectory = realpath($this->absoluteDirectory());
        if ($baseDirectory === false) {
            return null;
        }

        $prefix = self::RELATIVE_DIRECTORY . '/';
        if (!str_starts_with($relativePath, $prefix)) {
            return null;
        }

        $filename = basename($relativePath);
        $candidate = realpath($baseDirectory . '/' . $filename);
        if ($candidate === false || !str_starts_with($candidate, $baseDirectory . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }
}
