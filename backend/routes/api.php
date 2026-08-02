<?php

use App\Domain\Assignment\Http\Controllers\AssignmentController;
use App\Domain\Assignment\Http\Controllers\AssignmentWorkflowController;
use App\Domain\Billing\Http\Controllers\BankReconciliationController;
use App\Domain\Billing\Http\Controllers\CommissionController;
use App\Domain\Billing\Http\Controllers\InvoiceController;
use App\Domain\Building\Http\Controllers\BuildingConditionAssessmentController;
use App\Domain\Building\Http\Controllers\BuildingController;
use App\Domain\Client\Http\Controllers\ClientController;
use App\Domain\ClientPortal\Http\Controllers\ClientPortalUserController;
use App\Domain\ClientPortal\Http\Controllers\PortalController;
use App\Domain\Dashboard\Http\Controllers\DashboardController;
use App\Domain\Gis\Http\Controllers\GisExportController;
use App\Domain\MasterData\Http\Controllers\MasterDataController;
use App\Domain\Party\Http\Controllers\BorrowerController;
use App\Domain\Party\Http\Controllers\PropertyOwnerController;
use App\Domain\Property\Http\Controllers\LandParcelCharacteristicsController;
use App\Domain\Property\Http\Controllers\LandParcelController;
use App\Domain\Property\Http\Controllers\PropertyController;
use App\Domain\Professional\Http\Controllers\ProfessionalProfileController;
use App\Domain\Settings\Http\Controllers\TenantSettingsController;
use App\Domain\Report\Http\Controllers\QrVerificationController;
use App\Domain\Report\Http\Controllers\ReportController;
use App\Domain\Review\Http\Controllers\ApprovalController;
use App\Domain\Review\Http\Controllers\ReviewController;
use App\Domain\SiteVisit\Http\Controllers\SitePhotoController;
use App\Domain\SiteVisit\Http\Controllers\SiteVisitController;
use App\Domain\Valuation\Http\Controllers\ValuationCalculationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // PUBLIC -- no auth, no tenant middleware. See Section 33 and
    // QrVerificationController's docblock for why this must stay outside
    // the authenticated group and why it only ever returns an allow-listed
    // payload, never a model straight off Eloquent.
    Route::get('/verify/{token}', [QrVerificationController::class, 'show'])
        ->middleware('throttle:30,1');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn (\Illuminate\Http\Request $request) => new UserResource($request->user()));

        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::post('/assignments', [AssignmentController::class, 'store']);
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::post('/assignments/{assignment}/workflow/transition', [AssignmentWorkflowController::class, 'transition']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::put('/clients/{client}', [ClientController::class, 'update']);

        Route::get('/master-data/valuation-purposes', [MasterDataController::class, 'valuationPurposes']);
        Route::get('/master-data/property-types', [MasterDataController::class, 'propertyTypes']);
        Route::get('/master-data/area-units', [MasterDataController::class, 'areaUnits']);
        Route::get('/master-data/districts', [MasterDataController::class, 'districts']);

        Route::get('/professional-profile', [ProfessionalProfileController::class, 'show']);
        Route::put('/professional-profile', [ProfessionalProfileController::class, 'update']);
        Route::get('/professional-profiles', [ProfessionalProfileController::class, 'index']);

        Route::get('/borrowers', [BorrowerController::class, 'index']);
        Route::post('/borrowers', [BorrowerController::class, 'store']);
        Route::get('/borrowers/{borrower}', [BorrowerController::class, 'show']);

        Route::get('/property-owners', [PropertyOwnerController::class, 'index']);
        Route::post('/property-owners', [PropertyOwnerController::class, 'store']);
        Route::get('/property-owners/{owner}', [PropertyOwnerController::class, 'show']);

        Route::get('/properties', [PropertyController::class, 'index']);
        Route::post('/properties', [PropertyController::class, 'store']);
        Route::get('/properties/{property}', [PropertyController::class, 'show']);
        Route::put('/properties/{property}', [PropertyController::class, 'update']);
        Route::get('/properties/{property}/parcels', [LandParcelController::class, 'index']);
        Route::post('/properties/{property}/parcels', [LandParcelController::class, 'store']);
        Route::get('/parcels/{parcel}', [LandParcelController::class, 'show']);
        Route::get('/properties/{property}/buildings', [BuildingController::class, 'index']);
        Route::post('/properties/{property}/buildings', [BuildingController::class, 'store']);
        Route::get('/buildings/{building}', [BuildingController::class, 'show']);

        Route::post('/assignments/{assignment}/calculations/market-comparison', [ValuationCalculationController::class, 'marketComparison']);
        Route::post('/assignments/{assignment}/calculations/cost-approach', [ValuationCalculationController::class, 'costApproach']);
        Route::post('/assignments/{assignment}/calculations/weighted-land-rate', [ValuationCalculationController::class, 'weightedLandRate']);
        Route::post('/assignments/{assignment}/calculations/vehicle', [ValuationCalculationController::class, 'vehicleValuation']);
        Route::post('/assignments/{assignment}/calculations/building-cost-estimation', [ValuationCalculationController::class, 'buildingCostEstimation']);
        Route::post('/assignments/{assignment}/certificate-summary', [ValuationCalculationController::class, 'certificateSummary']);

        Route::get('/settings', [TenantSettingsController::class, 'show']);
        Route::put('/settings', [TenantSettingsController::class, 'update']);

        Route::get('/parcels/{parcel}/characteristics', [LandParcelCharacteristicsController::class, 'show']);
        Route::put('/parcels/{parcel}/characteristics', [LandParcelCharacteristicsController::class, 'update']);
        Route::get('/parcels/{parcel}/suggested-adjustment-factors', [LandParcelCharacteristicsController::class, 'suggestedAdjustmentFactors']);

        Route::post('/buildings/{building}/condition-assessments', [BuildingConditionAssessmentController::class, 'store']);
        Route::get('/buildings/{building}/condition-assessments/latest', [BuildingConditionAssessmentController::class, 'show']);
        Route::get('/buildings/{building}/suggested-depreciation', [BuildingConditionAssessmentController::class, 'suggestedDepreciation']);

        Route::post('/site-photos', [SitePhotoController::class, 'store']);

        Route::get('/assignments/{assignment}/site-visits', [SiteVisitController::class, 'index']);
        Route::post('/assignments/{assignment}/site-visits', [SiteVisitController::class, 'store']);
        Route::get('/site-visits/{siteVisit}', [SiteVisitController::class, 'show']);
        Route::post('/site-visits/{siteVisit}/check-in', [SiteVisitController::class, 'checkIn']);
        Route::put('/site-visits/{siteVisit}', [SiteVisitController::class, 'update']);
        Route::post('/site-visits/{siteVisit}/complete', [SiteVisitController::class, 'complete']);

        Route::get('/assignments/{assignment}/review', [ReviewController::class, 'index']);
        Route::post('/assignments/{assignment}/review/comments', [ReviewController::class, 'addComment']);
        Route::post('/assignments/{assignment}/review/decision', [ReviewController::class, 'decide']);
        Route::post('/assignments/{assignment}/approval/decision', [ApprovalController::class, 'decide']);

        Route::post('/assignments/{assignment}/report/generate-draft', [ReportController::class, 'generateDraft']);
        Route::get('/assignments/{assignment}/report', [ReportController::class, 'show']);
        Route::post('/assignments/{assignment}/report/sign', [ReportController::class, 'sign']);
        Route::post('/assignments/{assignment}/report/issue', [ReportController::class, 'issue']);
        Route::post('/assignments/{assignment}/report/cancel', [ReportController::class, 'cancel']);
        Route::post('/assignments/{assignment}/report/supersede', [ReportController::class, 'supersede']);

        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
        Route::get('/clients/{clientId}/statement', [InvoiceController::class, 'clientStatement']);
        Route::get('/fiscal-years/{fiscalYearId}/financial-report', [InvoiceController::class, 'fiscalYearReport']);

        Route::get('/commissions', [CommissionController::class, 'index']);
        Route::post('/assignments/{assignment}/commissions', [CommissionController::class, 'store']);
        Route::post('/commissions/{commission}/approve', [CommissionController::class, 'approve']);
        Route::post('/commissions/{commission}/mark-paid', [CommissionController::class, 'markPaid']);

        Route::post('/bank-reconciliation/import', [BankReconciliationController::class, 'import']);
        Route::post('/bank-reconciliation/lines/{line}/match', [BankReconciliationController::class, 'matchManually']);
        Route::get('/bank-reconciliation/unmatched-summary', [BankReconciliationController::class, 'unmatchedSummary']);

        Route::get('/properties/{property}/export/geojson', [GisExportController::class, 'exportPropertyGeoJson']);
        Route::get('/parcels/{parcel}/export/geojson', [GisExportController::class, 'exportParcelGeoJson']);
        Route::get('/parcels/{parcel}/export/kml', [GisExportController::class, 'exportParcelKml']);
        Route::post('/parcels/{parcel}/import/geojson', [GisExportController::class, 'importParcelBoundary']);

        Route::get('/dashboards/firm', [DashboardController::class, 'firm']);
        Route::get('/dashboards/market-analytics', [DashboardController::class, 'marketAnalytics']);
        Route::get('/dashboards/platform', [DashboardController::class, 'platform']);
        Route::get('/dashboards/clients/{clientId}', [DashboardController::class, 'clientInstitution']);

        // Staff-side: tenant staff invite/manage their client's portal logins.
        Route::post('/clients/{client}/portal-users', [ClientPortalUserController::class, 'store']);
        Route::get('/clients/{client}/portal-users', [ClientPortalUserController::class, 'index']);

        // Client-portal-facing: guarded by EnsureIsClientPortalUser on top of
        // auth:sanctum+tenant. ClientPortalScope (bound by IdentifyTenant)
        // does the actual data narrowing -- these routes don't re-filter by
        // client_id themselves, proving the scope carries the weight.
        Route::middleware('client.portal')->prefix('portal')->group(function () {
            Route::get('/dashboard', [PortalController::class, 'dashboard']);
            Route::get('/assignments', [PortalController::class, 'assignments']);
            Route::get('/assignments/{assignmentId}', [PortalController::class, 'assignment']);
            Route::get('/invoices', [PortalController::class, 'invoices']);
            Route::get('/reports', [PortalController::class, 'reports']);
        });

        // Income-approach, residual, reconciliation, and risk-assessment
        // trigger endpoints follow the identical pattern established above
        // and are the next controllers to add in this same incremental style.
    });
});
