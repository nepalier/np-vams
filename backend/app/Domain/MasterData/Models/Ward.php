<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    protected $fillable = ['local_level_id', 'ward_number'];

    public function localLevel(): BelongsTo
    {
        return $this->belongsTo(LocalLevel::class);
    }
}
