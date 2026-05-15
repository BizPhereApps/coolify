<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NolbaseSetting extends Model
{
    protected $table = 'nolbase_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'label',
        'description',
        'updated_by_admin_id',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(NolbaseAdmin::class, 'updated_by_admin_id');
    }

    public static function read(string $key, ?string $default = null): ?string
    {
        return self::find($key)?->value ?? $default;
    }

    public static function write(string $key, ?string $value, ?NolbaseAdmin $admin = null, ?string $label = null, ?string $description = null): self
    {
        return self::updateOrCreate(['key' => $key], array_filter([
            'value' => $value,
            'updated_by_admin_id' => $admin?->id,
            'label' => $label,
            'description' => $description,
        ], static fn ($v) => $v !== null));
    }
}
