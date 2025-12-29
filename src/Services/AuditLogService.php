<?php

namespace Workz\Platform\Services;

use Workz\Platform\Core\Database;
use PDO;

class AuditLogService
{
    public function log(int $actorId, string $action, array $context = [], array $before = [], array $after = [], ?string $ip = null, ?string $ua = null): void
    {
        try {
            $pdo = Database::getInstance('workz_data');
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (actor_us, action, em, cm, ap, target_type, target_id, before_json, after_json, ip, ua)
                VALUES (:actor_us, :action, :em, :cm, :ap, :target_type, :target_id, :before_json, :after_json, :ip, :ua)
            ");

            $stmt->execute([
                ':actor_us' => $actorId,
                ':action' => $action,
                ':em' => $context['em'] ?? null,
                ':cm' => $context['cm'] ?? null,
                ':ap' => $context['ap'] ?? null,
                ':target_type' => $context['target_type'] ?? null,
                ':target_id' => $context['target_id'] ?? null,
                ':before_json' => $before ? json_encode($before) : null,
                ':after_json' => $after ? json_encode($after) : null,
                ':ip' => $ip,
                ':ua' => $ua,
            ]);
        } catch (\Throwable $e) {
            // Falha de auditoria não deve quebrar fluxo principal
            error_log('AuditLogService failure: ' . $e->getMessage());
        }
    }
}
