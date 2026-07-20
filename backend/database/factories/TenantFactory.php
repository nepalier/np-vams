<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'plan' => 'standard',
            'status' => 'active',
            'subscription_starts_at' => now(),
            'subscription_ends_at' => now()->addYear(),
        ];
    }
}
