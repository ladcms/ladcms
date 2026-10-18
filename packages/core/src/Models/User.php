<?php

declare(strict_types=1);

namespace Ladcms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Ladcms\Core\Database\Factories\UserFactory;
use Ladcms\Core\Enums\MessagesFrom;
use Ladcms\Core\Enums\Sex;
use Ladcms\Core\Settings\RegistrationSettings;
use Ladcms\Core\Support\LoginRules;
use Ladcms\Core\Support\RoleLevel;
use Ladcms\Core\Support\UserModel;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $login
 * @property string $name
 * @property string $email
 * @property ?string $avatar_path
 * @property-read ?string $avatar_url
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
 * @property ?Carbon $created_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /** Guard для spatie/permission: явно, чтобы не зависеть от того, какой класс указан в `auth.providers`. */
    protected string $guard_name = 'web';

    protected $fillable = [
        'login', 'name', 'email', 'password', 'avatar_path', 'sex', 'birth_date', 'about', 'signature',
        'language', 'timezone',
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

    /**
     * Единый морф-тип для core User и наследника приложения: `model_has_roles`, `notifications`,
     * `activity_log` хранят `user`, а morphMap (CoreServiceProvider) резолвит его в модель приложения.
     */
    public function getMorphClass(): string
    {
        return UserModel::MORPH_ALIAS;
    }

    /** Внешний ключ `user_id` и для наследников (иначе Eloquent вывел бы его из имени класса наследника). */
    public function getForeignKey(): string
    {
        return 'user_id';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public static function findByCurrentLogin(string $login): ?self
    {
        return static::query()->where('login', LoginRules::normalize($login))->first();
    }

    /** Finds a user by current login, falling back to the latest owner of a historical login. */
    public static function findByLogin(string $login): ?self
    {
        $login = LoginRules::normalize($login);

        return static::findByCurrentLogin($login)
            ?? LoginHistory::query()->where('old_login', $login)->latest('changed_at')->first()?->user;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name !== '' ? $this->name : $this->login;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatarUrl('md');
    }

    public function avatarUrl(string $size = 'md'): ?string
    {
        if ($this->avatar_path === null || $this->avatar_path === '') {
            return null;
        }

        return Storage::disk(self::avatarDisk())->url(self::avatarFile($this->id, $size));
    }

    public static function avatarDisk(): string
    {
        return (string) config('core.avatars.disk', 'public');
    }

    /** Каталог аватаров конкретного пользователя; строится только из int id. */
    public static function avatarDirectory(int $userId): string
    {
        return trim((string) config('core.avatars.path', 'avatars'), '/').'/'.$userId;
    }

    public static function avatarFile(int $userId, string $size): string
    {
        return self::avatarDirectory($userId).'/'.$size.'.jpg';
    }

    /**
     * Максимальный уровень ролей. Считается по загруженной связи `roles` (один запрос на экземпляр):
     * spatie сбрасывает связь при assignRole/removeRole/syncRoles, refresh() перезагружает её.
     */
    public function level(): int
    {
        return (int) ($this->roles->max('level') ?? 0);
    }

    public function outranks(User $other): bool
    {
        return $this->level() > $other->level();
    }

    public function isCreator(): bool
    {
        return $this->level() >= RoleLevel::CREATOR;
    }

    /** Онлайн — активность за последние 10 минут. */
    public const ONLINE_MINUTES = 10;

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('last_activity_at', '>', now()->subMinutes(self::ONLINE_MINUTES));
    }

    public function isOnline(): bool
    {
        return $this->last_activity_at !== null && $this->last_activity_at->greaterThan(now()->subMinutes(self::ONLINE_MINUTES));
    }

    /** @return HasMany<Ban, $this> */
    public function bans(): HasMany
    {
        return $this->hasMany(Ban::class);
    }

    public function activeBan(): ?Ban
    {
        return $this->bans()->active()->latest('starts_at')->first();
    }

    public function isBanned(): bool
    {
        return $this->activeBan() instanceof Ban;
    }

    /** Может ли писать: нет активного бана любого типа и истёк read-only период после регистрации. */
    public function canWrite(): bool
    {
        return ! $this->isBanned() && $this->readonlyPeriodPassed();
    }

    /** Истёк ли read-only период после регистрации (`registration.readonly_hours`). */
    public function readonlyPeriodPassed(): bool
    {
        $hours = app(RegistrationSettings::class)->readonly_hours;

        return $hours === 0 || $this->created_at === null || $this->created_at->copy()->addHours($hours)->isPast();
    }
}
