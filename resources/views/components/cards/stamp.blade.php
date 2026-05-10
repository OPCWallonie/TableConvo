{{-- Design Tampon (skeuomorphique) — papier kraft, cases tamponnées --}}
@php
    $used    = $card->sessions_total - $card->sessions_remaining;
    $total   = $card->sessions_total;
    $expired = isset($card->status) && $card->status === \App\Enums\CardStatus::Expired;
    $expiringSoon = !$expired && $card->expires_at->diffInDays() <= 30 && $card->expires_at->isFuture();
@endphp

<div class="relative rounded-xl border-2 border-amber-200 bg-amber-50 p-5 shadow-sm font-sans overflow-hidden"
     style="background-color: #fef9ee; border-color: #d97706; font-family: 'Georgia', serif;">

    {{-- Grain texture overlay (CSS radial) --}}
    <div class="absolute inset-0 opacity-5 pointer-events-none"
         style="background-image: radial-gradient(circle, #92400e 1px, transparent 1px); background-size: 8px 8px;"></div>

    {{-- Header --}}
    <div class="relative flex justify-between items-start mb-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest" style="color: #92400e;">TableConvo</p>
            <p class="text-base font-bold mt-0.5" style="color: #1c1917;">{{ $card->cardType->name }}</p>
        </div>
        <div class="text-right">
            @if($expired)
                <span class="text-xs font-bold px-2 py-0.5 rounded" style="background:#fee2e2; color:#b91c1c;">Expirée</span>
            @elseif($expiringSoon)
                <span class="text-xs font-bold px-2 py-0.5 rounded" style="background:#fef3c7; color:#92400e;">Expire bientôt</span>
            @else
                <span class="text-xs font-bold px-2 py-0.5 rounded" style="background:#d1fae5; color:#065f46;">Active</span>
            @endif
        </div>
    </div>

    {{-- Stamp grid --}}
    <div class="relative grid gap-1.5 mb-4" style="grid-template-columns: repeat(5, 1fr);">
        @for($i = 0; $i < $total; $i++)
            <div class="aspect-square rounded flex items-center justify-center text-lg border"
                 style="{{ $i < $used
                    ? 'background-color: var(--color-primary, #2563eb); border-color: var(--color-primary, #2563eb); color: #fff; transform: rotate(-8deg);'
                    : 'background-color: #fef9ee; border-color: #d97706; color: #d97706;' }}">
                @if($i < $used)
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" />
                    </svg>
                @else
                    <span class="text-xs font-bold" style="color: var(--color-primary, #2563eb);">{{ $i + 1 }}</span>
                @endif
            </div>
        @endfor
    </div>

    {{-- Footer --}}
    <div class="relative flex justify-between text-xs" style="color: #78350f;">
        <span><strong>{{ $card->sessions_remaining }}</strong> séance(s) restante(s)</span>
        <span>Exp. {{ $card->expires_at->format('d/m/Y') }}</span>
    </div>
</div>
