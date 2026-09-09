<?php

declare(strict_types=1);

class ClassroomImportService
{
    public function __construct(
        private PDO $pdo,
        private ClassroomCsvImportService $csv,
        private ClassroomLookupService $lookup,
    ) {
    }

    public function importUploadedFile(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return AppHelper::result('No s’ha rebut cap fitxer CSV de Classrooms.', 'error');
        }

        try {
            $data = $this->csv->readCsvWithHeaders((string) $file['tmp_name']);
            $this->csv->validateHeaders($data['headers'], [
                'project_academic_years.id',
                'classrooms.classroom_key',
                'classrooms.classroom_name',
            ], 'classrooms');
        } catch (Throwable $throwable) {
            return AppHelper::result('No s’ha pogut llegir el CSV de Classrooms: ' . $throwable->getMessage(), 'error');
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($data['rows'] as $rowNumber => $row) {
            try {
                $projectAcademicYearId = filter_var($row['project_academic_years.id'] ?? null, FILTER_VALIDATE_INT);
                $key = trim((string) ($row['classrooms.classroom_key'] ?? ''));
                $name = trim((string) ($row['classrooms.classroom_name'] ?? ''));
                $url = trim((string) ($row['classrooms.classroom_url'] ?? ''));
                $googleId = trim((string) ($row['classrooms.google_classroom_id'] ?? ''));

                if ($projectAcademicYearId === false || $projectAcademicYearId === null || $projectAcademicYearId <= 0 || $key === '' || $name === '') {
                    throw new RuntimeException('project_academic_years.id, classrooms.classroom_key i classrooms.classroom_name són obligatoris.');
                }

                $context = $this->pdo->prepare('SELECT academic_year_id FROM project_academic_years WHERE id = :id LIMIT 1');
                $context->execute(['id' => (int) $projectAcademicYearId]);
                $academicYearId = $context->fetchColumn();
                if ($academicYearId === false) {
                    throw new RuntimeException('No existeix l’edició de projecte indicada.');
                }

                $existing = $this->pdo->prepare('SELECT id FROM classrooms WHERE academic_year_id = :academic_year_id AND classroom_key = :classroom_key LIMIT 1');
                $existing->execute(['academic_year_id' => (int) $academicYearId, 'classroom_key' => $key]);
                $wasExisting = $existing->fetchColumn() !== false;

                $this->lookup->findOrCreateClassroom((int) $academicYearId, (int) $projectAcademicYearId, $key, $name, $url, $googleId);
                $wasExisting ? $updated++ : $created++;
            } catch (Throwable $throwable) {
                $errors[] = 'Fila ' . $rowNumber . ': ' . $throwable->getMessage();
            }
        }

        $message = 'Importació de Classrooms: ' . $created . ' creats i ' . $updated . ' actualitzats.';
        if ($errors !== []) {
            return AppHelper::result($message . ' Errors: ' . implode(' | ', $errors), 'error');
        }

        return AppHelper::result($message, 'success');
    }
}
