<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Provider;
use App\Models\User;

final class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProvidersView->value);
    }

    public function view(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProvidersManage->value);
    }

    public function update(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersManage->value);
    }

    public function enable(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersEnable->value) || $user->can(PermissionName::ProvidersManage->value);
    }

    public function disable(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersDisable->value) || $user->can(PermissionName::ProvidersManage->value);
    }

    public function syncProducts(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersProductsSync->value) || $user->can(PermissionName::ProvidersManage->value);
    }

    public function runHealthCheck(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersHealthRun->value) || $user->can(PermissionName::ProvidersManage->value);
    }

    public function viewMaskedCredentials(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersCredentialsViewMasked->value);
    }

    public function manageCredentials(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersCredentialsManage->value);
    }

    public function viewServices(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersView->value);
    }

    public function viewProducts(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersView->value);
    }

    public function viewCoverage(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersView->value);
    }

    public function manageServices(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersServicesManage->value);
    }

    public function manageCoverage(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersCoverageManage->value);
    }

    public function manageWebhook(User $user, Provider $provider): bool
    {
        return $user->can(PermissionName::ProvidersManage->value);
    }
}
