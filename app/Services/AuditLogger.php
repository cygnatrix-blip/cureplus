<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PatientAccount;
use App\Models\PlatformAdmin;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SECRET_KEYS = ['password', 'secret', 'token', 'otp', 'code', 'key', 'recovery'];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?int $organizationId = null): AuditLog
    {
        // Portal routes set the default guard, so the person acting on this page wins.
        $actor = Auth::guard()->user() ?? Auth::guard('platform')->user() ?? Auth::guard('web')->user();
        /** @var Request $request */
        $request = request();

        return AuditLog::create([
            'organization_id' => $organizationId ?? $this->organizationIdFor($subject),
            'actor_type' => match (true) {
                $actor instanceof PlatformAdmin => 'platform_admin',
                $actor instanceof PatientAccount => 'patient',
                $actor !== null => 'user',
                default => null,
            },
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'impersonator_id' => $request->hasSession() ? $request->session()->get('impersonation.admin_id') : null,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $this->sanitize($metadata) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
    }

    private function organizationIdFor(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Organization => $subject->id,
            $subject !== null && isset($subject->organization_id) => (int) $subject->organization_id,
            default => $this->tenant->id(),
        };
    }

    /**
     * Last line of defence: a caller passing a secret by mistake must not persist it.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitize(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $metadata[$key] = $this->sanitize($value);
            } elseif (Str::contains(Str::lower($key), self::SECRET_KEYS)) {
                $metadata[$key] = '[redacted]';
            }
        }

        return $metadata;
    }
}
