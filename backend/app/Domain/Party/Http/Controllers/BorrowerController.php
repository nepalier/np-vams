<?php

declare(strict_types=1);

namespace App\Domain\Party\Http\Controllers;

use App\Domain\Party\Http\Requests\StoreBorrowerRequest;
use App\Domain\Party\Models\Borrower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BorrowerController
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('assignments.view') || abort(403);

        $borrowers = Borrower::query()
            ->when($request->filled('search'), fn ($q) => $q->where('name_en', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name_en')
            ->paginate($request->integer('per_page', 50));

        return response()->json($borrowers);
    }

    public function store(StoreBorrowerRequest $request): JsonResponse
    {
        $borrower = Borrower::create([
            'tenant_id' => $request->user()->tenant_id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $borrower], 201);
    }

    public function show(Borrower $borrower): JsonResponse
    {
        request()->user()->can('assignments.view') || abort(403);

        return response()->json(['data' => $borrower->load('guarantors')]);
    }
}
