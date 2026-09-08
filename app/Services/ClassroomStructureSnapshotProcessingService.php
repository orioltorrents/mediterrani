<?php

declare(strict_types=1);

class ClassroomStructureSnapshotProcessingService
{
    private const EVENT_TYPE = 'classroom_structure_snapshot';

    public function __construct(private PDO $pdo)
    {
    }

    public function processPending(): array
    {
        $rows = $this->pendingRows();
        $processed = 0;
        $errors = [];
        $importService = new AdminClassroomService($this->pdo);

        foreach ($rows as $row) {
            $rowId = (int) $row['id'];

            try {
                $payload = json_decode((string) $row['raw_row_json'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload)) {
                    throw new RuntimeException('La fila staging no conté un objecte JSON vàlid.');
                }

                $this->pdo->beginTransaction();
                $this->markRow($rowId, 'processing', null);
                $importService->importTaskLinkRowData($payload);
                $this->markRow($rowId, 'processed', null);
                $this->pdo->commit();

                $processed++;
            } catch (Throwable $throwable) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $message = $this->truncateError($throwable->getMessage());
                $this->markRow($rowId, 'error', $message);
                $errors[] = 'Fila staging ' . $rowId . ': ' . $message;
            }
        }

        $message = 'Snapshots d’estructura processats: ' . $processed . ' correctes, ' . count($errors) . ' errors.';
        if ($errors !== []) {
            $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
        }

        return [
            'message' => $message,
            'type' => $errors === [] ? 'success' : 'error',
            'processed' => $processed,
            'errors' => count($errors),
        ];
    }

    private function pendingRows(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, raw_row_json
             FROM classroom_webhook_rows
             WHERE event_type = :event_type
               AND process_status = "pending"
             ORDER BY webhook_run_id, row_number, id'
        );
        $stmt->execute(['event_type' => self::EVENT_TYPE]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function markRow(int $rowId, string $status, ?string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE classroom_webhook_rows
             SET process_status = :process_status,
                 process_error = :process_error,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'process_status' => $status,
            'process_error' => $error,
            'id' => $rowId,
        ]);
    }

    private function truncateError(string $message): string
    {
        $message = trim(str_replace(["\r", "\n"], ' ', $message));

        return mb_substr($message !== '' ? $message : 'Error desconegut.', 0, 1000, 'UTF-8');
    }
}
