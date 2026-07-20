<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['name_en', 'name_ne', 'code'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
