<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalLevel extends Model
{
    protected $fillable = ['district_id', 'name_en', 'name_ne', 'type', 'ward_count'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
