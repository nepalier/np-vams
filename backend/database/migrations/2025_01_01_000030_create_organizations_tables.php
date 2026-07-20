<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('organization_type');
            $table->string('registration_number')->nullable();
            $table->string('registration_authority')->nullable();
            $table->date('registration_date')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('local_level_id')->nullable()->constrained('local_levels')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('postal_address')->nullable();
            $table->string('telephone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('authorized_contact_person')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->string('digital_seal_path')->nullable();
            $table->string('subscription_plan')->default('standard');
            $table->date('subscription_starts_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->string('account_status')->default('pending_approval');
            $table->string('approval_status')->default('pending');
            $table->boolean('is_suspended')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'organization_type']);
        });

        Schema::create('organization_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('organization_id');
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('branch_code')->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('local_level_id')->nullable()->constrained('local_levels')->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('telephone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'branch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_branches');
        Schema::dropIfExists('organizations');
    }
};
