# PHASE 9.5 — OrderResource + RegistrationResource + finalisation drill-down dashboard

> **Type :** Phase complète d'extension du panel admin Filament  
> **Méthodologie :** STOP/GO en 5 étapes A → E  
> **Durée estimée :** 6-8 heures Claude Code  
> **Risque :** modéré — création de 2 Resources non triviales + correction d'un bug pré-existant + patch de 4 widgets

---

## 0. RÈGLES D'EXÉCUTION

### 0.1 Avant de coder

1. Lire le brief intégralement
2. Lire le code de **toutes les Resources Filament existantes** dans `app/Filament/Resources/` pour identifier les patterns à répliquer (en particulier les Resources qui gèrent des entités liées à plusieurs autres : `UserResource`, `ConversationTableResource`)
3. Lire les modèles `Order` et `Registration` (+ leurs migrations) pour comprendre leur structure exacte
4. Confirmer en début de réponse : (a) le nombre de Resources existantes auditées, (b) les 3 patterns Filament que tu vas répliquer, (c) la liste des champs `Order` et `Registration` que tu identifies

### 0.2 STOP/GO

À chaque étape A, B, C, D, E : tu livres, tu rapportes, tu attends GO écrit d'Arnaud.

### 0.3 Format de rapport § 0.6 (rappel)

Sections obligatoires, dans l'ordre :
1. Livrables réalisés (chemins + résumé)
2. Checklist critères du step
3. Tests (sortie complète `php artisan test --parallel`)
4. Commandes auxiliaires exécutées (toutes, avec exit code et corrections appliquées)
5. Captures Playwright (étapes pertinentes)
6. Observations hors périmètre
7. Demande de GO

### 0.4 Tu ne touches PAS

- ❌ Aucune Action métier (`OrderAction`, `RegistrationAction`, etc.)
- ❌ Aucun modèle Eloquent (sauf si une relation manque manifestement — auquel cas STOP et rapporte, ne corrige pas seul)
- ❌ Aucune migration
- ❌ Aucun test existant
- ❌ Le widget Vivier global (déjà fonctionnel)
- ❌ Les 3 widgets déjà liés en Phase 9 (Sessions cette semaine, Cartes actives, Taux de remplissage)
- ❌ Le panel utilisateur final (`/espace/*`)

---

## 1. Contexte & objectifs

À l'issue de la Phase 9 :
- 3 widgets dashboard sont cliquables (drill-down fonctionnel)
- **4 widgets restent inertes** car leurs Resources cibles n'existent pas (`OrderResource`, `RegistrationResource`)
- Un **bug pré-existant** a été découvert dans `ActivityRelationManager.php` : usage de `tableFilters` au lieu de `filters` dans les query strings → les liens "Edit User → journal d'activité" et "Edit Card → journal d'activité" ne filtrent pas réellement

Cette Phase 9.5 :
1. Crée `OrderResource` (lecture + actions limitées)
2. Crée `RegistrationResource` (lecture + actions limitées)
3. Corrige le bug `ActivityRelationManager`
4. Lie les 4 widgets dashboard restants vers ces nouvelles Resources

---

## 2. Périmètre

### Inclus

- Création de `OrderResource` avec :
  - Table de liste avec colonnes, filtres, recherche
  - Page d'édition en lecture (champs disabled — modification de commande hors scope, c'est une opération métier qui doit passer par Action dédiée)
  - Page de visualisation des `OrderItem` associés (RelationManager ou colonnes inline)
  - Action "Télécharger la facture" si applicable (lien vers le PDF existant via `GenerateInvoiceAction`)
  - **Pas d'action `Create` ni `Delete` exposée** (commandes créées exclusivement via achat Mollie + soft delete via Action métier le cas échéant)

- Création de `RegistrationResource` avec :
  - Table de liste avec colonnes, filtres, recherche
  - Page d'édition en lecture (champs disabled)
  - **Pas d'action `Create` ni `Delete` exposée** (inscriptions créées via `RegisterUserToTableAction`, supprimées via `CancelRegistrationAction`)

