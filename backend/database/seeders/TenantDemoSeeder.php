<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demonstration data only — clearly separated from production seeders
 * (RolePermissionSeeder / NepalGeoSeeder / MasterDataSeeder run in all
 * environments; this one is opt-in, see DatabaseSeeder::run()).
 */
class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Everest Valuation Associates (Demo)',
            'slug' => 'everest-valuation-demo',
            'plan' => 'standard',
            'status' => 'active',
            'subscription_starts_at' => now(),
            'subscription_ends_at' => now()->addYear(),
        ]);

        app()->instance('currentTenantId', $tenant->id);

        $organization = Organization::create([
            'tenant_id' => $tenant->id,
            'name_en' => 'Everest Valuation Associates',
            'name_ne' => 'सगरमाथा मूल्याङ्कन एसोसिएट्स',
            'organization_type' => 'valuation_firm',
            'account_status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'name' => 'Demo Tenant Admin',
            'email' => 'admin@demo.npvams.local',
            'password' => Hash::make('ChangeMe!12345'),
            'is_active' => true,
        ]);
        $admin->assignRole('Tenant Administrator');

        (new DefaultRiskConfigSeeder)->run();
        (new DefaultNotificationTemplateSeeder)->run();

        app()->forgetInstance('currentTenantId');

        $this->command?->warn('Demo tenant seeded — credentials: admin@demo.npvams.local / ChangeMe!12345 (change immediately, demo data only).');
    }
}
