<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        {{-- Informations personnelles --}}
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Vos coordonnées</h3>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="first_name" value="Prénom" />
                <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name"
                    :value="old('first_name')" required autofocus autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" value="Nom" />
                <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name"
                    :value="old('last_name')" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="phone" value="Téléphone (optionnel)" />
            <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone"
                :value="old('phone')" autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- Informations société --}}
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mt-6 mb-3">Votre société</h3>

        <div>
            <x-input-label for="company_name" value="Nom de la société" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name"
                :value="old('company_name')" required autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="vat_number" value="Numéro de TVA (format BE0XXXXXXXXX)" />
            <x-text-input id="vat_number" class="block mt-1 w-full" type="text" name="vat_number"
                :value="old('vat_number')" required placeholder="BE0123456789" />
            <x-input-error :messages="$errors->get('vat_number')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="street" value="Rue et numéro" />
            <x-text-input id="street" class="block mt-1 w-full" type="text" name="street"
                :value="old('street')" required autocomplete="street-address" />
            <x-input-error :messages="$errors->get('street')" class="mt-2" />
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="col-span-1">
                <x-input-label for="postal_code" value="Code postal" />
                <x-text-input id="postal_code" class="block mt-1 w-full" type="text" name="postal_code"
                    :value="old('postal_code')" required autocomplete="postal-code" />
                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
            </div>

            <div class="col-span-2">
                <x-input-label for="city" value="Ville" />
                <x-text-input id="city" class="block mt-1 w-full" type="text" name="city"
                    :value="old('city')" required autocomplete="address-level2" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <div class="mt-4">
            <x-input-label for="billing_email" value="E-mail de facturation (optionnel)" />
            <x-text-input id="billing_email" class="block mt-1 w-full" type="email" name="billing_email"
                :value="old('billing_email')" />
            <x-input-error :messages="$errors->get('billing_email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                href="{{ route('login') }}">
                Déjà inscrit ?
            </a>

            <x-primary-button class="ms-4">
                Créer mon compte
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
