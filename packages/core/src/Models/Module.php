<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $version
 * @property Carbon $installed_at
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Module extends Model
{
    protected $fillable = ['name', 'version', 'installed_at', 'is_enabled'];

    protected function casts(): array
    {
        return ['installed_at' => 'datetime', 'is_enabled' => 'boolean'];
    }

    /**
     * @param  Builder<Module>  $query
     * @return Builder<Module>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
