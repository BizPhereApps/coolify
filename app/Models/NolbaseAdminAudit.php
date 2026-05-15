<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NolbaseAdminAudit extends Model
{
    public $timestamps = false;

    protected $table = 'nolbase_admin_audit';

    protected $fillable = [
        'nolbase_admin_id',
        'action',
        'target_type',
        'target_id',
        'payload',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(NolbaseAdmin::class, 'nolbase_admin_id');
    }

    public static function log(NolbaseAdmin $admin, string $action, ?Model $target = null, array $payload = [], ?string $ip = null): self
    {
        return self::create([
            'nolbase_admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'payload' => $payload ?: null,
            'ip' => $ip ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
