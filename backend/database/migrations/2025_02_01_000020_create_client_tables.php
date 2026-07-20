<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "clients" are the valuation firm's business counterparties (banks,
     * insurers, cooperatives, corporates...) as known to THIS tenant.
     * Distinct from "organizations" (Phase 2), which are entities that
     * subscribe to and log into the platform themselves. A client is very
     * often never a platform subscriber at all -- most banks just receive
     * PDF reports -- so these are deliberately separate tables rather than
     * overloading organizations with two meanings.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name_en');
            $table->string('name_ne')->nullable();
            $table->string('client_type'); // commercial_bank|development_bank|finance_company|microfinance|cooperative|insurance|government_agency|corporate|individual|other
            $table->string('registration_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('address')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('authorized_contact_person')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'client_type']);
        });

        Schema::create('client_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('client_id');
            $table->string('name_en');
            $table->string('name_ne')->nullable();
            $table->string('branch_code')->nullable();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_branches');
        Schema::dropIfExists('clients');
    }
};
