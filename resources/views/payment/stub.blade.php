<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Simulation de paiement
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-amber-50 border border-amber-300 rounded-xl p-6 mb-6">
                <div class="flex items-center gap-2 text-amber-800 font-semibold mb-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Mode test — Mollie non configuré
                </div>
                <p class="text-sm text-amber-700">
                    Aucune clé API Mollie n'est renseignée. Cette page simule le tunnel de paiement pour les tests.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="font-semibold text-gray-900 mb-4">Récapitulatif de la commande #{{ $order->id }}</h3>
                <div class="space-y-2 text-sm">
                    @foreach($order->items as $item)
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ $item->cardType->name }} × {{ $item->quantity }}</span>
                            <span class="font-medium">{{ number_format($item->total_ttc, 2, ',', ' ') }} €</span>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-gray-200 mt-4 pt-4 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Total HT</span>
                        <span>{{ number_format($order->total_ht, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>TVA 21%</span>
                        <span>{{ number_format($order->total_vat, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 text-base">
                        <span>Total TTC</span>
                        <span>{{ number_format($order->total_ttc, 2, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <form method="POST" action="{{ route('paiement.stub.confirm', $order) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition-colors">
                        ✓ Simuler paiement réussi
                    </button>
                </form>
                <form method="POST" action="{{ route('paiement.stub.fail', $order) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-lg transition-colors">
                        ✗ Simuler paiement échoué
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
