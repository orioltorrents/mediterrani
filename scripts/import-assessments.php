<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Aquest script nomes es pot executar des de terminal.\n");
    exit(1);
}

$projectAcademicYearId = (int) ($argv[1] ?? 0);
$csvPath = (string) ($argv[2] ?? '');
$sourceName = trim((string) ($argv[3] ?? ''));

if ($projectAcademicYearId <= 0 || $csvPath === '') {
    fwrite(STDERR, "Us: php scripts/import-assessments.php <project-academic-year-id> <fitxer.csv> [nom-font]\n");
    fwrite(STDERR, "Exemple: php scripts/import-assessments.php 12 storage/imports/notes/projecte-rius-2025-2026.csv \"Projecte Rius\"\n");
    exit(1);
}

if (!is_file($csvPath) || !is_readable($csvPath)) {
    fwrite(STDERR, "No es pot llegir el CSV: {$csvPath}\n");
    exit(1);
}

$sourceName = $sourceName !== '' ? $sourceName : pathinfo($csvPath, PATHINFO_FILENAME);
$pdo = require dirname(__DIR__) . '/config/database.php';

try {
    $rows = readCsv($csvPath);
    if ($rows === []) {
        throw new RuntimeException('El CSV no conte files.');
    }

    $headerRowIndex = findHeaderRowIndex($rows);
    if ($headerRowIndex === null) {
        throw new RuntimeException("No s'ha trobat cap fila de capcaleres amb una columna email.");
    }

    $headers = $rows[$headerRowIndex];
    $rows = array_slice($rows, $headerRowIndex + 1);
    $firstDataRowNumber = $headerRowIndex + 2;
    $headers = normalizeHeaders($headers);
    $emailIndex = findHeaderIndex($headers, ['email', 'correu', 'correu electronic', 'correu electrònic']);

    if ($emailIndex === null) {
        throw new RuntimeException("No s'ha trobat cap columna email al CSV.");
    }

    $projectAcademicYear = findProjectAcademicYear($pdo, $projectAcademicYearId);
    if ($projectAcademicYear === null) {
        throw new RuntimeException("No s'ha trobat l'edició de projecte amb id {$projectAcademicYearId}.");
    }

    $metadataIndexes = detectMetadataIndexes($headers);

    $pdo->beginTransaction();

    $sourceId = upsertSource(
        $pdo,
        (int) $projectAcademicYear['project_academic_year_id'],
        $sourceName,
        $csvPath,
        $headers[$emailIndex]
    );
    $runId = createImportRun(
        $pdo,
        (int) $projectAcademicYear['project_academic_year_id'],
        $sourceId,
        $csvPath,
        count($rows)
    );

    $deleteStmt = $pdo->prepare(
        'DELETE FROM assessment_records
         WHERE source_id = :source_id'
    );
    $deleteStmt->execute([
        'source_id' => $sourceId,
    ]);

    $insertRecordStmt = $pdo->prepare(
        'INSERT INTO assessment_records
            (user_id, source_id, import_run_id, student_email, label, source_column, value,
             value_type, numeric_value, achievement_value, group_name, team_code, role_name, display_order, imported_at)
         VALUES
            (:user_id, :source_id, :import_run_id, :student_email, :label, :source_column, :value,
             :value_type, :numeric_value, :achievement_value, :group_name, :team_code, :role_name, :display_order, NOW())'
    );

    $insertErrorStmt = $pdo->prepare(
        'INSERT INTO assessment_import_errors (import_run_id, row_number, student_email, message, raw_data)
         VALUES (:import_run_id, :row_number, :student_email, :message, :raw_data)'
    );

    $rowsImported = 0;
    $rowsFailed = 0;
    $recordsImported = 0;

    foreach ($rows as $rowOffset => $row) {
        $rowNumber = $rowOffset + $firstDataRowNumber;
        $row = normalizeRowLength($row, count($headers));
        $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));

        if ($email === '') {
            $rowsFailed++;
            insertImportError($insertErrorStmt, $runId, $rowNumber, null, 'Fila sense email.', $headers, $row);
            continue;
        }

        $user = findUserByEmail($pdo, $email);
        if ($user === null) {
            $rowsFailed++;
            insertImportError($insertErrorStmt, $runId, $rowNumber, $email, 'No existeix cap usuari amb aquest email.', $headers, $row);
            continue;
        }

        $groupName = valueAt($row, $metadataIndexes['group']);
        $teamCode = valueAt($row, $metadataIndexes['team']);
        $roleName = valueAt($row, $metadataIndexes['role']);
        $recordCountForRow = 0;

        foreach ($headers as $columnIndex => $header) {
            if ($header === '' || shouldSkipColumn($columnIndex, $emailIndex, $metadataIndexes, $header)) {
                continue;
            }

            $value = trim((string) ($row[$columnIndex] ?? ''));
            $typedValue = classifyValue($value);

            $insertRecordStmt->execute([
                'user_id' => (int) $user['id'],
                'source_id' => $sourceId,
                'import_run_id' => $runId,
                'student_email' => $email,
                'label' => $header,
                'source_column' => $header,
                'value' => $value !== '' ? $value : null,
                'value_type' => $typedValue['type'],
                'numeric_value' => $typedValue['numeric'],
                'achievement_value' => $typedValue['achievement'],
                'group_name' => $groupName,
                'team_code' => $teamCode,
                'role_name' => $roleName,
                'display_order' => $columnIndex,
            ]);

            $recordCountForRow++;
            $recordsImported++;
        }

        if ($recordCountForRow === 0) {
            $rowsFailed++;
            insertImportError($insertErrorStmt, $runId, $rowNumber, $email, 'Fila sense columnes importables.', $headers, $row);
            continue;
        }

        $rowsImported++;
    }

    $status = 'success';
    if ($rowsFailed > 0 && $rowsImported > 0) {
        $status = 'partial';
    } elseif ($rowsFailed > 0 && $rowsImported === 0) {
        $status = 'failed';
    }

    finishImportRun($pdo, $runId, $status, $rowsImported, $rowsFailed, $recordsImported);
    markSourceImported($pdo, $sourceId);
    $pdo->commit();

    print "Importacio finalitzada.\n";
    print "Projecte: {$projectAcademicYear['project_slug']}\n";
    print "Curs: {$projectAcademicYear['academic_year_name']}\n";
    print "Edicio: {$projectAcademicYear['project_academic_year_id']}\n";
    print "Font: {$sourceName}\n";
    print "Files totals: " . count($rows) . "\n";
    print "Files importades: {$rowsImported}\n";
    print "Files amb error: {$rowsFailed}\n";
    print "Registres de notes/comentaris: {$recordsImported}\n";
    print "Import run ID: {$runId}\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

