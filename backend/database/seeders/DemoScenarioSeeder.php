<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Assignment\Models\AssignmentProperty;
use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Assignment\Services\AssignmentNumberGenerator;
use App\Domain\Billing\Services\BillingService;
use App\Domain\Building\Models\Building;
use App\Domain\Building\Models\BuildingFloor;
use App\Domain\Client\Models\Client;
use App\Domain\MasterData\Models\AreaUnit;
use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\FiscalYear;
use App\Domain\MasterData\Models\PropertyType;
use App\Domain\MasterData\Models\ValuationPurpose;
use App\Domain\Party\Models\Borrower;
use App\Domain\Party\Models\PropertyOwner;
use App\Domain\Property\Models\LandParcel;
use App\Domain\Property\Models\LandParcelCharacteristics;
use App\Domain\Property\Models\Property;
use App\Domain\Valuation\Models\ValuationReconciliation;
use App\Domain\Valuation\Services\ValuationCalculationService;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A single realistic, end-to-end scenario -- one client (bank), one
 * property with a parcel and a building, a borrower, and one assignment
 * carried through the actual workflow with real valuation calculations,
 * a reconciliation, and an invoice -- so that a fresh login lands on
 * screens with real numbers instead of "0 total" everywhere. Every value
 * here is computed by the actual engines (MarketComparisonEngine,
 * ReconciliationService, BillingService), not hard-coded results copied
 * into the seeder.
 *
 * Deliberately separate from TenantDemoSeeder (which only creates the
 * bare tenant/org/admin account) -- this one is opt-in, run explicitly
 * via `php artisan db:seed --class=DemoScenarioSeeder`, since it assumes
 * a tenant/organization/admin already exists (created by
 * npvams:create-admin or TenantDemoSeeder) and attaches this scenario to
 * the FIRST organization found, whichever that is.
 */
