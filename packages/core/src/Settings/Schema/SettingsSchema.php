<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings\Schema;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ladcms\Core\Services\LocaleManager;
use Ladcms\Core\Settings\SettingsGroups;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelSettings\Settings;

/**
 * Описание формы настроек, построенное рефлексией по Settings-классу.
 *
 * @phpstan-type SchemaField array{name: string, type: FieldType, label: string, description: ?string, nullable: bool, options: list<array{value: string, label: string}>|null, min: ?int, max: ?int, value: mixed}
 * @phpstan-type Schema array{group: string, label: string, fields: list<SchemaField>}
 */
final class SettingsSchema
{
    /**
     * @param  class-string<Settings>  $class
     * @return Schema
     */
    public static function for(string $class): array
    {
        $module = app(SettingsGroups::class)->moduleOf($class) ?? 'core';
        $group = $class::group();
        $settings = app($class);

        $fields = [];
        foreach (new ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $name = $property->getName();
            $attribute = self::attribute($property);
            $type = $attribute->type ?? self::infer($property);

            $fields[] = [
                'name' => $name,
                'type' => $type,
                'label' => self::translate("{$module}::settings.{$group}.fields.{$name}.label") ?? Str::headline($name),
                'description' => $attribute->description ?? self::translate("{$module}::settings.{$group}.fields.{$name}.description"),
                'nullable' => $property->getType()?->allowsNull() ?? false,
                'options' => $type === FieldType::Select ? self::options($attribute?->options) : null,
                // Числа по умолчанию неотрицательны: отрицательные таймауты/лимиты в настройках — ошибка ввода.
                'min' => $attribute->min ?? ($type === FieldType::Number ? 0 : null),
                'max' => $attribute?->max,
                'value' => $settings->{$name},
            ];
        }

        return [
            'group' => $group,
            'label' => self::translate("{$module}::settings.{$group}.title") ?? Str::ucfirst($group),
            'fields' => $fields,
        ];
    }

    /**
     * Допустимые значения Select-провайдера.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(?string $provider): array
    {
        $pairs = match ($provider) {
            'locales' => app(LocaleManager::class)->available(),
            'themes' => ['system' => 'system', 'light' => 'light', 'dark' => 'dark'],
            'timezones' => array_combine(timezone_identifiers_list(), timezone_identifiers_list()),
            default => throw new InvalidArgumentException('Select field requires a known options provider.'),
        };

        $options = [];
        foreach ($pairs as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $options;
    }

    private static function attribute(ReflectionProperty $property): ?Field
    {
        foreach ($property->getAttributes(Field::class) as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }

    private static function infer(ReflectionProperty $property): FieldType
    {
        $type = $property->getType();

        return match ($type instanceof ReflectionNamedType ? $type->getName() : null) {
            'int', 'float' => FieldType::Number,
            'bool' => FieldType::Boolean,
            'array' => FieldType::Json,
            default => FieldType::Text,
        };
    }

    private static function translate(string $key): ?string
    {
        return Lang::has($key) ? (string) __($key) : null;
    }
}
