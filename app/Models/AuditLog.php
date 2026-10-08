<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\AdminAuth;
use App\Core\Database;

final class AuditLog
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function recordAction(string $action, string $entityType, ?int $entityId = null, array $meta = []): void
    {
        $adminId = AdminAuth::id();
        if ($adminId === null) {
            return;
        }
        self::record(
            $adminId,
            $action,
            $entityType,
            $entityId,
            $meta !== [] ? json_encode($meta) : null
        );
    }

    public static function record(int $adminId, string $action, string $entityType, ?int $entityId = null, ?string $meta = null): void
    {
        Database::run(
            'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, meta) VALUES (?, ?, ?, ?, ?)',
            [$adminId, $action, $entityType, $entityId, $meta]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 50): array
    {
        return Database::all(
            'SELECT a.*, u.name AS admin_name FROM audit_logs a JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC LIMIT ' . (int) $limit
        );
    }
}
