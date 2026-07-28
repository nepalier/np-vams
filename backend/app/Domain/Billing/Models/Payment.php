<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use BelongsToTenant, HasUuids, LogsActivity;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'payment_date', 'amount', 'payment_method',
        'reference_number', 'received_by_user_id', 'remarks',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankStatementLine(): HasOne
    {
        return $this->hasOne(BankStatementLine::class, 'matched_payment_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
