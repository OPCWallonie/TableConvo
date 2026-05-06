<div>
    @if($status === 'pending')
        <div class="text-center py-10">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-200 border-t-blue-600 mb-6"></div>
            <p class="text-lg font-medium text-gray-700">Vérification de votre paiement…</p>
            <p class="text-sm text-gray-500 mt-2">Merci de patienter quelques secondes.</p>
        </div>

    @elseif($status === 'paid')
        <div class="text-center py-10">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="text-xl font-semibold text-green-700">Paiement confirmé !</p>
            <p class="text-sm text-gray-500 mt-2 mb-6">Votre facture a été envoyée par e-mail. Vos cartes sont disponibles.</p>
            <script>
                setTimeout(function() {
                    window.location.href = "{{ route('espace.cartes') }}?success=1";
                }, 2000);
            </script>
            <a href="{{ route('espace.cartes') }}"
               class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Voir mes cartes →
            </a>
        </div>

    @elseif($status === 'failed')
        <div class="text-center py-10">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <p class="text-xl font-semibold text-red-700">Paiement refusé</p>
            <p class="text-sm text-gray-500 mt-2 mb-6">Le paiement n'a pas pu être traité. Votre panier a été conservé.</p>
            <a href="{{ route('tarifs') }}"
               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Retour aux offres
            </a>
        </div>

    @elseif($status === 'timeout')
        <div class="text-center py-10">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-8 w-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-xl font-semibold text-amber-700">Vérification en cours…</p>
            <p class="text-sm text-gray-500 mt-2">Le paiement est en cours de traitement. Vous recevrez un e-mail de confirmation dès qu'il sera validé.</p>
            <p class="text-sm text-gray-500 mt-4">
                <a href="{{ route('espace.cartes') }}" class="text-blue-600 hover:underline">Consulter mon espace</a>
            </p>
        </div>

    @else
        <div class="text-center py-10 text-gray-500">
            <p>Une erreur est survenue. Veuillez contacter le support.</p>
        </div>
    @endif
</div>
