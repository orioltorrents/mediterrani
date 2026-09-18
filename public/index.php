<?php

require_once dirname(__DIR__) . '/app/Helpers/env.php';
require_once dirname(__DIR__) . '/app/Helpers/session.php';
require_once dirname(__DIR__) . '/app/Helpers/lang.php';
require_once dirname(__DIR__) . '/app/Helpers/route.php';
require_once dirname(__DIR__) . '/app/Helpers/view.php';
require_once dirname(__DIR__) . '/app/Helpers/AppHelper.php';

if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

require_once dirname(__DIR__) . '/app/Support/Router.php';
require_once dirname(__DIR__) . '/app/Services/AuthService.php';
require_once dirname(__DIR__) . '/app/Services/ProjectAssetService.php';
require_once dirname(__DIR__) . '/app/Services/ProjectAccessService.php';
require_once dirname(__DIR__) . '/app/Services/ProjectAssignmentService.php';
require_once dirname(__DIR__) . '/app/Services/ProjectService.php';
require_once dirname(__DIR__) . '/app/Services/AssessmentService.php';
require_once dirname(__DIR__) . '/app/Services/AssessmentStructureImportService.php';
require_once dirname(__DIR__) . '/app/Services/AnalyticsService.php';
require_once dirname(__DIR__) . '/app/Services/AdminActionService.php';
require_once dirname(__DIR__) . '/app/Services/AdminAssessmentStructureService.php';
require_once dirname(__DIR__) . '/app/Services/AdminClassService.php';
require_once dirname(__DIR__) . '/app/Services/AdminClassroomService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomStructureSnapshotProcessingService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomWebhookService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomCsvImportService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomLookupService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomMemberImportService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomTaskImportService.php';
require_once dirname(__DIR__) . '/app/Services/ClassroomImportService.php';
require_once dirname(__DIR__) . '/app/Services/AdminDashboardUserService.php';
require_once dirname(__DIR__) . '/app/Services/AdminDashboardProjectService.php';
require_once dirname(__DIR__) . '/app/Services/AdminDashboardClassroomService.php';
require_once dirname(__DIR__) . '/app/Services/AdminDashboardAssessmentService.php';
require_once dirname(__DIR__) . '/app/Services/AdminEvidenceService.php';
require_once dirname(__DIR__) . '/app/Services/AdminDashboardService.php';
require_once dirname(__DIR__) . '/app/Services/AdminObjectivesService.php';
require_once dirname(__DIR__) . '/app/Services/AdminTeamService.php';
require_once dirname(__DIR__) . '/app/Services/AdminProjectService.php';
require_once dirname(__DIR__) . '/app/Services/AdminStudentImportService.php';
require_once dirname(__DIR__) . '/app/Services/AdminUserService.php';
require_once dirname(__DIR__) . '/app/Services/UserAvatarService.php';
require_once dirname(__DIR__) . '/app/Services/DocumentImportService.php';
require_once dirname(__DIR__) . '/app/Services/DocumentService.php';
require_once dirname(__DIR__) . '/app/Services/LogService.php';
require_once dirname(__DIR__) . '/app/Services/ProjectSectionService.php';
require_once dirname(__DIR__) . '/app/Services/SitePageService.php';
require_once dirname(__DIR__) . '/app/Controllers/PublicController.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/Controllers/StudentController.php';
require_once dirname(__DIR__) . '/app/Controllers/TeacherController.php';
require_once dirname(__DIR__) . '/app/Controllers/AdminController.php';
require_once dirname(__DIR__) . '/app/Controllers/ApiClassroomController.php';
require_once dirname(__DIR__) . '/app/Controllers/DocumentSyncController.php';

$authService = new AuthService();
$projectAccessService = new ProjectAccessService();
$projectAssignmentService = new ProjectAssignmentService();
$projectService = new ProjectService();
$assessmentService = new AssessmentService();
$analyticsService = new AnalyticsService();
$documentImportService = new DocumentImportService();
$documentService = new DocumentService();
$projectSectionService = new ProjectSectionService();
$sitePageService = new SitePageService();
$controller = new PublicController($projectService, $authService, $assessmentService, $documentService, $projectSectionService, $projectAccessService, $sitePageService);
$authController = new AuthController($authService);
$studentController = new StudentController($authService, $projectAssignmentService);
$teacherController = new TeacherController($authService, $projectAssignmentService);
$adminController = new AdminController();
$apiClassroomController = new ApiClassroomController(new ClassroomWebhookService(require dirname(__DIR__) . '/config/database.php'));
$documentSyncController = new DocumentSyncController($authService, $documentImportService);

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$basePath = appBasePath();

if ($basePath !== '' && str_starts_with($requestUri, $basePath)) {
    $requestUri = substr($requestUri, strlen($basePath));
}

$requestUri = '/' . trim($requestUri, '/');
$projectAcademicYearId = isset($_GET['edicio']) ? max(0, (int) $_GET['edicio']) : null;
$currentUser = $authService->user();
$analyticsService->recordVisit($requestUri, $_SERVER, $currentUser['id'] ?? null);

if ($authService->mustChangePassword() && !in_array($requestUri, ['/canviar-contrasenya', '/logout'], true)) {
    header('Location: ' . url('canviar-contrasenya'));
    exit;
}

$router = new Router();
$languageProjectConstraints = ['lang' => implode('|', supportedLanguages()), 'slug' => '[a-z0-9-]+'];

