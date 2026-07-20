<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Models;

use App\Domain\Property\Models\Property;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssignmentProperty extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'valuation_assignment_id', 'property_id', 'sequence'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ValuationAssignment::class, 'valuation_assignment_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Party\Models\PropertyOwner::class,
            'assignment_property_owners'
        );
    }
}
