<?php

declare(strict_types=1);

namespace Ladcms\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Ladcms\Core\Models\LoginHistory;

/**
 * A login released by one user (via a login change) cannot be taken by another user for 30 days.
 * The previous owner may always take it back.
 */
final readonly class LoginNotRecentlyReleased implements ValidationRule
{
    public const int DAYS = 30;

    public function __construct(private ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $recentlyReleased = LoginHistory::query()
            ->where('old_login', $value)
            ->where('changed_at', '>', now()->subDays(self::DAYS))
            ->when($this->ignoreUserId !== null, fn ($query) => $query->where('user_id', '!=', $this->ignoreUserId))
            ->exists();

        if ($recentlyReleased) {
            $fail('This login was recently released and cannot be taken yet.');
        }
    }
}
