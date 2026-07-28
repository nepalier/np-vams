<?php

use App\Domain\SiteVisit\Services\PhotoWatermarkService;

beforeEach(function () {
    $this->service = new PhotoWatermarkService;

    // Real in-memory JPEG, not a fixture file -- portable across any
    // environment running these tests, no bundled test image needed.
    $image = imagecreatetruecolor(200, 150);
    $blue = imagecolorallocate($image, 30, 60, 200);
    imagefill($image, 0, 0, $blue);
    ob_start();
    imagejpeg($image);
    $this->sampleJpeg = ob_get_clean();
    imagedestroy($image);
});

test('the watermarked image is taller than the original, since the watermark band extends the canvas rather than covering the photo', function () {
    $watermarked = $this->service->watermark($this->sampleJpeg, ['Assignment' => 'VAL-2082-000001'], 'image/jpeg');

    $originalImage = imagecreatefromstring($this->sampleJpeg);
    $watermarkedImage = imagecreatefromstring($watermarked);

    expect(imagesx($watermarkedImage))->toBe(imagesx($originalImage)); // width unchanged
    expect(imagesy($watermarkedImage))->toBeGreaterThan(imagesy($originalImage)); // height extended

    imagedestroy($originalImage);
    imagedestroy($watermarkedImage);
});

test('the watermarked image band grows with the number of lines provided', function () {
    $oneLineImage = imagecreatefromstring(
        $this->service->watermark($this->sampleJpeg, ['A' => '1'], 'image/jpeg')
    );
    $sevenLineImage = imagecreatefromstring(
        $this->service->watermark($this->sampleJpeg, [
            'Assignment' => 'VAL-2082-000001', 'Property' => 'PROP-001', 'Date' => '2026-07-28',
            'Time' => '10:00:00', 'GPS' => '27.7, 85.3', 'Valuer' => 'Test Valuer', 'Category' => 'Front View',
        ], 'image/jpeg')
    );

    expect(imagesy($sevenLineImage))->toBeGreaterThan(imagesy($oneLineImage));
});

test('buildStandardWatermarkLines produces exactly the fields Section 18 requires, in order', function () {
    $lines = $this->service->buildStandardWatermarkLines(
        assignmentNumber: 'VAL-2082-000001',
        propertyCode: 'PROP-001',
        latitude: 27.7172,
        longitude: 85.3240,
        valuerName: 'Ram Sharma',
        category: 'front_view',
        capturedAt: new DateTime('2026-07-28 10:30:00'),
    );

    expect(array_keys($lines))->toBe(['Assignment', 'Property', 'Date', 'Time', 'GPS', 'Valuer', 'Category']);
    expect($lines['Date'])->toBe('2026-07-28');
    expect($lines['GPS'])->toBe('27.717200, 85.324000');
    expect($lines['Category'])->toBe('Front View');
});

test('missing GPS coordinates render as "Not available" rather than a blank or fabricated value', function () {
    $lines = $this->service->buildStandardWatermarkLines(
        assignmentNumber: 'VAL-2082-000001', propertyCode: null,
        latitude: null, longitude: null, valuerName: 'Ram Sharma', category: 'other',
    );

    expect($lines['GPS'])->toBe('Not available');
});

test('rejects an unsupported image type rather than silently corrupting the file', function () {
    $this->service->watermark('not a real image', ['A' => '1'], 'image/gif');
})->throws(InvalidArgumentException::class);
