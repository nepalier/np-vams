<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = ['province_id', 'name_en', 'name_ne', 'code'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function localLevels(): HasMany
    {
        return $this->hasMany(LocalLevel::class);
    }
}
