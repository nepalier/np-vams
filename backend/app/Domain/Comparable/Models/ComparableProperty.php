<?php

declare(strict_types=1);

namespace App\Domain\Comparable\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComparableProperty extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'property_type_id', 'location', 'latitude', 'longitude', 'district_id', 'transaction_date',
        'offer_date', 'parcel_area_sqm', 'built_up_area_sqm', 'road_width_m', 'road_surface', 'frontage_m',
        'shape', 'land_use', 'is_corner', 'transaction_value', 'asking_value', 'verified_value', 'unit_rate',
        'data_source', 'contact_source', 'verification_status', 'reliability_grade', 'file_hash_sha256', 'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date', 'offer_date' => 'date', 'is_corner' => 'boolean',
    ];
}
