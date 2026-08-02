<?php

declare(strict_types=1);

namespace App\Domain\Document\Http\Controllers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Document\Http\Requests\UpdateDocumentVerificationRequest;
use App\Domain\Document\Http\Requests\UploadDocumentRequest;
use App\Domain\Document\Models\PropertyDocument;
use App\Domain\Document\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Documents attach to the ValuationAssignment (the "checklist for this
 * case" shape every reference report's "List of Documents Used" section
 * matches) rather than directly to a Property/LandParcel -- an
 * assignment often needs identity/organizational documents (citizenship,
 * company registration) that have no natural link to a specific parcel.
 */
class DocumentController
{
    public function __construct(private readonly DocumentService $service) {}

    public function index(ValuationAssignment $assignment): JsonResponse
    {
        request()->user()->can('view', $assignment) || abort(403);

        $documents = PropertyDocument::where('documentable_type', ValuationAssignment::class)
            ->where('documentable_id', $assignment->id)
            ->orderBy('category')
            ->get();

        return response()->json(['data' => $documents]);
    }

    public function store(UploadDocumentRequest $request, ValuationAssignment $assignment): JsonResponse
    {
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        if ($this->service->findDuplicateByHash($assignment, $hash) !== null) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'DuplicateDocument', 'detail' => 'This exact file has already been uploaded for this assignment.']],
            ], 422);
        }

        try {
            $document = $this->service->upload(
                $assignment,
                $file,
                $request->safe()->except('file'),
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'DocumentUploadError', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $document], 201);
    }

    public function updateVerification(UpdateDocumentVerificationRequest $request, PropertyDocument $document): JsonResponse
    {
        $document->update($request->validated());

        return response()->json(['data' => $document->fresh()]);
    }
}
