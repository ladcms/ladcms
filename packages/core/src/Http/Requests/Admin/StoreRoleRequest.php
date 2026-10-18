<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Новая роль. Ранговые ограничения проверяет `CreateRole`. */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'level' => ['nullable', 'integer', 'between:0,6', Rule::unique('roles', 'level')],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Типизированные данные: форма шлёт строки ("1", "2", "").
     *
     * @return array{name?: string, level?: int|null, color?: string|null, is_default?: bool}
     */
    public function roleData(): array
    {
        $data = [];
        if ($this->has('name')) {
            $data['name'] = (string) $this->validated('name');
        }
        if ($this->has('level')) {
            $level = $this->validated('level');
            $data['level'] = $level === null || $level === '' ? null : (int) $level;
        }
        if ($this->has('color')) {
            $color = $this->validated('color');
            $data['color'] = $color === null || $color === '' ? null : (string) $color;
        }
        if ($this->has('is_default')) {
            $data['is_default'] = $this->boolean('is_default');
        }

        return $data;
    }
}
