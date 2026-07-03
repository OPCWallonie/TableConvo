<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Membres de {{ $company->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash messages --}}
            @foreach (['request_approved' => ['success', 'Demande approuvée.'], 'request_rejected' => ['success', 'Demande rejetée.'], 'request_approved' => ['success', 'Demande approuvée.']] as $status => [$type, $msg])
            @endforeach

            @if (session('status') === 'request_approved')
                <div class="bg-success-50 border border-success-300 text-success-800 rounded-lg p-4 text-sm">
                    La demande a été approuvée. Le membre a reçu une notification.
                </div>
            @elseif (session('status') === 'request_rejected')
                <div class="bg-success-50 border border-success-300 text-success-800 rounded-lg p-4 text-sm">
                    La demande a été refusée. Le demandeur a été notifié.
                </div>
            @endif

            {{-- Section 1 : Demandes en attente --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">
                    Demandes d'adhésion en attente
                    @if ($pendingRequests->count() > 0)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800">
                            {{ $pendingRequests->count() }}
                        </span>
                    @endif
                </h3>

                @if ($pendingRequests->isEmpty())
                    <p class="text-sm text-gray-500">Aucune demande en attente.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($pendingRequests as $joinRequest)
                            <li class="py-4" x-data="{ showReject: false }">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $joinRequest->user->full_name }}</p>
                                        <p class="text-sm text-gray-500">{{ $joinRequest->user->email }}</p>
                                        @if ($joinRequest->message)
                                            <p class="mt-1 text-sm text-gray-600 italic">« {{ $joinRequest->message }} »</p>
                                        @endif
                                        <p class="mt-1 text-xs text-gray-400">
                                            Demandé le {{ $joinRequest->requested_at->format('d/m/Y à H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <form method="POST"
                                            action="{{ route('espace.societe.demandes.approuver', $joinRequest) }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md bg-success-600 text-white hover:bg-success-700">
                                                Approuver
                                            </button>
                                        </form>
                                        <button type="button"
                                            @click="showReject = ! showReject"
                                            class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-danger-300 text-danger-700 hover:bg-danger-50">
                                            Rejeter
                                        </button>
                                    </div>
                                </div>

                                {{-- Formulaire de rejet avec raison --}}
                                <div x-show="showReject" x-cloak class="mt-3">
                                    <form method="POST"
                                        action="{{ route('espace.societe.demandes.rejeter', $joinRequest) }}"
                                        class="space-y-2">
                                        @csrf
                                        <textarea name="rejection_reason" rows="2"
                                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring focus:ring-primary/30"
                                            placeholder="Raison du refus (optionnel)…" maxlength="500"></textarea>
                                        <div class="flex gap-2">
                                            <button type="submit"
                                                class="px-3 py-1.5 text-sm font-medium rounded-md bg-danger-600 text-white hover:bg-danger-700">
                                                Confirmer le refus
                                            </button>
                                            <button type="button" @click="showReject = false"
                                                class="px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                Annuler
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Section 2 : Membres actuels --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">
                    Membres ({{ $company->members->count() }})
                </h3>

                <ul class="divide-y divide-gray-100">
                    @foreach ($company->members->sortBy('created_at') as $member)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->full_name }}</p>
                                <p class="text-sm text-gray-500">{{ $member->email }}</p>
                            </div>
                            @if ($member->hasRole('company_admin'))
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                    Administrateur
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>
