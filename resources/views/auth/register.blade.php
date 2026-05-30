<x-guest-layout>
    <form method="POST" action="{{ route('register') }}"
          x-data="registerVatLookup()"
          @submit.prevent="submit">
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
            <x-input-label for="vat_number" value="Numéro de TVA (format BE0XXXXXXXXX)" />
            <x-text-input id="vat_number" class="block mt-1 w-full" type="text" name="vat_number"
                :value="old('vat_number')" required placeholder="BE0123456789"
                x-model="vatNumber"
                @input.debounce.600ms="lookup" />
            <x-input-error :messages="$errors->get('vat_number')" class="mt-2" />

            {{-- Bandeau info si société déjà connue --}}
            <template x-if="lookupStatus === 'exists'">
                <div class="mt-2 rounded-md bg-info-50 border border-info-200 p-3 text-sm text-info-800">
                    <span class="font-medium" x-text="knownName"></span> est déjà enregistrée chez TableConvo.
                    Si vous y travaillez, votre inscription vous rattachera automatiquement ou créera une demande d'adhésion.
                </div>
            </template>
        </div>

        {{-- Champs société — désactivés si company déjà connue --}}
        <div class="mt-4" :class="{ 'opacity-50 pointer-events-none': lookupStatus === 'exists' }">
            <x-input-label for="company_name" value="Nom de la société" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name"
                :value="old('company_name')" autocomplete="organization"
                x-model="companyName"
                x-bind:readonly="lookupStatus === 'exists'" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="mt-4" :class="{ 'opacity-50 pointer-events-none': lookupStatus === 'exists' }">
            <x-input-label for="street" value="Rue et numéro" />
            <x-text-input id="street" class="block mt-1 w-full" type="text" name="street"
                :value="old('street')" autocomplete="street-address"
                x-model="street"
                x-bind:readonly="lookupStatus === 'exists'" />
            <x-input-error :messages="$errors->get('street')" class="mt-2" />
        </div>

        <div class="grid grid-cols-3 gap-4 mt-4" :class="{ 'opacity-50 pointer-events-none': lookupStatus === 'exists' }">
            <div class="col-span-1">
                <x-input-label for="postal_code" value="Code postal" />
                <x-text-input id="postal_code" class="block mt-1 w-full" type="text" name="postal_code"
                    :value="old('postal_code')" autocomplete="postal-code"
                    x-bind:readonly="lookupStatus === 'exists'" />
                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
            </div>

            <div class="col-span-2">
                <x-input-label for="city" value="Ville" />
                <x-text-input id="city" class="block mt-1 w-full" type="text" name="city"
                    :value="old('city')" autocomplete="address-level2"
                    x-bind:readonly="lookupStatus === 'exists'" />
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

    <script>
    function registerVatLookup() {
        return {
            vatNumber: '{{ old('vat_number', '') }}',
            lookupStatus: null,
            knownName: '',
            companyName: '{{ old('company_name', '') }}',
            street: '{{ old('street', '') }}',

            async lookup() {
                if (this.vatNumber.length < 8) {
                    this.lookupStatus = null;
                    return;
                }

                try {
                    const res = await fetch('{{ route('register.vat-lookup') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ vat_number: this.vatNumber }),
                    });
                    const data = await res.json();

                    if (data.status === 'exists') {
                        this.lookupStatus = 'exists';
                        this.knownName = data.company?.name ?? '';
                    } else if (data.status === 'new') {
                        this.lookupStatus = 'new';
                        if (data.name) this.companyName = data.name;
                        if (data.street) this.street = data.street;
                    } else {
                        this.lookupStatus = null;
                    }
                } catch {
                    this.lookupStatus = null;
                }
            },

            submit() {
                this.$el.submit();
            },
        };
    }
    </script>
</x-guest-layout>
