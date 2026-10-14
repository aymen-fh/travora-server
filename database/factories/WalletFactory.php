<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
final class WalletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => 'LYD',
            'available_balance_minor' => 0,
            'held_balance_minor' => 0,
            'status' => WalletStatus::Active,
        ];
    }
}
