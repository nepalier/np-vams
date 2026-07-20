<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ne' => $this->name_ne,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'organization_id' => $this->organization_id,
            'organization_branch_id' => $this->organization_branch_id,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'mfa_enabled' => (bool) $this->mfa_enabled,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
