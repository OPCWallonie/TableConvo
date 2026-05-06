<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Vat\VatValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly VatValidationService $vat) {}

    public function show(Request $request): View
    {
        return view('espace.profil', [
            'user' => $request->user()->load('company', 'level'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->company && isset($validated['company_name'])) {
            $user->company->update([
                'name'          => $validated['company_name'],
                'street'        => $validated['street'],
                'postal_code'   => $validated['postal_code'],
                'city'          => $validated['city'],
                'billing_email' => $validated['billing_email'] ?? null,
            ]);
        }

        return redirect()->route('espace.profil')->with('status', 'profile-updated');
    }

    public function exportData(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user()->load(['company', 'level', 'cards.cardType', 'orders', 'registrations.conversationTable']);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'user'        => [
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'email'             => $user->email,
                'phone'             => $user->phone,
                'level'             => $user->level?->code,
                'level_assigned_at' => $user->level_assigned_at?->toIso8601String(),
                'created_at'        => $user->created_at->toIso8601String(),
            ],
            'company' => $user->company ? [
                'name'          => $user->company->name,
                'vat_number'    => $user->company->vat_number,
                'street'        => $user->company->street,
                'postal_code'   => $user->company->postal_code,
                'city'          => $user->company->city,
                'billing_email' => $user->company->billing_email,
            ] : null,
            'cards' => $user->cards->map(fn ($card) => [
                'type'               => $card->cardType->name,
                'sessions_total'     => $card->sessions_total,
                'sessions_remaining' => $card->sessions_remaining,
                'purchased_at'       => $card->purchased_at->toIso8601String(),
                'expires_at'         => $card->expires_at->toIso8601String(),
                'status'             => $card->status,
            ]),
            'registrations' => $user->registrations->map(fn ($r) => [
                'table'         => $r->conversationTable->topic ?? null,
                'scheduled_at'  => $r->conversationTable->scheduled_at?->toIso8601String(),
                'status'        => $r->status,
                'registered_at' => $r->registered_at->toIso8601String(),
            ]),
        ];

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'mes-donnees-tableconvo-' . now()->format('Y-m-d') . '.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('accountDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'account-deleted');
    }
}
