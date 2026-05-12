@props(['card'])

@php
    $cardType = $card->cardType;
    $total = $cardType->sessions_count;
    $remaining = $card->sessions_remaining;
    $used = $total - $remaining;

    $isExpired = $card->status === \App\Enums\CardStatus::Expired
        || $card->expires_at->isPast();

    $warnDays = app(\App\Settings\CardSettings::class)->expiration_warning_days;
    $maxWarn = collect($warnDays)->max() ?? 30;
    $expiresSoon = !$isExpired
        && $card->expires_at->diffInDays(now()) <= $maxWarn;

    $statusLabel = $isExpired ? 'Expirée' : ($expiresSoon ? 'Expire bientôt' : null);
    $expiryLong = $card->expires_at->translatedFormat('d F Y');
    $cardTypeName = $cardType->name;
@endphp

<div class="tc-wallet" style="width:540px;height:330px;background:var(--color-primary);background-image:radial-gradient(circle at 110% -10%,rgba(255,255,255,.18) 0%,transparent 55%);border-radius:18px;padding:26px 32px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#fff;position:relative;overflow:hidden;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="position:absolute;bottom:-40px;right:-40px;width:200px;height:200px;border:1px solid rgba(255,255,255,.08);border-radius:50%;"></div>
    <div style="position:absolute;bottom:-80px;right:-80px;width:280px;height:280px;border:1px solid rgba(255,255,255,.06);border-radius:50%;"></div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;position:relative;z-index:1;">
        <div style="display:flex;align-items:center;gap:11px;">
            <div style="width:38px;height:38px;background:#fff;color:var(--color-primary);display:flex;align-items:center;justify-content:center;border-radius:8px;font-family:Georgia,serif;font-size:16px;font-weight:500;letter-spacing:1px;">TC</div>
            <div>
                <div style="font-size:13px;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;">TableConvo</div>
                <div style="font-size:10.5px;opacity:.7;letter-spacing:.5px;margin-top:1px;">{{ $cardTypeName }} · {{ $total }} séances</div>
            </div>
        </div>
        @if($statusLabel)
            <div style="font-size:10px;color:#fffbe6;background:{{ $isExpired ? 'rgba(220,38,38,.7)' : 'rgba(217,119,6,.65)' }};padding:4px 10px;border-radius:99px;letter-spacing:.5px;white-space:nowrap;">{{ $statusLabel }}</div>
        @endif
    </div>

    <div style="margin-bottom:22px;position:relative;z-index:1;">
        <div style="font-size:10px;opacity:.6;letter-spacing:1.8px;text-transform:uppercase;margin-bottom:4px;">Séances restantes</div>
        <div style="font-size:54px;font-weight:500;line-height:1;letter-spacing:-2px;">{{ $remaining }}<span style="font-size:24px;opacity:.55;font-weight:400;"> / {{ $total }}</span></div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:18px;position:relative;z-index:1;">
        @for($i = 1; $i <= $total; $i++)
            <div style="flex:1;height:10px;background:{{ $i <= $used ? 'var(--color-accent)' : 'rgba(255,255,255,.22)' }};border-radius:99px;"></div>
        @endfor
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1;">
        <div style="font-size:11px;opacity:.6;letter-spacing:.5px;">Valable jusqu'au</div>
        <div style="font-size:14px;font-weight:500;letter-spacing:.5px;white-space:nowrap;">{{ $expiryLong }}</div>
    </div>
</div>
