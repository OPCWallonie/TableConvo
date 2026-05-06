<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Bienvenue, {{ $user->first_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Niveau --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Mon niveau</h3>
                @if ($user->level)
                    <p class="text-gray-700">
                        <span class="font-semibold text-blue-700">{{ $user->level->code }}</span>
                        — {{ $user->level->name }}
                    </p>
                @else
                    <p class="text-amber-700 bg-amber-50 border border-amber-200 rounded p-3 text-sm">
                        Aucun niveau ne vous a encore été attribué. Lors de votre première tentative d'inscription,
                        nous vous contacterons pour un entretien téléphonique de positionnement.
                    </p>
                @endif
            </div>

            {{-- Cartes actives --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Mes cartes actives</h3>
                @forelse ($activeCards as $card)
                    <div class="flex items-center justify-between py-3 border-b last:border-0">
                        <div>
                            <p class="font-medium text-gray-800">{{ $card->cardType->name }}</p>
                            <p class="text-sm text-gray-500">
                                Expire le {{ $card->expires_at->format('d/m/Y') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold text-blue-700">{{ $card->sessions_remaining }}</span>
                            <span class="text-sm text-gray-500"> / {{ $card->sessions_total }} sessions</span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Vous n'avez aucune carte active.</p>
                @endforelse
            </div>

            {{-- Prochaines inscriptions --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Prochaines sessions</h3>
                @forelse ($upcomingRegistrations as $registration)
                    <div class="py-3 border-b last:border-0">
                        <p class="font-medium text-gray-800">{{ $registration->conversationTable->topic }}</p>
                        <p class="text-sm text-gray-500">
                            {{ $registration->conversationTable->scheduled_at->format('l d/m/Y à H:i') }}
                            — {{ $registration->conversationTable->location }}
                        </p>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">Aucune session à venir.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
