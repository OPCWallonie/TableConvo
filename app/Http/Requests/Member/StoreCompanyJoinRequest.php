<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyJoinRequest extends FormRequest
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
            'vat_number' => ['required', 'string', 'max:30'],
            'message'    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vat_number.required' => 'Le numéro de TVA est obligatoire.',
        ];
    }
}
