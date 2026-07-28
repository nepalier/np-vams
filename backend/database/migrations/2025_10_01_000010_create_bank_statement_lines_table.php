<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Section 35: "Bank reconciliation" -- the last unbuilt billing-module line item. */
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 16, 2);
            $table->boolean('is_matched')->default(false);
            $table->uuid('matched_payment_id')->nullable();
            $table->string('match_method')->nullable(); // auto|manual
            $table->uuid('import_batch_id');
            $table->uuid('imported_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('matched_payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('imported_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'is_matched'], 'bank_lines_tenant_matched_idx');
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