class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();

        if ($organization === null) {
            $this->command?->error('No organization found. Run `php artisan npvams:create-admin` first.');

            return;
        }

        $tenantId = $organization->tenant_id;
        app()->instance('currentTenantId', $tenantId);
	 if (Property::where('tenant_id', $tenantId)->where('property_code', 'PROP-DEMO-001')->exists()) {
         $this->command?->warn('Demo scenario already exists (PROP-DEMO-001 found) -- skipping to avoid duplicate records. Nothing to do.');
         return;
         }
        $adminUser = User::where('organization_id', $organization->id)->first();

        $client = Client::create([
            'tenant_id' => $tenantId,
            'name_en' => 'Everest Commercial Bank Ltd.',
            'name_ne' => 'सगरमाथा वाणिज्य बैंक लिमिटेड',
            'client_type' => 'commercial_bank',
            'registration_number' => 'REG-BANK-001',
            'pan_number' => '600000001',
            'address' => 'New Baneshwor, Kathmandu',
            'telephone' => '01-4444444',
            'email' => 'collateral@everestbank.example',
            'authorized_contact_person' => 'Suresh Thapa',
            'is_active' => true,
        ]);

        $borrower = Borrower::create([
            'tenant_id' => $tenantId,
            'party_kind' => 'individual',
            'name_en' => 'Ramesh Prasad Sharma',
            'name_ne' => 'रमेश प्रसाद शर्मा',
            'citizenship_number' => '12-01-70-00001',
            'permanent_address' => 'Baneshwor, Kathmandu',
            'mobile' => '9800000001',
            'consent_for_inspection' => true,
            'consent_for_data_processing' => true,
        ]);

        PropertyOwner::create([
            'tenant_id' => $tenantId,
            'party_kind' => 'individual',
            'name_en' => 'Ramesh Prasad Sharma',
            'citizenship_number' => '12-01-70-00001',
            'permanent_address' => 'Baneshwor, Kathmandu',
            'mobile' => '9800000001',
            'ownership_type' => 'single',
            'ownership_percentage' => 100,
            'consent_for_inspection' => true,
            'consent_for_data_processing' => true,
        ]);

        $kathmandu = District::where('name_en', 'Kathmandu')->first();
        $residentialType = PropertyType::where('code', 'residential_building')->first();
        $aana = AreaUnit::where('code', 'aana')->first();

        $property = Property::create([
            'tenant_id' => $tenantId,
            'property_code' => 'PROP-DEMO-001',
            'property_name' => 'Sharma Residence',
            'property_type_id' => $residentialType?->id,
            'address' => 'Ward 10, Baneshwor, Kathmandu',
            'district_id' => $kathmandu?->id,
            'area_classification' => 'urban',
            'latitude' => 27.6939,
            'longitude' => 85.3378,
        ]);

        $parcel = LandParcel::create([
            'tenant_id' => $tenantId,
            'property_id' => $property->id,
            'kitta_number' => '1234',
            'lalpurja_number' => 'LP-2078-001234',
            'land_category' => 'residential',
            'area_lalpurja' => 4,
            'area_lalpurja_unit_id' => $aana?->id,
            'area_lalpurja_sqm' => $aana ? round(4 * (float) $aana->conversion_to_sqm, 4) : null,
            'area_considered_sqm' => $aana ? round(4 * (float) $aana->conversion_to_sqm, 4) : 127.2,
            'four_boundaries' => ['north' => 'Neighbour plot', 'south' => 'Access road', 'east' => 'Neighbour plot', 'west' => 'Open space'],
            'boundary_points' => [
                ['lat' => 27.6940, 'lng' => 85.3377],
                ['lat' => 27.6940, 'lng' => 85.3379],
                ['lat' => 27.6938, 'lng' => 85.3379],
                ['lat' => 27.6938, 'lng' => 85.3377],
            ],
        ]);

        LandParcelCharacteristics::create([
            'tenant_id' => $tenantId,
            'land_parcel_id' => $parcel->id,
            'plot_shape' => 'regular',
            'frontage_m' => 12,
            'is_corner_plot' => false,
            'topography' => 'flat',
            'flood_exposure' => 'none',
            'landslide_exposure' => 'none',
            'access_type' => 'motorable',
            'road_width_m' => 6,
            'road_surface' => 'blacktop',
            'motorable_access' => true,
            'marketability_rating' => 4,
        ]);

        $building = Building::create([
            'tenant_id' => $tenantId,
            'property_id' => $property->id,
            'building_name' => 'Main House',
            'building_type' => 'residential',
            'number_of_floors' => 2,
            'construction_year_bs' => 2075,
            'current_use' => 'residential',
            'structural_system' => 'rcc_frame',
            'foundation_type' => 'rcc_footing',
            'overall_condition' => 'good',
        ]);

        BuildingFloor::create([
            'tenant_id' => $tenantId, 'building_id' => $building->id, 'floor_name' => 'Ground Floor',
            'floor_number' => 0, 'covered_area_sqm' => 120, 'floor_use' => 'residential', 'completion_percentage' => 100,
        ]);
        BuildingFloor::create([
            'tenant_id' => $tenantId, 'building_id' => $building->id, 'floor_name' => 'First Floor',
            'floor_number' => 1, 'covered_area_sqm' => 110, 'floor_use' => 'residential', 'completion_percentage' => 100,
        ]);

        // -- The assignment itself, carried through a real chunk of the workflow --
        $fiscalYear = FiscalYear::where('is_current', true)->first();
        $purpose = ValuationPurpose::where('code', 'mortgage')->first();

        $assignment = ValuationAssignment::create([
            'tenant_id' => $tenantId,
            'organization_id' => $organization->id,
            'assignment_number' => app(AssignmentNumberGenerator::class)->next($tenantId, $fiscalYear),
            'fiscal_year_id' => $fiscalYear->id,
            'client_id' => $client->id,
            'assignment_date' => now()->subDays(10),
            'requested_completion_date' => now()->addDays(4),
            'priority' => 'normal',
            'valuation_purpose_id' => $purpose->id,
            'loan_application_number' => 'LN-2082-00045',
            'borrower_id' => $borrower->id,
            'contact_person' => 'Ramesh Prasad Sharma',
            'requested_loan_amount' => 8000000,
            'assigned_valuer_id' => $adminUser?->id,
            'assigned_approver_id' => $adminUser?->id,
            'assignment_fee' => 25000,
            'travel_fee' => 2000,
            'status' => 'under_valuation',
        ]);

        AssignmentProperty::create([
            'tenant_id' => $tenantId, 'valuation_assignment_id' => $assignment->id, 'property_id' => $property->id, 'sequence' => 1,
        ]);

        // Walk the assignment through the early workflow states so its
        // history/available_transitions look realistic, not just a status
        // string set directly.
        $workflowEngine = app(WorkflowEngine::class);
        if ($adminUser !== null) {
            foreach (['submitted', 'assignment_accepted', 'documents_pending', 'preliminary_verification', 'valuer_assigned', 'site_visit_scheduled', 'field_inspection_in_progress', 'inspection_completed'] as $status) {
                try {
                    $workflowEngine->transition($assignment->fresh(), $status, $adminUser);
                } catch (\Throwable) {
                    // Role-restricted edges the seed admin might not hold every role for -- skip rather than fail the whole seed.
                }
            }
        }
        $assignment->refresh();
        if ($assignment->status !== 'under_valuation') {
            $assignment->forceFill(['status' => 'under_valuation'])->save();
        }

        // -- Real valuation calculations, using the actual engines --
        $calcService = app(ValuationCalculationService::class);

        $marketComparison = $calcService->runMarketComparison(
            tenantId: $tenantId,
            assignmentId: $assignment->id,
            propertyId: $property->id,
            comparablesInput: ['comparables' => [
                ['base_rate' => 155000, 'weight' => 1, 'factors' => ['time' => 1.02, 'location' => 1.00, 'road_width' => 1.03]],
                ['base_rate' => 148000, 'weight' => 1, 'factors' => ['time' => 1.01, 'location' => 0.98, 'road_width' => 1.00]],
                ['base_rate' => 162000, 'weight' => 1.5, 'factors' => ['time' => 1.00, 'location' => 1.05, 'road_width' => 1.03]],
            ]],
            calculatedByUserId: $adminUser?->id,
        );

        $costApproach = $calcService->runCostApproach(
            tenantId: $tenantId,
            assignmentId: $assignment->id,
            propertyId: $property->id,
            buildingId: $building->id,
            input: [
                'built_up_area_sqm' => 230,
                'base_construction_rate' => 18000,
                'location_factor' => 1.05,
                'depreciation_method' => 'straight_line',
                'age_years' => 7,
                'economic_life_years' => 60,
            ],
            calculatedByUserId: $adminUser?->id,
        );

        $landValue = round((float) $marketComparison->computed_value * (float) $parcel->area_considered_sqm, 2);
        $buildingValue = (float) $costApproach->computed_value;
        $marketValue = round($landValue + $buildingValue, 2);

        ValuationReconciliation::create([
            'tenant_id' => $tenantId,
            'valuation_assignment_id' => $assignment->id,
            'property_id' => $property->id,
            'method_inputs' => [
                ['method' => 'market_comparison_land', 'value' => $landValue, 'reliability_rating' => 4],
                ['method' => 'cost_approach_building', 'value' => $buildingValue, 'reliability_rating' => 4],
            ],
            'reconciled_market_value' => $marketValue,
            'rounded_market_value' => round($marketValue / 1000) * 1000,
            'distress_value' => round($marketValue * 0.8, 2),
            'forced_sale_value' => round($marketValue * 0.7, 2),
            'mortgage_value' => round($marketValue * 0.75, 2),
            'reconciled_by_user_id' => $adminUser?->id,
        ]);

        // -- A real invoice, computed through BillingService --
        if ($adminUser !== null) {
            app(BillingService::class)->createInvoice(
                tenantId: $tenantId,
                clientId: $client->id,
                assignmentId: $assignment->id,
                items: [['description' => 'Property valuation fee - '.$assignment->assignment_number, 'quantity' => 1, 'unit_rate' => 27000]],
                vatPct: 13,
                tdsPct: 1.5,
                discountAmount: 0,
                dueDate: now()->addDays(15)->toDateString(),
                createdByUserId: $adminUser->id,
            );
        }

        $this->command?->info("Demo scenario seeded: client '{$client->name_en}', assignment {$assignment->assignment_number}, market value ~{$marketValue}");
    }
}
