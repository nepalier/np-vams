<?php

declare(strict_types=1);

namespace App\Domain\Property\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandParcelCharacteristics extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'land_parcel_characteristics';

    protected $fillable = [
        'tenant_id', 'land_parcel_id', 'plot_shape', 'frontage_m', 'average_depth_m',
        'number_of_road_frontages', 'is_corner_plot', 'ground_level_relative_to_road', 'topography',
        'slope_percentage', 'soil_condition', 'drainage', 'flood_exposure', 'landslide_exposure',
        'river_proximity_m', 'high_tension_line_proximity', 'access_type', 'road_width_m', 'road_surface',
        'road_ownership', 'motorable_access', 'has_boundary_wall', 'has_encroachment', 'encroachment_details',
        'subdivision_potential', 'development_potential', 'environmental_advantage', 'has_scenic_view',
        'adverse_influence', 'marketability_rating', 'saleability_rating', 'neighbourhood_quality_rating', 'remarks',
    ];

    protected $casts = [
        'is_corner_plot' => 'boolean',
        'high_tension_line_proximity' => 'boolean',
        'motorable_access' => 'boolean',
        'has_boundary_wall' => 'boolean',
        'has_encroachment' => 'boolean',
        'has_scenic_view' => 'boolean',
    ];

    public function landParcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class);
    }
}
