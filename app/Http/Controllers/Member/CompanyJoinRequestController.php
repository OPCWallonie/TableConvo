<?php

namespace App\Http\Controllers\Member;

use App\Actions\Company\AutoJoinCompanyByEmailDomainAction;
use App\Actions\Company\CancelCompanyJoinRequestAction;
use App\Actions\Company\RequestCompanyJoinAction;
use App\Enums\CompanyJoinRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreCompanyJoinRequest;
use App\Models\Company;
use App\Services\EmailDomain\EmailDomainService;
use App\Services\Vat\VatValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyJoinRequestController extends Controller
{
    public function __construct(
        private readonly VatValidationService $vatService,
        private readonly EmailDomainService $emailDomainService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        if ($user->company_id !== null) {
            return redirect()->route('espace.profil')->with('status', 'already_attached');
        }

        $pendingRequest = $user->companyJoinRequests()
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->with('company')
            ->first();

        return view('espace.societe.rejoindre', [
            'vatPrefill'     => $request->query('vat'),
            'pendingRequest' => $pendingRequest,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['vat_number' => ['required', 'string', 'max:30']]);

        $normalized = $this->vatService->normalize($request->vat_number);

        if (! $this->vatService->isFormatValid($normalized)) {
            return response()->json(['status' => 'invalid_format']);
        }

        $viesResult = $this->vatService->lookup($normalized);

        if ($viesResult === null) {
            return response()->json(['status' => 'vies_failed']);
        }

        $company = Company::where('vat_number', $normalized)->first();

        if ($company === null) {
            return response()->json([
                'status'  => 'unknown',
                'name'    => $viesResult->nameIsUndisclosed() ? null : $viesResult->name,
                'address' => $viesResult->addressIsUndisclosed() ? null : $viesResult->address,
            ]);
        }

        $user = $request->user();
        $canAutoJoin = $company->email_domain !== null
            && $this->emailDomainService->extract($user->email) === $company->email_domain;

        return response()->json([
            'status'       => 'exists',
            'company'      => [
                'name'    => $company->name,
                'address' => trim("{$company->street}, {$company->postal_code} {$company->city}"),
            ],
            'can_auto_join' => $canAutoJoin,
        ]);
    }

    public function store(
        StoreCompanyJoinRequest $request,
        AutoJoinCompanyByEmailDomainAction $autoJoin,
        RequestCompanyJoinAction $requestJoin,
    ): RedirectResponse {
        $user = $request->user();
        $normalized = $this->vatService->normalize($request->validated('vat_number'));

        if (! $this->vatService->isFormatValid($normalized)) {
            return back()->withErrors(['vat_number' => 'Format de TVA invalide.']);
        }

        $company = Company::where('vat_number', $normalized)->first();

        if ($company === null) {
            return redirect()
                ->route('espace.societe.creer', ['vat' => $normalized])
                ->with('status', 'company_not_found');
        }

        // Vérifier si auto-join possible
        $canAutoJoin = $company->email_domain !== null
            && $this->emailDomainService->extract($user->email) === $company->email_domain;

        if ($canAutoJoin) {
            $autoJoin->execute($user, $company);

            return redirect()->route('espace.dashboard')->with('status', 'auto_joined');
        }

        $autoJoin instanceof AutoJoinCompanyByEmailDomainAction; // type hint aide IDE

        try {
            $requestJoin->execute($user, $company, $request->validated('message'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return back()->with('status', 'already_requested');
            }
            throw $e;
        }

        return redirect()->route('espace.profil')->with('status', 'request_pending');
    }

    public function cancel(Request $request, CancelCompanyJoinRequestAction $action): RedirectResponse
    {
        $user = $request->user();

        $pendingRequest = $user->companyJoinRequests()
            ->where('status', CompanyJoinRequestStatus::Pending->value)
            ->firstOrFail();

        $action->execute($user, $pendingRequest);

        return back()->with('status', 'request_cancelled');
    }
}
