<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Users;

use Illuminate\Support\Facades\Cookie;
use InvalidArgumentException;
use Ladcms\Core\Models\User;
use Ladcms\Core\Services\LocaleManager;

class UpdateLocale
{
    public const int COOKIE_MINUTES = 60 * 24 * 365;

    public function __construct(private readonly LocaleManager $locales) {}

    /** Пользователю — `users.language`, гостю — cookie `locale` на год (читает `LocaleManager::resolve()`). */
    public function handle(?User $user, string $locale): void
    {
        if (! array_key_exists($locale, $this->locales->available())) {
            throw new InvalidArgumentException("Locale [{$locale}] is not available.");
        }

        if ($user instanceof User) {
            $user->forceFill(['language' => $locale])->save();

            return;
        }

        Cookie::queue('locale', $locale, self::COOKIE_MINUTES);
    }
}
