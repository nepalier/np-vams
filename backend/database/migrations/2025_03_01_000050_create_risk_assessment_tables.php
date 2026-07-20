<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant-configurable indicator catalogue + weight -- NOT hard-coded
        // in RiskAssessmentService, matching Section 49's "do not hard-code
        // Nepal-specific valuation percentages" spirit extended to risk
        // weights generally.
        Schema::create('risk_indicators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code')->unique();
            $table->string('label_en');
            $table->string('label_ne')->nullable();
            $table->decimal('default_weight', 6, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // Tenant-configurable score bands mapping a total weighted score to
        // a risk category label -- again, never hard-coded in the service.
        Schema::create('risk_score_bands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->decimal('min_score', 8, 2);
            $table->decimal('max_score', 8, 2);
            $table->string('category'); // low|moderate|high|unacceptable
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('property_id')->nullable();
            $table->decimal('computed_score', 8, 2);
            $table->string('computed_category'); // derived from risk_score_bands
            $table->string('final_category'); // = computed_category unless overridden
            $table->boolean('is_overridden')->default(false);
            $table->text('override_justification')->nullable();
            $table->uuid('assessed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });

        Schema::create('risk_assessment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('risk_assessment_id');
            $table->uuid('risk_indicator_id');
            $table->decimal('weight_applied', 6, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('risk_assessment_id')->references('id')->on('risk_assessments')->cascadeOnDelete();
            $table->foreign('risk_indicator_id')->references('id')->on('risk_indicators')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_items');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('risk_score_bands');
        Schema::dropIfExists('risk_indicators');
    }
};
