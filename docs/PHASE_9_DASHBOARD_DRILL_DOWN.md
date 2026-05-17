# PHASE 9 — Widgets dashboard cliquables (drill-down)

> **Type :** Phase courte de polish UX  
> **Méthodologie :** STOP/GO en 3 étapes A → C  
> **Durée estimée :** 3-4 heures  
> **Risque :** faible — uniquement ajout de `->url()` sur widgets existants + tests d'accessibilité des URLs

---

## 0. RÈGLES D'EXÉCUTION

### 0.1 Avant de coder

1. Lire le brief intégralement
2. Lire le code de **tous les widgets actuels** dans `app/Filament/Widgets/` avant la moindre modification
3. Confirmer en début de réponse : (a) le nombre de widgets dashboard identifiés, (b) leur type (Stat / Chart / TableWidget), (c) les 3 patterns du brief que tu vas appliquer

### 0.2 STOP/GO

À chaque étape A, B, C : tu livres, tu rapportes, tu attends GO écrit d'Arnaud.

### 0.3 Format de rapport § 0.6

Sections obligatoires :
1. Livrables réalisés (chemins + résumé)
2. Checklist critères
3. Tests (sortie complète `php artisan test --parallel`)
4. Commandes auxiliaires exécutées (toutes, avec exit code)
5. Captures d'écran (Étape C uniquement)
6. Observations hors périmètre
7. Demande de GO

### 0.4 Tu ne touches PAS

