<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Включение/выключение модуля. Права проверяет `ToggleModule`. */
class ToggleModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['is_enabled' => ['required', 'boolean']];
    }

    public function isEnabled(): bool
    {
        return $this->boolean('is_enabled');
    }
}
