<?php

use App\Support\Geo\GeoMathService;

beforeEach(function () {
    $this->geoMath = new GeoMathService;
});

test('distance from a point to itself is zero', function () {
    expect($this->geoMath->haversineDistanceMeters(27.7172, 85.3240, 27.7172, 85.3240))->toBe(0.0);
});

test('one degree of latitude is approximately 111km, a well-known geodetic constant', function () {
    // Independent of any polygon/projection code in the service itself --
    // this checks the haversine formula against a fact anyone can verify.
    $distance = $this->geoMath->haversineDistanceMeters(0, 0, 1, 0);

    expect($distance)->toBeGreaterThan(110_000);
    expect($distance)->toBeLessThan(112_000);
});

test('distance is symmetric regardless of point order', function () {
    $a = $this->geoMath->haversineDistanceMeters(27.70, 85.30, 27.68, 85.32);
    $b = $this->geoMath->haversineDistanceMeters(27.68, 85.32, 27.70, 85.30);

    expect(round($a, 4))->toBe(round($b, 4));
});

test('polygon area for a square constructed with known metre offsets matches the expected area independently of the service', function () {
    // Build a 100m x 100m square by independently converting metre offsets
    // to degree offsets (the inverse of what the service itself does
    // internally) -- this is a genuine independent check, not circular.
    $refLat = 27.7000;
    $refLng = 85.3000;
    $sideMetres = 100.0;
    $earthRadius = 6_371_000.0;

    $latOffsetDeg = rad2deg($sideMetres / $earthRadius);
    $lngOffsetDeg = rad2deg($sideMetres / ($earthRadius * cos(deg2rad($refLat))));

    $square = [
        ['lat' => $refLat, 'lng' => $refLng],
        ['lat' => $refLat, 'lng' => $refLng + $lngOffsetDeg],
        ['lat' => $refLat + $latOffsetDeg, 'lng' => $refLng + $lngOffsetDeg],
        ['lat' => $refLat + $latOffsetDeg, 'lng' => $refLng],
    ];

    $area = $this->geoMath->polygonAreaSquareMeters($square);

    // Expected 100m * 100m = 10,000 sqm -- allow small tolerance for the
    // projection approximation itself.
    expect($area)->toBeGreaterThan(9_900);
    expect($area)->toBeLessThan(10_100);
});

test('a larger polygon has a larger area than a smaller one built the same way', function () {
    $small = [['lat' => 27.70, 'lng' => 85.30], ['lat' => 27.70, 'lng' => 85.3005], ['lat' => 27.7005, 'lng' => 85.3005], ['lat' => 27.7005, 'lng' => 85.30]];
    $large = [['lat' => 27.70, 'lng' => 85.30], ['lat' => 27.70, 'lng' => 85.31], ['lat' => 27.71, 'lng' => 85.31], ['lat' => 27.71, 'lng' => 85.30]];

    expect($this->geoMath->polygonAreaSquareMeters($large))->toBeGreaterThan($this->geoMath->polygonAreaSquareMeters($small));
});

test('rejects a polygon with fewer than 3 vertices', function () {
    $this->geoMath->polygonAreaSquareMeters([['lat' => 27.7, 'lng' => 85.3], ['lat' => 27.71, 'lng' => 85.31]]);
})->throws(InvalidArgumentException::class);

test('distanceBetweenPoints returns null when either point is incomplete, rather than throwing or fabricating zero', function () {
    expect($this->geoMath->distanceBetweenPoints(27.7, 85.3, null, null))->toBeNull();
    expect($this->geoMath->distanceBetweenPoints(null, null, 27.7, 85.3))->toBeNull();
});
