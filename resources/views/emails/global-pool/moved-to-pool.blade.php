@component('mail::message')
# Bonjour {{ $firstName }},

@if($wasCancelled && $topic)
Suite à l'annulation de votre inscription à la session **{{ $topic }}** ({{ $date }}), votre dossier a été conservé dans notre vivier d'attente.
@elseif($topic)
Suite à votre retrait de la liste d'attente pour la session **{{ $topic }}** ({{ $date }}), votre dossier a été conservé dans notre vivier d'attente.
@else
Vous avez été ajouté(e) à notre vivier d'attente.
@endif

Votre profil est désormais référencé pour le **niveau {{ $level }}**. Notre équipe vous proposera une session compatible dès qu'une opportunité se présentera, en respectant l'ordre d'ancienneté.

@if($wasRecredited)
@component('mail::panel')
✓ La séance a été recréditée sur votre carte.
@endcomponent
@endif

@component('mail::button', ['url' => route('espace.inscriptions')])
Voir mes inscriptions
@endcomponent

À bientôt,
{{ config('app.name') }}
@endcomponent
