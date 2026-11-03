<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Bcrypt compares at most 72 bytes; reject truncation, including multibyte passwords. */
class PasswordByteLength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 72) $fail('The :attribute must not exceed 72 bytes.');
    }
}
