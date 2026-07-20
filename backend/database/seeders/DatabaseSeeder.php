<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always run — real reference data, safe for every environment.
        $this->call([
            RolePermissionSeeder::class,
            NepalGeoSeeder::class,
            MasterDataSeeder::class,
        ]);

        // Demonstration data only — do not run against production.
        if (app()->environment(['local', 'testing', 'staging'])) {
            $this->call(TenantDemoSeeder::class);
        }
    }
}
