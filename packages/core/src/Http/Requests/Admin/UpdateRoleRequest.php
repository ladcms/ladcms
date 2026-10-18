<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Models\Role;

/** Изменение роли; передаются только изменяемые поля. Правила для системных ролей проверяет `UpdateRole`. */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->getKey())],
            'level' => ['sometimes', 'nullable', 'integer', 'between:0,6', Rule::unique('roles', 'level')->ignore($role->getKey())],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
