<?php

namespace App\Models;

use App\Support\Mask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'guard', 'identifier_masked', 'subject_id', 'ip_address', 'user_agent'];

    public static function record(string $type, ?string $guard = null, ?string $identifier = null, ?int $subjectId = null): self
    {
        $request = request();

        return self::create([
            'type' => $type,
            'guard' => $guard,
            'identifier_masked' => Mask::identifier($identifier),
            'subject_id' => $subjectId,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }
}
