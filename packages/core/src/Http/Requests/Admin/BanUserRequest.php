<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Admin;

use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Enums\BanType;

/**
 * Бан из карточки пользователя: срок в днях или «бессрочно». Код нарушения и допустимый срок
 * проверяет `BanUser` (по настройкам модерации). Авторизация — там же.
 */
class BanUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BanType::class)],
            'reason_code' => ['required', 'string', 'max:64'],
            'permanent' => ['sometimes', 'boolean'],
            'expires_in_days' => [Rule::requiredIf(! $this->boolean('permanent')), 'nullable', 'integer', 'min:1', 'max:36500'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function banType(): BanType
    {
        return BanType::from((string) $this->validated('type'));
    }

    public function expiresAt(): ?DateTimeInterface
    {
        return $this->boolean('permanent') ? null : now()->addDays((int) $this->validated('expires_in_days'));
    }
}
