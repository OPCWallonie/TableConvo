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
    $cardTypeName = $cardType->name;
    $cardSlug = strtolower(\Illuminate\Support\Str::slug($cardTypeName, '.'));
@endphp

<div class="tc-swiss" style="width:540px;height:330px;background:#fff;border:0.5px solid #e5e5e5;border-radius:4px;padding:30px 36px;box-sizing:border-box;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#000;position:relative;display:flex;flex-direction:column;justify-content:space-between;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:13px;font-weight:500;letter-spacing:.5px;">TC</div>
                <div style="font-size:13px;font-weight:500;letter-spacing:.5px;">TableConvo</div>
            </div>
            @if($statusLabel)
                <div style="font-family:'JetBrains Mono','Courier New',monospace;font-size:11px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};letter-spacing:.5px;white-space:nowrap;">! {{ $statusLabel }}</div>
            @endif
        </div>
        <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:6px;">
            <div style="font-size:72px;font-weight:300;line-height:1;letter-spacing:-3px;color:var(--color-primary);">{{ $remaining }}</div>
            <div style="font-size:22px;font-weight:300;color:#999;letter-spacing:-1px;">/ {{ $total }}</div>
        </div>
        <div style="font-size:11px;color:#999;letter-spacing:.8px;text-transform:uppercase;font-weight:500;">Séances restantes</div>
    </div>

    <div>
        <div style="display:flex;gap:3px;margin-bottom:18px;">
            @for($i = 1; $i <= $total; $i++)
                <div style="flex:1;height:4px;background:{{ $i <= $used ? 'var(--color-primary)' : '#e5e5e5' }};"></div>
            @endfor
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-family:'JetBrains Mono','Courier New',monospace;font-size:11px;color:#666;letter-spacing:.5px;">
            <span>{{ $cardSlug }}.{{ $total }}</span>
            <span style="white-space:nowrap;">exp {{ $card->expires_at->format('Y-m-d') }}</span>
        </div>
    </div>
</div>
