<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Enums\MessagesFrom;

class UpdatePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'show_email' => ['required', 'boolean'],
            'show_birthday' => ['required', 'boolean'],
            'messages_from' => ['required', Rule::enum(MessagesFrom::class)],
        ];
    }

    /** @return array{show_email: bool, show_birthday: bool, messages_from: string} */
    public function privacyData(): array
    {
        return [
            'show_email' => $this->boolean('show_email'),
            'show_birthday' => $this->boolean('show_birthday'),
            'messages_from' => (string) $this->validated('messages_from'),
        ];
    }
}
