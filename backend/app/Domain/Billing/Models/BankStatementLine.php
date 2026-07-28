<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'transaction_date', 'description', 'reference_number', 'amount',
        'is_matched', 'matched_payment_id', 'match_method', 'import_batch_id', 'imported_by_user_id',
    ];

    protected $casts = ['transaction_date' => 'date', 'is_matched' => 'boolean', 'amount' => 'decimal:2'];

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }
}
