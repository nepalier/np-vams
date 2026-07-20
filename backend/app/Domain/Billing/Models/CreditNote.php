<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CreditNote extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'invoice_id', 'credit_note_number', 'amount', 'reason', 'issued_by_user_id', 'issued_at'];

    protected $casts = ['amount' => 'decimal:2', 'issued_at' => 'datetime'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
