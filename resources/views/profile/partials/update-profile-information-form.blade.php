<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Informations du profil</h2>
        <p class="mt-1 text-sm text-gray-600">Mettez à jour vos coordonnées personnelles et professionnelles.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Coordonnées personnelles --}}
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Coordonnées personnelles</h3>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="first_name" value="Prénom" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                    :value="old('first_name', $user->first_name)" required autofocus autocomplete="given-name" />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div>
                <x-input-label for="last_name" value="Nom" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full"
                    :value="old('last_name', $user->last_name)" required autocomplete="family-name" />
                <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        Votre adresse e-mail n'est pas vérifiée.
                        <button form="send-verification"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cliquez ici pour renvoyer le lien de vérification.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" value="Téléphone (optionnel)" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full"
                :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        {{-- Informations société --}}
        @if ($user->company)
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide pt-2">Société</h3>

            <div>
                <x-input-label for="company_name" value="Nom de la société" />
                <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full"
                    :value="old('company_name', $user->company->name)" required autocomplete="organization" />
                <x-input-error class="mt-2" :messages="$errors->get('company_name')" />
            </div>

            <div>
                <x-input-label for="vat_number" value="Numéro de TVA" />
                <x-text-input id="vat_number" name="vat_number" type="text" class="mt-1 block w-full bg-gray-50"
                    :value="$user->company->vat_number" readonly />
                <p class="mt-1 text-xs text-gray-500">Le numéro de TVA ne peut pas être modifié. Contactez-nous si nécessaire.</p>
            </div>

            <div>
                <x-input-label for="street" value="Rue et numéro" />
                <x-text-input id="street" name="street" type="text" class="mt-1 block w-full"
                    :value="old('street', $user->company->street)" required autocomplete="street-address" />
                <x-input-error class="mt-2" :messages="$errors->get('street')" />
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-1">
                    <x-input-label for="postal_code" value="Code postal" />
                    <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full"
                        :value="old('postal_code', $user->company->postal_code)" required autocomplete="postal-code" />
                    <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                </div>

                <div class="col-span-2">
                    <x-input-label for="city" value="Ville" />
                    <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                        :value="old('city', $user->company->city)" required autocomplete="address-level2" />
                    <x-input-error class="mt-2" :messages="$errors->get('city')" />
                </div>
            </div>

            <div>
                <x-input-label for="billing_email" value="E-mail de facturation (optionnel)" />
                <x-text-input id="billing_email" name="billing_email" type="email" class="mt-1 block w-full"
                    :value="old('billing_email', $user->company->billing_email)" />
                <x-input-error class="mt-2" :messages="$errors->get('billing_email')" />
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>Enregistrer</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600">Enregistré.</p>
            @endif
        </div>
    </form>
</section>
