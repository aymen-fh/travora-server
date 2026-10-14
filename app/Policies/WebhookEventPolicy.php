<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\WebhookEvent;

final class WebhookEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::WebhooksView->value);
    }

    public function view(User $user, WebhookEvent $event): bool
    {
        return $user->can(PermissionName::WebhooksView->value);
    }

    public function retry(User $user, WebhookEvent $event): bool
    {
        return $user->can(PermissionName::WebhooksRetry->value);
    }

    public function viewDeadLetter(User $user): bool
    {
        return $user->can(PermissionName::WebhooksDeadLetterView->value) || $user->can(PermissionName::WebhooksView->value);
    }
}
