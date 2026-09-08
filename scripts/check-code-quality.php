<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Aquest script nomes es pot executar des de terminal.\n");
    exit(1);
}

$root = dirname(__DIR__);
$errors = [];

section('Lint PHP');
$phpFiles = phpFiles($root);
foreach ($phpFiles as $phpFile) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($phpFile);
    $output = [];
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $errors[] = 'Error de sintaxi a ' . relativePath($root, $phpFile) . ': ' . implode(' ', $output);
    }
}
ok(count($phpFiles) . ' fitxers PHP revisats.');

section('Controladors sense SQL');
$controllerSqlErrors = controllerSqlErrors($root, $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers');
foreach ($controllerSqlErrors as $controllerSqlError) {
    $errors[] = $controllerSqlError;
}
ok('Controladors revisats.');

section('Coherencia schema');
$schemaCheck = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'check-schema-coherence.php';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($schemaCheck);
passthru($command, $schemaExitCode);
if ($schemaExitCode !== 0) {
    $errors[] = 'scripts/check-schema-coherence.php ha fallat.';
}

if ($errors !== []) {
    fwrite(STDERR, "\nErrors de qualitat detectats:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }

    exit(1);
}

fwrite(STDOUT, "\nQualitat de codi OK.\n");
exit(0);

function section(string $title): void
{
    fwrite(STDOUT, "\n== " . $title . " ==\n");
}

function ok(string $message): void
{
    fwrite(STDOUT, $message . "\n");
}

/**
 * @return array<string>
 */
function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current): bool {
                if (!$current->isDir()) {
                    return true;
                }

                return !in_array($current->getFilename(), ['.git', 'vendor', 'storage'], true);
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * @return array<string>
 */
function controllerSqlErrors(string $root, string $controllersPath): array
{
    if (!is_dir($controllersPath)) {
        return ['No existeix la carpeta de controladors: ' . $controllersPath];
    }

    $errors = [];
    $pattern = '/\b(SELECT|INSERT|UPDATE|DELETE|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersPath, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $lines = file($file->getPathname());
        if ($lines === false) {
            $errors[] = 'No es pot llegir ' . $file->getPathname();
            continue;
        }

        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line) === 1) {
                $errors[] = 'SQL/DDL detectat a ' . relativePath($root, $file->getPathname()) . ':' . ($lineNumber + 1);
            }
        }
    }

    return $errors;
}

function relativePath(string $root, string $path): string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, $normalizedRoot)) {
        return substr($normalizedPath, strlen($normalizedRoot));
    }

    return $path;
}
