<?php

declare(strict_types=1);

namespace App\Domain\Professional\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfessionalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Self-service: any authenticated staff user can set their OWN
        // registration/license details -- there is deliberately no
        // separate "manage other people's professional profile"
        // permission check here, since the controller only ever
        // updates the CALLER's own profile (see ProfessionalProfileController).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nec_registration_number' => ['nullable', 'string', 'max:100'],
            'professional_license_number' => ['nullable', 'string', 'max:100'],
            'registration_validity_date' => ['nullable', 'date'],
            'license_expiry_date' => ['nullable', 'date'],
        ];
    }
}
