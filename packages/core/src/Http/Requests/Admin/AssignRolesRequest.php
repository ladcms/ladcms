<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Набор ролей пользователя (полная замена). Ранговые ограничения проверяет `AssignRoles`. */
class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        /** @var list<string> $roles */
        $roles = array_values((array) $this->validated('roles'));

        return $roles;
    }
}
