<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Domain\MasterData\Models\AreaUnit;
use InvalidArgumentException;

/**
 * Immutable area value object. Always carries the ORIGINALLY entered value
 * and unit (never overwritten, per Step 1 Section 10) alongside the
 * canonical square-metre figure, so a caller never has to guess which one
 * a raw decimal column represents.
 */
final class Area
{
    private function __construct(
        public readonly float $value,
        public readonly AreaUnit $unit,
        public readonly float $squareMetres,
    ) {}

    public static function from(float $value, AreaUnit $unit): self
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Area cannot be negative.');
        }

        return new self($value, $unit, round($value * (float) $unit->conversion_to_sqm, 4));
    }

    public function convertTo(AreaUnit $targetUnit): self
    {
        $targetValue = $this->squareMetres / (float) $targetUnit->conversion_to_sqm;

        return self::from(round($targetValue, 4), $targetUnit);
    }
}
