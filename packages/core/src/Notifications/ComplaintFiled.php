<?php

declare(strict_types=1);

namespace Ladcms\Core\Notifications;

use Illuminate\Notifications\Notification;
use Ladcms\Core\Models\Complaint;

class ComplaintFiled extends Notification
{
    public function __construct(public readonly Complaint $complaint) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{complaint_id: int, reason_code: string, author_login: ?string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'reason_code' => $this->complaint->reason_code,
            'author_login' => $this->complaint->author?->login,
        ];
    }
}
