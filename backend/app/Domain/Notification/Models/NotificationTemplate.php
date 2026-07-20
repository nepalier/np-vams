<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'event_code', 'channel', 'locale', 'subject', 'body_template', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
