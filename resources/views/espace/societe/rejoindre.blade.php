<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Rejoindre une société
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Demande déjà en attente --}}
            @if ($pendingRequest)
                <div class="bg-warning-50 border border-warning-300 text-warning-800 rounded-lg p-4">
                    <p class="font-medium">Demande en attente pour {{ $pendingRequest->company->name }}</p>
                    <p class="text-sm mt-1">Votre demande d'adhésion a été transmise. Vous serez notifié par e-mail dès qu'elle sera traitée.</p>
                    <form method="POST" action="{{ route('espace.societe.ma-demande.annuler') }}" class="mt-3">
                        @csrf
                        <button type="submit"
                            class="text-sm font-medium text-warning-700 underline hover:no-underline"
                            onclick="return confirm('Annuler votre demande ?')">
                            Annuler ma demande
                        </button>
                    </form>
                </div>
            @endif

            @if (session('status') === 'request_cancelled')
                <div class="bg-success-50 border border-success-300 text-success-800 rounded-lg p-4 text-sm">
                    Votre demande a bien été annulée.
                </div>
            @endif

            @if (session('status') === 'already_requested')
                <div class="bg-warning-50 border border-warning-300 text-warning-800 rounded-lg p-4 text-sm">
                    Vous avez déjà une demande en attente pour cette société.
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6"
                 x-data="companyLookup('{{ $vatPrefill ?? '' }}', @json(Auth::user()->email))">

                <h3 class="text-base font-semibold text-gray-900 mb-1">Rechercher votre société par numéro de TVA</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Saisissez le numéro de TVA de votre employeur. Si la société est inconnue, vous pourrez la
                    <a href="{{ route('espace.societe.creer') }}" class="underline hover:no-underline">créer</a>.
                </p>

                {{-- Champ TVA + recherche --}}
                <div class="flex gap-2 mb-4">
                    <x-text-input type="text" class="block w-full" placeholder="BE0123456789"
                        x-model="vatNumber" x-on:keydown.enter.prevent="search" />
                    <button type="button" @click="search" :disabled="loading"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        <span x-show="!loading">Rechercher</span>
                        <span x-show="loading">…</span>
                    </button>
                </div>

                {{-- Résultats --}}
                <div x-show="result !== null" class="mt-4 space-y-4">

                    {{-- TVA inconnue --}}
                    <template x-if="result && result.status === 'unknown'">
                        <div class="rounded-lg border border-info-200 bg-info-50 p-4 text-sm text-info-800">
                            <p class="font-medium">Cette société n'est pas encore enregistrée chez TableConvo.</p>
                            <p class="mt-1" x-show="result.name">
                                VIES nous indique : <span x-text="result.name"></span>
                            </p>
                            <a :href="'{{ route('espace.societe.creer') }}?vat=' + encodeURIComponent(vatNumber)"
                               class="mt-3 inline-flex items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:opacity-90">
                                Créer cette société
                            </a>
                        </div>
                    </template>

                    {{-- Société trouvée + auto-join --}}
                    <template x-if="result && result.status === 'exists' && result.can_auto_join">
                        <div class="rounded-lg border border-success-200 bg-success-50 p-4">
                            <p class="font-semibold text-success-800" x-text="result.company.name"></p>
                            <p class="text-sm text-success-700 mt-1" x-text="result.company.address"></p>
                            <p class="text-sm text-success-700 mt-2">
                                Votre adresse e-mail professionnelle correspond — vous pouvez rejoindre immédiatement.
                            </p>
                            <form method="POST" action="{{ route('espace.societe.rejoindre.store') }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="vat_number" :value="vatNumber" />
                                <x-primary-button type="submit" x-text="'Rejoindre ' + result.company.name"></x-primary-button>
                            </form>
                        </div>
                    </template>

                    {{-- Société trouvée — demande requise --}}
                    <template x-if="result && result.status === 'exists' && !result.can_auto_join">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <p class="font-semibold text-gray-900" x-text="result.company.name"></p>
                            <p class="text-sm text-gray-600 mt-1" x-text="result.company.address"></p>
                            <p class="text-sm text-gray-700 mt-2">
                                Votre demande sera transmise à l'administrateur de la société pour approbation.
                            </p>
                            <form method="POST" action="{{ route('espace.societe.rejoindre.store') }}" class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="vat_number" :value="vatNumber" />
                                <div>
                                    <x-input-label for="message" value="Message (optionnel)" />
                                    <textarea id="message" name="message" rows="3"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-primary/30"
                                        placeholder="Précisez votre poste ou département si utile…" maxlength="1000"></textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('message')" />
                                </div>
                                <x-primary-button type="submit">Envoyer la demande</x-primary-button>
                            </form>
                        </div>
                    </template>

                    {{-- Format invalide --}}
                    <template x-if="result && result.status === 'invalid_format'">
                        <div class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-800">
                            Format de TVA invalide. Utilisez le format <strong>BE0XXXXXXXXX</strong>.
                        </div>
                    </template>

                    {{-- VIES indisponible --}}
                    <template x-if="result && result.status === 'vies_failed'">
                        <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800">
                            Le service VIES est momentanément indisponible. Réessayez dans quelques instants.
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </div>

    <script>
    function companyLookup(prefill, userEmail) {
        return {
            vatNumber: prefill || '',
            loading: false,
            result: null,

            async search() {
                if (! this.vatNumber) return;
                this.loading = true;
                this.result = null;

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
                    this.result = await res.json();
                } catch (e) {
                    this.result = { status: 'vies_failed' };
                } finally {
                    this.loading = false;
                }
            },

            init() {
                if (this.vatNumber) this.search();
            },
        };
    }
    </script>
</x-app-layout>
