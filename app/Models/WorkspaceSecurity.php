<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSecurity extends Model
{
    protected $table = 'workspace_security';

    protected $guarded = ['id'];

    protected $hidden = ['member_pin_hash', 'admin_pin_configured_at'];

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public static function adminPinConfigured(): bool
    {
        return static::query()->whereKey(1)->whereNotNull('admin_pin_configured_at')->exists()
            || Member::query()->where('role', 'admin')->whereNotNull('pin_hash')->exists();
    }

    public static function adminCount(): int
    {
        return Member::query()->where('role', 'admin')->count();
    }

    public static function bootstrapSetupAvailable(): bool
    {
        return ! static::adminPinConfigured() && static::adminCount() === 0;
    }
}
