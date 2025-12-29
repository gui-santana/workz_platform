<?php
// src/Controllers/AppBuilderController.php
// DEPRECATED: mantido apenas para compatibilidade de rotas.
// Toda a lógica real vive em UniversalAppController.

namespace Workz\Platform\Controllers;

class AppBuilderController
{
    /**
     * GET /api/apps/{id}/build-status
     * @deprecated Use UniversalAppController::getBuildStatus
     */
    public function getBuildStatus(object $auth, int $appId): void
    {
        (new UniversalAppController())->getBuildStatus($auth, $appId);
    }

    /**
     * PUT /api/apps/{id}
     * @deprecated Use UniversalAppController::updateApp
     */
    public function updateApp(object $auth, int $appId): void
    {
        (new UniversalAppController())->updateApp($auth, $appId);
    }

    /**
     * POST /api/apps/{id}/rebuild
     * @deprecated Use UniversalAppController::rebuildApp
     */
    public function rebuildApp(object $auth, int $appId): void
    {
        (new UniversalAppController())->rebuildApp($auth, $appId);
    }

    /**
     * GET /api/apps/my-apps
     * @deprecated Use UniversalAppController::myApps
     */
    public function myApps(object $auth): void
    {
        (new UniversalAppController())->myApps($auth);
    }
}

