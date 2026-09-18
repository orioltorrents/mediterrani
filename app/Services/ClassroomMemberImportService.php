<?php

declare(strict_types=1);

class ClassroomMemberImportService
{
    public function __construct(
        private PDO $pdo,
        private ClassroomCsvImportService $csvImportService,
        private ClassroomLookupService $lookupService,
    ) {
    }

    public function importMembersUploadedFile(array $file): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            return AppHelper::result('No s\'ha rebut cap fitxer CSV de membres de Classroom.', 'error');
        }

        try {
            $csv = $this->csvImportService->readCsvWithHeaders((string) $file['tmp_name']);
            $this->csvImportService->validateHeaders($csv['headers'], ['academic_year', 'classroom_key', 'email'], 'classroom_members');
        } catch (Throwable $throwable) {
            return AppHelper::result('No s\'ha pogut llegir el CSV: ' . $throwable->getMessage(), 'error');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $warnings = [];

        foreach ($csv['rows'] as $rowNumber => $row) {
            try {
                $result = $this->importMemberRow($row);
                if ($result['created'] === true) {
                    $created++;
                } else {
                    $updated++;
                }

                foreach ($result['warnings'] as $warning) {
                    $warnings[] = 'Fila ' . $rowNumber . ': ' . $warning;
                }
            } catch (Throwable $throwable) {
                $errors[] = 'Fila ' . $rowNumber . ': ' . $throwable->getMessage();
            }
        }

        $message = 'Importació de membres Classroom: ' . $created . ' creats i ' . $updated . ' actualitzats.';
        if ($warnings !== []) {
            $message .= ' Avisos: ' . implode(' | ', array_slice($warnings, 0, 5));
        }

        if ($errors !== []) {
            $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
            return AppHelper::result($message, 'error');
        }

        return AppHelper::result($message, 'success');
    }

    private function importMemberRow(array $row): array
    {
        $academicYear = trim((string) ($row['academic_year'] ?? ''));
        $projectSlug = trim((string) ($row['project_slug'] ?? ''));
        $classroomKey = trim((string) ($row['classroom_key'] ?? ''));
        $classroomName = trim((string) ($row['classroom_name'] ?? ''));
        $classroomUrl = trim((string) ($row['classroom_url'] ?? ''));
        $googleClassroomId = trim((string) ($row['google_classroom_id'] ?? ''));
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $surname = trim((string) ($row['surname'] ?? ''));
        $googleUserId = trim((string) ($row['google_user_id'] ?? ''));
        $googlePhotoUrl = trim((string) ($row['google_photo_url'] ?? ''));

        if ($academicYear === '' || $classroomKey === '' || $email === '') {
            throw new RuntimeException('academic_year, classroom_key i email son obligatoris.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('email no vàlid: ' . $email);
        }

        $academicYearId = $this->lookupService->academicYearId($academicYear);
        $projectAcademicYearId = $projectSlug !== '' ? $this->lookupService->projectAcademicYearId($academicYear, $projectSlug) : null;
        $classroom = $this->lookupService->findOrCreateClassroom($academicYearId, $projectAcademicYearId, $classroomKey, $classroomName, $classroomUrl, $googleClassroomId);
        $user = $this->lookupService->userByEmail($email);
        $warnings = [];

        if ($projectAcademicYearId !== null) {
            $this->lookupService->upsertProjectLink((int) $classroom['id'], $projectAcademicYearId, true);
        }

        if ($googleClassroomId !== '' && trim((string) ($classroom['google_classroom_id'] ?? '')) !== '' && $googleClassroomId !== trim((string) $classroom['google_classroom_id'])) {
            $warnings[] = 'google_classroom_id no coincideix amb el Classroom configurat.';
        }

        if (!$this->lookupService->userHasRole((int) $user['id'], 'student')) {
            $warnings[] = 'l\'usuari existeix però no té rol student.';
        }

        if ($name !== '' && $this->csvImportService->normalizeText($name) !== $this->csvImportService->normalizeText((string) ($user['name'] ?? ''))) {
            $warnings[] = 'el name del CSV no coincideix amb users.name.';
        }

        if ($surname !== '' && $this->csvImportService->normalizeText($surname) !== $this->csvImportService->normalizeText((string) ($user['surname'] ?? ''))) {
            $warnings[] = 'el surname del CSV no coincideix amb users.surname.';
        }

        $membershipExists = $this->lookupService->classroomMembershipExists((int) $classroom['id'], (int) $user['id']);
        $stmt = $this->pdo->prepare(
            'INSERT INTO classroom_members
                (classroom_id, user_id, google_user_id, google_photo_url, classroom_group, external_group_id, is_active)
             VALUES
                (:classroom_id, :user_id, :google_user_id, :google_photo_url, NULL, NULL, 1)
             ON DUPLICATE KEY UPDATE
                 google_user_id = VALUES(google_user_id),
                google_photo_url = VALUES(google_photo_url),
                classroom_group = NULL,
                external_group_id = NULL,
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'classroom_id' => (int) $classroom['id'],
            'user_id' => (int) $user['id'],
            'google_user_id' => $googleUserId !== '' ? $googleUserId : null,
            'google_photo_url' => $googlePhotoUrl !== '' ? $googlePhotoUrl : null,
        ]);

        return [
            'created' => !$membershipExists,
            'warnings' => $warnings,
        ];
    }
}
