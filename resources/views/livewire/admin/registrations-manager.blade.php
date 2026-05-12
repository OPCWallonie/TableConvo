<div>

    {{-- ─── Header ───────────────────────────────────────────────── --}}
    <div class="border-b border-gray-200 pb-4 mb-6">
        <h2 class="text-lg font-semibold text-gray-900">
            Inscrits — {{ $this->table->topic }}
        </h2>
        <div class="mt-1 flex items-center gap-3 text-sm text-gray-600">
            <span>{{ $this->table->scheduled_at->translatedFormat('d F Y · H:i') }}</span>
            <span class="text-gray-400">•</span>
            <span>{{ $this->table->level->code }}</span>
        </div>
        <div class="mt-3 flex items-center gap-4 text-sm">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 font-medium">
                {{ $registered->count() }} / {{ $this->table->max_participants }} inscrits
            </span>
            @if($waitlist->count() > 0)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 font-medium">
                    {{ $waitlist->count() }} en liste d'attente
                </span>
            @endif
            @if($isFull)
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Complet</span>
            @endif
        </div>
    </div>

    {{-- ─── Panneau de déplacement ─────────────────────────────── --}}
    @if($moveRegistrationId && $movingRegistration)
        <div class="border border-blue-200 bg-blue-50 rounded-xl p-4 space-y-3 mb-6">
            <p class="font-medium text-blue-900">
                Déplacer <strong>{{ $movingRegistration->user?->full_name }}</strong> vers :
            </p>
            <select wire:model.live="targetTableId"
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">— Choisir une session —</option>
                @foreach($availableTables as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            @if($availableTables->isEmpty())
                <p class="text-xs text-gray-500 italic">Aucune autre session disponible.</p>
            @endif
            <div class="flex gap-2">
                <button wire:click="confirmMove"
                        @disabled(!$targetTableId)
                        class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    Confirmer le déplacement
                </button>
                <button wire:click="closeMoveModal"
                        class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-600 text-xs font-semibold hover:bg-gray-50 transition">
                    Annuler
                </button>
            </div>
        </div>
    @endif

    {{-- ─── Inscrits confirmés ─────────────────────────────────── --}}
    <div class="mb-6">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
            Inscrits confirmés ({{ $registered->count() }})
        </h3>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($registered as $reg)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $reg->user?->full_name }}</div>
                                <div class="text-sm text-gray-500">{{ $reg->user?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $reg->card?->cardType?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="openMoveModal({{ $reg->id }})"
                                        class="text-sm text-gray-700 hover:text-gray-900 hover:bg-gray-100 px-2.5 py-1 rounded-md font-medium transition">
                                    Déplacer
                                </button>
                                <button wire:click="cancel({{ $reg->id }})"
                                        wire:confirm="Annuler l'inscription de {{ $reg->user?->full_name }} ? La séance sera recréditée si applicable."
                                        class="text-sm text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                    Annuler
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-sm text-gray-400 italic text-center">
                                Aucun inscrit confirmé pour cette session.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ─── Liste d'attente ───────────────────────────────────── --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
            Liste d'attente ({{ $waitlist->count() }})
        </h3>
        @if($waitlist->isEmpty())
            <div class="text-sm text-gray-500 italic py-4 px-4 border border-dashed border-gray-200 rounded-lg">
                Aucune personne en attente.
            </div>
        @else
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($waitlist as $index => $reg)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-mono text-gray-400">#{{ $index + 1 }}</span>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $reg->user?->full_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $reg->user?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button wire:click="promote({{ $reg->id }})"
                                            @disabled($isFull)
                                            wire:confirm="Promouvoir {{ $reg->user?->full_name }} de la liste d'attente vers inscrit confirmé ?"
                                            class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md font-medium transition {{ $isFull ? 'opacity-40 cursor-not-allowed' : '' }}">
                                        Promouvoir
                                    </button>
                                    <button wire:click="openMoveModal({{ $reg->id }})"
                                            class="text-sm text-gray-700 hover:text-gray-900 hover:bg-gray-100 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                        Déplacer
                                    </button>
                                    <button wire:click="cancel({{ $reg->id }})"
                                            wire:confirm="Retirer {{ $reg->user?->full_name }} de la liste d'attente ?"
                                            class="text-sm text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                        Retirer
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
