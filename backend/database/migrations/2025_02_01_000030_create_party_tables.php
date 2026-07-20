<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sharedPartyColumns = function (Blueprint $table) {
            $table->string('party_kind')->default('individual'); // individual|company|institution|trust
            $table->string('name_en');
            $table->string('name_ne')->nullable();
            $table->string('citizenship_number')->nullable();
            $table->string('passport_number')->nullable();
            $table->string('company_registration_number')->nullable();
            $table->string('pan_or_vat_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('current_address')->nullable();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('telephone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->boolean('consent_for_inspection')->default(false);
            $table->boolean('consent_for_data_processing')->default(false);
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('property_owners', function (Blueprint $table) use ($sharedPartyColumns) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $sharedPartyColumns($table);
            $table->string('ownership_type')->default('single'); // single|joint|company|institutional|trust|guthi|inherited|leasehold|undivided_share
            $table->decimal('ownership_percentage', 5, 2)->nullable();
            $table->text('remarks')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'citizenship_number']);
        });

        Schema::create('borrowers', function (Blueprint $table) use ($sharedPartyColumns) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $sharedPartyColumns($table);
            $table->string('relationship_with_owner')->nullable();
            $table->text('remarks')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'citizenship_number']);
        });

        Schema::create('guarantors', function (Blueprint $table) use ($sharedPartyColumns) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('borrower_id')->nullable();
            $sharedPartyColumns($table);
            $table->text('remarks')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('borrower_id')->references('id')->on('borrowers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantors');
        Schema::dropIfExists('borrowers');
        Schema::dropIfExists('property_owners');
    }
};
