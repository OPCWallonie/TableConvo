<?php

namespace App\Http\Controllers\Member;

use App\Actions\Company\ApproveCompanyJoinRequestAction;
use App\Actions\Company\RejectCompanyJoinRequestAction;
use App\Http\Controllers\Controller;
use App\Models\CompanyJoinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyMembersController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // La vérification est faite ici plutôt qu'en middleware de route
        // car on a besoin de l'instance concrète de la company du user.
        $this->authorize('manageMembers', $user->company);

        $company = $user->company->load('members.roles');
        $pendingRequests = $company->pendingJoinRequests()->with('user')->get();

        return view('espace.societe.membres', [
            'company'         => $company,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    public function approve(
        Request $request,
        CompanyJoinRequest $joinRequest,
        ApproveCompanyJoinRequestAction $action,
    ): RedirectResponse {
        $this->authorize('approve', $joinRequest);

        $action->execute($request->user(), $joinRequest);

        return back()->with('status', 'request_approved');
    }

    public function reject(
        Request $request,
        CompanyJoinRequest $joinRequest,
        RejectCompanyJoinRequestAction $action,
    ): RedirectResponse {
        $this->authorize('reject', $joinRequest);

        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute($request->user(), $joinRequest, $request->input('rejection_reason'));

        return back()->with('status', 'request_rejected');
    }
}
