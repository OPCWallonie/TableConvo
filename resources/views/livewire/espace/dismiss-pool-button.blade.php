<div>
    <button wire:click="openDismissDialog({{ $entryId }})"
            class="px-3 py-1.5 rounded-lg border border-warning-300 text-warning-700 bg-warning-50 text-sm font-medium hover:bg-warning-100 transition">
        Me retirer du vivier
    </button>

    @if($showDialog)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4"
         x-on:keydown.escape.window="$wire.closeDismissDialog()">

        <div class="absolute inset-0 bg-black/50"
             wire:click="closeDismissDialog"></div>

        <div class="relative z-10 w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4">

            <h3 class="font-semibold text-gray-900 text-base">
                Vous retirer du vivier ?
            </h3>

            <p class="text-sm text-gray-600">
                Votre dossier sera archivé. Pour retrouver une place, il faudra nous contacter à nouveau.
            </p>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Raison <span class="text-gray-400">(optionnelle)</span>
                </label>
                <textarea wire:model="reason" rows="2"
                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-warning-500 focus:border-warning-500"
                          placeholder="Je ne suis plus disponible…"></textarea>
            </div>

            <div class="flex gap-2 pt-1">
                <button wire:click="confirmDismiss"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-lg bg-warning-600 text-white text-sm font-semibold hover:bg-warning-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    Confirmer
                </button>
                <button wire:click="closeDismissDialog"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold hover:bg-gray-50 transition">
                    Annuler
                </button>
            </div>

        </div>
    </div>
    @endif
</div>
