<div style="--color-primary: {{ $primaryColor }}; --color-accent: {{ $accentColor }}; --color-surface: {{ $surfaceColor }}; max-width: 24rem;">
    @include('components.cards.' . $safeDesign, ['card' => $card])
</div>
