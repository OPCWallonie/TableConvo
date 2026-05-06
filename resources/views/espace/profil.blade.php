<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mon profil
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Formulaire de mise à jour du profil --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                    @csrf
                </form>

                <form method="post" action="{{ route('espace.profil.update') }}" class="space-y-5">
                    @csrf
                    @method('patch')

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
                            <div class="mt-2">
                                <p class="text-sm text-gray-800">
                                    Votre adresse e-mail n'est pas vérifiée.
                                    <button form="send-verification"
                                        class="underline text-sm text-gray-600 hover:text-gray-900">
                                        Renvoyer le lien.
                                    </button>
                                </p>
                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-1 text-sm text-green-600">Lien envoyé !</p>
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

                    <div class="flex items-center gap-4 pt-2">
                        <x-primary-button>Enregistrer</x-primary-button>

                        @if (session('status') === 'profile-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition
                                x-init="setTimeout(() => show = false, 2000)"
                                class="text-sm text-green-600">Profil mis à jour.</p>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Société (lecture seule) --}}
            @if ($user->company)
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-medium text-gray-900 mb-1">Ma société</h3>
                    <p class="text-xs text-gray-500 mb-4">Pour modifier les informations de votre société, contactez un administrateur.</p>

                    <dl class="space-y-2 text-sm text-gray-700">
                        <div><dt class="font-medium">Société</dt><dd>{{ $user->company->name }}</dd></div>
                        <div><dt class="font-medium">TVA</dt><dd>{{ $user->company->vat_number }}</dd></div>
                        <div><dt class="font-medium">Adresse</dt><dd>{{ $user->company->street }}, {{ $user->company->postal_code }} {{ $user->company->city }}</dd></div>
                        @if ($user->company->billing_email)
                            <div><dt class="font-medium">E-mail facturation</dt><dd>{{ $user->company->billing_email }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Niveau --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-medium text-gray-900 mb-2">Mon niveau CECRL</h3>
                @if ($user->level)
                    <p class="text-gray-700">
                        <span class="font-bold text-blue-700">{{ $user->level->code }}</span>
                        — {{ $user->level->name }}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">Attribué le {{ $user->level_assigned_at?->format('d/m/Y') }}</p>
                @else
                    <p class="text-amber-700 text-sm">Aucun niveau attribué pour le moment.</p>
                @endif
            </div>

            {{-- Export données RGPD --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-medium text-gray-900 mb-2">Mes données personnelles</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Conformément au RGPD, vous pouvez télécharger l'ensemble de vos données personnelles.
                </p>
                <a href="{{ route('espace.donnees') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                    Télécharger mes données (JSON)
                </a>
            </div>

            {{-- Suppression du compte --}}
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-medium text-gray-900 mb-2">Supprimer mon compte</h3>
                <p class="text-sm text-gray-600 mb-4">
                    La suppression désactive votre accès et anonymise vos données personnelles. Vos factures sont conservées 7 ans conformément à la législation belge.
                </p>

                <x-danger-button
                    x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-account-deletion')">
                    Supprimer mon compte
                </x-danger-button>
            </div>
        </div>
    </div>

    {{-- Modal suppression compte --}}
    <x-modal name="confirm-account-deletion" :show="$errors->accountDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('espace.compte.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-gray-900">Confirmer la suppression du compte</h2>

            <p class="mt-1 text-sm text-gray-600">
                Cette action est irréversible. Entrez votre mot de passe pour confirmer.
            </p>

            <div class="mt-6">
                <x-input-label for="account_password" value="Mot de passe" class="sr-only" />
                <x-text-input id="account_password" name="password" type="password" class="mt-1 block w-3/4"
                    placeholder="Mot de passe" />
                <x-input-error :messages="$errors->accountDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Annuler</x-secondary-button>
                <x-danger-button>Supprimer le compte</x-danger-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
