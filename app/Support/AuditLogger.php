<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        User $user,
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        AuditLog::query()->create([
            'shop_id' => $user->shop_id,
            'user_id' => $user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'before' => $before,
            'after' => $after,
            'ip' => request()->ip(),
        ]);
    }
}
