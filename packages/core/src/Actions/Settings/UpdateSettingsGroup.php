<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Settings;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\Schema\FieldType;
use Ladcms\Core\Settings\Schema\SettingsSchema;
use Ladcms\Core\Support\Audit;
use Spatie\LaravelSettings\Settings;

/**
 * Сохраняет группу настроек, валидируя входные данные по типам полей из `SettingsSchema`.
 * Поля, которых нет во входных данных, остаются как есть.
 *
 * @phpstan-import-type SchemaField from SettingsSchema
 */
class UpdateSettingsGroup
{
    private const int AUDIT_VALUE_LIMIT = 200;

    /**
     * @param  class-string<Settings>  $class
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, string $class, array $input): Settings
    {
        if (! $actor->can('settings.manage')) {
            throw new AuthorizationException('You cannot manage settings.');
        }

        $schema = SettingsSchema::for($class);
        $fields = collect($schema['fields'])->keyBy('name');

        $input = $this->decodeJson($fields->all(), $input);

        $rules = [];
        foreach ($fields as $name => $field) {
            $rules = array_merge($rules, $this->rules($name, $field));
        }

        $validated = Validator::make($input, $rules)->validate();

        $settings = app($class);
        $changed = [];

        foreach ($fields as $name => $field) {
            if (! array_key_exists($name, $validated)) {
                continue;
            }

            $old = $settings->{$name};
            $new = $this->cast($field, $validated[$name]);

            if ($old !== $new) {
                $changed[$name] = [$this->auditValue($old), $this->auditValue($new)];
                $settings->{$name} = $new;
            }
        }

        if ($changed === []) {
            return $settings;
        }

        $settings->save();

        Audit::log('settings.updated', $actor, null, ['group' => $schema['group'], 'changed' => $changed]);

        return $settings;
    }

    /**
     * @param  array<string, SchemaField>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function decodeJson(array $fields, array $input): array
    {
        foreach ($fields as $name => $field) {
            if ($field['type'] === FieldType::Json && isset($input[$name]) && is_string($input[$name])) {
                $decoded = json_decode($input[$name], true);
                $input[$name] = is_array($decoded) ? $decoded : $input[$name];
            }
        }

        return $input;
    }

    /**
     * @param  SchemaField  $field
     * @return array<string, list<mixed>>
     */
    private function rules(string $name, array $field): array
    {
        $nullable = $field['nullable'];

        $rules = match ($field['type']) {
            FieldType::Text => [$name => ['sometimes', ...($nullable ? ['nullable'] : ['required']), 'string', 'max:255']],
            FieldType::Textarea, FieldType::Markdown => [$name => ['sometimes', 'nullable', 'string']],
            FieldType::Number => [$name => ['sometimes', ...($nullable ? ['nullable'] : ['required']), 'integer']],
            FieldType::Boolean => [$name => ['sometimes', 'required', 'boolean']],
            FieldType::Select => [$name => ['sometimes', ...($nullable ? ['nullable'] : ['required']), Rule::in(array_column($field['options'] ?? [], 'value'))]],
            FieldType::Color => [$name => ['sometimes', ...($nullable ? ['nullable'] : ['required']), 'regex:/^#[0-9a-fA-F]{6}$/']],
            FieldType::Json => [$name => ['sometimes', 'present', 'array']],
            FieldType::ViolationCodes => [
                $name => ['sometimes', 'present', 'array'],
                "{$name}.*" => ['array'],
                "{$name}.*.code" => ['required', 'string', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
                "{$name}.*.label" => ['required', 'string', 'max:255'],
                "{$name}.*.min_days" => ['required', 'integer', 'min:0'],
                "{$name}.*.max_days" => ['nullable', 'integer', "gte:{$name}.*.min_days"],
            ],
        };

        // min/max понимаются валидатором по типу: значение числа, длина строки, размер массива.
        if (! in_array($field['type'], [FieldType::Boolean, FieldType::Select, FieldType::Color], true)) {
            if ($field['min'] !== null) {
                $rules[$name][] = 'min:'.$field['min'];
            }
            if ($field['max'] !== null) {
                $rules[$name][] = 'max:'.$field['max'];
            }
        }

        return $rules;
    }

    /** @param  SchemaField  $field */
    private function cast(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            FieldType::Text, FieldType::Textarea, FieldType::Markdown, FieldType::Select, FieldType::Color => $value === null || ($value === '' && $field['nullable'])
                ? ($field['nullable'] ? null : '')
                : (string) $value,
            FieldType::Number => $value === null ? null : (int) $value,
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FieldType::Json => (array) $value,
            FieldType::ViolationCodes => $this->castViolationCodes((array) $value),
        };
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @return list<array{code: string, label: string, min_days: int, max_days: ?int}>
     */
    private function castViolationCodes(array $rows): array
    {
        $codes = [];
        foreach ($rows as $row) {
            /** @var array{code: string, label: string, min_days: int|string, max_days: int|string|null} $row */
            $codes[] = [
                'code' => (string) $row['code'],
                'label' => (string) $row['label'],
                'min_days' => (int) $row['min_days'],
                'max_days' => ($row['max_days'] ?? null) === null ? null : (int) $row['max_days'],
            ];
        }

        return $codes;
    }

    private function auditValue(mixed $value): mixed
    {
        $length = is_string($value) ? mb_strlen($value) : (is_array($value) ? mb_strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE)) : 0);

        return $length > self::AUDIT_VALUE_LIMIT ? '[…]' : $value;
    }
}
