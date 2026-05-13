<?php

// Worktree-local bootstrap: vendor symlinked autoload points to main project paths.
// Pre-require modified files so PHP keeps the worktree version (autoloader won't override).

require_once __DIR__ . '/../vendor/autoload.php';

$worktreeOverrides = [
    __DIR__ . '/../app/Models/Destination.php',
    __DIR__ . '/../app/Models/AiSearchLog.php',
    __DIR__ . '/../app/Http/Controllers/AiSearchController.php',
    __DIR__ . '/../app/Services/KnowledgeService.php',
    __DIR__ . '/../app/Services/DestinationResearcher.php',
    __DIR__ . '/../app/Jobs/ResearchDestinationJob.php',
    __DIR__ . '/../app/Observers/TourObserver.php',
    __DIR__ . '/../app/Console/Commands/ResearchDestinations.php',
];

foreach ($worktreeOverrides as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}
