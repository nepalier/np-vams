<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateInitialAdmin extends Command
{
    protected $signature = 'npvams:create-admin
        {--org-name= : Organization name (English)}
        {--email= : Admin email}
        {--password= : Admin password}';

    protected $description = 'Create the first tenant, organization, and admin user for a fresh production database.';

    public function handle(): int
    {
        $orgName = $this->option('org-name') ?: $this->ask('Organization name (English)');
        $email = $this->option('email') ?: $this->ask('Admin email address');
        $password = $this->option('password') ?: $this->secret('Admin password (min 10 characters)');

        if (strlen((string) $password) < 10) {
            $this->error('Password must be at least 10 characters.');
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            if (! $this->confirm("A user with email {$email} already exists. Reset their password instead?")) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
            $existing->forceFill(['password' => Hash::make($password), 'is_active' => true])->save();
            if (! $existing->hasRole('Tenant Administrator')) {
                $existing->assignRole('Tenant Administrator');
            }
            $this->info("Password reset for existing user: {$email}");
            return self::SUCCESS;
        }

        $tenant = Tenant::create([
            'name' => $orgName,
            'slug' => \Illuminate\Support\Str::slug($orgName).'-'.substr(uniqid(), -6),
            'plan' => 'standard', 'status' => 'active',
            'subscription_starts_at' => now(), 'subscription_ends_at' => now()->addYear(),
        ]);

        app()->instance('currentTenantId', $tenant->id);

        $organization = Organization::create([
            'tenant_id' => $tenant->id, 'name_en' => $orgName, 'name_ne' => $orgName,
            'organization_type' => 'valuation_firm', 'account_status' => 'active', 'approval_status' => 'approved',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id, 'organization_id' => $organization->id,
            'name' => 'Administrator', 'email' => $email, 'password' => Hash::make($password), 'is_active' => true,
        ]);
        $user->assignRole('Tenant Administrator');

        app()->forgetInstance('currentTenantId');
        $this->info("Created organization '{$orgName}' and admin account: {$email}");
        return self::SUCCESS;
    }
}