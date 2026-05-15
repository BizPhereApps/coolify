<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'bank_code',
        'account_number_encrypted',
        'account_name',
        'paystack_recipient_code',
        'verified_at',
    ];

    protected $hidden = [
        'account_number_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'account_number_encrypted' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Last 4 digits of the account number for display, decrypting once.
     */
    public function maskedAccountNumber(): string
    {
        $number = $this->account_number_encrypted ?? '';

        return strlen($number) > 4 ? '••••'.substr($number, -4) : $number;
    }
}
