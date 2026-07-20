<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('currentTenantId') && app('currentTenantId') !== null) {
            $builder->where($model->getTable().'.tenant_id', '=', app('currentTenantId'));
        }
    }
}
