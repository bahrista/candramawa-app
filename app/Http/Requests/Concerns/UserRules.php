<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rules\Password;

trait UserRules
{
    protected function usernameRules(?int $ignoreId = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:50',
            'regex:/^[a-z0-9._]+$/',
            'unique:users,username'.($ignoreId ? ','.$ignoreId : ''),
        ];
    }

    protected function emailRules(?int $ignoreId = null): array
    {
        return ['nullable', 'email', 'max:255', 'unique:users,email'.($ignoreId ? ','.$ignoreId : '')];
    }

    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(8)];
    }

    protected function normaliseUsername(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username may only contain lowercase letters, numbers, dots and underscores.',
        ];
    }
}
