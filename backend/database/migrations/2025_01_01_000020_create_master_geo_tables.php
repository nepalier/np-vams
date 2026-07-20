<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('code')->unique();
            $table->timestamps();
            $table->index(['province_id', 'name_en']);
        });

        Schema::create('local_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ne');
            $table->string('type')->nullable(); // metropolitan|sub_metropolitan|municipality|rural_municipality
            $table->unsignedSmallInteger('ward_count')->default(0);
            $table->timestamps();
            $table->index(['district_id', 'name_en']);
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_level_id')->constrained('local_levels')->cascadeOnDelete();
            $table->unsignedTinyInteger('ward_number');
            $table->timestamps();
            $table->unique(['local_level_id', 'ward_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
        Schema::dropIfExists('local_levels');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
    }
};
