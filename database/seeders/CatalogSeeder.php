<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HealthStatus;
use App\Enums\ProviderType;
use App\Enums\RecordStatus;
use App\Models\Country;
use App\Models\Provider;
use App\Models\Region;
use Illuminate\Database\Seeder;

final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $europe = Region::query()->firstOrCreate(
            ['code' => 'EU'],
            ['name' => 'Europe'],
        );

        $asia = Region::query()->firstOrCreate(
            ['code' => 'AS'],
            ['name' => 'Asia'],
        );

        $countries = [
            ['iso2' => 'TR', 'iso3' => 'TUR', 'name' => 'Turkey', 'name_ar' => 'تركيا', 'region_id' => $asia->id],
            ['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'France', 'name_ar' => 'فرنسا', 'region_id' => $europe->id],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Germany', 'name_ar' => 'ألمانيا', 'region_id' => $europe->id],
            ['iso2' => 'IT', 'iso3' => 'ITA', 'name' => 'Italy', 'name_ar' => 'إيطاليا', 'region_id' => $europe->id],
            ['iso2' => 'ES', 'iso3' => 'ESP', 'name' => 'Spain', 'name_ar' => 'إسبانيا', 'region_id' => $europe->id],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['iso2' => $country['iso2']],
                $country + ['is_active' => true, 'currency' => 'LYD', 'timezone' => 'UTC'],
            );
        }

        Provider::query()->updateOrCreate(
            ['code' => 'fake'],
            [
                'type' => ProviderType::Esim,
                'name' => 'Fake eSIM Provider',
                'status' => RecordStatus::Active,
                'priority' => 1,
                'health_status' => HealthStatus::Healthy,
                'metadata' => ['scenario' => 'success'],
            ],
        );

        Provider::query()->updateOrCreate(
            ['code' => 'esimaccess'],
            [
                'type' => ProviderType::Esim,
                'name' => 'eSIM Access',
                'status' => RecordStatus::Active,
                'priority' => 10,
                'health_status' => HealthStatus::Unknown,
                'metadata' => ['docs' => 'https://docs.esimaccess.com'],
            ],
        );
    }
}
