<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
final class CountryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => fake()->country(),
            'name_ar' => fake()->country(),
            'iso2' => fake()->unique()->countryCode(),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'calling_code' => '+218',
            'currency' => 'LYD',
            'timezone' => 'Africa/Tripoli',
            'is_active' => true,
        ];
    }
}
