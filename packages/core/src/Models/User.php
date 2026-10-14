<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Ladcms\Core\Database\Factories\UserFactory;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Support\RoleLevel;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $login
 * @property string $name
 * @property string $email
 * @property ?string $avatar_path
 * @property ?Sex $sex
 * @property ?Carbon $birth_date
 * @property ?string $about
 * @property ?string $signature
 * @property string $language
 * @property string $timezone
 * @property ?Carbon $last_activity_at
 * @property ?string $registered_ip
 * @property bool $is_suspicious
 * @property ?string $suspicious_note
 * @property bool $show_email
 * @property bool $show_birthday
 * @property MessagesFrom $messages_from
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'login', 'name', 'email', 'password', 'avatar_path', 'sex', 'birth_date', 'about', 'signature',
        'language', 'timezone', 'registered_ip', 'is_suspicious', 'suspicious_note',
        'show_email', 'show_birthday', 'messages_from',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sex' => Sex::class,
            'birth_date' => 'date',
            'last_activity_at' => 'datetime',
            'is_suspicious' => 'boolean',
            'show_email' => 'boolean',
            'show_birthday' => 'boolean',
            'messages_from' => MessagesFrom::class,
        ];
    }

    protected $attributes = [
        'language' => 'ru',
        'timezone' => 'UTC',
        'is_suspicious' => false,
        'show_email' => false,
        'show_birthday' => true,
        'messages_from' => 'all',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name !== '' ? $this->name : $this->login;
    }

    public function level(): int
    {
        return (int) ($this->roles()->max('level') ?? 0);
    }

    public function outranks(User $other): bool
    {
        return $this->level() > $other->level();
    }

    public function isCreator(): bool
    {
        return $this->level() >= RoleLevel::CREATOR;
    }
}