- Correction `ActivityRelationManager.php` : `tableFilters` → `filters`

- Patch des 4 widgets dashboard restants pour pointer vers les nouvelles Resources avec filtres pré-appliqués

- Tests Pest pour les 2 Resources + tests pour le drill-down des 4 widgets

### Exclus

- ❌ Aucune Action métier créée ou modifiée
- ❌ Aucune migration BDD
- ❌ Aucun nouveau champ sur les modèles
- ❌ Aucun export CSV/Excel (peut être backlog Phase 10 ou ultérieur)
- ❌ Aucune statistique custom dans les Resources (les widgets dashboard suffisent)
- ❌ Création d'un panel de gestion des litiges/remboursements (hors scope produit)

---

## 3. Étape A — Audit & cartographie

### Livrables Étape A

**A.1 — Audit des modèles `Order` et `Registration`**

Pour chaque modèle, produire :
- Liste exhaustive des champs (avec types, casts, défauts)
- Liste des relations Eloquent (`belongsTo`, `hasMany`, `morphTo`, etc.)
- Liste des scopes existants (`scopePaid`, `scopeRegistered`, etc.)
- Liste des accessors / mutators
- Présence ou non du trait `SoftDeletes`
- Énumérations utilisées (`OrderStatus`, `RegistrationStatus`, etc.) avec valeurs possibles

**A.2 — Audit des Resources existantes du projet**

Identifier 2 Resources de référence à cloner comme pattern :
- Pour `OrderResource` : la Resource existante la plus proche (probablement `CompanyResource` ou `UserResource`)
- Pour `RegistrationResource` : idem

Pour chaque Resource de référence, documenter :
- Structure du dossier (`Pages/`, `Tables/`, `Schemas/`, `RelationManagers/`)
- Pattern d'organisation entre `Resource.php` et `Tables/*Table.php`
- Conventions de nommage (`ListXxx`, `EditXxx`, `CreateXxx`...)
- Gestion des Filters (SelectFilter, TernaryFilter, Filter avec query closure)

**A.3 — Cartographie des filtres requis**

Pour chaque widget dashboard à lier, déterminer les filtres URL exacts à appliquer :

| Widget | Stat / Chart | Resource cible | Filtres URL attendus |
|---|---|---|---|
| `OperationalStatsWidget` | Inscriptions en cours | `RegistrationResource` | `filters[status][value]=registered` + `filters[session_future][isActive]=true` (à créer ?) |
| `OperationalStatsWidget` | Revenus du mois (HT) | `OrderResource` | `filters[status][value]=paid` + `filters[current_month][isActive]=true` (à créer) |
| `NoShowRateWidget` | Taux de no-show (30 j) | `RegistrationResource` | `filters[attendance_status][value]=no_show` + filtre période 30j (à créer) |
| `RevenueChartWidget` | Revenus HT (12 mois) | `OrderResource` | `filters[status][value]=paid` + filtre période 12 mois (à créer) |

**Important** : la logique métier de chaque chiffre (par exemple "comment compte-t-on les revenus du mois ?") doit être **strictement identique** entre le widget et le filtre de la Resource. Vérifier le code des widgets pour s'aligner.

**A.4 — Vérification visuelle du format URL `filters`**

Tester manuellement au moins une URL filtrée sur les Resources existantes patchées en Phase 9 (`ConversationTableResource` ou `CardResource`) pour confirmer que la syntaxe `filters[xxx][isActive]=true` ou `filters[xxx][value]=yyy` fonctionne. Coller la sortie d'au moins 1 capture ou de `curl -I` confirmant le statut 200.

**A.5 — Identification du bug `ActivityRelationManager.php`**

Localiser le fichier, identifier les occurrences exactes de `tableFilters` à remplacer par `filters`. Lister chaque occurrence (numéro de ligne).

### Critère STOP/GO Étape A

