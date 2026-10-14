<?php

declare(strict_types=1);

namespace App\Actions\Providers;

use App\Models\Provider;
use App\Services\Catalog\ImportProviderCatalog;

final class SyncProviderProducts
{
    public function __construct(
        private readonly ImportProviderCatalog $import,
    ) {}

    /**
     * @return array{imported: int, updated: int, disabled: int, plans: int}
     */
    public function __invoke(Provider $provider): array
    {
        return ($this->import)($provider);
    }
}
