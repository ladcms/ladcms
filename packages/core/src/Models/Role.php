<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property ?int $level
 * @property ?string $color
 * @property bool $is_default
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'level', 'color', 'is_default', 'is_system'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'is_default' => 'boolean', 'is_system' => 'boolean'];
    }

    public static function defaultForRegistration(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }
}
