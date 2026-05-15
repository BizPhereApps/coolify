<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaystackEvent extends Model
{
    protected $fillable = [
        'event_type',
        'paystack_reference',
        'paystack_event_id',
        'payload',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
