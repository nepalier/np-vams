<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Client\Models\ClientBranch;
use App\Domain\Party\Models\Borrower;
use App\Domain\Workflow\Models\WorkflowTransition;
use App\Models\Organization;
use App\Support\Tenancy\ScopedToClientPortal;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ValuationAssignment extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, ScopedToClientPortal, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'organization_id', 'organization_branch_id', 'assignment_number', 'fiscal_year_id',
        'client_id', 'client_branch_id', 'assignment_date', 'requested_completion_date', 'priority',
        'valuation_purpose_id', 'loan_application_number', 'borrower_id', 'contact_person',
        'requested_loan_amount', 'assigned_valuer_id', 'assigned_surveyor_id', 'assigned_reviewer_id',
        'assigned_approver_id', 'assignment_fee', 'travel_fee', 'additional_charges', 'vat_amount',
        'discount_amount', 'total_fee', 'payment_status', 'status', 'sla_duration_hours', 'sla_due_at',
        'instruction_letter_path', 'client_remarks', 'internal_remarks', 'cancellation_reason',
        'is_revaluation', 'parent_assignment_id',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'requested_completion_date' => 'date',
        'sla_due_at' => 'datetime',
        'is_revaluation' => 'boolean',
        'requested_loan_amount' => 'decimal:2',
        'assignment_fee' => 'decimal:2',
        'travel_fee' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_fee' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ValuationAssignment $assignment) {
            $assignment->total_fee = round(
                (float) $assignment->assignment_fee
                + (float) $assignment->travel_fee
                + (float) $assignment->additional_charges
                + (float) $assignment->vat_amount
                - (float) $assignment->discount_amount,
                2
            );
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function valuationPurpose(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\MasterData\Models\ValuationPurpose::class);
    }

    public function clientBranch(): BelongsTo
    {
        return $this->belongsTo(ClientBranch::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function assignedValuer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_valuer_id');
    }

    public function assignedApprover(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_approver_id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(AssignmentProperty::class, 'valuation_assignment_id');
    }

    public function parentAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_assignment_id');
    }

    public function workflowTransitions()
    {
        return $this->morphMany(WorkflowTransition::class, 'transitionable', 'transitionable_type', 'transitionable_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
