<?php

namespace App\Core\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'remember_token',
        'session_id',
    ];

    public function record(
        string $event,
        Request $request,
        ?User $user = null,
        ?int $gymId = null,
        ?Model $auditable = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'gym_id' => $gymId,
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true))
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->sanitize($value) : $value)
            ->all();
    }
}
