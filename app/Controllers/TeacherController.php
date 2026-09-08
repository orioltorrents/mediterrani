<?php

declare(strict_types=1);

class TeacherController
{
    public function __construct(
        private AuthService $authService,
        private ProjectAssignmentService $projectAssignmentService
    ) {
    }

    public function dashboard(): string
    {
        $user = $this->authService->user();

        return view('teachers.dashboard', [
            'title' => 'Dashboard professorat',
            'user' => $user,
            'classes' => $this->projectAssignmentService->projectsForTeacher(
                (int) $user['id'],
                getLanguage()
            ),
        ]);
    }
}
