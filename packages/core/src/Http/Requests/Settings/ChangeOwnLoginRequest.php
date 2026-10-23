<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Новый логин; нормализация и правила (`LoginRules`) — в `ChangeLogin`. */
class ChangeOwnLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:64'],
        ];
    }

    public function login(): string
    {
        return (string) $this->validated('login');
    }
}
