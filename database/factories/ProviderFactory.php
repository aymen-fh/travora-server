<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HealthStatus;
use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Provider>
 */
final class ProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'type' => ProviderType::Esim,
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => RecordStatus::Active,
            'priority' => 100,
            'health_status' => HealthStatus::Unknown,
            'metadata' => null,
        ];
    }

    public function fake(string $scenario = 'success'): static
    {
        return $this->state(fn (): array => [
            'name' => 'Fake eSIM Provider',
            'code' => 'fake',
            'metadata' => ['scenario' => $scenario],
        ]);
    }

    public function template(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Template eSIM Provider',
            'code' => 'template',
        ]);
    }

    public function esimAccess(): static
    {
        return $this->state(fn (): array => [
            'name' => 'eSIMAccess',
            'code' => 'esimaccess',
        ]);
    }
}
