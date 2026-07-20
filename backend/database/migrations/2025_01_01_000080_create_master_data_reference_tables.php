<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('code_bs')->unique(); // e.g. "2082/83"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('area_units', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('code')->unique(); // bigha|kattha|dhur|ropani|aana|paisa|daam|sqm|sqft|hectare|acre
            $table->decimal('conversion_to_sqm', 18, 8);
            $table->string('region_context')->nullable(); // e.g. "Terai" for Bigha/Kattha/Dhur, "Hill" for Ropani/Aana
            $table->timestamps();
        });

        Schema::create('valuation_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_purposes');
        Schema::dropIfExists('area_units');
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('fiscal_years');
    }
};
