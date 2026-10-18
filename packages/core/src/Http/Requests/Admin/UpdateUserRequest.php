<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Models\User;

/** Профиль пользователя в админке. Авторизация — в `UpdateUserProfile` (политика `update`). */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target instanceof User ? $target->id : null),
            ],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'about' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string', 'max:1000'],
            'language' => ['required', 'string', Rule::in(array_keys((array) config('core.locales', [])))],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
