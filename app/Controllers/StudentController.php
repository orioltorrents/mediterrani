<?php

declare(strict_types=1);

class StudentController
{
    public function __construct(
        private AuthService $authService,
        private ProjectAssignmentService $projectAssignmentService
    ) {
    }

    public function dashboard(): string
    {
        $user = $this->authService->user();

        return view('students.dashboard', [
            'title' => 'Dashboard alumne',
            'user' => $user,
            'classes' => $this->projectAssignmentService->projectsForStudent(
                (int) $user['id'],
                getLanguage()
            ),
        ]);
    }
}
