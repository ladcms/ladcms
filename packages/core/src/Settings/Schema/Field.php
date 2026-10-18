<?php

declare(strict_types=1);

namespace Ladcms\Core\Settings\Schema;

use Attribute;

/**
 * Уточняет, как свойство Settings-класса отображается в админке. Без атрибута тип берётся из PHP-типа
 * (string → Text, int → Number, bool → Boolean, array → Json).
 *
 * `$options` — имя провайдера вариантов для Select: `locales`, `themes` или `timezones`.
 * `$min`/`$max` — границы: значение для Number (по умолчанию min 0), длина для текста, число элементов для списков.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    public function __construct(
        public ?FieldType $type = null,
        public ?string $options = null,
        public ?string $description = null,
        public ?int $min = null,
        public ?int $max = null,
    ) {}
}