- ❌ Le code des Resources Filament (sauf si l'Étape A identifie qu'une URL cible n'a pas le filtre attendu — auquel cas tu STOPpes et tu rapportes, tu ne corriges pas seul)
- ❌ Les Actions métier
- ❌ Les modèles Eloquent
- ❌ Le widget Vivier global (cf. § 3.3 ci-dessous)
- ❌ Aucun changement sur le panel utilisateur final (`/espace/*`)

---

## 1. Contexte & objectif

Le dashboard admin actuel affiche plusieurs widgets statistiques (Sessions cette semaine, Inscriptions en cours, Cartes actives, Revenus du mois HT, Taux de remplissage, Taux de no-show) ainsi que le widget Vivier global déjà livré en Phase 8.

Aujourd'hui ces widgets sont **purement informatifs** : un admin qui voit "3 inscriptions en cours" doit naviguer manuellement dans la sidebar pour trouver la liste correspondante.

**Objectif** : rendre les widgets cliquables, avec une logique de drill-down qui amène l'admin **directement sur la liste filtrée** correspondant au chiffre affiché.

---

## 2. Décisions Arnaud (verrouillées — ne pas remettre en question)

| Type de widget | Pattern à appliquer |
|---|---|
| Widgets **statistiques** (Stat numérique simple) | **Pattern 1** : lien sur toute la card via `->url(...)`, sans filtre BDD complexe — la liste cible est la Resource standard correspondante |
| Widgets **chart** (Taux de remplissage, Revenus 12 mois, Taux de no-show 30j) | **Pattern 3** : lien vers la liste cible **avec filtres pré-appliqués** correspondant à la fenêtre temporelle et au critère métier du chart |
| Widget **Vivier global** | **Inchangé** — ne pas dupliquer le lien "Voir tout le vivier" déjà présent en pied. Pas de lien sur la card globalement. |

---

## 3. Périmètre détaillé

### 3.1 Widgets statistiques (Pattern 1)

Pour chaque widget Stat du dashboard, ajouter `->url(...)` sur le Stat correspondant. La cible est la Resource Filament standard, **sans filtre BDD complexe** au-delà de filtres triviaux ("statut planifié", "période = ce mois") si la Resource les expose nativement.

Liste exhaustive (à confirmer en Étape A en lisant le code) :

| Widget | Stat affiché | Resource cible | Filtre URL si pertinent |
|---|---|---|---|
| Sessions cette semaine | Nombre de sessions planifiées cette semaine | `ConversationTableResource` | Période `scheduled_at` cette semaine, statut `Scheduled` |
| Inscriptions en cours | Nombre d'inscriptions confirmées sur sessions futures | `RegistrationResource` (si elle existe) ou `ConversationTableResource` filtrée | Statut `Registered`, session future |
| Cartes actives | Nombre de cartes avec séances disponibles | `CardResource` | Statut `Active`, `sessions_remaining > 0` |
| Revenus du mois (HT) | Total HT des commandes payées ce mois | `OrderResource` (si elle existe) | `paid_at` ce mois, statut `paid` |

**Important** : si une Resource cible n'existe pas dans le projet (par exemple `OrderResource` ou `RegistrationResource`), **ne pas la créer**. STOPPE et rapporte en Étape A : "Le widget X ne peut pas être lié — la Resource Y n'existe pas." Arnaud tranchera (création hors scope, ou skip ce widget).

### 3.2 Widgets chart (Pattern 3)

Pour les widgets chart, la card entière reste sur la page dashboard (un chart est exploratoire — on ne quitte pas le dashboard en cliquant). À la place : **ajouter une ligne "→ Voir le détail" en pied du chart**, qui pointe vers la liste cible avec filtres pré-appliqués.

| Widget chart | Lien pied à ajouter | Resource cible | Filtres URL |
|---|---|---|---|
| Taux de remplissage (12 semaines) | "→ Voir les sessions des 12 dernières semaines" | `ConversationTableResource` | `scheduled_at` >= now()->subWeeks(12) |
| Taux de no-show (30 j) | "→ Voir les inscriptions no-show des 30 derniers jours" | `RegistrationResource` (si présente) | `attendance_status = no_show`, période 30j |
| Revenus HT (12 mois) | "→ Voir les commandes payées sur 12 mois" | `OrderResource` (si présente) | `paid_at` >= now()->subMonths(12) |

**Même règle** : si la Resource cible n'existe pas, on STOPPE et on rapporte.

### 3.3 Widget Vivier global — inchangé

Ce widget a déjà son lien pied "Voir tout le vivier (N personnes) →" depuis la Phase 8. **N'y touche pas.** Pas de lien card-wide à ajouter (ça créerait deux zones de clic ambiguës).

---

## 4. Étape A — Audit & cartographie

**Objectif :** produire une cartographie exhaustive avant toute modification de code.

**Livrables Étape A :**

1. **Liste des widgets dashboard actuels** : chemin du fichier, type (`StatsOverviewWidget` / `ChartWidget` / `TableWidget`), nom de chaque Stat ou de chaque série dans le widget
2. **Mapping widget → Resource cible** : pour chaque widget, indiquer la Resource cible Filament et confirmer son existence dans `app/Filament/Resources/`
3. **Mapping filtres URL** : pour chaque cas Pattern 3, déterminer la syntaxe exacte des `tableFilters` Filament à passer en query string. **Tester** au moins une URL filtrée manuellement dans le navigateur pour confirmer qu'elle fonctionne — Filament v5 a des subtilités (`tableFilters[col][value]` vs `tableFilters[col][operator]=...`).
4. **Liste des cas bloquants** : Resources manquantes, filtres non exposés, widgets non liables — à remonter à Arnaud pour décision

**Format du rapport Étape A :**

Un tableau Markdown complet avec une ligne par Stat / Chart, colonnes : Widget | Type | Pattern | Resource cible | URL générée | Statut (OK / À discuter)

**Critère STOP/GO Étape A :**
- ✅ Cartographie exhaustive (tous les widgets sont listés)
- ✅ Au moins une URL filtrée testée manuellement et fonctionnelle (sortie de la commande `curl -I` ou test navigateur documenté)
- ✅ Liste des cas bloquants explicite

**Aucun fichier modifié à ce stade.** Arnaud valide, donne le GO Étape B.

---

## 5. Étape B — Implémentation

Pour chaque widget identifié en Étape A et validé par Arnaud :

### Pattern 1 — Stat widget

```php
Stat::make('Sessions cette semaine', $count)
    ->description('Sessions planifiées à venir cette semaine')
    ->descriptionIcon('heroicon-m-calendar')
    ->color('primary')
    ->url(\App\Filament\Resources\ConversationTable\ConversationTableResource::getUrl('index', [
        'tableFilters' => [
            'scheduled_at' => [
                'from' => now()->startOfWeek()->format('Y-m-d'),
                'to' => now()->endOfWeek()->format('Y-m-d'),
            ],
            'status' => ['value' => 'scheduled'],
        ],
    ]))
```

- Utiliser `Resource::getUrl(...)` plutôt qu'un `route(...)` direct : c'est l'API Filament officielle et ça reste cohérent si l'admin slug change un jour
- Toujours générer l'URL avec un Carbon courant — ne pas hardcoder une date

### Pattern 3 — Chart widget avec lien pied

Deux options techniques selon le widget :

**Option A — Widget natif Filament Chart sans footer** : on override la vue blade du widget (`getView()` → vue custom) qui appelle la vue Filament native et ajoute un `<a>` en pied. Plus de code mais plus contrôlable.

**Option B — Méthode `getDescription()` ou `getHeading()`** : si Filament v5 expose ces hooks sur ChartWidget, on peut peut-être y mettre un lien HTML. À tester en Étape A — sinon retomber sur Option A.

Pour Option A, structure de la vue custom :

```blade
{{-- resources/views/filament/widgets/fill-rate-chart-with-link.blade.php --}}
<x-filament-widgets::widget>
    {{-- Délègue au rendu natif du ChartWidget --}}
    @php($parent = $this::getView())
    @include('filament-widgets::chart-widget', get_defined_vars())
    
    <div style="padding: 0.75rem 1rem 0; text-align: right; font-size: 0.8125rem;">
        <a href="{{ $this->getDrillDownUrl() }}" 
           style="color: rgb(59 130 246); font-weight: 500; text-decoration: none;">
            Voir le détail →
        </a>
    </div>
</x-filament-widgets::widget>
```

Et la méthode `getDrillDownUrl()` ajoutée dans la classe widget.

**Conventions visuelles** :
- Le lien pied est **discret** : taille réduite, couleur primaire, jamais en bouton
- Style identique entre les 3 widgets chart concernés (cohérence visuelle)
- Sur les widgets Stat (Pattern 1), pas de modification visuelle — Filament gère nativement l'effet hover/click sur les cards avec `->url()`

### Contraintes Étape B

- **Aucune création de widget**, uniquement modification des widgets existants
- **Aucune modification de Resource**
- **Aucune modification des données affichées** (les compteurs et calculs actuels restent identiques)
- Les classes Tailwind utilisées dans la vue custom doivent être **soit du CSS inline**, soit des classes **garanties présentes dans le CSS Filament admin** (`text-primary-600`, `font-medium`, etc.). En cas de doute → CSS inline (cf. leçon Phase 8 sur les variables CSS manquantes du theming Phase 7 dans le panel admin)

**Critère STOP/GO Étape B :**
- ✅ Tous les widgets validés en Étape A sont patchés
- ✅ Aucune autre modification
- ✅ Suite complète Pest : 439/439 verts (les widgets ne sont pas couverts par des tests unitaires sur le contenu HTML, mais les tests existants ne doivent pas régresser)

---

## 6. Étape C — Tests + captures

### 6.1 Tests Pest

Créer `tests/Feature/Filament/Widgets/DashboardDrillDownTest.php` avec un test par widget patché :

```php
it('renders dashboard widget with drill-down URL', function () {
    actingAsAdmin();
    
    livewire(\App\Filament\Widgets\SessionsThisWeekStat::class)
        ->assertSuccessful();
    
    // Vérifier que le rendu HTML contient l'URL attendue
    livewire(\App\Filament\Widgets\SessionsThisWeekStat::class)
        ->assertSeeHtml(\App\Filament\Resources\ConversationTable\ConversationTableResource::getUrl('index'));
});
```

**Minimum requis :** 1 test par widget Stat patché + 1 test par widget Chart patché.

**Pas de test fonctionnel des filtres URL** (ce serait un test d'intégration Filament trop fragile). On se contente de vérifier que l'URL pointe vers la bonne Resource.

### 6.2 Captures Playwright

Une capture par widget patché, montrant :
- Le widget rendu avec l'élément cliquable (curseur pointer visible si possible)
- La page de destination après clic, avec les filtres effectivement appliqués visibles dans l'URL et dans l'UI Filament

**Total attendu :** 2 captures × nombre de widgets patchés (avant clic + après clic).

### Critère STOP/GO Étape C

- ✅ Tests verts : 439 + (nb widgets patchés) tests
- ✅ Captures complètes
- ✅ Vérification manuelle des URLs : Arnaud sera invité à cliquer sur chaque widget dans son propre navigateur pour confirmer le comportement avant le GO final

---

## 7. Bilan final attendu

| Indicateur | Avant Phase 9 | Après Phase 9 |
|---|---|---|
| Widgets dashboard | N (lecture seule) | N (avec navigation) |
| Widgets Pattern 1 (Stat → liste) | 0 | X |
| Widgets Pattern 3 (Chart → liste filtrée) | 0 | Y |
| Tests Pest | 439 | 439 + (X + Y) |
| Resources modifiées | — | 0 (uniquement les widgets) |

---

## 8. Hors scope explicite

- ❌ Création de Resources manquantes (`OrderResource`, `RegistrationResource` si absentes)
- ❌ Charts cliquables sur un point spécifique (drill-down par point de donnée) — c'est une Phase ultérieure si besoin
- ❌ Personnalisation des couleurs/styles des widgets au-delà du lien pied
- ❌ Réorganisation du dashboard
- ❌ Ajout de nouveaux widgets

---

**Fin du brief Phase 9.**

*Phase 10 = déploiement (intentionnellement repoussée après la Phase 9).*
