<x-filament-widgets::widget class="fi-wi-table">
    {{ $this->table ?? null }}

    @php $totalPending = \App\Models\GlobalWaitlistEntry::where('status', \App\Enums\GlobalWaitlistEntryStatus::Pending)->count(); @endphp
    @if($totalPending > 0)
    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800">
        <a href="{{ route('filament.admin.resources.pool.index') }}"
           class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium">
            Voir tout le vivier ({{ $totalPending }} personne{{ $totalPending !== 1 ? 's' : '' }}) →
        </a>
    </div>
    @endif
</x-filament-widgets::widget>
