<?php

namespace App\Http\Controllers\Member;

use App\Actions\Company\CreateCompanyFromMemberSpaceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\CreateCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        if ($user->company_id !== null) {
            return redirect()->route('espace.profil')->with('status', 'already_attached');
        }

        return view('espace.societe.creer', [
            'vatPrefill' => $request->query('vat'),
        ]);
    }

    public function store(CreateCompanyRequest $request, CreateCompanyFromMemberSpaceAction $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $request->validated());
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'company_exists') {
                return redirect()
                    ->route('espace.societe.rejoindre', ['vat' => $request->validated('vat_number')])
                    ->with('status', 'company_exists');
            }

            throw $e;
        }

        return redirect()->route('espace.profil')->with('status', 'company_created');
    }
}
