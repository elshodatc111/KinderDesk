<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Ilova ichidagi bildirishnoma (qo'ng'iroqcha). */
class GeneralNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body = '',
        public ?string $url = null,
        public string $level = 'info', // info | success | warning | danger
        public string $icon = 'bell',
        public ?string $permission = null, // ko'rish uchun kerakli ruxsat
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'level' => $this->level,
            'icon' => $this->icon,
            'perm' => $this->permission,
        ];
    }
}
