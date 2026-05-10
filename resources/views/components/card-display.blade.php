@props(['card', 'design' => null])

@php
    $design = $design ?? app(\App\Settings\ThemeSettings::class)->card_design;
@endphp

@switch($design)
    @case('wallet')
        @include('components.cards.wallet', ['card' => $card])
        @break
    @case('editorial')
        @include('components.cards.editorial', ['card' => $card])
        @break
    @case('swiss')
        @include('components.cards.swiss', ['card' => $card])
        @break
    @default
        @include('components.cards.stamp', ['card' => $card])
@endswitch
