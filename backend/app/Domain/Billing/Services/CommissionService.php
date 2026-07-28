<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Models\ValuerCommission;
use App\Models\User;
use RuntimeException;

/**
 * calculate -> approve -> pay lifecycle, mirroring the invoice/payment
 * pattern from Phase 7 rather than inventing a separate shape. The
 * commission rate/amount is ALWAYS caller-supplied (never a hard-coded
 * percentage baked into this class) per Section 49's "do not hard-code
 * Nepal-specific valuation percentages" -- extended here to commission
 * rates, which are just as firm-specific as an LTV or haircut percentage.
 */
class CommissionService
{
    public function calculate(
        ValuationAssignment $assignment,
        User $valuer,
        string $commissionType,
        ?float $commissionRatePct,
        ?float $fixedAmount,
    ): ValuerCommission {
        $existing = ValuerCommission::where('valuation_assignment_id', $assignment->id)
            ->where('user_id', $valuer->id)
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('A commission record already exists for this valuer on this assignment. Use the existing record rather than creating a duplicate.');
        }

        $baseAmount = (float) $assignment->total_fee;

        $commissionAmount = match ($commissionType) {
            'percentage' => $this->calculatePercentage($baseAmount, $commissionRatePct),
            'fixed' => $this->calculateFixed($fixedAmount),
            default => throw new RuntimeException("Unknown commission_type: {$commissionType}"),
        };

        return ValuerCommission::create([
            'tenant_id' => $assignment->tenant_id,
            'valuation_assignment_id' => $assignment->id,
            'user_id' => $valuer->id,
            'commission_type' => $commissionType,
            'commission_rate_pct' => $commissionType === 'percentage' ? $commissionRatePct : null,
            'base_amount' => $baseAmount,
            'commission_amount' => $commissionAmount,
            'status' => 'pending',
        ]);
    }

    public function approve(ValuerCommission $commission, User $approver): ValuerCommission
    {
        if ($commission->status !== 'pending') {
            throw new RuntimeException("Only a pending commission can be approved (current status: {$commission->status}).");
        }

        $commission->forceFill([
            'status' => 'approved',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $commission->fresh();
    }

    public function markPaid(ValuerCommission $commission, ?string $paymentReference): ValuerCommission
    {
        if ($commission->status !== 'approved') {
            throw new RuntimeException("Only an approved commission can be marked paid (current status: {$commission->status}).");
        }

        $commission->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ])->save();

        return $commission->fresh();
    }

    public function cancel(ValuerCommission $commission, string $reason): ValuerCommission
    {
        if ($commission->status === 'paid') {
            throw new RuntimeException('A commission that has already been paid cannot be cancelled.');
        }

        $commission->forceFill(['status' => 'cancelled', 'remarks' => $reason])->save();

        return $commission->fresh();
    }

    private function calculatePercentage(float $baseAmount, ?float $ratePct): float
    {
        if ($ratePct === null || $ratePct <= 0) {
            throw new RuntimeException('commission_rate_pct must be a positive number for a percentage-type commission.');
        }

        return round($baseAmount * $ratePct / 100, 2);
    }

    private function calculateFixed(?float $fixedAmount): float
    {
        if ($fixedAmount === null || $fixedAmount <= 0) {
            throw new RuntimeException('A positive fixed_amount is required for a fixed-type commission.');
        }

        return round($fixedAmount, 2);
    }
}
