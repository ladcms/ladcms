<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\User;

class UserMentioned extends Notification
{
    public function __construct(
        public readonly User $author,
        public readonly Model $context,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{author_id: int, author_login: string, context_type: string, context_id: mixed, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'author_id' => $this->author->id,
            'author_login' => $this->author->login,
            'context_type' => $this->context->getMorphClass(),
            'context_id' => $this->context->getKey(),
            'url' => $this->url,
        ];
    }
}
