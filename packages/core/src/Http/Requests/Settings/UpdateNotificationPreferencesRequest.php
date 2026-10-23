<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Settings;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Ladcms\Core\Support\NotificationTypes;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $types = array_keys(NotificationTypes::all());

        return [
            // Неизвестный тип — ошибка на `preferences`, а не молчаливое игнорирование.
            'preferences' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail) use ($types): void {
                if (is_array($value) && array_diff(array_keys($value), $types) !== []) {
                    $fail('Unknown notification type.');
                }
            }],
            'preferences.*' => ['array'],
            'preferences.*.database' => ['sometimes', 'boolean'],
            'preferences.*.mail' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, array{database: bool, mail: bool}> */
    public function preferences(): array
    {
        $result = [];
        foreach ((array) $this->input('preferences', []) as $type => $channels) {
            $result[(string) $type] = [
                'database' => filter_var($channels['database'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mail' => filter_var($channels['mail'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $result;
    }
}
