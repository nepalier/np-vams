<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyType extends Model
{
    protected $fillable = ['name_en', 'name_ne', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
