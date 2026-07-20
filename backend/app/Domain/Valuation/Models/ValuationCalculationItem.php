<?php

declare(strict_types=1);

namespace App\Domain\Valuation\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationCalculationItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'valuation_calculation_id', 'item_type', 'reference_id',
        'label', 'quantity', 'rate', 'adjustment_factor', 'amount', 'sequence',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(ValuationCalculation::class, 'valuation_calculation_id');
    }
}
