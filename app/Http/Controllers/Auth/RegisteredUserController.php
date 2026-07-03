<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Company\AutoJoinCompanyByEmailDomainAction;
use App\Actions\Company\RequestCompanyJoinAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\EmailDomain\EmailDomainService;
use App\Services\Vat\VatValidationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
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
    public function __construct(
        private readonly VatValidationService $vat,
        private readonly EmailDomainService $emailDomain,
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Endpoint public JSON pour lookup TVA pendant la saisie dans le formulaire d'inscription.
     * Pas d'auth requise — utilisé via Alpine.js avant soumission du formulaire.
     */
    public function vatLookup(Request $request): JsonResponse
    {
        $request->validate(['vat_number' => ['required', 'string', 'max:30']]);

        $normalized = $this->vat->normalize($request->vat_number);

        if (! $this->vat->isFormatValid($normalized)) {
            return response()->json(['status' => 'invalid_format']);
        }

        $company = Company::where('vat_number', $normalized)->first();

        if ($company !== null) {
            return response()->json([
                'status'  => 'exists',
                'company' => [
                    'name'    => $company->name,
                    'street'  => $company->street,
                    'postal_code' => $company->postal_code,
                    'city'    => $company->city,
                ],
            ]);
        }

        $viesResult = $this->vat->lookup($normalized);

        if ($viesResult === null) {
            return response()->json(['status' => 'vies_failed']);
        }

        return response()->json([
            'status'  => 'new',
            'name'    => $viesResult->nameIsUndisclosed() ? null : $viesResult->name,
            'street'  => $viesResult->addressIsUndisclosed() ? null : $viesResult->address,
        ]);
    }

    public function store(
        Request $request,
        AutoJoinCompanyByEmailDomainAction $autoJoin,
        RequestCompanyJoinAction $requestJoin,
    ): RedirectResponse {
        // Validation de base — les champs société sont optionnels à ce stade
        // (non requis pour cas 2/3 où la company existe déjà)
        $request->validate([
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone'         => ['nullable', 'string', 'max:50'],
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'vat_number'    => ['required', 'string', 'max:30'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'company_name'  => ['nullable', 'string', 'max:255'],
            'street'        => ['nullable', 'string', 'max:255'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'city'          => ['nullable', 'string', 'max:100'],
        ]);

        // Cas 4 : TVA invalide (format)
        $vatNormalized = $this->vat->normalize($request->vat_number);

        if (! $this->vat->isFormatValid($vatNormalized)) {
            throw ValidationException::withMessages([
                'vat_number' => 'Le numéro de TVA doit être au format BE0XXXXXXXXX (10 chiffres).',
            ]);
        }

        // Cas 4 : TVA invalide (VIES)
        if (! $this->vat->validate($vatNormalized)) {
            throw ValidationException::withMessages([
                'vat_number' => 'Ce numéro de TVA n\'a pas pu être validé auprès de la base VIES.',
            ]);
        }

        $existingCompany = Company::where('vat_number', $vatNormalized)->first();

        if ($existingCompany !== null) {
            // Cas 2 ou 3 : company déjà enregistrée — créer l'user sans company
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => Hash::make($request->password),
                'company_id' => null,
            ]);

            event(new Registered($user));
            Auth::login($user);

            $canAutoJoin = $existingCompany->email_domain !== null
                && $this->emailDomain->extract($user->email) === $existingCompany->email_domain;

            if ($canAutoJoin) {
                // Cas 2 : email pro match → rattachement immédiat
                $autoJoin->execute($user, $existingCompany);

                return redirect(route('espace.dashboard', absolute: false))
                    ->with('status', 'auto_joined');
            }

            // Cas 3 : pas d'auto-join → demande d'adhésion pending
            $requestJoin->execute($user, $existingCompany);

            return redirect(route('espace.profil', absolute: false))
                ->with('status', 'request_pending');
        }

        // Cas 1 : nouvelle TVA — valider les champs société, maintenant requis
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'street'       => ['required', 'string', 'max:255'],
            'postal_code'  => ['required', 'string', 'max:20'],
            'city'         => ['required', 'string', 'max:100'],
        ]);

        $emailDomain = $this->emailDomain->isAcceptableCompanyDomain($request->email)
            ? $this->emailDomain->extract($request->email)
            : null;

        $user = DB::transaction(function () use ($request, $vatNormalized, $emailDomain): User {
            $company = Company::create([
                'name'          => $request->company_name,
                'vat_number'    => $vatNormalized,
                'street'        => $request->street,
                'postal_code'   => $request->postal_code,
                'city'          => $request->city,
                'country'       => 'Belgique',
                'billing_email' => $request->billing_email,
                'email_domain'  => $emailDomain,
            ]);

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => Hash::make($request->password),
                'company_id' => $company->id,
            ]);

            $user->assignRole('company_admin');

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect(route('espace.dashboard', absolute: false));
    }
}
