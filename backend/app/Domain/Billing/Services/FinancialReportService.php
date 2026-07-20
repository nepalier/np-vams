<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Section 35: "Fiscal-year financial reports." */
class FinancialReportService
{
    public function fiscalYearSummary(int $fiscalYearId): array
    {
        $invoices = Invoice::where('fiscal_year_id', $fiscalYearId);

        return [
            'fiscal_year_id' => $fiscalYearId,
            'total_invoiced' => round((float) $invoices->clone()->sum('total_amount'), 2),
            'total_vat_collected' => round((float) $invoices->clone()->sum('vat_amount'), 2),
            'total_tds_withheld' => round((float) $invoices->clone()->sum('tds_amount'), 2),
            'total_collected' => round((float) Payment::whereHas(
                'invoice',
                fn ($q) => $q->where('fiscal_year_id', $fiscalYearId)
            )->sum('amount'), 2),
            'total_outstanding' => round((float) $invoices->clone()->sum('outstanding_amount'), 2),
            'invoice_count_by_status' => $invoices->clone()
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all(),
            'monthly_invoiced_totals' => $invoices->clone()
                ->select(DB::raw("DATE_FORMAT(issue_date, '%Y-%m') as month"), DB::raw('sum(total_amount) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->pluck('total', 'month')
                ->map(fn ($v) => round((float) $v, 2))
                ->all(),
        ];
    }
}
