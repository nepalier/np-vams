<?php

declare(strict_types=1);

namespace App\Domain\Risk\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiskScoreBand extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'min_score', 'max_score', 'category'];

    protected $casts = ['min_score' => 'decimal:2', 'max_score' => 'decimal:2'];
}
