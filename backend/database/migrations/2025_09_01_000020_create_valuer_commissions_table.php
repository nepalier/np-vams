<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 35: "Valuer commission, Staff payment" -- the only two
     * billing-module line items from the source spec never built. One
     * row per (assignment, valuer): what they're owed for that specific
     * assignment, its approval, and its payment -- mirroring the same
     * calculate -> approve -> pay lifecycle shape as invoices/payments
     * elsewhere in this module, not a separate ad-hoc mechanism.
     */
    public function up(): void
    {
        Schema::create('valuer_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id');
            $table->uuid('user_id'); // the valuer being paid
            $table->string('commission_type'); // percentage|fixed
            $table->decimal('commission_rate_pct', 5, 2)->nullable(); // used when type=percentage
            $table->decimal('base_amount', 16, 2); // the assignment fee the percentage was calculated against, snapshotted (never recomputed later if the assignment fee changes)
            $table->decimal('commission_amount', 16, 2);
            $table->string('status')->default('pending'); // pending|approved|paid|cancelled
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['valuation_assignment_id', 'user_id'], 'valuer_commission_assignment_user_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuer_commissions');
    }
};
