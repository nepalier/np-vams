<?php

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Billing\Services\CommissionService;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $client = Client::create(['tenant_id' => $this->tenant->id, 'name_en' => 'Bank', 'client_type' => 'commercial_bank']);
    $this->valuer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->approver = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->assignment = ValuationAssignment::create([
        'tenant_id' => $this->tenant->id, 'assignment_number' => 'VAL-2082-000001',
        'fiscal_year_id' => FiscalYear::where('is_current', true)->first()->id, 'client_id' => $client->id,
        'assignment_date' => now(), 'priority' => 'normal',
        'valuation_purpose_id' => ValuationPurpose::first()->id, 'status' => 'draft',
        'assignment_fee' => 50000,
    ]);

    $this->service = app(CommissionService::class);
});

test('a percentage commission is calculated correctly from the assignment total fee', function () {
    $commission = $this->service->calculate($this->assignment, $this->valuer, 'percentage', 20.0, null);

    // assignment_fee 50000, no vat/travel/discount -> total_fee = 50000; 20% = 10000
    expect((float) $commission->commission_amount)->toBe(10000.0);
    expect($commission->status)->toBe('pending');
});

test('a fixed commission uses the fixed amount regardless of the assignment fee', function () {
    $commission = $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 7500.0);

    expect((float) $commission->commission_amount)->toBe(7500.0);
});

test('cannot create a second commission for the same valuer on the same assignment', function () {
    $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 5000.0);

    $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 5000.0);
})->throws(RuntimeException::class);

test('a pending commission cannot be marked paid without first being approved', function () {
    $commission = $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 5000.0);

    $this->service->markPaid($commission, 'REF123');
})->throws(RuntimeException::class);

test('the full approve then pay lifecycle works in order', function () {
    $commission = $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 5000.0);

    $approved = $this->service->approve($commission, $this->approver);
    expect($approved->status)->toBe('approved');
    expect($approved->approved_by_user_id)->toBe($this->approver->id);

    $paid = $this->service->markPaid($approved, 'REF123');
    expect($paid->status)->toBe('paid');
    expect($paid->payment_reference)->toBe('REF123');
});

test('a paid commission cannot be cancelled', function () {
    $commission = $this->service->calculate($this->assignment, $this->valuer, 'fixed', null, 5000.0);
    $this->service->approve($commission, $this->approver);
    $paid = $this->service->markPaid($commission->fresh(), 'REF123');

    $this->service->cancel($paid, 'changed my mind');
})->throws(RuntimeException::class);

test('a zero or negative percentage rate is rejected', function () {
    $this->service->calculate($this->assignment, $this->valuer, 'percentage', 0, null);
})->throws(RuntimeException::class);