function readCsv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("No es pot obrir el CSV: {$path}");
    }

    $sample = (string) fread($handle, 4096);
    rewind($handle);

    $delimiter = detectDelimiter($sample);
    $rows = [];

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($row === [null] || isEmptyCsvRow($row)) {
            continue;
        }

        if ($rows === [] && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        $rows[] = array_map(static fn($value): string => trim((string) $value), $row);
    }

    fclose($handle);

    return $rows;
}

function detectDelimiter(string $sample): string
{
    $semicolons = substr_count($sample, ';');
    $commas = substr_count($sample, ',');

    return $semicolons > $commas ? ';' : ',';
}

function isEmptyCsvRow(array $row): bool
{
    foreach ($row as $value) {
        if (trim((string) $value) !== '') {
            return false;
        }
    }

    return true;
}

function normalizeHeaders(array $headers): array
{
    $seen = [];

    return array_map(static function ($header, $index) use (&$seen): string {
        $header = trim((string) $header);
        if ($header === '') {
            return '';
        }

        $key = normalizeText($header);
        $seen[$key] = ($seen[$key] ?? 0) + 1;

        return $seen[$key] === 1 ? $header : $header . ' ' . $seen[$key];
    }, $headers, array_keys($headers));
}

function normalizeText(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

function findHeaderIndex(array $headers, array $candidates): ?int
{
    $normalizedCandidates = array_map('normalizeText', $candidates);

    foreach ($headers as $index => $header) {
        if (in_array(normalizeText($header), $normalizedCandidates, true)) {
            return $index;
        }
    }

    return null;
}

function findHeaderRowIndex(array $rows): ?int
{
    $maxRowsToScan = min(count($rows), 10);

    for ($index = 0; $index < $maxRowsToScan; $index++) {
        $headers = normalizeHeaders($rows[$index]);
        if (findHeaderIndex($headers, ['email', 'correu', 'correu electronic', 'correu electrònic']) !== null) {
            return $index;
        }
    }

    return null;
}

function detectMetadataIndexes(array $headers): array
{
    return [
        'name' => findFirstMatchingHeader($headers, ['cognoms nom', 'nom cognoms', 'nom', 'alumne', 'alumna', 'estudiant']),
        'group' => findFirstMatchingHeader($headers, ['grup classe', 'grup', 'classe']),
        'team' => findFirstMatchingHeader($headers, ['codi grup', 'codi grup 1t', 'codi grup 2t', 'codi grup 3t', 'codi equip', 'team']),
        'role' => findFirstMatchingHeader($headers, ['rol', 'rol cooperatiu', 'carrec', 'paper']),
        'photo' => findFirstMatchingHeader($headers, ['foto', 'fotografia', 'photo', 'imatge']),
    ];
}

function findFirstMatchingHeader(array $headers, array $needles): ?int
{
    foreach ($headers as $index => $header) {
        $normalizedHeader = normalizeText($header);

        foreach ($needles as $needle) {
            $normalizedNeedle = normalizeText($needle);
            if ($normalizedHeader === $normalizedNeedle || str_starts_with($normalizedHeader, $normalizedNeedle . ' ')) {
                return $index;
            }
        }
    }

    return null;
}

function normalizeRowLength(array $row, int $length): array
{
    if (count($row) < $length) {
        return array_pad($row, $length, '');
    }

    return array_slice($row, 0, $length);
}

function valueAt(array $row, ?int $index): ?string
{
    if ($index === null) {
        return null;
    }

    $value = trim((string) ($row[$index] ?? ''));

    return $value !== '' ? $value : null;
}

function shouldSkipColumn(int $columnIndex, int $emailIndex, array $metadataIndexes, string $header): bool
{
    if ($columnIndex === $emailIndex || in_array($columnIndex, $metadataIndexes, true)) {
        return true;
    }

    $normalized = normalizeText($header);

    return in_array($normalized, [
        'id',
        'url',
        'descripcio',
        'genere',
        'article',
        'article 2',
        'psi',
    ], true);
}

function classifyValue(string $value): array
{
    $trimmed = trim($value);
    if ($trimmed === '' || in_array(mb_strtoupper($trimmed, 'UTF-8'), ['-', 'N/A', 'SENSE NOTA', 'ABS', 'NP'], true)) {
        return ['type' => 'empty', 'numeric' => null, 'achievement' => null];
    }

    $upper = mb_strtoupper($trimmed, 'UTF-8');
    if (in_array($upper, ['AE', 'AN', 'AS', 'NA'], true)) {
        return ['type' => 'achievement', 'numeric' => null, 'achievement' => $upper];
    }

    $numericText = str_replace(',', '.', $trimmed);
    if (is_numeric($numericText)) {
        return ['type' => 'numeric', 'numeric' => round((float) $numericText, 2), 'achievement' => null];
    }

    return ['type' => 'text', 'numeric' => null, 'achievement' => null];
}

function findProjectAcademicYear(PDO $pdo, int $projectAcademicYearId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
             pay.id AS project_academic_year_id,
             pay.project_id,
             p.slug AS project_slug,
             p.name AS project_name,
             pay.academic_year_id,
             ay.name AS academic_year_name
         FROM project_academic_years pay
         INNER JOIN projects p ON p.id = pay.project_id
         INNER JOIN academic_years ay ON ay.id = pay.academic_year_id
         WHERE pay.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $projectAcademicYearId]);
    $projectAcademicYear = $stmt->fetch(PDO::FETCH_ASSOC);

    return $projectAcademicYear === false ? null : $projectAcademicYear;
}

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user === false ? null : $user;
}

