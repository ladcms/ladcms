<?php

declare(strict_types=1);

namespace Ladcms\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Ladcms\Core\Module\ModuleRegistry;

/**
 * Плоская карта переводов для фронта (`useT()`): `<module>::public.*` на всех страницах,
 * `<module>::admin.*` дополнительно на админ-роутах — для текущей локали.
 */
class TranslationMap
{
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<string> */
    public function groups(Request $request): array
    {
        return $request->routeIs('admin.*') ? ['public', 'admin'] : ['public'];
    }

    /**
     * Ключ once-пропа: меняется с локалью, областью (public/admin) и набором модулей,
     * тогда клиент получает карту заново; иначе использует запомненную.
     */
    public function cacheKey(Request $request): string
    {
        $names = $this->modules->names();
        sort($names);

        return sprintf('i18n:%s:%s:%s', app()->getLocale(), implode('+', $this->groups($request)), substr(md5(implode(',', $names)), 0, 8));
    }

    /** @return array<string, string> */
    public function build(Request $request): array
    {
        $map = [];
        foreach ($this->modules->names() as $name) {
            foreach ($this->groups($request) as $group) {
                $lines = Lang::get("{$name}::{$group}");
                if (! is_array($lines)) {
                    continue;
                }

                // Только строки: списки/числа в языковых файлах не должны ломать `replaceAll` на клиенте.
                foreach (Arr::dot($lines) as $key => $value) {
                    if (is_string($value)) {
                        $map["{$name}::{$group}.{$key}"] = $value;
                    }
                }
            }
        }

        return $map;
    }
}
