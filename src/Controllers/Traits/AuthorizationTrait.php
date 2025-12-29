<?php

namespace Workz\Platform\Controllers\Traits;

use Workz\Platform\Services\AuthorizationService;
use Workz\Platform\Services\AuthorizationResult;

trait AuthorizationTrait
{
    protected ?AuthorizationService $authzService = null;

    protected function getAuthorizationService(): AuthorizationService
    {
        if ($this->authzService === null) {
            $this->authzService = new AuthorizationService();
        }
        return $this->authzService;
    }

    protected function currentUserFromPayload(?object $payload): array
    {
        return [
            'id' => isset($payload->sub) ? (int)$payload->sub : 0,
            'sub' => isset($payload->sub) ? (int)$payload->sub : 0,
        ];
    }

    /**
        * Envolve AuthorizationService e finaliza a resposta em caso de negativa.
        */
    protected function sanitizeAuthReason(string $reason): string
    {
        if ($reason === '') {
            return 'forbidden';
        }
        if (str_starts_with($reason, 'app.not_')) {
            return 'no_entitlement';
        }
        if (str_contains($reason, 'policy_denied')) {
            return 'insufficient_role';
        }
        if (str_contains($reason, 'context_required') || str_contains($reason, 'inactive') || $reason === 'unauthenticated') {
            return 'forbidden';
        }
        return 'forbidden';
    }

    protected function authorize(string $action, array $ctx = [], ?object $payload = null): AuthorizationResult
    {
        $result = $this->getAuthorizationService()->can($this->currentUserFromPayload($payload), $action, $ctx);
        if (!$result->allowed) {
            if (property_exists($this, 'authzDenyLogContext') && is_array($this->authzDenyLogContext)) {
                error_log('general_crud_forbidden: ' . json_encode($this->authzDenyLogContext));
                $this->authzDenyLogContext = null;
            }
            http_response_code(403);
            header("Content-Type: application/json");
            $reason = $this->sanitizeAuthReason($result->reason);
            echo json_encode([
                'status' => 'error',
                'error' => 'forbidden',
                'reason' => $reason,
            ]);
            exit();
        }
        if (property_exists($this, 'authzDenyLogContext')) {
            $this->authzDenyLogContext = null;
        }
        return $result;
    }
}
