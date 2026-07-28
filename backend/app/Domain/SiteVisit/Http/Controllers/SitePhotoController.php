<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Http\Controllers;

use App\Domain\SiteVisit\Http\Requests\UploadSitePhotoRequest;
use App\Domain\SiteVisit\Services\PhotoWatermarkService;
use App\Domain\SiteVisit\Services\SitePhotoService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SitePhotoController
{
    public function __construct(
        private readonly SitePhotoService $service,
        private readonly PhotoWatermarkService $watermarkService,
    ) {}

    public function store(UploadSitePhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        $watermarkLines = $this->watermarkService->buildStandardWatermarkLines(
            assignmentNumber: $request->string('assignment_number')->toString(),
            propertyCode: $request->input('property_code'),
            latitude: $request->float('latitude') ?: null,
            longitude: $request->float('longitude') ?: null,
            valuerName: $user->name,
            category: $request->string('category')->toString(),
        );

        try {
            $photo = $this->service->upload(
                file: $request->file('photo'),
                tenantId: $user->tenant_id,
                category: $request->string('category')->toString(),
                siteVisitId: $request->input('site_visit_id'),
                propertyId: $request->input('property_id'),
                latitude: $request->float('latitude') ?: null,
                longitude: $request->float('longitude') ?: null,
                uploadedByUserId: $user->id,
                watermarkLines: $watermarkLines,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [['status' => '422', 'title' => 'PhotoUploadError', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return response()->json(['data' => $photo], 201);
    }
}
