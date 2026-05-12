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

    // Calcul des dimensions de grille selon le total
    $cols = $total <= 5 ? $total : 5;
    $rows = (int) ceil($total / 5);
    $rowHeight = $rows === 1 ? 62 : ($rows === 2 ? 62 : ($rows === 3 ? 40 : 30));
    $rotations = [-9, -5, -12, -7, -10, -6, -11, -8, -4, -13, -6, -10, -8, -11, -5, -9, -7, -12, -6, -8];
@endphp

<div class="tc-stamp" style="width:540px;height:330px;background:#f6f1e6;background-image:radial-gradient(circle at 18% 28%,rgba(217,119,6,.05) 0%,transparent 45%),radial-gradient(circle at 82% 75%,rgba(37,99,235,.04) 0%,transparent 50%);border:0.5px solid #e8dec5;border-radius:14px;padding:22px 30px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#1a2b4e;position:relative;overflow:hidden;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="position:absolute;top:14px;right:16px;width:74px;height:74px;border:1.5px solid rgba(217,119,6,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;transform:rotate(8deg);opacity:.35;">
        <div style="text-align:center;font-family:Georgia,serif;color:var(--color-accent);">
            <div style="font-size:9px;letter-spacing:1.5px;">CONVERSATION</div>
            <div style="font-size:18px;font-weight:500;line-height:1;margin:2px 0;">★</div>
            <div style="font-size:9px;letter-spacing:1.5px;">CARD</div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;">
        <div style="width:38px;height:38px;background:#1a2b4e;color:#f6f1e6;display:flex;align-items:center;justify-content:center;border-radius:6px;font-family:Georgia,serif;font-size:16px;font-weight:500;letter-spacing:1px;">TC</div>
        <div>
            <div style="font-size:13px;font-weight:500;letter-spacing:1.8px;text-transform:uppercase;">TableConvo</div>
            <div style="font-size:10.5px;color:#6b7894;letter-spacing:.5px;margin-top:1px;">{{ $cardTypeName }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat({{ $cols }},1fr);grid-template-rows:repeat({{ $rows }},{{ $rowHeight }}px);gap:9px;margin-bottom:20px;">
        @for($i = 1; $i <= $total; $i++)
            @if($i <= $used)
                <div style="background:rgba(217,119,6,.04);border:0.5px solid rgba(217,119,6,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" width="32" height="32" style="transform:rotate({{ $rotations[($i-1) % count($rotations)] }}deg);opacity:.82;color:var(--color-primary);"><path d="M5 12 L10 17 L19 7" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            @else
                <div style="background:#fff;border:0.5px solid rgba(217,119,6,.3);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#6b7894;font-size:15px;font-family:Georgia,serif;">{{ $i }}</div>
            @endif
        @endfor
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:0.5px dashed #e8dec5;padding-top:12px;">
        <div>
            <div style="font-size:10px;color:#6b7894;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:3px;">Séances restantes</div>
            <div style="font-family:Georgia,serif;color:var(--color-primary);line-height:1;"><span style="font-size:26px;font-weight:500;">{{ $remaining }}</span><span style="font-size:14px;color:#6b7894;"> / {{ $total }}</span></div>
        </div>
        <div style="text-align:right;white-space:nowrap;">
            <div style="font-size:10px;color:#6b7894;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:3px;">Validité</div>
            <div style="font-size:14px;color:#1a2b4e;font-family:Georgia,serif;line-height:1;">{{ $expiryFormatted }}</div>
            @if($statusLabel)
                <div style="display:inline-block;margin-top:5px;font-size:10px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};background:{{ $isExpired ? 'rgba(220,38,38,.1)' : 'rgba(217,119,6,.1)' }};padding:2px 7px;border-radius:3px;letter-spacing:.5px;line-height:1.4;">{{ $statusLabel }}</div>
            @endif
        </div>
    </div>
</div>
