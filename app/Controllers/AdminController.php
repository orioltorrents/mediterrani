<?php

class AdminController
{
    public function dashboard(): string
    {
        $pdo = $this->pdo();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($pdo);
            header('Location: ' . url('admin'));
            exit;
        }

        $dashboardData = (new AdminDashboardService(
            $pdo,
            new AdminDashboardUserService($pdo),
            new AdminDashboardProjectService($pdo),
            new AdminDashboardClassroomService($pdo),
            new AdminDashboardAssessmentService($pdo),
        ))->dashboardData();

        $message = $_SESSION['admin_message'] ?? null;
        $messageType = $_SESSION['admin_message_type'] ?? 'success';
        $importSummary = $_SESSION['admin_import_summary'] ?? null;
        $resetLink = $_SESSION['admin_reset_link'] ?? null;

        if ($message !== null) {
            unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);
        }

        if ($importSummary !== null) {
            unset($_SESSION['admin_import_summary']);
        }

        if ($resetLink !== null) {
            unset($_SESSION['admin_reset_link']);
        }

        return view('admin.dashboard', $dashboardData + [
            'title' => 'Dashboard administració',
            'message' => $message,
            'messageType' => $messageType,
            'importSummary' => $importSummary,
            'resetLink' => $resetLink,
            'csrfToken' => (new AuthService())->csrfToken(),
        ]);
    }

    private function handlePost(PDO $pdo): void
    {
        $action = (string) ($_POST['action'] ?? '');
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        $authService = new AuthService();
        $adminActionService = new AdminActionService($pdo);

        if (!$authService->verifyCsrfToken($csrfToken)) {
            $this->setMessage('La sessió del formulari ha caducat. Torna-ho a provar.', 'error');
            $adminActionService->auditAdminAction('csrf_failed', ['action' => $action]);
            return;
        }

        $result = $adminActionService->handle($action, $_POST, $_FILES);
        if (!empty($result['summary'])) {
            $_SESSION['admin_import_summary'] = $result['summary'];
        }

        if (!empty($result['reset_link'])) {
            $_SESSION['admin_reset_link'] = $result['reset_link'];
        }

        $this->setMessage((string) $result['message'], (string) $result['type']);
    }

    private function setMessage(string $message, string $type): void
    {
        $_SESSION['admin_message'] = $message;
        $_SESSION['admin_message_type'] = $type;
    }

    private function pdo(): PDO
    {
        return require dirname(__DIR__, 2) . '/config/database.php';
    }
}
