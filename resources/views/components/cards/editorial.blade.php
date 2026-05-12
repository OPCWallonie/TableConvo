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
    $expiryFormatted = $card->expires_at->format('d.m.Y');
    $cardTypeName = $cardType->name;

    $progressPercent = $total > 0 ? round(($remaining / $total) * 100) : 0;
@endphp

<div class="tc-edit" style="width:540px;height:330px;background:#fbf9f4;border:1.5px solid #1a2b4e;border-radius:0;padding:26px 32px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#1a2b4e;position:relative;box-shadow:6px 6px 0 0 #1a2b4e;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;border-bottom:1px solid #1a2b4e;padding-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;background:#1a2b4e;color:#fbf9f4;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:17px;font-weight:500;letter-spacing:1px;">TC</div>
            <div>
                <div style="font-family:Georgia,serif;font-size:18px;font-weight:500;letter-spacing:.5px;">TableConvo</div>
                <div style="font-size:10px;letter-spacing:2.5px;text-transform:uppercase;margin-top:2px;opacity:.65;">{{ $cardTypeName }} · Édition limitée</div>
            </div>
        </div>
        @if($statusLabel)
            <div style="font-size:10px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};background:transparent;border:1px solid {{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};padding:3px 9px;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap;">{{ $statusLabel }}</div>
        @endif
    </div>

    <div style="display:flex;align-items:flex-end;gap:16px;margin-bottom:22px;">
        <div style="font-family:Georgia,serif;font-size:88px;font-weight:500;line-height:.85;color:var(--color-primary);letter-spacing:-4px;">{{ $remaining }}</div>
        <div style="padding-bottom:8px;">
            <div style="font-family:Georgia,serif;font-size:22px;color:#1a2b4e;opacity:.45;font-style:italic;">/ {{ $total }}</div>
            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;margin-top:4px;opacity:.6;">séances restantes</div>
        </div>
    </div>

    <div style="height:3px;background:rgba(26,43,78,.1);position:relative;margin-bottom:18px;">
        <div style="position:absolute;top:0;left:0;height:100%;width:{{ $progressPercent }}%;background:var(--color-primary);"></div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7;">
        <span>{{ $used }} séance{{ $used > 1 ? 's' : '' }} utilisée{{ $used > 1 ? 's' : '' }}</span>
        <span style="font-family:Georgia,serif;font-size:13px;letter-spacing:.5px;text-transform:none;opacity:1;color:#1a2b4e;white-space:nowrap;">Expire le {{ $expiryFormatted }}</span>
    </div>
</div>
