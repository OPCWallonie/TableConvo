<div>
    @if($added)
        <div class="flex items-center gap-3">
            <span class="text-green-600 font-medium flex items-center gap-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Ajouté au panier !
            </span>
            <a href="{{ route('panier') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors">
                Voir le panier →
            </a>
        </div>
    @else
        <button wire:click="addToCart"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">
            Ajouter au panier
        </button>
    @endif
</div>
