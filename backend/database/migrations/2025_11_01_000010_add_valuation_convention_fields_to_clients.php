<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three real reference bank documents used to build this system's
     * valuation engines showed THREE different weighted-land-rate
     * conventions: 30% government/70% market, 70% government/30% market,
     * and 20% government/80% market. Each is a real, valid convention --
     * specific to that bank's own valuation guideline -- so the split
     * cannot be a single system-wide default; it has to be remembered
     * per client (bank), the same way a bank's VAT/TDS rate or fee
     * schedule already is.
     *
     * All nullable: a client with none of these configured falls back to
     * WeightedLandRateEngine's own constructor defaults (30/70, matching
     * the JBBL reference) and the valuer enters/overrides explicitly --
     * never silently forced to one convention or another.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('land_rate_government_weight_pct', 5, 2)->nullable()->after('remarks');
            $table->decimal('land_rate_market_weight_pct', 5, 2)->nullable()->after('land_rate_government_weight_pct');
            $table->decimal('distress_value_pct', 5, 2)->nullable()->after('land_rate_market_weight_pct'); // e.g. 80.00 for "80% of FMV", seen identically across all three reference formats
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['land_rate_government_weight_pct', 'land_rate_market_weight_pct', 'distress_value_pct']);
        });
    }
};
