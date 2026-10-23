<?php

declare(strict_types=1);

namespace Ladcms\Core\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Ladcms\Core\Services\LocaleManager;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(array_keys(app(LocaleManager::class)->available()))],
        ];
    }

    public function locale(): string
    {
        return (string) $this->validated('locale');
    }
}
