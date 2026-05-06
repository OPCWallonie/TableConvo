<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\Vat\VatValidationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly VatValidationService $vat) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name'      => ['required', 'string', 'max:255'],
            'last_name'       => ['required', 'string', 'max:255'],
            'email'           => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone'           => ['nullable', 'string', 'max:50'],
            'password'        => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name'    => ['required', 'string', 'max:255'],
            'vat_number'      => ['required', 'string', 'max:30'],
            'street'          => ['required', 'string', 'max:255'],
            'postal_code'     => ['required', 'string', 'max:20'],
            'city'            => ['required', 'string', 'max:100'],
            'billing_email'   => ['nullable', 'email', 'max:255'],
        ]);

        $vatNormalized = $this->vat->normalize($request->vat_number);

        if (! $this->vat->isFormatValid($vatNormalized)) {
            throw ValidationException::withMessages([
                'vat_number' => 'Le numéro de TVA doit être au format BE0XXXXXXXXX (10 chiffres).',
            ]);
        }

        if (! $this->vat->validate($vatNormalized)) {
            throw ValidationException::withMessages([
                'vat_number' => 'Ce numéro de TVA n\'a pas pu être validé auprès de la base VIES.',
            ]);
        }

        $user = DB::transaction(function () use ($request, $vatNormalized): User {
            $company = Company::firstOrCreate(
                ['vat_number' => $vatNormalized],
                [
                    'name'          => $request->company_name,
                    'street'        => $request->street,
                    'postal_code'   => $request->postal_code,
                    'city'          => $request->city,
                    'country'       => 'Belgique',
                    'billing_email' => $request->billing_email,
                ]
            );

            return User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => Hash::make($request->password),
                'company_id' => $company->id,
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('espace.dashboard', absolute: false));
    }
}
