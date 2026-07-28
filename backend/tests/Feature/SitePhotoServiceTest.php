<?php

use App\Domain\Property\Models\Property;
use App\Domain\SiteVisit\Models\SitePhoto;
use App\Domain\SiteVisit\Services\SitePhotoService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(MasterDataSeeder::class);
    Storage::fake(config('npvams.documents.disk'));

    $this->tenant = Tenant::factory()->create();
    app()->instance('currentTenantId', $this->tenant->id);

    $this->property = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Test Property']);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->service = app(SitePhotoService::class);
    $this->watermarkLines = ['Assignment' => 'VAL-2082-000001'];
});

function fakeJpegUpload(string $name = 'photo.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 200, 150);
}

test('uploading a photo stores both an original and a watermarked file at different paths', function () {
    $photo = $this->service->upload(
        file: fakeJpegUpload(),
        tenantId: $this->tenant->id,
        category: 'front_view',
        siteVisitId: null,
        propertyId: $this->property->id,
        latitude: 27.7172,
        longitude: 85.3240,
        uploadedByUserId: $this->user->id,
        watermarkLines: $this->watermarkLines,
    );

    expect($photo->original_path)->not->toBe($photo->watermarked_path);
    Storage::disk($photo->storage_disk)->assertExists($photo->original_path);
    Storage::disk($photo->storage_disk)->assertExists($photo->watermarked_path);
});

test('uploading the exact same photo twice for the same property is rejected as a duplicate', function () {
    $file = fakeJpegUpload();
    $contents = file_get_contents($file->getRealPath());

    $this->service->upload(
        file: $file, tenantId: $this->tenant->id, category: 'front_view', siteVisitId: null,
        propertyId: $this->property->id, latitude: null, longitude: null,
        uploadedByUserId: $this->user->id, watermarkLines: $this->watermarkLines,
    );

    // Re-create an UploadedFile pointing at a new tmp copy of the SAME bytes
    // (simulates the same physical photo being uploaded again).
    $tmpPath = tempnam(sys_get_temp_dir(), 'dup_');
    file_put_contents($tmpPath, $contents);
    $duplicateUpload = new UploadedFile($tmpPath, 'photo2.jpg', 'image/jpeg', null, true);

    $this->service->upload(
        file: $duplicateUpload, tenantId: $this->tenant->id, category: 'rear_view', siteVisitId: null,
        propertyId: $this->property->id, latitude: null, longitude: null,
        uploadedByUserId: $this->user->id, watermarkLines: $this->watermarkLines,
    );
})->throws(RuntimeException::class);

test('the same photo bytes ARE allowed across two different properties -- duplicate detection is per-property, not global', function () {
    $file = fakeJpegUpload();
    $contents = file_get_contents($file->getRealPath());

    $this->service->upload(
        file: $file, tenantId: $this->tenant->id, category: 'front_view', siteVisitId: null,
        propertyId: $this->property->id, latitude: null, longitude: null,
        uploadedByUserId: $this->user->id, watermarkLines: $this->watermarkLines,
    );

    $otherProperty = Property::create(['tenant_id' => $this->tenant->id, 'property_name' => 'Other Property']);

    $tmpPath = tempnam(sys_get_temp_dir(), 'dup2_');
    file_put_contents($tmpPath, $contents);
    $secondUpload = new UploadedFile($tmpPath, 'photo3.jpg', 'image/jpeg', null, true);

    $photo = $this->service->upload(
        file: $secondUpload, tenantId: $this->tenant->id, category: 'front_view', siteVisitId: null,
        propertyId: $otherProperty->id, latitude: null, longitude: null,
        uploadedByUserId: $this->user->id, watermarkLines: $this->watermarkLines,
    );

    expect($photo->property_id)->toBe($otherProperty->id);
    expect(SitePhoto::count())->toBe(2);
});
