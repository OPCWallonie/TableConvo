<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $cardType->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-blue-600 px-8 py-6">
                    <h3 class="text-white text-2xl font-semibold">{{ $cardType->name }}</h3>
                    <p class="text-blue-200 mt-1 text-sm">Carte de {{ $cardType->sessions_count }} sessions</p>
                </div>

                <div class="px-8 py-6 space-y-4">
                    <div class="flex items-end gap-1">
                        <span class="text-5xl font-bold text-gray-900">{{ number_format($cardType->price, 2, ',', ' ') }} €</span>
                        <span class="text-gray-500 text-sm mb-1.5">TTC</span>
                    </div>

                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $cardType->sessions_count }} sessions de conversation en néerlandais
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Valable {{ $cardType->validity_months }} mois à partir de l'achat
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ number_format($cardType->price / $cardType->sessions_count, 2, ',', ' ') }} € par session
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Facturation au nom de votre société (TVA 21% incluse)
                        </li>
                    </ul>

                    <div class="border-t border-gray-100 pt-4">
                        @auth
                            <livewire:cart.cart-add-button :card-type-id="$cardType->id" />
                        @else
                            <a href="{{ route('login') }}"
                               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
                                Connectez-vous pour acheter
                            </a>
                        @endauth
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('tarifs') }}" class="text-sm text-gray-500 hover:text-blue-600">← Retour aux tarifs</a>
            </div>
        </div>
    </div>
</x-app-layout>