- ✅ Audit des 2 modèles complet et structuré
- ✅ Pattern de référence identifié pour chaque Resource (1 Resource existante citée explicitement)
- ✅ Cartographie filtres complète avec mention explicite des filtres à créer
- ✅ Vérification visuelle du format URL `filters` documentée
- ✅ Liste des lignes à corriger dans `ActivityRelationManager.php`

**Aucun fichier modifié à ce stade.** Arnaud valide la cartographie, le plan, et donne le GO Étape B.

---

## 4. Étape B — `OrderResource`

### Livrables Étape B

**B.1 — Structure de la Resource**

Créer dans `app/Filament/Resources/Orders/` :

```
OrderResource.php
Pages/
  ListOrders.php
  ViewOrder.php  (PAS EditOrder — la Resource est en lecture)
Tables/
  OrdersTable.php
Schemas/
  OrderForm.php  (si nécessaire pour la page View, avec champs disabled)
RelationManagers/
  OrderItemsRelationManager.php  (pour afficher les lignes de la commande)
```

**B.2 — Configuration de la Resource**

```text
Slug              : orders     (URL /admin/orders)
Model             : Order
Navigation label  : Commandes
Navigation group  : Gestion des inscriptions  (ou crée un nouveau groupe "Comptabilité")
Navigation sort   : à déterminer en cohérence avec l'arbo existante
Icon              : Heroicon::OutlinedShoppingBag
canCreate         : false
canEdit           : false  (lecture seule)
canDelete         : false
canView           : auth()->user()?->hasRole('admin')
```

**B.3 — Table de liste**

Colonnes minimales :
- **Numéro de commande** (champ existant, probablement `invoice_number` ou similaire — vérifier sur le modèle)
- **Date** (`created_at` formatée `d/m/Y H:i`)
- **Client** (`user.full_name`, searchable, sortable)
- **Société** (`company.name` si applicable, toggleable hidden par défaut)
- **Montant HT** (formaté en €, sortable)
- **Montant TTC** (formaté en €, sortable, toggleable hidden par défaut)
- **Statut** (badge coloré via enum `OrderStatus`)
- **Payée le** (`paid_at` si présent, formaté, nullable)

