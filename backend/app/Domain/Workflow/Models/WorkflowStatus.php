<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStatus extends Model
{
    protected $fillable = ['code', 'label_en', 'label_ne', 'sequence', 'is_terminal', 'is_active'];

    protected $casts = ['is_terminal' => 'boolean', 'is_active' => 'boolean'];
}
