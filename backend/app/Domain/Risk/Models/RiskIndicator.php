<?php

declare(strict_types=1);

namespace App\Domain\Risk\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiskIndicator extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'code', 'label_en', 'label_ne', 'default_weight', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'default_weight' => 'decimal:2'];
}
