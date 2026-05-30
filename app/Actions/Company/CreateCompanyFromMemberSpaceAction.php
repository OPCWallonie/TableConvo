<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Services\EmailDomain\EmailDomainService;
use App\Services\Vat\VatValidationService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\Activity;

class CreateCompanyFromMemberSpaceAction
{
    public function __construct(
        private readonly VatValidationService $vatService,
        private readonly EmailDomainService $emailDomainService,
    ) {}

    /**
     * Crée une nouvelle Company depuis l'espace membre et y rattache le user comme company_admin.
     *
     * @return Company La company créée.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si admin TableConvo.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 409 si user déjà rattaché.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 si TVA invalide.
     * @throws \RuntimeException 'company_exists' si la TVA est déjà enregistrée → le controller redirige.
     */
    public function execute(User $user, array $data): Company
    {
        abort_if($user->hasRole('admin'), 403);
        abort_if($user->company_id !== null, 409);

        $normalized = $this->vatService->normalize($data['vat_number']);

        abort_unless($this->vatService->isFormatValid($normalized), 422, 'vat_format_invalid');

        // Anti-hijacking : TVA déjà enregistrée → signal pour rediriger vers join request
        if (Company::withTrashed()->where('vat_number', $normalized)->exists()) {
            throw new \RuntimeException('company_exists');
        }

        $emailDomain = $this->emailDomainService->isAcceptableCompanyDomain($user->email)
            ? $this->emailDomainService->extract($user->email)
            : null;

        $company = DB::transaction(function () use ($user, $data, $normalized, $emailDomain) {
            $company = Company::create([
                'name'          => $data['company_name'],
                'vat_number'    => $normalized,
                'street'        => $data['street'] ?? null,
                'postal_code'   => $data['postal_code'] ?? null,
                'city'          => $data['city'] ?? null,
                'country'       => $data['country'] ?? 'Belgique',
                'billing_email' => $data['billing_email'] ?? null,
                'email_domain'  => $emailDomain,
            ]);

            $user->update(['company_id' => $company->id]);
            $user->assignRole('company_admin');

            return $company;
        });

        activity()
            ->causedBy($user)
            ->performedOn($company)
            ->withProperties(['context' => 'member_space', 'email_domain' => $emailDomain])
            ->log('Société créée depuis espace membre');

        return $company;
    }
}
