<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single settings area for a tenant's own org-wide default
     * percentages -- sits between a client's own bank-specific convention
     * (clients.land_rate_*, set in the previous batch) and each engine's
     * hard-coded final fallback in the resolution chain. Before this
     * migration, the vehicle and building-cost engines had NO way to be
     * adjusted at all outside editing PHP source -- every one of these
     * nine fields fixes that for a specific engine's specific percentage.
     * All nullable: an unconfigured tenant simply falls through to the
     * engine's own default, exactly as it did before this existed.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Weighted land rate (Section: falls between a client's own
            // override and the 30/70 engine default)
            $table->decimal('default_land_rate_government_weight_pct', 5, 2)->nullable();
            $table->decimal('default_land_rate_market_weight_pct', 5, 2)->nullable();
            $table->decimal('default_distress_value_pct', 5, 2)->nullable();

            // Vehicle / machinery valuation
            $table->decimal('default_vehicle_scrap_pct', 5, 2)->nullable();
            $table->decimal('default_vehicle_depreciation_pct_per_annum', 5, 2)->nullable();
            $table->decimal('default_vehicle_other_cost_pct_per_annum', 5, 2)->nullable();

            // Building cost estimation
            $table->decimal('default_building_sanitary_fixture_pct', 5, 2)->nullable();
            $table->decimal('default_building_electrical_fixture_pct', 5, 2)->nullable();
            $table->decimal('default_building_depreciation_pct_per_annum', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'default_land_rate_government_weight_pct', 'default_land_rate_market_weight_pct', 'default_distress_value_pct',
                'default_vehicle_scrap_pct', 'default_vehicle_depreciation_pct_per_annum', 'default_vehicle_other_cost_pct_per_annum',
                'default_building_sanitary_fixture_pct', 'default_building_electrical_fixture_pct', 'default_building_depreciation_pct_per_annum',
            ]);
        });
    }
};
