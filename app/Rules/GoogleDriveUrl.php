<?php

namespace App\Rules;

use App\Support\DriveLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleDriveUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || DriveLink::parse($value) === null) {
            $fail('Use a Google Drive file or folder link (https://drive.google.com/...).');
        }
    }
}
