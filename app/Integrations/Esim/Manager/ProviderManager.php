<?php

declare(strict_types=1);

namespace App\Integrations\Esim\Manager;

use App\Enums\ProviderCapability;
use App\Enums\RecordStatus;
use App\Exceptions\CapabilityNotSupportedException;
use App\Integrations\Esim\Contracts\EsimProvider;
use App\Integrations\Esim\Contracts\PartnerEsimOperations;
use App\Integrations\Esim\Exceptions\ProviderNotImplementedException;
use App\Models\Provider;
use InvalidArgumentException;

final class ProviderManager
{
    public function driver(Provider $provider): EsimProvider
    {
        if ($provider->status !== RecordStatus::Active) {
            throw new InvalidArgumentException("Provider [{$provider->code}] is not active.");
        }

        $class = config('esim.drivers.'.$provider->code);

        if (! is_string($class) || ! is_subclass_of($class, EsimProvider::class)) {
            throw new InvalidArgumentException("No EsimProvider registered for [{$provider->code}].");
        }

        /** @var EsimProvider $driver */
        $driver = app($class);

        return $driver->using($provider);
    }

    public function partner(Provider $provider): PartnerEsimOperations
    {
        $driver = $this->driver($provider);

        if (! $driver instanceof PartnerEsimOperations) {
            throw ProviderNotImplementedException::untilOfficialDocs('partner', $driver->code());
        }

        return $driver;
    }

    public function assertCapability(EsimProvider $driver, ProviderCapability $capability): void
    {
        if (! $driver->capabilities()->supports($capability)) {
            throw new CapabilityNotSupportedException($driver->code(), $capability->value);
        }
    }
}
