<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Google account a practice connected to store copies of patient files in its own Drive.
 *
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $quota_checked_at
 */
class DriveAccount extends Model
{
    use BelongsToOrganization;

    public const ACTIVE = 'active';

    /** Google refused the saved permission, so the practice must connect the account again. */
    public const NEEDS_RECONNECT = 'reconnect';

    public const DISCONNECTED = 'disconnected';

    /** Room kept free in a Drive, so its owner's own files still fit. */
    public const RESERVE_BYTES = 200 * 1024 * 1024;

    protected $fillable = ['organization_id', 'google_email', 'refresh_token', 'access_token', 'token_expires_at', 'folder_id', 'quota_total', 'quota_used', 'quota_checked_at', 'status', 'last_error', 'connected_by'];

    protected $hidden = ['refresh_token', 'access_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'quota_checked_at' => 'datetime',
            'quota_total' => 'integer',
            'quota_used' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /**
     * Space left for new files, or null when Google sets no limit.
     */
    public function freeBytes(): ?int
    {
        return $this->quota_total === null ? null : max(0, $this->quota_total - $this->quota_used - self::RESERVE_BYTES);
    }

    public function hasRoomFor(int $bytes): bool
    {
        $free = $this->freeBytes();

        return $free === null || $free >= $bytes;
    }

    /**
     * Number::fileSize needs the intl extension, which shared hosting may not have.
     */
    public static function readableSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::ACTIVE => 'Connected',
            self::NEEDS_RECONNECT => 'Connect again',
            default => 'Disconnected',
        };
    }
}
