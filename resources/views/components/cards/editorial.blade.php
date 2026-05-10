{{-- Design Éditorial (haut de gamme) — ombre brutaliste, typo forte --}}
@php
    $used    = $card->sessions_total - $card->sessions_remaining;
    $total   = $card->sessions_total;
    $expired = isset($card->status) && $card->status === \App\Enums\CardStatus::Expired;
    $expiringSoon = !$expired && $card->expires_at->diffInDays() <= 30 && $card->expires_at->isFuture();
    $pct     = $total > 0 ? round(($card->sessions_remaining / $total) * 100) : 0;
@endphp

<div class="relative bg-white border-2 border-black p-5"
     style="box-shadow: 5px 5px 0 0 #000; font-family: 'Georgia', serif;">

    {{-- Top bar --}}
    <div class="flex justify-between items-center border-b-2 border-black pb-3 mb-4">
        <p class="text-xs font-black uppercase tracking-widest">TableConvo — {{ $card->cardType->name }}</p>
        @if($expired)
            <span class="text-xs font-black uppercase" style="color:#b91c1c;">[Expirée]</span>
        @elseif($expiringSoon)
            <span class="text-xs font-black uppercase" style="color: var(--color-accent, #d97706);">[Expire bientôt]</span>
        @endif
    </div>

    {{-- Big counter --}}
    <div class="flex items-baseline gap-2 mb-4">
        <span class="font-black leading-none" style="font-size: 4rem; color: var(--color-primary, #2563eb);">{{ $card->sessions_remaining }}</span>
        <span class="text-xl font-bold text-gray-400">/ {{ $total }}</span>
        <span class="text-sm font-semibold uppercase ml-2" style="color:#374151;">séances restantes</span>
    </div>

    {{-- Progress bar --}}
    <div class="w-full border-2 border-black h-4 mb-4" style="background:#f3f4f6;">
        <div class="h-full transition-all"
             style="width: {{ $pct }}%; background-color: var(--color-primary, #2563eb);"></div>
    </div>

    {{-- Footer --}}
    <div class="flex justify-between text-xs font-bold uppercase tracking-wide text-gray-600">
        <span>{{ $used }} utilisée(s)</span>
        <span>Exp. {{ $card->expires_at->format('d/m/Y') }}</span>
    </div>
</div>
