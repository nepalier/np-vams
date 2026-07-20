<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\PropertyType;
use App\Domain\MasterData\Models\ValuationPurpose;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MasterDataSeeder extends Seeder
{
    private const AREA_UNITS = [
        // code, name_en, name_ne, conversion_to_sqm, region_context
        ['bigha', 'Bigha', 'बिघा', 6772.63, 'Terai'],
        ['kattha', 'Kattha', 'कठ्ठा', 338.63, 'Terai'],
        ['dhur', 'Dhur', 'धुर', 16.93, 'Terai'],
        ['ropani', 'Ropani', 'रोपनी', 508.72, 'Hill'],
        ['aana', 'Aana', 'आना', 31.80, 'Hill'],
        ['paisa', 'Paisa', 'पैसा', 7.95, 'Hill'],
        ['daam', 'Daam', 'दाम', 1.99, 'Hill'],
        ['sqm', 'Square Metre', 'वर्ग मिटर', 1.0, null],
        ['sqft', 'Square Feet', 'वर्ग फिट', 0.092903, null],
        ['hectare', 'Hectare', 'हेक्टर', 10000.0, null],
        ['acre', 'Acre', 'एकड', 4046.86, null],
    ];

    private const PROPERTY_TYPES = [
        'vacant_land' => 'Vacant Land', 'residential_building' => 'Residential Building',
        'commercial_building' => 'Commercial Building', 'mixed_use_building' => 'Mixed-Use Building',
        'industrial_property' => 'Industrial Property', 'apartment' => 'Apartment',
        'housing_unit' => 'Housing Unit', 'hotel' => 'Hotel', 'resort' => 'Resort',
        'hospital' => 'Hospital', 'school' => 'School', 'college' => 'College',
        'warehouse' => 'Warehouse', 'agricultural_land' => 'Agricultural Land', 'farm' => 'Farm',
        'institutional_property' => 'Institutional Property', 'special_purpose_property' => 'Special-Purpose Property',
        'plant_and_machinery' => 'Plant and Machinery', 'equipment' => 'Equipment', 'other' => 'Other',
    ];

    private const VALUATION_PURPOSES = [
        'mortgage' => 'Mortgage', 'loan_security' => 'Loan Security', 'purchase' => 'Purchase',
        'sale' => 'Sale', 'insurance' => 'Insurance', 'taxation' => 'Taxation',
        'financial_reporting' => 'Financial Reporting', 'court_proceeding' => 'Court Proceeding',
        'compensation' => 'Compensation', 'auction' => 'Auction', 'rent_fixation' => 'Rent Fixation',
        'merger_acquisition' => 'Merger and Acquisition', 'internal_asset_management' => 'Internal Asset Management',
        'government_acquisition' => 'Government Acquisition', 'revaluation' => 'Revaluation', 'other' => 'Other',
    ];

    public function run(): void
    {
        foreach (self::AREA_UNITS as [$code, $nameEn, $nameNe, $conversion, $region]) {
            AreaUnit::updateOrCreate(['code' => $code], [
                'name_en' => $nameEn, 'name_ne' => $nameNe,
                'conversion_to_sqm' => $conversion, 'region_context' => $region,
            ]);
        }

        foreach (self::PROPERTY_TYPES as $code => $nameEn) {
            PropertyType::updateOrCreate(['code' => $code], ['name_en' => $nameEn, 'name_ne' => $nameEn, 'is_active' => true]);
        }

        foreach (self::VALUATION_PURPOSES as $code => $nameEn) {
            ValuationPurpose::updateOrCreate(['code' => $code], ['name_en' => $nameEn, 'name_ne' => $nameEn, 'is_active' => true]);
        }

        // Current Nepal fiscal year (Shrawan-Ashad). Adjust dates each year via
        // the master-data admin UI in a later phase — never hard-coded elsewhere.
        FiscalYear::query()->update(['is_current' => false]);
        FiscalYear::updateOrCreate(
            ['code_bs' => '2082/83'],
            ['starts_on' => Carbon::parse('2025-07-17'), 'ends_on' => Carbon::parse('2026-07-16'), 'is_current' => true]
        );

        $this->command?->info('Seeded area units, property types, valuation purposes, and current fiscal year.');
    }
}
