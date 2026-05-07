<div class="space-y-1 text-sm">
    @php
        use App\Enums\RegistrationStatus;
        $registered = $registrations->filter(fn ($r) => $r->status === RegistrationStatus::Registered)
                                     ->sortBy('registered_at');
        $waitlist   = $registrations->filter(fn ($r) => $r->status === RegistrationStatus::Waitlist)
                                     ->sortBy('waitlist_position');
    @endphp

    {{-- Inscrits confirmés --}}
    @if ($registered->isNotEmpty())
        <p class="font-semibold text-gray-700 dark:text-gray-200 mt-2 mb-1">
            Inscrits confirmés ({{ $registered->count() }})
        </p>
        @foreach ($registered as $reg)
            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <span class="font-medium">{{ $reg->user?->full_name }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $reg->user?->email }}</span>
            </div>
        @endforeach
    @endif

    {{-- Liste d'attente --}}
    @if ($waitlist->isNotEmpty())
        <p class="font-semibold text-gray-700 dark:text-gray-200 mt-4 mb-1">
            Liste d'attente ({{ $waitlist->count() }})
        </p>
        @foreach ($waitlist as $reg)
            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                <span class="text-yellow-800 dark:text-yellow-200">
                    #{{ $reg->waitlist_position }} — {{ $reg->user?->full_name }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $reg->user?->email }}</span>
            </div>
        @endforeach
    @endif

    @if ($registered->isEmpty() && $waitlist->isEmpty())
        <p class="text-gray-400 dark:text-gray-500 italic py-4 text-center">
            Aucun inscrit pour cette session.
        </p>
    @endif
</div>
