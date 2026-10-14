<?php

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
