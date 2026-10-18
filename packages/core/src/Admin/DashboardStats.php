<?php

declare(strict_types=1);

namespace Ladcms\Core\Admin;

use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\Complaint;
use Ladcms\Core\Models\Module;
use Ladcms\Core\Models\User;
use Ladcms\Core\Module\ModuleRegistry;

/** Счётчики для карточек дашборда. */
final readonly class DashboardStats
{
    private const int ONLINE_MINUTES = 15;

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * @return array{users_total: int, users_online: int, users_today: int, pending_complaints: int, active_bans: int, modules: array{installed: int, failed: int, disabled: int}}
     */
    public function collect(): array
    {
        return [
            'users_total' => User::query()->count(),
            'users_online' => User::query()->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->count(),
            'users_today' => User::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'pending_complaints' => Complaint::query()->pending()->count(),
            'active_bans' => Ban::query()->active()->count(),
            'modules' => [
                'installed' => Module::query()->count(),
                'failed' => count(array_diff_key($this->registry->failures(), array_flip($this->registry->disabled()))),
                'disabled' => count($this->registry->disabled()),
            ],
        ];
    }
}
