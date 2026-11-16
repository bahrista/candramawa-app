<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\UserRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use UserRules;

    protected function prepareForValidation(): void
    {
        $this->normaliseUsername();
    }

    public function rules(): array
    {
        $id = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => $this->usernameRules($id),
            'email' => $this->emailRules($id),
            'role' => ['required', Rule::in(User::ROLES)],
        ];
    }
}
