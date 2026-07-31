<?php

declare(strict_types=1);

namespace App\Support\Text;

use InvalidArgumentException;

/**
 * Every one of the real bank valuation report formats this system was
 * built from requires currency amounts spelled out ("In Words Nrs. ...
 * only") -- for both Fair Market Value and Distress Value, without
 * exception. Uses Western (English) number grouping -- thousand/million/
 * billion -- matching the exact convention observed in the reference
 * documents themselves ("Four Million Three Hundred Sixty Thousand
 * only", "Ten Million Seven Hundred Sixty Thousand only"), NOT the
 * Nepali/Indian lakh-crore grouping some other contexts use -- verified
 * against those exact real figures in AmountToWordsConverterTest, not
 * invented test cases.
 */
class AmountToWordsConverter
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /** Largest-to-smallest so the recursive split below peels off the biggest unit first. */
    private const SCALES = [
        [1_000_000_000, 'Billion'],
        [1_000_000, 'Million'],
        [1_000, 'Thousand'],
        [100, 'Hundred'],
    ];

    public function convert(float $amount, string $currencyLabel = 'Rupees', bool $appendOnly = true): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Cannot convert a negative amount to words.');
        }

        $wholeAmount = (int) round($amount); // valuation report amounts are always whole currency units, never paisa-level precision

        $words = $wholeAmount === 0 ? 'Zero' : $this->convertInteger($wholeAmount);
        $suffix = $appendOnly ? ' only' : '';

        return trim("{$words} {$currencyLabel}{$suffix}");
    }

    private function convertInteger(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $tensDigit = intdiv($number, 10);
            $remainder = $number % 10;

            return trim(self::TENS[$tensDigit].($remainder > 0 ? ' '.self::ONES[$remainder] : ''));
        }

        foreach (self::SCALES as [$scaleValue, $scaleName]) {
            if ($number >= $scaleValue) {
                $count = intdiv($number, $scaleValue);
                $remainder = $number % $scaleValue;

                $countWords = $scaleValue === 100
                    ? self::ONES[$count] // "Four Hundred", not "Four Hundred" via recursive scale lookup
                    : $this->convertInteger($count);

                $remainderWords = $remainder > 0 ? ' '.$this->convertInteger($remainder) : '';

                return trim("{$countWords} {$scaleName}{$remainderWords}");
            }
        }

        return self::ONES[$number]; // unreachable given the < 100 branch above, kept for exhaustiveness
    }
}
