<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrVerification extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'report_id', 'public_token', 'status'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
