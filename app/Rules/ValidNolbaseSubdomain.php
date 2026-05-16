<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidNolbaseSubdomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private const RESERVED = [
        'www', 'app', 'api', 'mail', 'ftp', 'smtp', 'pop', 'imap',
        'admin', 'nolbase', 'help', 'status', 'support', 'billing',
        'dashboard', 'login', 'register', 'static', 'cdn', 'assets',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/', (string) $value)) {
            $fail('The subdomain must be 3–63 characters: lowercase letters, numbers, and hyphens only (no leading/trailing hyphens).');

            return;
        }

        if (in_array(strtolower($value), self::RESERVED, true)) {
            $fail("The subdomain \"{$value}\" is reserved and cannot be used.");
        }
    }
}
