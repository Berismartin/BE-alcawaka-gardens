<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\Notification;

/** Tells the right staff about something they should act on. Never the person who did it. */
class NotificationService
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function notify(string $ability, PortalNotification $notification): void
    {
        $recipients = $this->users->activeStaff()
            ->filter(fn ($u) => $u->role->can($ability) && $u->id !== auth()->id());
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}
