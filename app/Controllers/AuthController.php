<?php

declare(strict_types=1);

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(): string
    {
        if ($this->authService->check()) {
            $this->redirectToDashboard();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleLogin();
        }

        return view('auth.login', [
            'title' => trans('login_title'),
            'csrfToken' => $this->authService->csrfToken(),
            'error' => null,
            'email' => '',
        ]);
    }

    public function logout(): void
    {
        $user = $this->authService->actorUser();
        $userId = $user !== null ? (int) $user['id'] : 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = (string) ($_POST['csrf_token'] ?? '');
            if (!$this->authService->verifyCsrfToken($csrfToken)) {
                (new LogService())->write('auth=logout_csrf_failed user_id=' . $userId);
                header('Location: ' . url(''));
                exit;
            }
        }

        $this->authService->logout();

        if ($userId > 0) {
            (new LogService())->write('auth=logout user_id=' . $userId);
        }

        header('Location: ' . url('login'));
        exit;
    }

    public function changePassword(): string
    {
        if (!$this->authService->check()) {
            header('Location: ' . url('login'));
            exit;
        }

        if (!$this->authService->mustChangePassword()) {
            $this->redirectToDashboard();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleChangePassword();
        }

        return view('auth.change-password', [
            'title' => 'Canviar contrasenya',
            'csrfToken' => $this->authService->csrfToken(),
            'error' => null,
        ]);
    }

    private function handleLogin(): string
    {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (!$this->authService->verifyCsrfToken($csrfToken)) {
            return $this->loginView($email, 'La sessio ha caducat. Torna-ho a provar.');
        }

        if ($this->authService->attemptLogin($email, $password)) {
            $this->redirectToDashboard();
        }

        $rateError = $this->authService->getLoginRateError();

        return $this->loginView($email, $rateError ?? 'Email o contrasenya incorrectes.');
    }

    private function loginView(string $email, string $error): string
    {
        return view('auth.login', [
            'title' => trans('login_title'),
            'csrfToken' => $this->authService->csrfToken(),
            'error' => $error,
            'email' => $email,
        ]);
    }

    private function handleChangePassword(): string
    {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (!$this->authService->verifyCsrfToken($csrfToken)) {
            return $this->changePasswordView('La sessió ha caducat. Torna-ho a provar.');
        }

        $error = $this->authService->changeRequiredPassword(
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['new_password_confirmation'] ?? '')
        );

        if ($error === null) {
            $this->redirectToDashboard();
        }

        return $this->changePasswordView($error);
    }

    private function changePasswordView(string $error): string
    {
        return view('auth.change-password', [
            'title' => 'Canviar contrasenya',
            'csrfToken' => $this->authService->csrfToken(),
            'error' => $error,
        ]);
    }

    public function redirectToDashboard(): void
    {
        $path = $this->authService->redirectPathForCurrentUser();

        header('Location: ' . url($path !== '' ? $path : 'login'));
        exit;
    }
}
