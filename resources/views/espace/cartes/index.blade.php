<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mes cartes
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $cards   = auth()->user()->cards()->with('cardType')->latest()->get();
                $active  = $cards->where('status', \App\Enums\CardStatus::Active);
                $expired = $cards->where('status', \App\Enums\CardStatus::Expired);
            @endphp

            @if($cards->isEmpty())
                <div class="text-center py-12 text-gray-500">
                    <p class="text-lg">Vous n'avez pas encore de carte.</p>
                    <a href="{{ route('tarifs') }}" class="mt-3 inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                        Acheter une carte
                    </a>
                </div>
            @else
                @if($active->isNotEmpty())
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cartes actives</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        @foreach($active as $card)
                            <x-card-display :card="$card" />
                        @endforeach
                    </div>
                @endif

                @if($expired->isNotEmpty())
                    <h3 class="text-lg font-semibold text-gray-500 mb-4">Historique</h3>
                    <div class="space-y-2">
                        @foreach($expired as $card)
                            <x-card-display :card="$card" />
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 text-center">
                    <a href="{{ route('tarifs') }}" class="text-sm text-blue-600 hover:underline">
                        Acheter une nouvelle carte →
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
