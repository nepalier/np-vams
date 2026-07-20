<?php

declare(strict_types=1);

namespace App\Domain\Party\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Borrower extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'party_kind', 'name_en', 'name_ne', 'citizenship_number', 'passport_number',
        'company_registration_number', 'pan_or_vat_number', 'date_of_birth', 'incorporation_date',
        'gender', 'permanent_address', 'current_address', 'district_id', 'telephone', 'mobile', 'email',
        'consent_for_inspection', 'consent_for_data_processing', 'relationship_with_owner', 'remarks',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'incorporation_date' => 'date',
        'consent_for_inspection' => 'boolean',
        'consent_for_data_processing' => 'boolean',
    ];

    public function guarantors(): HasMany
    {
        return $this->hasMany(Guarantor::class);
    }
}
