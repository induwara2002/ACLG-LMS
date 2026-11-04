<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class AuditLogService
{
    private const SECRET_FIELD = '/password|secret|token|credential|authorization|cookie|api.?key|app.?key/i';

    private const PRIVATE_FIELD = '/(^|_)(name|full_name|email|nic|phone|address|reference|notes|description)($|_)/i';

    public function record(
        string $action,
        ?User $user = null,
        ?Model $auditable = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        try {
            $request = request();

            return AuditLog::create([
                'user_id' => ($user ?? Auth::user())?->getAuthIdentifier(),
                'action' => substr($action, 0, 100),
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'description' => $this->sanitizeText($description),
                'old_values' => $this->sanitize($oldValues),
                'new_values' => $this->sanitize($newValues),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 512) : null,
            ]);
        } catch (Throwable) {
            throw new RuntimeException('The audit record could not be saved.');
        }
    }

    private function sanitize(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (preg_match(self::SECRET_FIELD, $key) || in_array($key, ['id', 'created_at', 'updated_at', 'remember_token'], true)) {
                continue;
            }

            if (preg_match(self::PRIVATE_FIELD, $key)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
                continue;
            }

            if (is_string($value)) {
                $safe[$key] = $this->sanitizeText($value, 2000);
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    private function sanitizeText(?string $value, int $limit = 1000): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace(
            '/\b((?:admin_)?password|app_key|db_password|(?:access|refresh)_token)\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $value
        );
        $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[REDACTED]', $value);
        $value = preg_replace('/(?<!\d)\+?\d[\d\s().-]{7,}\d(?!\d)/', '[REDACTED]', $value);

        return substr($value, 0, $limit);
    }
}