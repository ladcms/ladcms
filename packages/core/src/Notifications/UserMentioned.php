<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\User;
use Ladcms\Core\Notifications\Concerns\RespectsPreferences;

class UserMentioned extends Notification
{
    use RespectsPreferences;

    public const string TYPE = 'mentions';

    public function __construct(
        public readonly User $author,
        public readonly Model $context,
        public readonly string $url,
    ) {}

    /** @return array{author_id: int, author_login: string, context_type: string, context_id: mixed, url: string, title: string, params: array{name: string}} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'author_id' => $this->author->id,
            'author_login' => $this->author->login,
            'context_type' => $this->context->getMorphClass(),
            'context_id' => $this->context->getKey(),
            'url' => $this->url,
            'title' => 'core::public.notifications.mentioned',
            'params' => ['name' => $this->author->login],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned')
            ->line("{$this->author->login} mentioned you.")
            ->action('Open', $this->url);
    }
}
