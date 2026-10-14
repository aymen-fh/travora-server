<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\EsimPlan;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EsimPlan>
 */
final class EsimPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => fake()->words(3, true).' eSIM',
            'data_mb' => 1024,
            'is_unlimited' => false,
            'validity_days' => 7,
            'hotspot_supported' => false,
            'auto_topup_supported' => false,
            'is_featured' => false,
            'status' => RecordStatus::Active,
        ];
    }
}
