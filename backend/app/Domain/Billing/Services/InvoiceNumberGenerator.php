<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\MasterData\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

/**
 * Same MySQL named-lock pattern as AssignmentNumberGenerator (Phase 3) --
 * MAX()+1 is not safe under concurrent invoice creation.
 */
class InvoiceNumberGenerator
{
    public function next(string $tenantId, FiscalYear $fiscalYear): string
    {
        $bsYear = explode('/', $fiscalYear->code_bs)[0];
        $lockName = "invoice_number:{$tenantId}:{$fiscalYear->id}";

        $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName])->acquired;

        if (! $acquired) {
            throw new \RuntimeException('Could not acquire the invoice-numbering lock within 10 seconds. Please retry.');
        }

        try {
            $count = DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->count();

            $sequence = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);

            return "INV-{$bsYear}-{$sequence}";
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
