<?php

declare(strict_types=1);

namespace App\Domain\Client\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name_en', 'name_ne', 'client_type', 'registration_number',
        'pan_number', 'vat_number', 'address', 'telephone', 'email',
        'authorized_contact_person', 'is_active', 'remarks',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function branches(): HasMany
    {
        return $this->hasMany(ClientBranch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->logAll();
    }
}
