<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Полный набор permissions роли. Ограничения по рангу и набору прав актора проверяет `SyncRolePermissions`. */
class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        /** @var list<string> $names */
        $names = array_values((array) $this->validated('permissions'));

        return $names;
    }
}
