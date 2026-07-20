<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $fillable = ['code_bs', 'starts_on', 'ends_on', 'is_current'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_current' => 'boolean',
    ];
}
