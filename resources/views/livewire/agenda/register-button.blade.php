<div>
    {{-- Message de succès --}}
    @if($flashMessage)
        <div class="mb-3 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm font-medium">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- Message d'erreur / info --}}
    @if($errorMessage)
        <div class="mb-3 px-4 py-3 rounded-lg
                    {{ $status === 'no_level' ? 'bg-blue-50 border border-blue-200 text-blue-800' : 'bg-orange-50 border border-orange-200 text-orange-800' }}
                    text-sm">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Bouton selon état --}}
    @switch($status)

        @case('guest')
            <a href="{{ route('login') }}"
               class="inline-block w-full text-center px-4 py-2.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                Se connecter pour s'inscrire
            </a>
            @break

        @case('registered')
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Vous êtes inscrit
            </div>
            @break

        @case('waitlisted')
            <div class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-sm font-medium">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                En liste d'attente
                @if($waitlistPosition)
                    (position #{{ $waitlistPosition }})
                @endif
            </div>
            @break

        @case('can_register')
            <button wire:click="register"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-wait"
                    class="w-full px-4 py-2.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                <span wire:loading.remove>S'inscrire</span>
                <span wire:loading>Inscription en cours…</span>
            </button>
            @break

        @case('can_waitlist')
            <button wire:click="joinWaitlist"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-wait"
                    class="w-full px-4 py-2.5 rounded-lg text-sm font-medium bg-amber-500 hover:bg-amber-600 text-white transition-colors">
                <span wire:loading.remove>Rejoindre la liste d'attente</span>
                <span wire:loading>Inscription en cours…</span>
            </button>
            @break

        @case('no_level')
            <button wire:click="register"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-60 cursor-wait"
                    class="w-full px-4 py-2.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                <span wire:loading.remove>S'inscrire</span>
                <span wire:loading>Vérification…</span>
            </button>
            @break

        @case('blocked')
            <button disabled
                    class="w-full px-4 py-2.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed">
                Inscription non disponible
            </button>
            @break

        @default
            {{-- loading state --}}
            <div class="h-10 rounded-lg bg-gray-100 animate-pulse"></div>

    @endswitch
</div>
