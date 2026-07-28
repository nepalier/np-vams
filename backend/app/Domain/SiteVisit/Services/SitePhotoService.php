<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Services;

use App\Domain\SiteVisit\Models\SitePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SitePhotoService
{
    public function __construct(private readonly PhotoWatermarkService $watermarkService) {}

    /**
     * @param  array<string, string>  $watermarkLines  from PhotoWatermarkService::buildStandardWatermarkLines()
     */
    public function upload(
        UploadedFile $file,
        string $tenantId,
        string $category,
        ?string $siteVisitId,
        ?string $propertyId,
        ?float $latitude,
        ?float $longitude,
        ?string $uploadedByUserId,
        array $watermarkLines,
    ): SitePhoto {
        $mimeType = $file->getMimeType();

        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Only JPEG, PNG, or WebP photos are accepted.');
        }

        $originalContents = file_get_contents($file->getRealPath());
        $hash = hash('sha256', $originalContents);

        // Section 18: "Detect duplicate photographs using file hashes" --
        // checked per-property, so the same physical photo genuinely
        // re-uploaded for a different property is not flagged (a valuer
        // photographing two adjacent, visually-similar boundary walls is
        // legitimate; the same file uploaded twice for the SAME property
        // almost never is).
        $duplicate = $propertyId !== null
            ? SitePhoto::where('property_id', $propertyId)->where('file_hash_sha256', $hash)->first()
            : null;

        if ($duplicate !== null) {
            throw new RuntimeException("This photo appears to already be uploaded for this property (category: {$duplicate->category}, uploaded ".$duplicate->created_at->diffForHumans().').');
        }

        $disk = config('npvams.documents.disk');
        $basePath = sprintf('tenants/%s/site-photos/%s', $tenantId, Str::uuid());
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        };

        $originalPath = "{$basePath}/original.{$extension}";
        $watermarkedPath = "{$basePath}/watermarked.{$extension}";

        Storage::disk($disk)->put($originalPath, $originalContents);

        $watermarkedContents = $this->watermarkService->watermark($originalContents, $watermarkLines, $mimeType);
        Storage::disk($disk)->put($watermarkedPath, $watermarkedContents);

        return DB::transaction(fn () => SitePhoto::create([
            'tenant_id' => $tenantId,
            'site_visit_id' => $siteVisitId,
            'property_id' => $propertyId,
            'category' => $category,
            'storage_disk' => $disk,
            'original_path' => $originalPath,
            'watermarked_path' => $watermarkedPath,
            'file_hash_sha256' => $hash,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'captured_at' => now(),
            'uploaded_by_user_id' => $uploadedByUserId,
        ]));
    }
}
