<?php

use App\Domain\Assignment\Http\Controllers\AssignmentController;
use App\Domain\Assignment\Http\Controllers\AssignmentWorkflowController;
use App\Domain\Billing\Http\Controllers\InvoiceController;
use App\Domain\ClientPortal\Http\Controllers\ClientPortalUserController;
use App\Domain\ClientPortal\Http\Controllers\PortalController;
use App\Domain\Dashboard\Http\Controllers\DashboardController;
use App\Domain\Report\Http\Controllers\QrVerificationController;
use App\Domain\Report\Http\Controllers\ReportController;
use App\Domain\Review\Http\Controllers\ApprovalController;
use App\Domain\Review\Http\Controllers\ReviewController;
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

        Route::post('/assignments/{assignment}/calculations/market-comparison', [ValuationCalculationController::class, 'marketComparison']);
        Route::post('/assignments/{assignment}/calculations/cost-approach', [ValuationCalculationController::class, 'costApproach']);

        Route::post('/assignments/{assignment}/review/comments', [ReviewController::class, 'addComment']);
        Route::post('/assignments/{assignment}/review/decision', [ReviewController::class, 'decide']);
        Route::post('/assignments/{assignment}/approval/decision', [ApprovalController::class, 'decide']);

        Route::post('/assignments/{assignment}/report/generate-draft', [ReportController::class, 'generateDraft']);
        Route::post('/assignments/{assignment}/report/sign', [ReportController::class, 'sign']);
        Route::post('/assignments/{assignment}/report/issue', [ReportController::class, 'issue']);

        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
        Route::get('/clients/{clientId}/statement', [InvoiceController::class, 'clientStatement']);
        Route::get('/fiscal-years/{fiscalYearId}/financial-report', [InvoiceController::class, 'fiscalYearReport']);

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
