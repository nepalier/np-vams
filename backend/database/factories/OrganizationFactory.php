<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name_en' => $this->faker->company(),
            'name_ne' => $this->faker->company(),
            'organization_type' => 'valuation_firm',
            'account_status' => 'active',
            'approval_status' => 'approved',
        ];
    }
}