$router->get('/', static fn (): string => $controller->home());
$router->get('/{lang}', static function (array $params) use ($controller): string {
    setLanguage((string) $params['lang'], true);

    return $controller->home();
}, ['lang' => implode('|', supportedLanguages())]);
$router->get('/{lang}/que-es-entorns', static function (array $params) use ($controller): string {
    setLanguage((string) $params['lang'], true);

    return $controller->about();
}, ['lang' => implode('|', supportedLanguages())]);

foreach (['/projectes', '/ca/projectes', '/es/projectes', '/en/projectes', '/fr/projectes'] as $projectsRoute) {
    $router->get($projectsRoute, static function () use ($controller, $projectsRoute): string {
        $routeSegments = explode('/', trim($projectsRoute, '/'));
        $routeLanguage = (string) ($routeSegments[0] ?? '');
        if (in_array($routeLanguage, supportedLanguages(), true)) {
            setLanguage($routeLanguage, true);
        }

        return $controller->projects();
    });
}

$router->any('/login', static fn (): string => $authController->login());
$router->any('/logout', static function () use ($authController): void {
    $authController->logout();
});
$router->any('/canviar-contrasenya', static fn (): string => $authController->changePassword());

$router->post('/api/classroom/webhook', static fn (): string => $apiClassroomController->webhook());

$router->get('/dashboard', static function () use ($authService, $authController): void {
    if (!$authService->check()) {
        header('Location: ' . url('login'));
        exit;
    }

    $authController->redirectToDashboard();
});

$router->any('/alumne', static function () use ($authService, $studentController): string {
    $authService->requireRole('student');
    $authService->requirePasswordChangeCompleted();

    $user = $authService->user();
    (new LogService())->write('access=dashboard_student user_id=' . ((int) ($user['id'] ?? 0)));

    return $studentController->dashboard();
});

$router->any('/professor', static function () use ($authService, $teacherController): string {
    $authService->requireRole('teacher');
    $authService->requirePasswordChangeCompleted();

    $user = $authService->user();
    (new LogService())->write('access=dashboard_teacher user_id=' . ((int) ($user['id'] ?? 0)));

    return $teacherController->dashboard();
});

$router->any('/admin', static function () use ($authService, $adminController): string {
    $authService->requireRole('admin');
    $authService->requirePasswordChangeCompleted();

    $user = $authService->user();
    (new LogService())->write('access=dashboard_admin user_id=' . ((int) ($user['id'] ?? 0)));

    return $adminController->dashboard();
});

$router->get('/admin/impersonate-student', static function (): void {
    header('Location: ' . url('admin'));
    exit;
});
$router->post('/admin/impersonate-student', static function () use ($authService): void {
    $authService->requireActorRole('admin');

    $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $actor = $authService->actorUser();
    $actorId = $actor !== null ? (int) ($actor['id'] ?? 0) : 0;

    if ($studentId > 0 && $authService->verifyCsrfToken($csrfToken) && $authService->impersonateStudent($studentId)) {
        (new LogService())->write('admin_action=impersonate_student actor_id=' . $actorId . ' target_student_id=' . $studentId);
        header('Location: ' . url('alumne'));
        exit;
    }

    (new LogService())->write('admin_action=impersonate_student_failed actor_id=' . $actorId . ' target_student_id=' . $studentId);
    $_SESSION['admin_message'] = 'No s’ha pogut activar la vista com alumne.';
    $_SESSION['admin_message_type'] = 'error';
    header('Location: ' . url('admin'));
    exit;
});

$router->get('/admin/stop-impersonation', static function (): void {
    header('Location: ' . url('admin'));
    exit;
});
$router->post('/admin/stop-impersonation', static function () use ($authService): void {
    $authService->requireActorRole('admin');

    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $actor = $authService->actorUser();
    $actorId = $actor !== null ? (int) ($actor['id'] ?? 0) : 0;

    if ($authService->verifyCsrfToken($csrfToken)) {
        $targetId = isset($_SESSION['impersonation']['target']['id']) ? (int) $_SESSION['impersonation']['target']['id'] : 0;
        $authService->stopImpersonating();
        (new LogService())->write('admin_action=stop_impersonation actor_id=' . $actorId . ' target_student_id=' . $targetId);
    } else {
        (new LogService())->write('admin_action=stop_impersonation_csrf_failed actor_id=' . $actorId);
    }

    header('Location: ' . url('admin'));
    exit;
});

$router->get('/admin/sync-documents', static fn (): string => $documentSyncController->index());
$router->post('/admin/sync-documents', static fn (): string => $documentSyncController->store());

$router->get('/user-avatar/{id}', static function (array $params) use ($authService): void {
    $authService->requireRole('admin');
    $authService->requirePasswordChangeCompleted();

    $userId = isset($params['id']) ? (int) $params['id'] : 0;
    if ($userId <= 0) {
        http_response_code(404);
        echo 'Foto no trobada';
        return;
    }

    (new UserAvatarService(require dirname(__DIR__) . '/config/database.php'))->avatarResponse($userId);
}, ['id' => '\\d+']);

$router->get('/{lang}/projectes/{slug}/tasques', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectTasks((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}/notes', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectNotes((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}/documents', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectDocuments((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}/grups', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectGroups((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}/alumnes', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);
    return $controller->projectStudents((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}/objectius-aprenentatge', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectObjectives((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);
$router->get('/{lang}/projectes/{slug}', static function (array $params) use ($controller, $projectAcademicYearId): string {
    setLanguage((string) $params['lang'], true);

    return $controller->projectDetail((string) $params['slug'], $projectAcademicYearId);
}, $languageProjectConstraints);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $requestUri);
