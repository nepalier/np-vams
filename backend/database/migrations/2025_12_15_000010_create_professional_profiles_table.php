<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section 5 of the source spec ("Professional Valuer and Engineer
     * Management") was never built across this whole project -- this is
     * a deliberately MINIMAL version, just the fields the license/
     * registration expiry alert (Section 5: "Generate automated alerts
     * before license or registration expiry") actually needs. The full
     * module (photograph, academic qualifications, bank enlistments,
     * training records, signature specimen, etc.) remains a real,
     * separate, larger follow-on -- not silently pretended to be covered
     * by this.
     */
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('nec_registration_number')->nullable();
            $table->string('professional_license_number')->nullable();
            $table->date('registration_validity_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
