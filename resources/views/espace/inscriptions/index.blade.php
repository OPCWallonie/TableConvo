<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mes inscriptions
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- ─── AU VIVIER D'ATTENTE ─────────────────────────────── --}}
            @if($poolEntries->isNotEmpty())
            <section>
                <h3 class="text-base font-semibold text-warning-700 mb-4 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-warning-400"></span>
                    Au vivier d'attente
                    <span class="ml-1 text-xs font-normal text-warning-500">({{ $poolEntries->count() }})</span>
                </h3>

                <div class="rounded-xl bg-warning-50 border border-warning-200 p-4 space-y-3">
                    <p class="text-sm text-warning-800">
                        Vous êtes inscrit(e) dans notre vivier d'attente. Notre équipe vous proposera
                        une session compatible dès qu'une opportunité se présentera.
                    </p>

                    @foreach($poolEntries as $entry)
                    <div class="flex items-center justify-between gap-4 bg-white rounded-lg px-4 py-3 border border-warning-100 shadow-sm">
                        <div class="min-w-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-warning-100 text-warning-800">
                                Niveau {{ $entry->level->code }}
                            </span>
                            <p class="text-xs text-gray-400 mt-1">
                                En attente depuis {{ $entry->waitingDays }}
                                {{ Str::plural('jour', $entry->waitingDays) }}
                            </p>
                        </div>
                        <livewire:espace.dismiss-pool-button
                            :entry-id="$entry->id"
                            :key="'pool-'.$entry->id" />
                    </div>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- ─── À VENIR ─────────────────────────────────────────── --}}
            <section>
                <h3 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                    Sessions à venir
                    <span class="ml-1 text-xs font-normal text-gray-400">({{ $upcoming->count() }})</span>
                </h3>

                @forelse($upcoming as $registration)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-4 overflow-hidden">
                        <div class="flex flex-col sm:flex-row">

                            {{-- Colonne date --}}
                            <div class="flex-shrink-0 flex flex-col items-center justify-center
                                        {{ $registration->status->value === 'waitlist' ? 'bg-amber-500' : 'bg-blue-600' }}
                                        text-white px-5 py-4 sm:w-24 text-center">
                                <span class="text-2xl font-bold leading-none">
                                    {{ $registration->conversationTable->scheduled_at->format('d') }}
                                </span>
                                <span class="text-xs uppercase tracking-wide mt-0.5">
                                    {{ $registration->conversationTable->scheduled_at->translatedFormat('M Y') }}
                                </span>
                                <span class="mt-1.5 text-xs opacity-80">
                                    {{ $registration->conversationTable->scheduled_at->format('H:i') }}
                                </span>
                            </div>

                            {{-- Corps --}}
                            <div class="flex-1 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            {{ $registration->conversationTable->level->code }}
                                        </span>
                                        @if($registration->status->value === 'waitlist')
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                                Liste d'attente #{{ $registration->waitlist_position }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="font-semibold text-gray-900">
                                        {{ $registration->conversationTable->topic }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        @if($registration->conversationTable->location)
                                            {{ $registration->conversationTable->location }} ·
                                        @endif
                                        {{ $registration->conversationTable->duration_minutes }} min
                                    </p>
                                </div>

                                {{-- Bouton d'annulation via RegisterButton --}}
                                <div class="sm:w-52 flex-shrink-0">
                                    <livewire:agenda.register-button
                                        :table="$registration->conversationTable"
                                        :key="'reg-'.$registration->id" />
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-sm">Aucune session à venir.</p>
                        <a href="{{ route('agenda') }}" class="mt-2 inline-block text-sm text-blue-600 hover:underline">
                            Voir l'agenda
                        </a>
                    </div>
                @endforelse
            </section>

            {{-- ─── PASSÉES ─────────────────────────────────────────── --}}
            <section>
                <h3 class="text-base font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                    Historique
                    <span class="ml-1 text-xs font-normal text-gray-400">({{ $past->count() }})</span>
                </h3>

                @forelse($past as $registration)
                    @php
                        $statusLabel = match($registration->status->value) {
                            'attended'  => ['label' => 'Présent',  'class' => 'bg-green-100 text-green-700'],
                            'no_show'   => ['label' => 'Absent',   'class' => 'bg-red-100 text-red-600'],
                            'cancelled' => ['label' => 'Annulée',  'class' => 'bg-gray-100 text-gray-500'],
                            default     => ['label' => ucfirst($registration->status->value), 'class' => 'bg-gray-100 text-gray-500'],
                        };
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3 px-5 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 truncate">
                                {{ $registration->conversationTable->topic }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $registration->conversationTable->scheduled_at->format('d/m/Y à H:i') }}
                                · {{ $registration->conversationTable->level->code }}
                            </p>
                        </div>
                        <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusLabel['class'] }}">
                            {{ $statusLabel['label'] }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-6">Aucune session passée.</p>
                @endforelse
            </section>

        </div>
    </div>
</x-app-layout>
