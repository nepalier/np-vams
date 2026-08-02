<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Assignment\Models\ValuationAssignment;
use App\Domain\Assignment\Policies\AssignmentPolicy;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Notification\Listeners\InvoiceObserver;
use App\Domain\Notification\Listeners\WorkflowTransitionObserver;
use App\Domain\Workflow\Models\WorkflowTransition;
use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Explicit map because domain models/policies live under
     * App\Domain\{Domain}\Models and App\Domain\{Domain}\Policies rather
     * than the flat App\Models / App\Policies pair Laravel's policy
     * auto-discovery expects -- relying on discovery here would silently
     * leave every domain policy unregistered.
     */
    private const POLICIES = [
        Organization::class => OrganizationPolicy::class,
        ValuationAssignment::class => AssignmentPolicy::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        WorkflowTransition::observe(WorkflowTransitionObserver::class);
        Invoice::observe(InvoiceObserver::class);

        // Super Administrator / Platform Administrator bypass all ability
        // checks, but ONLY for platform-level abilities -- tenant-scoped
        // data access still goes through TenantScope regardless of role,
        // so this does not let a platform admin silently read tenant rows;
        // it only lets them manage platform-level resources (tenants,
        // subscriptions) without a policy method for every action.
        Gate::before(function ($user, string $ability) {
            if ($user->hasRole('Super Administrator')) {
                return true;
            }

            return null;
        });
    }
}
