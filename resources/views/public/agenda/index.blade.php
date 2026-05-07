<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Agenda des tables de conversation
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Filtre par niveau (GET → query string, liens partageables) --}}
            <form method="GET" action="{{ route('agenda') }}" class="flex flex-wrap items-center gap-3">
                <label for="level" class="text-sm font-medium text-gray-700">Niveau :</label>

                <select id="level" name="level"
                        onchange="this.form.submit()"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 py-1.5">
                    <option value="">Tous les niveaux</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->code }}" @selected($levelCode === $level->code)>
                            {{ $level->code }} — {{ $level->name }}
                        </option>
                    @endforeach
                </select>

                @if($levelCode)
                    <a href="{{ route('agenda') }}"
                       class="text-sm text-gray-500 hover:text-gray-700 underline underline-offset-2">
                        Réinitialiser
                    </a>
                @endif
            </form>

            {{-- Liste des tables --}}
            @if($tables->isEmpty())
                <div class="text-center py-16 text-gray-500">
                    <svg class="mx-auto mb-4 w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-lg font-medium">Aucune session disponible</p>
                    <p class="mt-1 text-sm">
                        @if($levelCode)
                            Aucune session à venir pour ce niveau. <a href="{{ route('agenda') }}" class="text-blue-600 hover:underline">Voir tous les niveaux</a>
                        @else
                            Revenez bientôt, de nouvelles sessions sont ajoutées régulièrement.
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($tables as $table)
                        @php
                            $spotsLeft     = $table->max_participants - $table->registered_count;
                            $isFull        = $spotsLeft <= 0;
                            $isAlmostFull  = !$isFull && $spotsLeft <= max(1, (int) ($table->max_participants * 0.25));
                        @endphp

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="flex flex-col sm:flex-row">

                                {{-- Colonne date --}}
                                <div class="flex-shrink-0 flex flex-col items-center justify-center bg-blue-600 text-white px-6 py-5 sm:w-28 text-center">
                                    <span class="text-3xl font-bold leading-none">
                                        {{ $table->scheduled_at->format('d') }}
                                    </span>
                                    <span class="text-sm uppercase tracking-wide mt-0.5">
                                        {{ $table->scheduled_at->translatedFormat('M Y') }}
                                    </span>
                                    <span class="mt-2 text-blue-200 text-xs font-medium">
                                        {{ $table->scheduled_at->format('H:i') }}
                                    </span>
                                </div>

                                {{-- Corps --}}
                                <div class="flex-1 px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            {{-- Niveau badge --}}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                                {{ $table->level->code }}
                                            </span>

                                            {{-- Badge places --}}
                                            @if($isFull)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                    Complet
                                                </span>
                                            @elseif($isAlmostFull)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                                    {{ $spotsLeft }} place{{ $spotsLeft > 1 ? 's' : '' }} restante{{ $spotsLeft > 1 ? 's' : '' }}
                                                </span>
                                            @endif
                                        </div>

                                        <h3 class="font-semibold text-gray-900 text-base">{{ $table->topic }}</h3>

                                        <p class="text-sm text-gray-500 flex items-center gap-3 flex-wrap">
                                            @if($table->location)
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                    {{ $table->location }}
                                                </span>
                                            @endif
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ $table->duration_minutes }} min
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                {{ $table->registered_count }}/{{ $table->max_participants }} inscrits
                                                @if($table->waitlist_count > 0)
                                                    · {{ $table->waitlist_count }} en attente
                                                @endif
                                            </span>
                                        </p>
                                    </div>

                                    {{-- CTA --}}
                                    <div class="flex flex-col gap-2 sm:items-end">
                                        <a href="{{ route('tables.show', $table) }}"
                                           class="text-sm text-blue-600 hover:underline underline-offset-2">
                                            Voir le détail
                                        </a>

                                        {{--
                                            Étape F : remplacer ce bloc par
                                            <livewire:agenda.register-button :table="$table" />
                                        --}}
                                        @auth
                                            @if($isFull)
                                                <a href="{{ route('tables.show', $table) }}"
                                                   class="inline-block text-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                    Liste d'attente
                                                </a>
                                            @else
                                                <a href="{{ route('tables.show', $table) }}"
                                                   class="inline-block text-center px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                                    S'inscrire
                                                </a>
                                            @endif
                                        @else
                                            <a href="{{ route('login') }}"
                                               class="inline-block text-center px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                                Se connecter pour s'inscrire
                                            </a>
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Pagination (withQueryString() préserve ?level=A2) --}}
            @if($tables->hasPages())
                <div class="mt-6">
                    {{ $tables->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
