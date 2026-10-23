<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Ladcms\Core\Support\LoginRules;

/** Новый логин. Нормализация и правила (`LoginRules`) — в `ChangeLogin`. */
class ChangeLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:'.LoginRules::MAX_LENGTH],
        ];
    }
}