function upsertSource(PDO $pdo, int $projectAcademicYearId, string $name, string $csvPath, string $emailColumn): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO assessment_sources (project_academic_year_id, name, source_type, source_reference, email_column)
         VALUES (:project_academic_year_id, :name, :source_type, :source_reference, :email_column)
         ON DUPLICATE KEY UPDATE
              source_reference = VALUES(source_reference),
              email_column = VALUES(email_column),
              is_active = 1,
              updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'project_academic_year_id' => $projectAcademicYearId,
        'name' => $name,
        'source_type' => 'csv',
        'source_reference' => $csvPath,
        'email_column' => $emailColumn,
    ]);

    $select = $pdo->prepare('SELECT id FROM assessment_sources WHERE project_academic_year_id = :project_academic_year_id AND name = :name LIMIT 1');
    $select->execute(['project_academic_year_id' => $projectAcademicYearId, 'name' => $name]);

    return (int) $select->fetchColumn();
}

function createImportRun(PDO $pdo, int $projectAcademicYearId, int $sourceId, string $filename, int $rowsTotal): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO assessment_import_runs (project_academic_year_id, source_id, filename, status, rows_total, started_at)
         VALUES (:project_academic_year_id, :source_id, :filename, :status, :rows_total, NOW())'
    );
    $stmt->execute([
        'project_academic_year_id' => $projectAcademicYearId,
        'source_id' => $sourceId,
        'filename' => $filename,
        'status' => 'running',
        'rows_total' => $rowsTotal,
    ]);

    return (int) $pdo->lastInsertId();
}

function insertImportError(PDOStatement $stmt, int $runId, int $rowNumber, ?string $email, string $message, array $headers, array $row): void
{
    $rawData = [];
    foreach ($headers as $index => $header) {
        if ($header !== '') {
            $rawData[$header] = $row[$index] ?? '';
        }
    }

    $stmt->execute([
        'import_run_id' => $runId,
        'row_number' => $rowNumber,
        'student_email' => $email,
        'message' => $message,
        'raw_data' => json_encode($rawData, JSON_UNESCAPED_UNICODE),
    ]);
}

function finishImportRun(PDO $pdo, int $runId, string $status, int $rowsImported, int $rowsFailed, int $recordsImported): void
{
    $stmt = $pdo->prepare(
        'UPDATE assessment_import_runs
         SET status = :status,
             rows_imported = :rows_imported,
             rows_failed = :rows_failed,
             finished_at = NOW(),
             message = :message
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'rows_imported' => $rowsImported,
        'rows_failed' => $rowsFailed,
        'message' => "Registres importats: {$recordsImported}",
        'id' => $runId,
    ]);
}

function markSourceImported(PDO $pdo, int $sourceId): void
{
    $stmt = $pdo->prepare('UPDATE assessment_sources SET last_imported_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $sourceId]);
}
