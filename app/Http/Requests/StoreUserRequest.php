<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\UserRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use UserRules;

    protected function prepareForValidation(): void
    {
        $this->normaliseUsername();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => $this->usernameRules(),
            'email' => $this->emailRules(),
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => $this->passwordRules(),
        ];
    }
}
