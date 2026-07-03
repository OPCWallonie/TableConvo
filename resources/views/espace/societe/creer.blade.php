<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Créer ma société
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Info : lien vers rejoindre --}}
            <div class="bg-info-50 border border-info-200 rounded-lg p-4 text-sm text-info-800">
                Si votre entreprise est <strong>déjà enregistrée chez TableConvo</strong>, vous pouvez
                <a href="{{ route('espace.societe.rejoindre') }}" class="font-medium underline hover:no-underline">
                    rejoindre la société existante
                </a>
                plutôt que d'en créer une nouvelle.
            </div>

            {{-- Flash company_exists --}}
            @if (session('status') === 'company_exists')
                <div class="bg-warning-50 border border-warning-300 text-warning-800 rounded-lg p-4 text-sm">
                    Ce numéro de TVA est déjà enregistré chez TableConvo. Vous avez été redirigé vers le formulaire de demande d'adhésion.
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Informations de la société</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Renseignez votre numéro de TVA. Cliquez sur "Vérifier VIES" pour pré-remplir automatiquement le nom et l'adresse.
                </p>

                <form method="POST" action="{{ route('espace.societe.store') }}" class="space-y-5"
                      x-data="vatLookup('{{ $vatPrefill ?? '' }}')" @submit.prevent="submitForm">

                    @csrf

                    {{-- TVA + bouton VIES --}}
                    <div>
                        <x-input-label for="vat_number" value="Numéro de TVA (format BE0XXXXXXXXX)" />
                        <div class="flex gap-2 mt-1">
                            <x-text-input id="vat_number" name="vat_number" type="text"
                                class="block w-full" placeholder="BE0123456789"
                                x-model="vatNumber"
                                :value="old('vat_number', $vatPrefill ?? '')" />
                            <button type="button"
                                @click="checkVies"
                                :disabled="loading"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                                <span x-show="!loading">Vérifier VIES</span>
                                <span x-show="loading">…</span>
                            </button>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('vat_number')" />
                        <p x-show="viesMessage" x-text="viesMessage" class="mt-1 text-sm text-info-700"></p>
                    </div>

                    {{-- Nom --}}
                    <div>
                        <x-input-label for="company_name" value="Nom de la société" />
                        <x-text-input id="company_name" name="company_name" type="text"
                            class="mt-1 block w-full" x-model="companyName"
                            :value="old('company_name')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('company_name')" />
                    </div>

                    {{-- Adresse --}}
                    <div>
                        <x-input-label for="street" value="Rue et numéro" />
                        <x-text-input id="street" name="street" type="text"
                            class="mt-1 block w-full" x-model="street"
                            :value="old('street')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('street')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="postal_code" value="Code postal" />
                            <x-text-input id="postal_code" name="postal_code" type="text"
                                class="mt-1 block w-full" :value="old('postal_code')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                        </div>
                        <div>
                            <x-input-label for="city" value="Ville" />
                            <x-text-input id="city" name="city" type="text"
                                class="mt-1 block w-full" :value="old('city')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('city')" />
                        </div>
                    </div>

                    {{-- E-mail facturation --}}
                    <div>
                        <x-input-label for="billing_email" value="E-mail de facturation (optionnel)" />
                        <x-text-input id="billing_email" name="billing_email" type="email"
                            class="mt-1 block w-full" :value="old('billing_email')" />
                        <x-input-error class="mt-2" :messages="$errors->get('billing_email')" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <x-primary-button type="submit">Créer ma société</x-primary-button>
                        <a href="{{ route('espace.profil') }}"
                            class="text-sm text-gray-600 hover:text-gray-800">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function vatLookup(prefill) {
        return {
            vatNumber: prefill || '',
            companyName: '',
            street: '',
            loading: false,
            viesMessage: '',

            async checkVies() {
                if (! this.vatNumber) return;
                this.loading = true;
                this.viesMessage = '';

                try {
                    const res = await fetch('{{ route('espace.societe.rejoindre.lookup') }}', {
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
                        this.viesMessage = 'Ce numéro de TVA est déjà enregistré chez TableConvo. Utilisez le formulaire de demande d\'adhésion.';
                    } else if (data.status === 'unknown' && data.name) {
                        this.companyName = data.name;
                        this.street = data.address || '';
                        this.viesMessage = 'Données VIES importées. Vérifiez et complétez si nécessaire.';
                    } else if (data.status === 'invalid_format') {
                        this.viesMessage = 'Format de TVA invalide. Utilisez le format BE0XXXXXXXXX.';
                    } else if (data.status === 'vies_failed') {
                        this.viesMessage = 'Service VIES indisponible. Saisissez les informations manuellement.';
                    }
                } catch (e) {
                    this.viesMessage = 'Erreur lors de la vérification. Saisissez les informations manuellement.';
                } finally {
                    this.loading = false;
                }
            },

            submitForm() {
                this.$el.submit();
            },
        };
    }
    </script>
</x-app-layout>
