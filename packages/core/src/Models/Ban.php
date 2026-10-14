<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ladcms\Core\Enums\BanType;

/**
 * @property int $id
 * @property int $user_id
 * @property ?int $banned_by_id
 * @property BanType $type
 * @property string $reason_code
 * @property ?string $comment
 * @property Carbon $starts_at
 * @property ?Carbon $expires_at
 * @property-read ?User $user
 * @property-read ?User $bannedBy
 */
class Ban extends Model
{
    protected $fillable = ['user_id', 'banned_by_id', 'type', 'reason_code', 'comment', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'type' => BanType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Ban>  $query
     * @return Builder<Ban>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('starts_at', '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by_id');
    }
}
