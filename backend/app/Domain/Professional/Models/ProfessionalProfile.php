<?php

declare(strict_types=1);

namespace App\Domain\Professional\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalProfile extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'nec_registration_number', 'professional_license_number',
        'registration_validity_date', 'license_expiry_date', 'is_active',
    ];

    protected $casts = [
        'registration_validity_date' => 'date',
        'license_expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
