<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class BrandRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)
                    ->dimensions(Rule::dimensions()->maxWidth(4000)->maxHeight(4000)),
            ],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Choose a valid colour.',
            'logo.max' => 'The logo may not be larger than 2 MB.',
        ];
    }
}
