<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Same shape as TenantScope, one layer narrower: where TenantScope confines
 * a query to one tenant, this confines it to one CLIENT within that tenant
 * -- automatically, whenever the current request is authenticated as a
 * client-portal user (app('currentClientId') bound). A staff user's
 * requests never bind this value, so this scope is a complete no-op for
 * them; it only ever adds a restriction, never removes one a staff member
 * would otherwise have.
 */
class ClientPortalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentClientId') && app('currentClientId') !== null) {
            $builder->where($model->getTable().'.client_id', '=', app('currentClientId'));
        }
    }
}
