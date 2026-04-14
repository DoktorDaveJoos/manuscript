<?php

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| App Guardrails
|--------------------------------------------------------------------------
|
| This app has NO authentication or authorization. These tests enforce
| two rules that must hold for every change:
|
|   1. No new auth/authz calls land in app/ or routes/ (outside the
|      grandfathered allowlist below).
|   2. Every controller in app/Http/Controllers/ has a matching
|      Feature test file (FooController -> tests/Feature/FooControllerTest.php),
|      except the grandfathered set whose tests are named differently.
|
| If these fail, see CLAUDE.md ## Guardrails.
|
*/

it('has no forbidden auth calls outside the allowlist', function () {
    $bannedPatterns = [
        'auth\(\)->user\(' => 'auth()->user()',
        'auth\(\)->check\(' => 'auth()->check()',
        'auth\(\)->guest\(' => 'auth()->guest()',
        '\bAuth::' => 'Auth:: facade',
        "->middleware\(['\"]auth\\b" => "->middleware('auth')",
        "middleware\(\[\s*['\"]auth\\b" => "middleware(['auth', ...])",
        '\bGate::define' => 'Gate::define',
        '\$this->authorize\(' => '$this->authorize()',
    ];

    $allowlist = [
        'app/Http/Controllers/AiController.php',
        'app/Http/Controllers/EditorialReviewController.php',
        'app/Http/Controllers/Concerns/StreamsConversation.php',
        'app/Http/Middleware/HandleInertiaRequests.php',
    ];

    $violations = [];
    $finder = (new Finder)
        ->files()
        ->in([base_path('app'), base_path('routes')])
        ->name('*.php');

    foreach ($finder as $file) {
        $relative = str_replace(base_path().'/', '', $file->getRealPath());

        if (in_array($relative, $allowlist, true)) {
            continue;
        }

        $isFormRequest = str_starts_with($relative, 'app/Http/Requests/');
        $content = $file->getContents();

        foreach ($bannedPatterns as $pattern => $label) {
            if ($isFormRequest && $label === '$this->authorize()') {
                continue;
            }

            if (preg_match('/'.$pattern.'/', $content)) {
                $violations[] = "{$relative}: {$label}";
            }
        }
    }

    expect($violations)->toBeEmpty(
        "This app has NO users. Do not add auth/authz checks.\n".
        "Scope data by domain FK (book_id, chapter_id), not by user.\n".
        "Violations:\n  ".implode("\n  ", $violations)
    );
});

it('has a Feature test file for every controller', function () {
    $grandfathered = [
        'AiConversationController',
        'AiDashboardController',
        'CanvasController',
        'SearchController',
        'SettingsController',
        'WikiController',
        'WikiPanelController',
    ];

    $missing = [];
    $finder = (new Finder)
        ->files()
        ->in(base_path('app/Http/Controllers'))
        ->name('*Controller.php')
        ->notName('Controller.php');

    foreach ($finder as $file) {
        $name = $file->getBasename('.php');

        if (in_array($name, $grandfathered, true)) {
            continue;
        }

        $testPath = base_path("tests/Feature/{$name}Test.php");

        if (! file_exists($testPath)) {
            $missing[] = "tests/Feature/{$name}Test.php";
        }
    }

    expect($missing)->toBeEmpty(
        "Every new controller must have a matching Feature test.\n".
        'Convention: App\\Http\\Controllers\\FooController '.
        "=> tests/Feature/FooControllerTest.php.\n".
        "Missing:\n  ".implode("\n  ", $missing)
    );
});
