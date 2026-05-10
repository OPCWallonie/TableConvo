{{-- Design Swiss (ultra-minimaliste) — grille de barres horizontales, beaucoup de blanc --}}
@php
    $used    = $card->sessions_total - $card->sessions_remaining;
    $total   = $card->sessions_total;
    $expired = isset($card->status) && $card->status === \App\Enums\CardStatus::Expired;
    $expiringSoon = !$expired && $card->expires_at->diffInDays() <= 30 && $card->expires_at->isFuture();
@endphp

<div class="bg-white border border-gray-100 p-6 shadow-sm" style="font-family: 'JetBrains Mono', 'Courier New', monospace;">

    {{-- Header line --}}
    <div class="flex justify-between items-center mb-5">
        <p class="text-xs text-gray-400 uppercase tracking-widest">TC / {{ $card->cardType->name }}</p>
        @if($expired)
            <span class="text-xs text-red-600 font-mono font-bold">EXP</span>
        @elseif($expiringSoon)
            <span class="text-xs font-mono font-bold" style="color: var(--color-accent, #d97706);">!</span>
        @else
            <span class="text-xs text-green-600 font-mono font-bold">OK</span>
        @endif
    </div>

    {{-- Sessions as horizontal bars --}}
    <div class="space-y-1 mb-5">
        @for($i = 0; $i < $total; $i++)
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-300 w-5 text-right font-mono">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                <div class="flex-1 h-1.5 rounded-full"
                     style="{{ $i < $used
                        ? 'background-color: var(--color-primary, #2563eb);'
                        : 'background-color: #e5e7eb;' }}"></div>
            </div>
        @endfor
    </div>

    {{-- Counter + date --}}
    <div class="flex justify-between items-baseline pt-4 border-t border-gray-100">
        <span class="text-2xl font-black" style="color: var(--color-primary, #2563eb); letter-spacing: -0.05em;">
            {{ str_pad($card->sessions_remaining, 2, '0', STR_PAD_LEFT) }}
            <span class="text-xs text-gray-400 font-normal ml-1">restant(es)</span>
        </span>
        <span class="text-xs text-gray-400 font-mono">{{ $card->expires_at->format('Y-m-d') }}</span>
    </div>
</div>
