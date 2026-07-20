<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Default roles from Step 1, Section 4. This is the SEED only —
     * roles/permissions remain fully configurable at runtime per-tenant
     * through the admin UI in a later phase; nothing here is hard-coded
     * into application logic.
     */
    private const ROLES = [
        'Super Administrator',
        'Platform Administrator',
        'Tenant Administrator',
        'Valuation Firm Administrator',
        'Branch Administrator',
        'Valuer or Engineer',
        'Field Surveyor',
        'Technical Reviewer',
        'Approving Authority',
        'Finance Officer',
        'Client Institution Administrator',
        'Bank Branch User',
        'Insurance User',
        'Cooperative User',
        'Property Owner or Applicant',
        'Auditor',
        'Read-Only User',
    ];

    /**
     * module.action convention. Expanded module-by-module as each domain
     * ships; this seed covers the modules that exist as of Phase 2
     * (tenancy, organizations, users, master data, audit).
     */
    private const PERMISSIONS = [
        'organizations.view', 'organizations.create', 'organizations.update', 'organizations.delete', 'organizations.approve',
        'branches.view', 'branches.create', 'branches.update', 'branches.delete',
        'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign_roles',
        'master_data.view', 'master_data.manage',
        'audit_logs.view',
        'assignments.view', 'assignments.create', 'assignments.update', 'assignments.delete',
        'valuations.create', 'valuations.view', 'valuations.finalize',
        'risk_assessments.create', 'risk_assessments.view',
        'government_rates.manage', 'government_rates.approve',
        'invoices.view', 'invoices.create', 'invoices.record_payment', 'invoices.credit_note',
        'dashboards.view',
    ];

    private const ROLE_PERMISSIONS = [
        'Tenant Administrator' => [
            'organizations.view', 'organizations.create', 'organizations.update', 'organizations.approve',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.assign_roles',
            'master_data.view', 'audit_logs.view',
            'assignments.view', 'assignments.create', 'assignments.update', 'assignments.delete',
            'valuations.create', 'valuations.view', 'valuations.finalize',
            'risk_assessments.create', 'risk_assessments.view',
            'government_rates.manage', 'government_rates.approve',
            'invoices.view', 'invoices.create', 'invoices.record_payment', 'invoices.credit_note', 'dashboards.view',
        ],
        'Valuation Firm Administrator' => [
            'organizations.view', 'branches.view', 'branches.create', 'branches.update',
            'users.view', 'users.create', 'users.update',
            'assignments.view', 'assignments.create', 'assignments.update',
            'valuations.view', 'risk_assessments.view', 'invoices.view', 'invoices.create', 'dashboards.view',
        ],
        'Branch Administrator' => [
            'organizations.view', 'branches.view', 'users.view', 'users.create', 'users.update',
            'assignments.view', 'assignments.create', 'dashboards.view',
        ],
        'Valuer or Engineer' => [
            'assignments.view', 'valuations.create', 'valuations.view',
            'risk_assessments.create', 'risk_assessments.view',
        ],
        'Field Surveyor' => ['assignments.view'],
        'Technical Reviewer' => ['assignments.view', 'valuations.view', 'risk_assessments.view'],
        'Approving Authority' => ['assignments.view', 'valuations.view', 'valuations.finalize', 'risk_assessments.view'],
        'Finance Officer' => ['invoices.view', 'invoices.create', 'invoices.record_payment', 'invoices.credit_note', 'dashboards.view'],
        'Client Institution Administrator' => ['assignments.view', 'dashboards.view'],
        'Auditor' => ['organizations.view', 'branches.view', 'users.view', 'audit_logs.view', 'assignments.view', 'valuations.view', 'invoices.view'],
        'Read-Only User' => ['organizations.view', 'branches.view', 'users.view', 'assignments.view'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }

        // Super Administrator bypasses via Gate::before in AppServiceProvider
        // and intentionally receives no explicit permission grants here.
    }
}
