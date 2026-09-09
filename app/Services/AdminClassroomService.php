<?php

declare(strict_types=1);

class AdminClassroomService
{
    private ClassroomCsvImportService $csvImportService;
    private ClassroomLookupService $lookupService;
    private ClassroomMemberImportService $memberImportService;
    private ClassroomTaskImportService $taskImportService;
    private ClassroomImportService $classroomImportService;

    public function __construct(private PDO $pdo)
    {
        $this->csvImportService = new ClassroomCsvImportService();
        $this->lookupService = new ClassroomLookupService($pdo);
        $this->memberImportService = new ClassroomMemberImportService($pdo, $this->csvImportService, $this->lookupService);
        $this->taskImportService = new ClassroomTaskImportService($pdo, $this->csvImportService, $this->lookupService);
        $this->classroomImportService = new ClassroomImportService($pdo, $this->csvImportService, $this->lookupService);
    }

    public function importUploadedFile(array $file): array
    {
        return $this->classroomImportService->importUploadedFile($file);
    }

    public function toggleClassroom(array $input): array
    {
        $classroomId = filter_var($input['classroom_id'] ?? null, FILTER_VALIDATE_INT);
        if ($classroomId === false || $classroomId === null) {
            return AppHelper::result('Classroom no vàlid.', 'error');
        }

        $stmt = $this->pdo->prepare('SELECT is_active FROM classrooms WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $classroomId]);
        $classroom = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($classroom === false) {
            return AppHelper::result('No s\'ha trobat el Classroom.', 'error');
        }

        $newState = ((int) $classroom['is_active'] === 1) ? 0 : 1;
        $update = $this->pdo->prepare('UPDATE classrooms SET is_active = :is_active WHERE id = :id');
        $update->execute([
            'is_active' => $newState,
            'id' => (int) $classroomId,
        ]);

        return AppHelper::result($newState === 1 ? 'Classroom reactivat.' : 'Classroom arxivat.', 'success');
    }

    public function importMembersUploadedFile(array $file): array
    {
        return $this->memberImportService->importMembersUploadedFile($file);
    }

    public function importProjectLinksUploadedFile(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return AppHelper::result('No s\'ha rebut cap fitxer CSV de vincles Classroom-projecte.', 'error');
        }

        try {
            $csv = $this->csvImportService->readCsvWithHeaders((string) $file['tmp_name']);
            $this->csvImportService->validateHeaders($csv['headers'], ['academic_year', 'classroom_key', 'project_slug'], 'classroom_project_links');
        } catch (Throwable $throwable) {
            return AppHelper::result('No s\'ha pogut llegir el CSV: ' . $throwable->getMessage(), 'error');
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($csv['rows'] as $rowNumber => $row) {
            try {
                $wasCreated = $this->importProjectLinkRow($row);
                if ($wasCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $throwable) {
                $errors[] = 'Fila ' . $rowNumber . ': ' . $throwable->getMessage();
            }
        }

        $message = 'Importació de vincles Classroom-projecte: ' . $created . ' creats i ' . $updated . ' actualitzats.';
        if ($errors !== []) {
            $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
            return AppHelper::result($message, 'error');
        }

        return AppHelper::result($message, 'success');
    }

    public function importTaskLinksUploadedFile(array $file): array
    {
        return $this->taskImportService->importTaskLinksUploadedFile($file);
    }

    public function importTaskLinkRowData(array $row): bool
    {
        return $this->taskImportService->importTaskLinkRowData($row);
    }

    private function importProjectLinkRow(array $row): bool
    {
        $academicYear = trim((string) ($row['academic_year'] ?? ''));
        $classroomKey = trim((string) ($row['classroom_key'] ?? ''));
        $projectSlug = trim((string) ($row['project_slug'] ?? ''));
        $isActive = $this->csvImportService->parseActiveFlag($row['is_active'] ?? '1');

        if ($academicYear === '' || $classroomKey === '' || $projectSlug === '') {
            throw new RuntimeException('academic_year, classroom_key i project_slug son obligatoris.');
        }

        $academicYearId = $this->lookupService->academicYearId($academicYear);
        $projectAcademicYearId = $this->lookupService->projectAcademicYearId($academicYear, $projectSlug);
        $classroom = $this->lookupService->findOrCreateClassroom($academicYearId, $projectAcademicYearId, $classroomKey, '', '', '');

        return $this->lookupService->upsertProjectLink((int) $classroom['id'], $projectAcademicYearId, $isActive);
    }
}
