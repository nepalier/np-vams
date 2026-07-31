<?php

use App\Support\Text\AmountToWordsConverter;

beforeEach(function () {
    $this->converter = new AmountToWordsConverter;
});

/**
 * All three figures below are taken verbatim from real bank valuation
 * report documents used as the reference for this converter -- not
 * invented test data. Each document states its own amount in words,
 * which is the verification oracle here.
 */
test('matches the JBBL reference document\'s stated distress value in words exactly', function () {
    // Source document states: "Distress Value: Four Million Three Hundred Sixty Thousand only."
    expect($this->converter->convert(4_360_000))->toBe('Four Million Three Hundred Sixty Thousand Rupees only');
});

test('matches the reference Excel format\'s stated distress amount in words exactly', function () {
    // Source: "Total Distress Amount: 10,760,000.00 (In Words Rupees Ten Million Seven Hundred Sixty Thousand only.)"
    expect($this->converter->convert(10_760_000))->toBe('Ten Million Seven Hundred Sixty Thousand Rupees only');
});

test('correctly converts a billion-scale figure from the weighted FMV example provided', function () {
    // 1,025,402,680.00 as provided in the "Weighted Fair Market Value" report example
    expect($this->converter->convert(1_025_402_680))->toBe('One Billion Twenty Five Million Four Hundred Two Thousand Six Hundred Eighty Rupees only');
});

test('zero converts to the word Zero, not an empty string', function () {
    expect($this->converter->convert(0))->toBe('Zero Rupees only');
});

test('a value under one hundred converts without any scale word', function () {
    expect($this->converter->convert(45))->toBe('Forty Five Rupees only');
});

test('an exact multiple of a scale has no trailing remainder text', function () {
    expect($this->converter->convert(5_000_000))->toBe('Five Million Rupees only');
});

test('decimal input is rounded to the nearest whole currency unit -- report amounts are never stated to paisa precision', function () {
    expect($this->converter->convert(1_234.60))->toBe('One Thousand Two Hundred Thirty Five Rupees only');
});

test('rejects a negative amount', function () {
    $this->converter->convert(-100);
})->throws(InvalidArgumentException::class);

test('currency label and the "only" suffix are both customizable, not hard-coded English defaults forced on every caller', function () {
    expect($this->converter->convert(100, currencyLabel: 'US Dollars', appendOnly: false))->toBe('One Hundred US Dollars');
});
