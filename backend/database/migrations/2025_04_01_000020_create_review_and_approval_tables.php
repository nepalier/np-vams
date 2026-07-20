<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->string('section')->nullable(); // e.g. "land_valuation", "documents", "risk_assessment"
            $table->string('comment_type')->default('inline'); // inline|calculation_validation|rate_verification|document_deficiency|risk_review
            $table->text('comment');
            $table->string('severity')->default('information'); // information|warning|high_risk|blocking_error
            $table->boolean('is_resolved')->default(false);
            $table->uuid('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('created_by_user_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->index(['valuation_assignment_id', 'is_resolved']);
        });

        // One row per review DECISION (accept / reject / recommend
        // approval), separate from review_comments (which can be many
        // inline notes per decision) so "who decided what, when" is a
        // clean, queryable timeline independent of comment volume.
        Schema::create('approval_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->string('stage'); // technical_review|final_approval
            $table->string('decision'); // accept|reject|recommend_approval|approve|return_for_correction|cancel|supersede
            $table->uuid('decided_by_user_id');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->index(['valuation_assignment_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
        Schema::dropIfExists('review_comments');
    }
};
