<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 15: inspection checklist covering foundation/columns/beams/
     * slabs/walls/cracks/settlement/dampness/roof/doors/windows/electrical/
     * plumbing/sanitation/fire-safety/lift/HVAC/maintenance/functional &
     * economic obsolescence/structural risk, with "photographs and remarks
     * for every assessment item." Deferred at Phase 3 alongside building
     * blocks/components; closing the checklist half of that gap now (the
     * point being CostApproachEngine's observed_condition depreciation
     * method, which needs exactly this kind of per-item severity data to
     * be more than a bare percentage the valuer types in with no
     * supporting record).
     */
    public function up(): void
    {
        Schema::create('building_condition_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('building_id');
            $table->uuid('assessed_by_user_id')->nullable();
            $table->timestamp('assessed_at')->useCurrent();
            $table->string('structural_risk')->nullable(); // low|moderate|high|critical
            $table->text('required_repairs')->nullable();
            $table->decimal('repair_cost_estimate', 16, 2)->nullable();
            $table->unsignedSmallInteger('remaining_life_years')->nullable();
            // 1 (excellent) .. 5 (critical) -- consumed directly by
            // BuildingConditionToDepreciationMapper below.
            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('building_id')->references('id')->on('buildings')->cascadeOnDelete();
            $table->foreign('assessed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['building_id', 'assessed_at']);
        });

        Schema::create('building_condition_assessment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('building_condition_assessment_id');
            $table->string('item_type'); // foundation|columns|beams|slabs|walls|cracks|settlement|dampness|roof|doors|windows|electrical|plumbing|sanitation|fire_safety|lift|hvac|maintenance|functional_obsolescence|economic_obsolescence
            $table->unsignedTinyInteger('condition_rating'); // 1 (excellent) .. 5 (critical), same scale as overall_rating
            $table->text('remarks')->nullable();
            // Photos reuse the existing polymorphic property_documents
            // table (Phase 3) rather than a parallel photo table --
            // documentable_type=BuildingConditionAssessmentItem,
            // documentable_id=this row's id.
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('building_condition_assessment_id', 'condition_items_assessment_fk')->references('id')->on('building_condition_assessments')->cascadeOnDelete();
            $table->unique(['building_condition_assessment_id', 'item_type'], 'condition_assessment_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_condition_assessment_items');
        Schema::dropIfExists('building_condition_assessments');
    }
};
