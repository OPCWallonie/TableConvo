<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('agenda') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">
                {{ $table->topic }}
            </h2>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Carte principale --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                {{-- En-tête coloré --}}
                <div class="bg-blue-600 px-6 py-5 text-white">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 mb-2">
                                Niveau {{ $table->level->code }}
                            </span>
                            <h1 class="text-xl font-bold">{{ $table->topic }}</h1>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-2xl font-bold">{{ $table->scheduled_at->format('d') }}</p>
                            <p class="text-sm text-blue-200">{{ $table->scheduled_at->translatedFormat('M Y') }}</p>
                            <p class="text-sm text-blue-200">{{ $table->scheduled_at->format('H:i') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Détails --}}
                <div class="px-6 py-5 space-y-4">
                    @php
                        $spotsLeft = $table->max_participants - $table->registered_count;
                        $isFull    = $spotsLeft <= 0;
                    @endphp

                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Durée</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $table->duration_minutes }} minutes</dd>
                        </div>
                        @if($table->location)
                        <div>
                            <dt class="text-gray-500">Lieu</dt>
                            <dd class="font-medium text-gray-900 mt-0.5">{{ $table->location }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">Places</dt>
                            <dd class="font-medium mt-0.5 {{ $isFull ? 'text-red-600' : 'text-gray-900' }}">
                                {{ $table->registered_count }}/{{ $table->max_participants }}
                                @if($isFull)
                                    <span class="ml-1 text-xs">(Complet)</span>
                                @else
                                    <span class="ml-1 text-xs text-gray-500">— {{ $spotsLeft }} disponible{{ $spotsLeft > 1 ? 's' : '' }}</span>
                                @endif
                            </dd>
                        </div>
                        @if($table->waitlist_count > 0)
                        <div>
                            <dt class="text-gray-500">Liste d'attente</dt>
                            <dd class="font-medium text-orange-600 mt-0.5">{{ $table->waitlist_count }} personne{{ $table->waitlist_count > 1 ? 's' : '' }}</dd>
                        </div>
                        @endif
                    </dl>

                    @if($table->description)
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $table->description }}</p>
                        </div>
                    @endif
                </div>

                {{-- CTA --}}
                <div class="px-6 pb-6">
                    {{--
                        Étape F : remplacer ce bloc par
                        <livewire:agenda.register-button :table="$table" />
                    --}}
                    @auth
                        @if($isFull)
                            <button disabled
                                    class="w-full py-2.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed">
                                Complet — vous pouvez rejoindre la liste d'attente
                            </button>
                        @else
                            <button disabled
                                    class="w-full py-2.5 rounded-lg text-sm font-medium bg-blue-600 text-white opacity-60 cursor-not-allowed">
                                S'inscrire (disponible prochainement)
                            </button>
                        @endif
                    @else
                        <a href="{{ route('login') }}"
                           class="block w-full text-center py-2.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                            Se connecter pour s'inscrire
                        </a>
                    @endauth
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
