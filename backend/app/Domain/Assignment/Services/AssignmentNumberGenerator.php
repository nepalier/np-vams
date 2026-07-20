<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Services;

use App\Domain\MasterData\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

/**
 * Generates tenant-scoped, fiscal-year-scoped, sequential assignment
 * numbers in the VAL-{fiscal_year_bs}-{6-digit-sequence} format from
 * Step 1 Section 6, e.g. VAL-2083-000001.
 *
 * Uses a MySQL named lock (GET_LOCK/RELEASE_LOCK) rather than
 * MAX(sequence)+1, which is not safe under concurrent assignment creation
 * (two requests could read the same MAX and collide). Named locks are
 * connection-scoped in MySQL (not transaction-scoped like Postgres
 * advisory locks), so the release happens in a finally block rather than
 * relying on transaction end.
 */
class AssignmentNumberGenerator
{
    public function next(string $tenantId, FiscalYear $fiscalYear): string
    {
        $bsYear = explode('/', $fiscalYear->code_bs)[0];
        $lockName = "assignment_number:{$tenantId}:{$fiscalYear->id}";

        $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName])->acquired;

        if (! $acquired) {
            throw new \RuntimeException('Could not acquire the assignment-numbering lock within 10 seconds. Please retry.');
        }

        try {
            $count = DB::table('valuation_assignments')
                ->where('tenant_id', $tenantId)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->count();

            $sequence = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);

            return "VAL-{$bsYear}-{$sequence}";
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
