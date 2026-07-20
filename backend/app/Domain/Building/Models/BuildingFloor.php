<?php

declare(strict_types=1);

namespace App\Domain\Building\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuildingFloor extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'building_id', 'floor_name', 'floor_number',
        'approved_area_sqm', 'measured_area_sqm', 'valuation_area_sqm', 'covered_area_sqm',
        'balcony_area_sqm', 'staircase_area_sqm', 'common_area_sqm', 'parking_area_sqm',
        'commercial_area_sqm', 'residential_area_sqm', 'unauthorized_area_sqm', 'floor_use',
        'number_of_rooms', 'kitchen_count', 'toilet_count', 'bathroom_count', 'store_count',
        'completion_percentage', 'construction_class', 'unit_construction_rate',
        'depreciation_percentage', 'adjusted_value', 'remarks',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
