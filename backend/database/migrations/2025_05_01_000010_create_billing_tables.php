<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nepal tax convention modelled here: VAT is added on top of the
     * subtotal and IS part of what the client owes (`total_amount`). TDS is
     * withheld BY the client at the source when they pay -- the firm never
     * actually receives that portion in cash, but it isn't lost either
     * (it's remitted to the tax authority as an advance credit against the
     * firm's own tax liability). So `outstanding_amount` treats TDS as
     * settled the moment it's recorded, separately from `paid_amount`
     * (actual cash received) -- see InvoiceCalculationService for the
     * exact arithmetic, kept in one place rather than duplicated per
     * caller.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('valuation_assignment_id')->nullable();
            $table->uuid('client_id');
            $table->string('invoice_number');
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('vat_pct', 5, 2)->default(0);
            $table->decimal('vat_amount', 16, 2)->default(0);
            $table->decimal('tds_pct', 5, 2)->default(0);
            $table->decimal('tds_amount', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->decimal('credited_amount', 16, 2)->default(0);
            $table->decimal('outstanding_amount', 16, 2)->default(0);
            $table->string('status')->default('draft'); // draft|issued|partially_paid|paid|overdue|cancelled
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('valuation_assignment_id')->references('id')->on('valuation_assignments')->nullOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->restrictOnDelete();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_rate', 16, 2);
            $table->decimal('amount', 16, 2);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->date('payment_date');
            $table->decimal('amount', 16, 2);
            $table->string('payment_method'); // cash|bank_transfer|cheque|online
            $table->string('reference_number')->nullable();
            $table->uuid('received_by_user_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->string('credit_note_number');
            $table->decimal('amount', 16, 2);
            $table->text('reason');
            $table->uuid('issued_by_user_id')->nullable();
            $table->timestamp('issued_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->unique(['tenant_id', 'credit_note_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
