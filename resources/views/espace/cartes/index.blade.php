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
                $cards = auth()->user()->cards()->with('cardType')->latest()->get();
                $active = $cards->where('status', \App\Enums\CardStatus::Active);
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
                            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="font-semibold text-gray-900">{{ $card->cardType->name }}</span>
                                    <span class="text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Active</span>
                                </div>
                                <div class="space-y-1 text-sm text-gray-600">
                                    <div class="flex justify-between">
                                        <span>Sessions restantes</span>
                                        <span class="font-bold text-blue-700">{{ $card->sessions_remaining }} / {{ $card->sessions_total }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Expire le</span>
                                        <span class="{{ $card->expires_at->diffInDays() <= 30 ? 'text-amber-600 font-medium' : '' }}">
                                            {{ $card->expires_at->format('d/m/Y') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full"
                                             style="width: {{ $card->sessions_total > 0 ? round($card->sessions_remaining / $card->sessions_total * 100) : 0 }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($expired->isNotEmpty())
                    <h3 class="text-lg font-semibold text-gray-500 mb-4">Historique</h3>
                    <div class="space-y-2">
                        @foreach($expired as $card)
                            <div class="bg-gray-50 rounded-lg border border-gray-200 px-5 py-3 flex justify-between items-center text-sm text-gray-500">
                                <span>{{ $card->cardType->name }}</span>
                                <span>Expirée le {{ $card->expires_at->format('d/m/Y') }}</span>
                            </div>
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
