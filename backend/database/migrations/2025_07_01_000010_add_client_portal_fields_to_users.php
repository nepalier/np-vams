<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client-portal users share the SAME `users` table as tenant staff --
     * same login flow, same Sanctum tokens, same Spatie roles/permissions,
     * same MFA -- rather than a parallel auth system. What distinguishes a
     * portal user is simply: `organization_id` is null and `client_id` is
     * set (a staff user is the opposite). This is enforced at the
     * application layer (CreateClientPortalUserRequest / User model), not
     * a DB constraint, since MySQL's CHECK constraint support is
     * inconsistent across shared-hosting versions.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('organization_branch_id');
            $table->uuid('client_branch_id')->nullable()->after('client_id');
            $table->string('user_type')->default('staff')->after('client_branch_id'); // staff|client_portal

            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('client_branch_id')->references('id')->on('client_branches')->nullOnDelete();
            $table->index(['tenant_id', 'client_id']);
        });

        // Denormalized from the assignment at report-generation time (same
        // pattern as DigitalSignature.signer_name elsewhere in this
        // codebase) -- lets ClientPortalScope filter reports directly by
        // column instead of a join through valuation_assignments on every
        // query a portal user makes.
        Schema::table('reports', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('valuation_assignment_id');
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['client_branch_id']);
            $table->dropColumn(['client_id', 'client_branch_id', 'user_type']);
        });
    }
};
