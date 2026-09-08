<?php

declare(strict_types=1);

class DocumentSyncController
{
    public function __construct(private AuthService $authService, private DocumentImportService $documentImportService)
    {
    }

    public function index(): string
    {
        $this->authService->requireRole('admin');

        return view('admin.document-sync', [
            'title' => 'Sincronització de documents',
            'result' => null,
            'error' => null,
            'jsonPayload' => '',
            'csrfToken' => $this->authService->csrfToken(),
        ]);
    }

    public function store(): string
    {
        $this->authService->requireRole('admin');

        $jsonPayload = $this->extractJsonPayload();
        $result = null;
        $error = null;

        if (!$this->authService->verifyCsrfToken($this->extractCsrfToken())) {
            $this->auditDocumentSync('csrf_failed');

            return view('admin.document-sync', [
                'title' => 'Sincronització de documents',
                'result' => null,
                'error' => 'La sessió del formulari ha caducat. Torna-ho a provar.',
                'jsonPayload' => $jsonPayload,
                'csrfToken' => $this->authService->csrfToken(),
            ]);
        }

        try {
            $payload = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('El JSON no té una estructura vàlida.');
            }

            $result = $this->documentImportService->importPayload($payload);
            $this->auditDocumentSync('import_documents', [
                'documents' => (int) ($result['documents_imported'] ?? 0),
                'sources' => (int) ($result['sources_imported'] ?? 0),
                'fragments' => (int) ($result['fragments_imported'] ?? 0),
                'rules' => (int) ($result['rules_imported'] ?? 0),
            ]);
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
            $this->auditDocumentSync('import_documents_failed', ['error' => $error]);
        }

        return view('admin.document-sync', [
            'title' => 'Sincronització de documents',
            'result' => $result,
            'error' => $error,
            'jsonPayload' => $jsonPayload,
            'csrfToken' => $this->authService->csrfToken(),
        ]);
    }

    private function extractCsrfToken(): string
    {
        return (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    private function auditDocumentSync(string $action, array $context = []): void
    {
        $actor = $this->authService->actorUser();
        $actorId = $actor !== null ? (int) ($actor['id'] ?? 0) : 0;
        $parts = [
            'admin_action=' . $action,
            'actor_id=' . $actorId,
        ];

        foreach ($context as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $key);
            $parts[] = $safeKey . '=' . str_replace(["\r", "\n"], ' ', (string) $value);
        }

        (new LogService())->write(implode(' ', $parts));
    }

    private function extractJsonPayload(): string
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if (is_string($rawInput) && trim($rawInput) !== '') {
                return $rawInput;
            }
        }

        return (string) ($_POST['json_payload'] ?? '');
    }
}
