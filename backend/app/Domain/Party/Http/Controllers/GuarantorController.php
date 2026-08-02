<?php

declare(strict_types=1);

namespace App\Domain\Party\Http\Controllers;

use App\Domain\Party\Http\Requests\StoreGuarantorRequest;
use App\Domain\Party\Models\Borrower;
use App\Domain\Party\Models\Guarantor;
use Illuminate\Http\JsonResponse;

class GuarantorController
{
    public function index(Borrower $borrower): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $borrower->guarantors]);
    }

    public function store(StoreGuarantorRequest $request, Borrower $borrower): JsonResponse
    {
        $guarantor = Guarantor::create([
            'tenant_id' => $borrower->tenant_id,
            'borrower_id' => $borrower->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $guarantor], 201);
    }
}