Filtres requis :
- `status` (SelectFilter sur les valeurs de l'enum)
- `current_month` (Filter avec query closure `whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])`)
- `last_12_months` (Filter avec query closure `where('paid_at', '>=', now()->subMonths(12))`)
- `period` (SelectFilter avec options : "Ce mois", "Mois dernier", "Cette année", "Année dernière", "12 derniers mois")
- Recherche : par `invoice_number`, `user.full_name`, `user.email`

Pas de bulk actions, pas d'action Delete/Create.

Action ligne :
- **Voir** (action `view` standard Filament → page ViewOrder)
- **Télécharger la facture** : si l'Order a un PDF généré (via `GenerateInvoiceAction` existant), proposer un download direct. Sinon, action désactivée avec tooltip "Facture en attente de génération"

**B.4 — Page ViewOrder (lecture seule)**

Affichage en sections :
- Bloc "Informations commande" : numéro, date, statut, client, société
- Bloc "Détails financiers" : montant HT, TVA, montant TTC, date de paiement, méthode de paiement (Mollie)
- Bloc "Lignes de commande" via le RelationManager `OrderItemsRelationManager`
- Tous les champs en `disabled()` — c'est de la consultation

**B.5 — RelationManager `OrderItemsRelationManager`**

Affiche les `OrderItem` de la commande, colonnes :
- Type de carte (`cardType.name`)
- Quantité
- Prix unitaire HT
- Prix total HT

Lecture seule (pas de `CreateAction`, `EditAction`, `DeleteAction`).

**B.6 — Tests Pest**

Créer `tests/Feature/Filament/Resources/OrderResourceTest.php` avec au minimum :
- ✓ admin can list orders
- ✓ non-admin cannot access
- ✓ filter by status works
- ✓ filter current_month works (test avec une commande dans le mois et une hors mois)
- ✓ filter last_12_months works
- ✓ search by invoice_number works
- ✓ view page renders order details
- ✓ no create/edit/delete actions exposed

### Critère STOP/GO Étape B

- ✅ Resource complète, conforme au pattern de référence identifié en Étape A
- ✅ Tous les filtres listés en A.3 fonctionnels (testés via Pest + capture visuelle)
- ✅ Page ViewOrder propre, données affichées en lecture seule
- ✅ Tests Pest verts (cible : 452 + ~ 8 = 460+)
- ✅ Aucune Action métier modifiée
- ✅ Aucune création de migration

**Captures attendues (section 5 du rapport) :**
- Liste des commandes avec filtres visibles
- Page ViewOrder avec un Order chargé + RelationManager visible
- Au moins 1 filtre activé via URL `filters[...]` avec pill visible

---

## 5. Étape C — `RegistrationResource`

### Livrables Étape C

**C.1 — Structure de la Resource**

Identique à OrderResource (lecture seule). Créer dans `app/Filament/Resources/Registrations/`.

**C.2 — Configuration**

```text
Slug              : registrations
Navigation label  : Inscriptions
Navigation group  : Gestion des inscriptions
Icon              : Heroicon::OutlinedClipboardDocumentList
canCreate         : false
canEdit           : false
canDelete         : false
```

**C.3 — Table de liste**

Colonnes minimales :
- **Date inscription** (`registered_at` ou `created_at`)
- **Personne** (`user.full_name`, searchable, sortable)
- **Session** (`conversationTable.topic`, sortable par `conversationTable.scheduled_at`)
- **Date session** (`conversationTable.scheduled_at`)
- **Niveau** (`conversationTable.level.code`, badge)
- **Statut inscription** (`status`, badge — enum `RegistrationStatus`)
- **Présence** (`attendance_status` si existant — Present / Absent / NoShow)
- **Carte utilisée** (`card.invoice_number` ou similaire, toggleable hidden)

Filtres requis :
- `status` (SelectFilter sur valeurs `RegistrationStatus`)
- `attendance_status` (SelectFilter)
- `session_future` (Filter : `whereHas('conversationTable', fn ($q) => $q->where('scheduled_at', '>', now()))`)
- `session_past_30_days` (Filter : `whereHas('conversationTable', fn ($q) => $q->whereBetween('scheduled_at', [now()->subDays(30), now()]))`)
- `level_id` (SelectFilter via `conversationTable.level_id`)
- Recherche : `user.full_name`, `user.email`, `conversationTable.topic`

**C.4 — Page ViewRegistration**

Bloc "Informations inscription" : date, personne, session, niveau, statut, présence
Bloc "Détails complémentaires" : carte associée, raison d'annulation si applicable, position waitlist si applicable

Tous champs disabled.

**C.5 — Tests Pest**

`tests/Feature/Filament/Resources/RegistrationResourceTest.php` :
- ✓ admin can list registrations
- ✓ non-admin cannot access
- ✓ filter by status works
- ✓ filter by attendance_status works
- ✓ filter session_future works
- ✓ filter session_past_30_days works
- ✓ filter by level works
- ✓ search by user full_name works
- ✓ view page renders registration details
- ✓ no create/edit/delete actions exposed

### Critère STOP/GO Étape C

Mêmes critères que Étape B, adaptés à RegistrationResource. Cible tests : 460 + ~ 10 = **470+ verts**.

---

## 6. Étape D — Correction `ActivityRelationManager` + drill-down des 4 widgets restants

### Livrables Étape D

**D.1 — Correction du bug `ActivityRelationManager.php`**

Pour chaque occurrence de `tableFilters` identifiée en Étape A, remplacer par `filters`. Pas de modification de logique, juste la clé de query string.

**D.2 — Patch des 4 widgets dashboard**

**`OperationalStatsWidget` — stat "Inscriptions en cours"** :

```php
Stat::make('Inscriptions en cours', $count)
    ->description('Inscrits confirmés, sessions à venir')
    ->color('primary')
    ->url(\App\Filament\Resources\Registrations\RegistrationResource::getUrl('index') . '?' . http_build_query([
        'filters' => [
            'status' => ['value' => 'registered'],
            'session_future' => ['isActive' => '1'],
        ],
    ]))
```

**`OperationalStatsWidget` — stat "Revenus du mois (HT)"** :

```php
Stat::make('Revenus du mois (HT)', $amount)
    ->description('Commandes payées ce mois-ci')
    ->color('success')
    ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('index') . '?' . http_build_query([
        'filters' => [
            'status' => ['value' => 'paid'],
            'current_month' => ['isActive' => '1'],
        ],
    ]))
```

**`NoShowRateWidget` — stat "Taux de no-show (30 j)"** :

Pattern 1 (Stat avec `->url()`), pas Pattern 3 — c'est un StatsOverviewWidget, pas un ChartWidget. Cf. observation Phase 9 Étape A.

```php
Stat::make('Taux de no-show (30 j)', $percent . ' %')
    ->description($absent . ' absent(s) sur ' . $total . ' inscription(s)')
    ->color('warning')
    ->url(\App\Filament\Resources\Registrations\RegistrationResource::getUrl('index') . '?' . http_build_query([
        'filters' => [
            'attendance_status' => ['value' => 'no_show'],
            'session_past_30_days' => ['isActive' => '1'],
        ],
    ]))
```

**`RevenueChartWidget` — Pattern 3 (lien pied)** :

Approche identique à `SessionFillRateChartWidget` patché en Phase 9 :

1. Créer la vue `resources/views/filament/widgets/revenue-chart-with-link.blade.php` sur le modèle exact de `session-fill-rate-chart-with-link.blade.php`
2. Dans `RevenueChartWidget.php` : ajouter `protected string $view = 'filament.widgets.revenue-chart-with-link';` + méthode `getDrillDownUrl()`

```php
public function getDrillDownUrl(): string
{
    return \App\Filament\Resources\Orders\OrderResource::getUrl('index') . '?' . http_build_query([
        'filters' => [
            'status' => ['value' => 'paid'],
            'last_12_months' => ['isActive' => '1'],
        ],
    ]);
}
```

**D.3 — Cohérence chiffres widget ↔ filtres Resources**

Pour chaque widget patché, **vérifier explicitement** que le chiffre affiché par le widget correspond strictement au nombre de lignes obtenu sur la Resource avec les filtres URL appliqués. Si divergence, **STOP et rapporte** — la divergence indique soit que le filtre de la Resource n'a pas la même logique que le widget, soit que le widget compte différemment.

Cette vérification se fait via Playwright + comparaison visuelle widget ↔ liste filtrée (cf. Étape E).

### Critère STOP/GO Étape D

- ✅ `ActivityRelationManager.php` corrigé (toutes occurrences)
- ✅ 4 widgets patchés selon le pattern décrit
- ✅ Aucun autre fichier modifié
- ✅ Tests existants verts (cible : 470+ verts, pas de régression)

**Tests Pest à ajouter** : 4 tests dans `tests/Feature/Filament/Widgets/DashboardDrillDownTest.php` (existant Phase 9) :
- ✓ OperationalStatsWidget renders Inscriptions en cours stat with drill-down URL
- ✓ OperationalStatsWidget renders Revenus du mois stat with drill-down URL
- ✓ NoShowRateWidget renders with drill-down URL
- ✓ RevenueChartWidget renders custom view with footer drill-down link

Cible finale tests Étape D : ~ 474 verts.

---

## 7. Étape E — Vérification Playwright complète + bilan

### Livrables Étape E

**E.1 — Captures Playwright des 4 nouveaux drill-down**

Pour chaque widget patché en Étape D, capturer :
1. Widget visible sur dashboard (lien cliquable confirmé)
2. Page Resource après clic, avec **URL `filters[...]` visible dans la barre d'adresse + pill filter visible au-dessus de la table**

Soit 8 captures minimum.

**E.2 — Captures bonus : vérification cohérence chiffres**

Pour au moins 2 des 4 widgets patchés, faire une capture qui montre **côte à côte** ou **en séquence** :
- Le chiffre affiché par le widget (par exemple "Inscriptions en cours = 3")
- Le nombre de résultats affiché par la Resource filtrée (par exemple "Affichage de 1 à 3 sur 3 résultats")

Les deux doivent correspondre. Si non, STOP.

**E.3 — Bilan final Phase 9.5**

Tableau :

| Indicateur | Avant Phase 9.5 | Après Phase 9.5 |
|---|---|---|
| Tests Pest | 452 | ~ 474+ |
| Resources Filament admin | 8 | 10 |
| Widgets dashboard liés | 3/7 | 7/7 (100% — hors Vivier qui a son lien pied) |
| Bug `ActivityRelationManager` | présent | corrigé |

### Critère STOP/GO Étape E

- ✅ 8+ captures Playwright des drill-down avec filter pills vérifiés
- ✅ 2+ captures de cohérence chiffres widget ↔ Resource
- ✅ Suite complète Pest verte : 474+ tests
- ✅ Aucune régression
- ✅ CLAUDE.md mis à jour avec section "Phase 9.5" (changelog 10-15 lignes)
- ✅ Demande de GO final

---

## 8. Points d'attention

### 8.1 Champs et colonnes des Resources

Le brief liste des colonnes attendues, mais **tu dois t'aligner sur les vrais noms de champs** des modèles `Order` et `Registration`. Par exemple, si le champ s'appelle `total_ht_amount` au lieu de `amount_ht`, utilise le vrai nom. Ne suppose rien — lis le modèle et la migration.

Si un champ attendu n'existe **pas** (par exemple si `attendance_status` n'existe pas sur `Registration`) :
- **STOP** et rapporte
- Ne crée pas de migration pour l'ajouter
- Ne mocke pas la fonctionnalité

