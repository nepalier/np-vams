<?php

declare(strict_types=1);

namespace App\Domain\Report\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'name', 'language', 'blade_view', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
