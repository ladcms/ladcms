<?php

declare(strict_types=1);

namespace Ladcms\Core\Services;

use Illuminate\Http\Request;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\GeneralSettings;
use Throwable;

class LocaleManager
{
    /** @return array<string, string> */
    public function available(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('core.locales', []);

        return $locales;
    }

    /**
     * Порядок: язык пользователя → cookie `locale` → Accept-Language → язык сайта → app.locale.
     */
    public function resolve(Request $request): string
    {
        $user = $request->user();
        $candidates = [
            $user instanceof User ? $user->language : null,
            $request->cookie('locale'),
        ];

        foreach ($request->getLanguages() as $language) {
            $candidates[] = strtolower(explode('_', $language)[0]);
        }

        $candidates[] = $this->siteDefault();

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->supports($candidate)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
    }

    public function current(): string
    {
        return app()->getLocale();
    }

    /** До установки (нет БД/таблицы настроек) язык сайта недоступен — идём дальше по цепочке. */
    private function siteDefault(): ?string
    {
        try {
            return app(GeneralSettings::class)->default_language;
        } catch (Throwable) {
            return null;
        }
    }

    private function supports(string $locale): bool
    {
        return array_key_exists($locale, $this->available());
    }
}
