@component('mail::message')
# Bonjour {{ $firstName }},

Bonne nouvelle ! Une session compatible avec votre profil de niveau **{{ $level }}** vous a été attribuée.

@component('mail::panel')
**{{ $topic }}**

{{ $scheduledAt }}
@endcomponent

@if($isRegistered)
Votre inscription est **confirmée**. Une séance a été débitée de votre carte.
@else
Vous avez été placé(e) en **liste d'attente** à la position **#{{ $waitlistPosition }}** pour cette session.
@endif

@component('mail::button', ['url' => route('espace.inscriptions')])
Voir mes inscriptions
@endcomponent

À bientôt,
{{ config('app.name') }}
@endcomponent
