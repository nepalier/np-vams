<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Construction rates (Section 27) are kept TENANT-scoped, unlike
     * government land rates: firms commonly maintain their own internal
     * rate library reflecting their region's actual contractor pricing,
     * which legitimately differs firm to firm -- unlike the government
     * minimum rate, there is no single public source of truth to
     * deduplicate against.
     */
    public function up(): void
    {
        Schema::create('construction_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->string('structural_type'); // rcc_frame|load_bearing_masonry|steel|prefab|timber|traditional_masonry|adobe|mixed
            $table->string('building_type')->nullable();
            $table->string('construction_class')->nullable(); // A/B/C or similar tenant-defined grading
            $table->string('quality_grade')->nullable();
            $table->foreignId('rate_unit_id')->constrained('area_units');
            $table->decimal('base_rate', 14, 2);
            $table->decimal('material_adjustment_pct', 6, 3)->default(0);
            $table->decimal('labour_adjustment_pct', 6, 3)->default(0);
            $table->decimal('location_adjustment_pct', 6, 3)->default(0);
            $table->decimal('transportation_adjustment_pct', 6, 3)->default(0);
            $table->decimal('professional_fee_pct', 6, 3)->default(0);
            $table->decimal('external_works_amount', 14, 2)->default(0);
            $table->date('effective_date');
            $table->string('source')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('superseded_by_id')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('superseded_by_id')->references('id')->on('construction_rates')->nullOnDelete();
            $table->index(['tenant_id', 'structural_type', 'fiscal_year_id', 'is_current'], 'construction_rates_lookup_idx');
        });

        // Economic-life reference table (Section 26/23): remaining_life =
        // economic_life_years - effective_age_years, used by the age-life
        // depreciation method. Tenant-configurable, never hard-coded in the
        // CostApproachEngine itself.
        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('structural_type');
            $table->string('building_type')->nullable();
            $table->unsignedSmallInteger('economic_life_years');
            $table->decimal('max_depreciation_pct', 5, 2)->default(80.00);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'structural_type', 'building_type'], 'depreciation_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_schedules');
        Schema::dropIfExists('construction_rates');
    }
};
