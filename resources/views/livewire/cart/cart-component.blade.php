<div>
    @if($this->isEmpty())
        <div class="text-center py-12 text-gray-500">
            <svg class="mx-auto w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
            </svg>
            <p class="text-lg font-medium">Votre panier est vide</p>
            <a href="{{ route('tarifs') }}" class="mt-4 inline-block text-blue-600 hover:underline text-sm">
                Voir nos offres →
            </a>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Offre</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sous-total TTC</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($lines as $line)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900">{{ $line['name'] }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $line['sessions_count'] }} sessions · {{ $line['validity_months'] }} mois
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="updateQuantity({{ $line['card_type_id'] }}, {{ $line['quantity'] - 1 }})"
                                            class="w-7 h-7 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 text-lg leading-none">−</button>
                                    <span class="w-8 text-center font-medium">{{ $line['quantity'] }}</span>
                                    <button wire:click="updateQuantity({{ $line['card_type_id'] }}, {{ $line['quantity'] + 1 }})"
                                            class="w-7 h-7 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 text-lg leading-none">+</button>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-900">
                                {{ number_format($line['subtotal'], 2, ',', ' ') }} €
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="removeItem({{ $line['card_type_id'] }})"
                                        class="text-red-400 hover:text-red-600 text-sm">Supprimer</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex flex-col items-end gap-1 text-sm">
                    <div class="flex gap-6">
                        <span class="text-gray-500">Total HT</span>
                        <span class="w-28 text-right font-medium">{{ number_format($this->totalHt, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex gap-6">
                        <span class="text-gray-500">TVA (21%)</span>
                        <span class="w-28 text-right font-medium">{{ number_format($this->totalVat, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex gap-6 text-base font-bold text-gray-900 border-t border-gray-300 pt-2 mt-1">
                        <span>Total TTC</span>
                        <span class="w-28 text-right">{{ number_format($this->totalTtc, 2, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('panier.checkout') }}" class="mt-6">
            @csrf
            <div class="flex items-start gap-3 mb-4">
                <input type="checkbox" name="cgv_accepted" id="cgv_accepted" value="1"
                       class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       required>
                <label for="cgv_accepted" class="text-sm text-gray-600">
                    J'ai lu et j'accepte les
                    <a href="{{ route('cgv') }}" target="_blank" class="text-blue-600 underline">Conditions Générales de Vente</a>.
                </label>
            </div>

            @if ($errors->has('cgv_accepted'))
                <p class="text-sm text-red-500 mb-3">{{ $errors->first('cgv_accepted') }}</p>
            @endif

            <div class="flex justify-between items-center">
                <button type="button" wire:click="clear"
                        class="text-sm text-gray-400 hover:text-red-500 transition-colors">
                    Vider le panier
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Procéder au paiement
                </button>
            </div>
        </form>
    @endif
</div>
