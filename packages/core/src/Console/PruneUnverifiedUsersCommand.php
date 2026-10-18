<?php

declare(strict_types=1);

namespace Ladcms\Core\Console;

use Illuminate\Console\Command;
use Ladcms\Core\Actions\Users\PruneUnverifiedUsers;
use Ladcms\Core\Settings\RegistrationSettings;

class PruneUnverifiedUsersCommand extends Command
{
    protected $signature = 'ladcms:prune-unverified {--days= : Delete accounts unverified for longer than this many days}';

    protected $description = 'Permanently delete users who never verified their email';

    public function handle(PruneUnverifiedUsers $prune, RegistrationSettings $registration): int
    {
        if (! $registration->require_email_verification) {
            $this->info('Email verification is disabled; nothing to prune.');

            return self::SUCCESS;
        }

        $days = $this->option('days') ?? config('core.prune_unverified_after_days', 7);

        if (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1) {
            $this->error('The days option must be a positive integer.');

            return self::FAILURE;
        }

        $this->info(sprintf('Pruned %d unverified users.', $prune->handle((int) $days)));

        return self::SUCCESS;
    }
}
