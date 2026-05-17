@component('mail::message')
# Bonjour {{ $firstName }},

Notre équipe vous a retiré(e) du vivier d'attente pour le niveau **{{ $level }}**.

Pour vous réinscrire ou obtenir plus d'informations, n'hésitez pas à nous contacter.

@component('mail::button', ['url' => url('/contact')])
Nous contacter
@endcomponent

À bientôt,
{{ config('app.name') }}
@endcomponent
