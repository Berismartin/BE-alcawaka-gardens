<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** In-app alert for the bell in the portal top bar. */
class PortalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public ?string $body = null,
        public ?string $url = null,
        public string $icon = 'i-lucide-bell',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'icon' => $this->icon];
    }
}