Arnaud tranchera : soit on supprime la fonctionnalité du brief, soit on planifie une mini-migration séparée.

### 8.2 Relation `Order → OrderItem`

Si la relation n'existe pas explicitement sur le modèle `Order` (méthode `orderItems()`), **STOP** et rapporte. Ne l'ajoute pas seul — c'est une modification de modèle qui doit être validée.

### 8.3 Format URL `filters` confirmé

Phase 9 a établi que le bon format est `filters[xxx][isActive]=1` ou `filters[xxx][value]=yyy`, **pas** `tableFilters[...]`. Garde ce format pour les 4 widgets de cette phase. Si tu doutes, refais une vérification Playwright sur les widgets Phase 9 avant d'attaquer Étape D.

### 8.4 Conventions de style

- CSS inline si tu ajoutes du visuel sur les widgets/vues (cf. leçon Phase 8)
- Filtres et colonnes Filament : utilise les composants natifs Filament 5, pas de CSS custom
- Notifications de succès : style Filament natif (`Notification::make()->success()->...`)

### 8.5 Précédents bugs Filament v5 à éviter

- `$view` doit être **non-static** (`protected string $view`, pas `protected static string $view`) cf. correction Phase 9
- `tableFilters` n'est **pas** l'alias URL — utiliser `filters`
- Les classes Tailwind du theming Phase 7 ne sont **pas** disponibles dans le panel admin — utiliser uniquement CSS inline ou classes Tailwind natives garanties

---

## 9. Hors scope explicite

Reporté en backlog ultérieur :
- Export CSV/Excel des Resources Orders/Registrations
- Statistiques inline dans les Resources (graphiques par client, etc.)
- Création manuelle de commandes via admin (uniquement Mollie en prod)
- Gestion des remboursements admin (Action métier dédiée à concevoir en phase produit)
- Gestion des litiges/disputes
- Notifications email automatiques sur changements de statut admin

---

**Fin du brief Phase 9.5.**

*Phase 10 = déploiement / ops / runbooks (phase finale).*
