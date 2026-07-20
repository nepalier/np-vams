<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 30: "The same user must not be allowed to prepare, review,
     * and approve the same report unless specifically permitted under a
     * documented exceptional policy." The exception is an explicit,
     * auditable per-organization toggle -- never a silent default -- and
     * every use of it is logged via Activitylog same as any other change
     * to the organizations table.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('allow_segregation_of_duties_exception')->default(false)->after('is_blacklisted');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('allow_segregation_of_duties_exception');
        });
    }
};
