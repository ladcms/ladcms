<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Ladcms\Core\Enums\ComplaintStatus;

/**
 * @property int $id
 * @property int $user_id
 * @property ?int $target_user_id
 * @property ?string $reportable_type
 * @property ?int $reportable_id
 * @property string $reason_code
 * @property string $comment
 * @property ComplaintStatus $status
 * @property ?int $reviewed_by_id
 * @property ?Carbon $reviewed_at
 * @property ?Carbon $created_at
 * @property-read ?Model $reportable
 * @property-read ?User $author
 * @property-read ?User $targetUser
 * @property-read ?User $reviewer
 */
class Complaint extends Model
{
    protected $fillable = [
        'user_id', 'target_user_id', 'reportable_type', 'reportable_id', 'reason_code', 'comment',
        'status', 'reviewed_by_id', 'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Complaint>  $query
     * @return Builder<Complaint>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ComplaintStatus::Pending->value);
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
