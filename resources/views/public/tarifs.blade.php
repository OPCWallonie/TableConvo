<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Nos tarifs
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($cardTypes->isEmpty())
                <div class="text-center py-16 text-gray-500">
                    <p class="text-lg">Aucune offre disponible pour le moment.</p>
                    <p class="mt-2 text-sm">Revenez bientôt ou contactez-nous.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($cardTypes as $cardType)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col overflow-hidden">
                            <div class="bg-blue-600 px-6 py-5">
                                <h3 class="text-white text-xl font-semibold">{{ $cardType->name }}</h3>
                            </div>
                            <div class="px-6 py-5 flex-1 flex flex-col gap-4">
                                <div class="flex items-end gap-1">
                                    <span class="text-4xl font-bold text-gray-900">{{ number_format($cardType->price, 2, ',', ' ') }}€</span>
                                    <span class="text-gray-500 text-sm mb-1">TTC</span>
                                </div>

                                <ul class="space-y-2 text-sm text-gray-600 flex-1">
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ $cardType->sessions_count }} sessions incluses
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Valable {{ $cardType->validity_months }} mois
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ number_format($cardType->price / $cardType->sessions_count, 2, ',', ' ') }}€ par session
                                    </li>
                                </ul>

                                <a href="{{ route('achat.show', $cardType) }}"
                                   class="mt-4 block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                                    Acheter
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-8 text-center text-xs text-gray-400">
                    Prix TTC, TVA 21% incluse. Paiement sécurisé via Mollie. Facturation au nom de votre société.
                </p>
            @endif

        </div>
    </div>
</x-app-layout>
