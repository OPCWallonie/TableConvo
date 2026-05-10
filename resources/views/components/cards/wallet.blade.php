{{-- Design Wallet (moderne) — dégradé Apple Wallet, pastilles dorées --}}
@php
    $used    = $card->sessions_total - $card->sessions_remaining;
    $total   = $card->sessions_total;
    $expired = isset($card->status) && $card->status === \App\Enums\CardStatus::Expired;
    $expiringSoon = !$expired && $card->expires_at->diffInDays() <= 30 && $card->expires_at->isFuture();
@endphp

<div class="relative rounded-2xl p-5 text-white shadow-lg overflow-hidden"
     style="background: linear-gradient(135deg, var(--color-primary, #2563eb) 0%, color-mix(in srgb, var(--color-primary, #2563eb) 60%, #000 40%) 100%); min-height: 160px;">

    {{-- Shine effect --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none"
         style="background: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, transparent 50%);"></div>

    {{-- Header --}}
    <div class="relative flex justify-between items-start mb-5">
        <div>
            <p class="text-xs font-medium opacity-75 uppercase tracking-widest">TableConvo</p>
            <p class="text-lg font-bold mt-0.5">{{ $card->cardType->name }}</p>
        </div>
        @if($expired)
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-500/80">Expirée</span>
        @elseif($expiringSoon)
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: var(--color-accent, #d97706);">Expire bientôt</span>
        @endif
    </div>

    {{-- Session dots --}}
    <div class="relative flex flex-wrap gap-1.5 mb-5">
        @for($i = 0; $i < $total; $i++)
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                 style="{{ $i < $used
                    ? 'background-color: var(--color-accent, #d97706); color: #fff; opacity: 1;'
                    : 'background-color: rgba(255,255,255,0.25); color: rgba(255,255,255,0.7);' }}">
                {{ $i + 1 }}
            </div>
        @endfor
    </div>

    {{-- Footer --}}
    <div class="relative flex justify-between text-sm">
        <span class="font-semibold">{{ $card->sessions_remaining }} / {{ $total }} séances</span>
        <span class="opacity-75 text-xs">{{ $card->expires_at->format('d/m/Y') }}</span>
    </div>
</div>
