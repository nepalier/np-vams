<?php

declare(strict_types=1);

namespace App\Domain\ClientPortal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteClientPortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('client_portal_users.invite');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'client_branch_id' => ['nullable', 'uuid', 'exists:client_branches,id'],
            'role' => ['nullable', 'in:Client Institution Administrator,Bank Branch User,Insurance User,Cooperative User'],
        ];
    }
}
