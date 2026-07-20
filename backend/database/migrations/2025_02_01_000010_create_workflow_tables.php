<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable status catalogue (Step 1 Section 7). Sequence is used
        // only for display ordering (e.g. a progress bar) -- it does NOT by
        // itself imply which transitions are legal; that is entirely driven
        // by workflow_transition_rules below, so the graph can include
        // branches, loops (correction -> resubmitted -> review) and terminal
        // states (cancelled) that a pure linear sequence could not express.
        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. draft, submitted, under_technical_review
            $table->string('label_en');
            $table->string('label_ne');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_transition_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            // JSON array of role names permitted to perform this transition.
            // Empty/null = any authenticated assignee on the assignment.
            $table->json('allowed_roles')->nullable();
            $table->boolean('requires_remarks')->default(false);
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id'], 'workflow_rule_unique_edge');
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('organization_id')->nullable();
            $table->uuid('organization_branch_id')->nullable();
            $table->string('transitionable_type'); // polymorphic: currently ValuationAssignment, extensible to Report etc.
            $table->uuid('transitionable_id');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->uuid('user_id')->nullable();
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['transitionable_type', 'transitionable_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_transition_rules');
        Schema::dropIfExists('workflow_statuses');
    }
};
