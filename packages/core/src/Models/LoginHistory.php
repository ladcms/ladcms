<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $old_login
 * @property Carbon $changed_at
 * @property-read ?User $user
 */
class LoginHistory extends Model
{
    public $timestamps = false;

    protected $table = 'login_history';

    protected $fillable = ['user_id', 'old_login', 'changed_at'];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
