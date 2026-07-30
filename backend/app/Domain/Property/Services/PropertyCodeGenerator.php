<?php

declare(strict_types=1);

namespace App\Domain\Property\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Same MySQL named-lock pattern as AssignmentNumberGenerator/InvoiceNumberGenerator -- MAX()+1 is not safe under concurrent creation. */
class PropertyCodeGenerator
{
    public function next(string $tenantId): string
    {
        $lockName = "property_code:{$tenantId}";

        $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName])->acquired;

        if (! $acquired) {
            throw new RuntimeException('Could not acquire the property-numbering lock within 10 seconds. Please retry.');
        }

        try {
            $count = DB::table('properties')->where('tenant_id', $tenantId)->count();
            $sequence = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);

            return "PROP-{$sequence}";
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
