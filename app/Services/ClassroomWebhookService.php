<?php

declare(strict_types=1);

class ClassroomWebhookService
{
    private const MAX_ROWS = 500;
    private const ALLOWED_EVENT_TYPES = ['classroom_extraction_test', 'classroom_structure_snapshot'];
    private const REQUIRED_ROW_FIELDS = [
        'classroom_structure_snapshot' => [
            'academic_year',
            'classroom_key',
            'project_slug',
            'phase_key',
            'phase_title',
            'task_key',
            'task_title',
            'task_url',
        ],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function storePayload(array $payload, array $serverContext = []): array
    {
        $source = trim((string) ($payload['source'] ?? ''));
        $eventType = trim((string) ($payload['event_type'] ?? ''));
        $rows = $payload['rows'] ?? null;

        if ($source === '') {
            throw new InvalidArgumentException('El camp source és obligatori.');
        }

        if (!in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            throw new InvalidArgumentException('event_type no permès.');
        }

        if (!is_array($rows)) {
            throw new InvalidArgumentException('El camp rows ha de ser un array.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('El payload supera el màxim de ' . self::MAX_ROWS . ' files.');
        }

        $this->validateRowsForEventType($eventType, $rows);

        $rawPayloadJson = $this->encodeJson($payload);
        $extractedAt = $this->parseDateTime($payload['extracted_at'] ?? null);
        $requestIp = $this->nullableString($serverContext['request_ip'] ?? null, 45);
        $userAgent = $this->nullableString($serverContext['user_agent'] ?? null, 500);

        $this->pdo->beginTransaction();

        try {
            $runId = $this->insertRun($source, $eventType, $extractedAt, count($rows), $requestIp, $userAgent, $rawPayloadJson);
            $this->insertRows($runId, $eventType, $rows);

            $this->pdo->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }

        return [
            'run_id' => $runId,
            'received_rows' => count($rows),
        ];
    }

    private function insertRun(string $source, string $eventType, ?string $extractedAt, int $totalRows, ?string $requestIp, ?string $userAgent, string $rawPayloadJson): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO classroom_webhook_runs
                (source, event_type, extracted_at, status, total_rows, request_ip, user_agent, raw_payload_json)
             VALUES
                (:source, :event_type, :extracted_at, "received", :total_rows, :request_ip, :user_agent, :raw_payload_json)'
        );
        $stmt->execute([
            'source' => $source,
            'event_type' => $eventType,
            'extracted_at' => $extractedAt,
            'total_rows' => $totalRows,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,
            'raw_payload_json' => $rawPayloadJson,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertRows(int $runId, string $eventType, array $rows): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO classroom_webhook_rows
                (webhook_run_id, row_number, event_type, academic_year, classroom_key, project_slug, task_key, student_email, google_course_work_id, raw_row_json)
             VALUES
                (:webhook_run_id, :row_number, :event_type, :academic_year, :classroom_key, :project_slug, :task_key, :student_email, :google_course_work_id, :raw_row_json)'
        );

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('La fila ' . ((int) $index + 1) . ' no és un objecte JSON vàlid.');
            }

            $stmt->execute([
                'webhook_run_id' => $runId,
                'row_number' => (int) $index + 1,
                'event_type' => $eventType,
                'academic_year' => $this->nullableString($row['academic_year'] ?? null, 50),
                'classroom_key' => $this->nullableString($row['classroom_key'] ?? null, 190),
                'project_slug' => $this->nullableString($row['project_slug'] ?? null, 190),
                'task_key' => $this->nullableString($row['task_key'] ?? null, 190),
                'student_email' => $this->nullableString($row['student_email'] ?? null, 190),
                'google_course_work_id' => $this->nullableString($row['google_course_work_id'] ?? null, 190),
                'raw_row_json' => $this->encodeJson($row),
            ]);
        }
    }

    private function validateRowsForEventType(string $eventType, array $rows): void
    {
        $requiredFields = self::REQUIRED_ROW_FIELDS[$eventType] ?? [];
        if ($requiredFields === []) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('La fila ' . ((int) $index + 1) . ' no és un objecte JSON vàlid.');
            }

            foreach ($requiredFields as $field) {
                if (trim((string) ($row[$field] ?? '')) === '') {
                    throw new InvalidArgumentException('La fila ' . ((int) $index + 1) . ' no informa el camp obligatori ' . $field . '.');
                }
            }

            $taskUrl = trim((string) ($row['task_url'] ?? ''));
            $taskUrlScheme = strtolower((string) parse_url($taskUrl, PHP_URL_SCHEME));
            if (filter_var($taskUrl, FILTER_VALIDATE_URL) === false || !in_array($taskUrlScheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('La fila ' . ((int) $index + 1) . ' té un task_url no vàlid.');
            }
        }
    }

    private function parseDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP', 'd/m/Y H:i:s'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
