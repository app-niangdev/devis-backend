<?php

namespace App\Rules;

use App\Support\SenegalPhone as Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SenegalPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!Phone::isValid(Phone::clean((string) $value))) {
            $fail('Le numéro doit comporter 9 chiffres et commencer par 70, 71, 75, 76, 77 ou 78.');
        }
    }
}
