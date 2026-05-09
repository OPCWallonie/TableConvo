<div class="space-y-4">
    @if($saved)
        <div class="rounded-lg border border-success-300 bg-success-50 p-4 text-sm text-success-800">
            Présences enregistrées. Vous pouvez fermer cette fenêtre.
        </div>
    @elseif($registrations->isEmpty())
        <p class="text-sm text-gray-500">Aucun inscrit en statut "Inscrit" pour cette session.</p>
    @else
        <p class="text-sm text-gray-500 mb-3">
            Cochez les participants présents. Les non-cochés seront marqués absents.
        </p>

        <form wire:submit="save">
            <div class="space-y-2">
                @foreach($registrations as $reg)
                    <label class="flex cursor-pointer items-center justify-between rounded-lg border border-gray-200 px-4 py-3 hover:bg-gray-50">
                        <span class="text-sm font-medium text-gray-900">
                            {{ $reg->user->first_name }} {{ $reg->user->last_name }}
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold {{ $presences[(string)$reg->user_id] ? 'text-success-600' : 'text-danger-600' }}">
                                {{ $presences[(string)$reg->user_id] ? 'Présent' : 'Absent' }}
                            </span>
                            <input
                                type="checkbox"
                                wire:model.live="presences.{{ $reg->user_id }}"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-50"
                >
                    <span wire:loading.remove>Valider les présences</span>
                    <span wire:loading>Enregistrement…</span>
                </button>
            </div>
        </form>
    @endif
</div>
