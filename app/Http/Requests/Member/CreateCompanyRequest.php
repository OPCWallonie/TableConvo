<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->hasRole('admin')
            && $user->company_id === null;
    }

    public function rules(): array
    {
        return [
            'company_name'  => ['required', 'string', 'max:255'],
            'vat_number'    => ['required', 'string', 'max:30'],
            'street'        => ['required', 'string', 'max:255'],
            'postal_code'   => ['required', 'string', 'max:20'],
            'city'          => ['required', 'string', 'max:100'],
            'billing_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Le nom de la société est obligatoire.',
            'vat_number.required'   => 'Le numéro de TVA est obligatoire.',
            'street.required'       => 'L\'adresse est obligatoire.',
            'postal_code.required'  => 'Le code postal est obligatoire.',
            'city.required'         => 'La ville est obligatoire.',
            'billing_email.email'   => 'L\'adresse e-mail de facturation n\'est pas valide.',
        ];
    }
}
