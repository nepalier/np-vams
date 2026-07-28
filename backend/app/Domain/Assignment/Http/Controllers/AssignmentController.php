<?php

declare(strict_types=1);

namespace App\Domain\Assignment\Http\Controllers;

use App\Domain\Assignment\Http\Requests\StoreAssignmentRequest;
use App\Domain\Assignment\Http\Resources\AssignmentResource;
use App\Domain\Assignment\Models\AssignmentProperty;
use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Assignment\Services\AssignmentNumberGenerator;
use App\Domain\MasterData\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignmentController
{
    public function __construct(private readonly AssignmentNumberGenerator $numberGenerator) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction('viewAny');

        $assignments = ValuationAssignment::query()
            ->with(['client', 'valuationPurpose'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->string('client_id')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return AssignmentResource::collection($assignments)->response();
    }

    public function show(ValuationAssignment $assignment): JsonResponse
    {
        request()->user()->can('view', $assignment) || abort(403);

        return (new AssignmentResource($assignment->load([
            'properties', 'client', 'valuationPurpose', 'assignedValuer', 'assignedApprover',
        ])))->response();
    }

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $currentFiscalYear = FiscalYear::where('is_current', true)->firstOrFail();
        $user = $request->user();

        $assignment = DB::transaction(function () use ($request, $currentFiscalYear, $user) {
            $assignmentNumber = $this->numberGenerator->next($user->tenant_id, $currentFiscalYear);

            $assignment = ValuationAssignment::create([
                'organization_id' => $user->organization_id,
                'organization_branch_id' => $user->organization_branch_id,
                'assignment_number' => $assignmentNumber,
                'fiscal_year_id' => $currentFiscalYear->id,
                'status' => 'draft',
                ...$request->safe()->except('property_ids'),
            ]);

            foreach ($request->input('property_ids') as $index => $propertyId) {
                AssignmentProperty::create([
                    'valuation_assignment_id' => $assignment->id,
                    'property_id' => $propertyId,
                    'sequence' => $index + 1,
                ]);
            }

            return $assignment;
        });

        return (new AssignmentResource($assignment->load('properties')))
            ->response()
            ->setStatusCode(201);
    }

    private function authorizeAction(string $ability): void
    {
        request()->user()->can($ability, ValuationAssignment::class) || abort(403);
    }
}
