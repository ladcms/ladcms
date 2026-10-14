<?php

declare(strict_types=1);

namespace Ladcms\Core\Actions\Moderation;

use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ladcms\Core\Enums\BanType;
use Ladcms\Core\Models\Ban;
use Ladcms\Core\Models\User;
use Ladcms\Core\Settings\ModerationSettings;

class BanUser
{
    public function __construct(private readonly ModerationSettings $settings) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $target, BanType $type, string $reasonCode, ?DateTimeInterface $expiresAt, ?string $comment = null): Ban
    {
        if (! $actor->can('users.ban') || ! $actor->outranks($target)) {
            throw new AuthorizationException('You cannot ban this user.');
        }

        $codes = collect($this->settings->violation_codes)->keyBy('code');
        Validator::make(['reason_code' => $reasonCode], ['reason_code' => ['required', Rule::in($codes->keys()->all())]])->validate();

        /** @var array{code: string, label: string, min_days: int, max_days: ?int} $code */
        $code = $codes[$reasonCode];

        if (! $expiresAt instanceof DateTimeInterface) {
            if ($code['max_days'] !== null) {
                throw ValidationException::withMessages(['expires_at' => 'This violation code does not allow a permanent ban.']);
            }
        } else {
            $days = (int) ceil(now()->diffInSeconds($expiresAt, absolute: true) / 86400);

            if ($days < $code['min_days'] || ($code['max_days'] !== null && $days > $code['max_days'])) {
                throw ValidationException::withMessages([
                    'expires_at' => "Duration must be between {$code['min_days']} and ".($code['max_days'] ?? '∞').' days.',
                ]);
            }
        }

        // TODO(Task 10): activity log.
        return Ban::create([
            'user_id' => $target->id,
            'banned_by_id' => $actor->id,
            'type' => $type,
            'reason_code' => $reasonCode,
            'comment' => $comment,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }
}
