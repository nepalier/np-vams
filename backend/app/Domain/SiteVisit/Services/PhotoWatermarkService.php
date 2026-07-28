<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Section 18: "Automatically watermark photographs with: Assignment
 * number, Property ID, Date, Time, Latitude, Longitude, Valuer name,
 * Photograph category. Store original photograph and watermarked
 * photograph separately."
 *
 * Uses PHP's built-in GD extension with GD's bitmap font
 * (imagestring/imagestring) rather than imagettftext, deliberately --
 * TTF rendering needs a font FILE PATH bundled with the app and portable
 * across arbitrary shared-hosting environments where a specific font
 * might not be installed system-wide; GD's built-in bitmap fonts need
 * nothing external and render identically everywhere PHP's gd extension
 * itself is available (which the Dockerfile/cPanel Extensions manager
 * already require for this app regardless).
 */
class PhotoWatermarkService
{
    private const FONT_SIZE = 5; // GD built-in font 1-5, 5 is the largest bitmap font
    private const LINE_HEIGHT = 16;
    private const PADDING = 10;

    /**
     * @param  array<string, string>  $lines  ordered label => value pairs to render, e.g.
     *         ['Assignment' => 'VAL-2082-000001', 'Property' => 'PROP-001', ...]
     * @return string  the watermarked image as raw bytes (same format as input: jpeg or png)
     */
    public function watermark(string $imageContents, array $lines, string $mimeType): string
    {
        $source = $this->decode($imageContents, $mimeType);

        $width = imagesx($source);
        $height = imagesy($source);

        $barHeight = self::PADDING * 2 + count($lines) * self::LINE_HEIGHT;

        // Extend the canvas downward rather than overlay-and-obscure part
        // of the photo itself -- the watermark band sits below the actual
        // image content, never covering it.
        $canvas = imagecreatetruecolor($width, $height + $barHeight);

        $backgroundBlack = imagecolorallocate($canvas, 0, 0, 0);
        imagefill($canvas, 0, 0, $backgroundBlack);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        $barColor = imagecolorallocate($canvas, 20, 20, 20);
        imagefilledrectangle($canvas, 0, $height, $width, $height + $barHeight, $barColor);

        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        $y = $height + self::PADDING;

        foreach ($lines as $label => $value) {
            $text = "{$label}: {$value}";
            imagestring($canvas, self::FONT_SIZE, self::PADDING, $y, $text, $textColor);
            $y += self::LINE_HEIGHT;
        }

        $encoded = $this->encode($canvas, $mimeType);

        imagedestroy($source);
        imagedestroy($canvas);

        return $encoded;
    }

    /**
     * Convenience builder for the exact field set Section 18 requires,
     * in a stable order -- callers pass the raw values, this fixes the
     * label wording/ordering in one place rather than each call site
     * building its own array.
     */
    public function buildStandardWatermarkLines(
        string $assignmentNumber,
        ?string $propertyCode,
        ?float $latitude,
        ?float $longitude,
        string $valuerName,
        string $category,
        ?\DateTimeInterface $capturedAt = null,
    ): array {
        $capturedAt ??= now();

        return [
            'Assignment' => $assignmentNumber,
            'Property' => $propertyCode ?? '—',
            'Date' => $capturedAt->format('Y-m-d'),
            'Time' => $capturedAt->format('H:i:s'),
            'GPS' => $latitude !== null && $longitude !== null
                ? sprintf('%.6f, %.6f', $latitude, $longitude)
                : 'Not available',
            'Valuer' => $valuerName,
            'Category' => str_replace('_', ' ', ucwords($category, '_')),
        ];
    }

    private function decode(string $contents, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromstring($contents),
            'image/png' => imagecreatefromstring($contents),
            'image/webp' => imagecreatefromstring($contents),
            default => throw new InvalidArgumentException("Unsupported image type for watermarking: {$mimeType}"),
        };

        if ($image === false) {
            throw new RuntimeException('Could not decode image data -- file may be corrupt.');
        }

        return $image;
    }

    private function encode(\GdImage $image, string $mimeType): string
    {
        ob_start();

        $success = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image),
            'image/webp' => imagewebp($image, null, 90),
            default => throw new InvalidArgumentException("Unsupported image type for watermarking: {$mimeType}"),
        };

        $bytes = ob_get_clean();

        if (! $success || $bytes === false) {
            throw new RuntimeException('Failed to encode watermarked image.');
        }

        return $bytes;
    }
}
