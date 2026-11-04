<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $beat_at
 */
class SystemHeartbeat extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    protected $fillable = ['name', 'beat_at'];

    protected function casts(): array
    {
        return ['beat_at' => 'datetime'];
    }

    public static function beat(string $name): void
    {
        self::query()->updateOrCreate(['name' => $name], ['beat_at' => now()]);
    }
}
