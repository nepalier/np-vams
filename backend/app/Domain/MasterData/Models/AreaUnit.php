<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * conversion_to_sqm: multiplier to convert 1 unit of this area unit into
 * square metres. Land-parcel area conversion (Section 10 of the source
 * design) always converts through square metres as the canonical unit,
 * while the originally entered value + unit are preserved verbatim on the
 * parcel record — this table is the single source of truth for the
 * conversion factor, never hard-coded in application code.
 */
class AreaUnit extends Model
{
    protected $fillable = ['name_en', 'name_ne', 'code', 'conversion_to_sqm', 'region_context'];

    protected $casts = ['conversion_to_sqm' => 'decimal:8'];
}
