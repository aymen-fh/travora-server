<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\NetworkOperator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkOperator>
 */
final class NetworkOperatorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->company().' Mobile',
            'mcc' => fake()->numerify('###'),
            'mnc' => fake()->numerify('##'),
            'is_active' => true,
        ];
    }
}
