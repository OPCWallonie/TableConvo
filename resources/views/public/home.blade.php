<x-app-layout>
    <div class="min-h-screen bg-gradient-to-b from-blue-50 to-white">

        {{-- Hero --}}
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-600 text-white text-2xl font-bold mb-6 shadow-lg">
                TC
            </div>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 leading-tight">
                TableConvo
            </h1>
            <p class="mt-4 text-xl text-gray-500 max-w-2xl mx-auto">
                Tables de conversation en néerlandais pour les professionnels — sessions hebdomadaires en présentiel.
            </p>

            <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('agenda') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold text-sm shadow hover:bg-blue-700 transition">
                    Voir l'agenda
                </a>
                <a href="{{ route('tarifs') }}"
                   class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-white text-blue-700 font-semibold text-sm shadow border border-blue-200 hover:bg-blue-50 transition">
                    Découvrir nos tarifs
                </a>
                @auth
                    <a href="{{ route('espace.dashboard') }}"
                       class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gray-800 text-white font-semibold text-sm shadow hover:bg-gray-900 transition">
                        Mon espace
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gray-800 text-white font-semibold text-sm shadow hover:bg-gray-900 transition">
                        Se connecter
                    </a>
                @endauth
            </div>
        </div>

        {{-- Trois arguments --}}
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <div class="text-3xl mb-3">🗓️</div>
                <h3 class="font-semibold text-gray-900 mb-1">Sessions chaque semaine</h3>
                <p class="text-sm text-gray-500">Créneaux récurrents, inscriptions en ligne, annulation flexible.</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <div class="text-3xl mb-3">🎓</div>
                <h3 class="font-semibold text-gray-900 mb-1">Niveaux A1 à C2</h3>
                <p class="text-sm text-gray-500">Groupes homogènes sur la base du CECRL pour un apprentissage efficace.</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <div class="text-3xl mb-3">🏢</div>
                <h3 class="font-semibold text-gray-900 mb-1">Formule B2B</h3>
                <p class="text-sm text-gray-500">Cartes de 10 séances facturées à votre société, TVA déduite.</p>
            </div>
        </div>

    </div>
</x-app-layout>
