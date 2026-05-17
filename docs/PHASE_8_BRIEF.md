# PHASE 8 — Vivier global d'attente + UX gestion des inscrits

> **Statut :** Brief prêt à transmettre à Claude Code  
> **Méthodologie :** STOP/GO en 5 étapes A → E (validation explicite Arnaud à chaque checkpoint)  
> **Phasage :** Phases 7 et 7.5 terminées (theming, security, dashboard widgets, audit log, corrections). Ce travail est Phase 8. Phase 9 = deployment.

---

## 0. RÈGLES D'EXÉCUTION — À LIRE INTÉGRALEMENT AVANT D'ÉCRIRE LA MOINDRE LIGNE

> Ces règles priment sur ton instinct et sur l'efficacité apparente. Elles existent parce qu'elles ont été violées par le passé.

### 0.1 Avant de commencer

1. **Lis le brief en entier** avant d'écrire quoi que ce soit. Pas de scan partiel.
2. **Lis `CLAUDE.md`, `PROJECT_BRIEF.md`, `FILAMENT_5_BRIEF.md`** intégralement. Ces fichiers font autorité sur tout ce que ce brief ne précise pas.
3. **Lis le code existant cité dans § 11** (Actions, Livewire RegistrationsManager, WaitlistResource). Tu vas répliquer leurs patterns — connais-les avant de coder.
4. **Confirme à Arnaud que tu as fait les 3 lectures ci-dessus** dans ton premier message, en citant 3 patterns que tu vas réutiliser à l'identique. C'est ton checkpoint zéro.

### 0.2 Méthodologie STOP/GO — non négociable

À chaque step A, B, C, D, E :
- Tu **termines la totalité** des livrables du step
- Tu **rapportes** selon le format défini § 9 (rien d'autre)
- Tu **attends** un GO écrit d'Arnaud
- Tu **ne commences jamais le step suivant sans GO explicite**, même si tu as une idée brillante ou si "ça va plus vite"
- Si un blocage technique surgit au milieu d'un step : tu **stoppes**, tu rapportes le blocage, tu attends une instruction

### 0.3 Périmètre — clôture stricte

Tu ne fais **rien** qui ne soit explicitement décrit dans ce brief. Notamment :
- ❌ Pas de "petite amélioration" non demandée sur du code existant
- ❌ Pas de refactoring "tant qu'on y est"
- ❌ Pas de modification d'Action existante (cf. § 10)
- ❌ Pas de mise à jour de dépendances (Composer/npm)
- ❌ Pas de changement de structure de dossiers
- ❌ Pas de fichier orphelin "au cas où" (helpers, traits, abstractions)

Si en codant tu **identifies** quelque chose qui mériterait d'être traité mais qui sort du périmètre : tu le notes dans ta section "Observations" du rapport de fin de step. Tu ne touches pas.

### 0.4 Exhaustivité des livrables

À chaque step, **tous** les livrables listés doivent être présents. Pas de "j'ai fait l'essentiel, le reste est trivial". Vérifie point par point la checklist du step avant de soumettre ton rapport.

Cas particulier des **tests** : un step n'est pas terminé tant que les tests listés pour ce step **passent tous au vert**. Si un test fail, tu débogues. Tu ne reportes pas un step "presque vert".

### 0.5 Conventions de code

- **Aucune classe Tailwind littérale** (`bg-blue-500`, `text-red-600`, etc.) dans les nouveaux fichiers de vues ou les vues modifiées des espaces public/membre. Uniquement classes sémantiques : `bg-primary`, `bg-accent`, `bg-surface`, `bg-success`, `bg-warning`, `bg-danger`, `bg-info`, et leurs variantes (`/10`, `/20`, `text-*-foreground`, etc.).
- **Le panel admin Filament reste hors scope du theming** — tu peux y utiliser les classes Filament natives normalement.
- **Toute notification implémente `ShouldQueue`** — sans exception.
- **Tout event de domaine passe par `DB::afterCommit`** quand dispatché depuis une Action transactionnelle.
- **Soft deletes uniquement**, aucun `forceDelete` métier.
- **PHPDoc complet** sur les méthodes publiques des Actions (description, `@param`, `@return`, `@throws`).
- **Pas de `// TODO`, `// FIXME`** laissés dans le code. Si quelque chose reste à faire : tu l'écris dans le rapport, pas dans le code.

### 0.6 Format des rapports STOP/GO

Chaque rapport de fin de step suit cette structure exacte :

```
## RAPPORT STEP X

### 1. Livrables réalisés
- [chemin/fichier1.php] — créé
- [chemin/fichier2.blade.php] — modifié (préciser quoi)
- ...

### 2. Checklist des critères du step
- [ ] / [x] Critère 1 du brief
- [ ] / [x] Critère 2 du brief
...

### 3. Tests
- Commande : `php artisan test --filter=...`
- Résultat : X tests, Y assertions, Z passed
- (Coller la sortie complète)

### 4. Captures d'écran (steps C et D uniquement)
- Lien ou description

### 5. Observations hors périmètre
- Tout ce que tu as remarqué mais pas touché

### 6. Demande de GO
Step X terminé. J'attends ton GO pour passer au Step X+1.
```

### 0.7 Si tu doutes

Tu poses la question à Arnaud. Tu ne devines pas. Tu ne tranches pas seul un point d'architecture. Tu ne "fais au mieux" sur une zone grise.

---

## 1. Contexte & objectifs

TableConvo permet aux admins de gérer les inscrits d'une session via une modale "Inscrits". Trois problèmes actuels :

1. **Design pauvre** de la modale Filament `voir_inscrits` (vue `filament.modals.registrations-list`).
2. **Boutons non câblés** : "Déplacer", "Annuler", "Retirer", "Promouvoir" présents visuellement mais non fonctionnels — alors qu'un composant Livewire `RegistrationsManager` existe déjà avec toute la logique.
3. **Suppression sèche** : retirer une personne d'une waitlist ou annuler une inscription la fait disparaître purement et simplement. Frustrant pour l'utilisateur, mauvais pour le CRM.

### Concept introduit : le **vivier global**

Une personne retirée/annulée n'est plus jetée. Elle entre dans un **vivier global** : une file d'attente *sans session*, par niveau de langue. L'admin peut ensuite la réassigner sur une session compatible quand l'opportunité se présente.

Distinction sémantique importante :

| Concept | Définition | Où |
|---|---|---|
| **File d'attente par session** | Personnes en waitlist d'une session précise (`registrations.status = waitlist`) | Existant — `WaitlistResource` (à renommer) |
| **Vivier global** | Personnes en attente sans session, par niveau souhaité | **Nouveau** — `GlobalWaitlistEntry` |

---

## 2. Périmètre

### Inclus

- Nouvelle entité `GlobalWaitlistEntry` (table dédiée, pas de modification du modèle `Registration`)
- 4 nouvelles Actions métier
- Resource Filament admin "Vivier global"
- Modale "Inscrits" liftée visuellement + dialog unifié de retrait/annulation
- Renommage cosmétique `WaitlistResource` → "Files d'attente par session"
- Section "Au vivier" dans `/espace/inscriptions`
- **Widget Dashboard admin** "Vivier global" (Phase 7 a livré le dashboard widgets — on s'y greffe)
- 3 notifications (mail + database, `ShouldQueue`)
- Tests Pest exhaustifs des nouvelles Actions et Resources
- **Solde dette technique :** tests `AssignLevelActionTest` et `RequestLevelInterviewActionTest`

### Exclus (à ne pas toucher)

- Aucune modification des Actions existantes (`CancelRegistrationAction`, `MoveRegistrationAction`, `PromoteFromWaitlistAction`, `FindEligibleTargetSessionsAction`)
- Aucune modification du modèle `Registration` ni de la table `registrations`
- Pas d'auto-réassignation FIFO (cohérent avec `waitlist_auto_promote = false`)
- Pas d'email utilisateur à la création d'une nouvelle session (notif admin uniquement)
- Pas de modification du theming Phase 7 (les classes sémantiques `bg-primary`, `text-accent` doivent être *utilisées* mais pas redéfinies)

---

## 3. Décisions architecturales lockées

| # | Décision | Justification |
|---|---|---|
| D1 | Table dédiée `global_waitlist_entries`, **PAS** de `conversation_table_id` nullable sur `registrations` | Préserve l'invariant "une Registration est liée à une session" ; n'impacte aucune Action existante |
| D2 | UX manuelle du retrait : popup unifié présente d'emblée les 2 voies (réorienter / vivier) | Choix Arnaud — admin garde le contrôle |
| D3 | Pas d'auto-réassignation depuis le vivier | Cohérence avec `waitlist_auto_promote = false` |
| D4 | Notification admin uniquement à la création d'une nouvelle session matching le vivier | Pas de pollution mail utilisateur |
| D5 | Raison admin **obligatoire** pour l'annulation d'un inscrit confirmé ; **optionnelle** pour retrait waitlist | Traçabilité CRM sur action lourde |
| D6 | Recrédit de séance conditionné à la carte active (même règle que `CancelRegistrationAction`) | Réutilise la logique existante |
| D7 | Soft deletes partout, immutabilité de l'historique | Cohérent avec les principes du projet |
| D8 | Renommage `WaitlistResource` — label uniquement, slug conservé (`/admin/waitlist`) | Pas de rupture URL pour liens externes |

---

## 4. Modèle de données

### Migration `global_waitlist_entries`

```text
id                                bigint PK
user_id                           FK users — onDelete CASCADE (cohérent avec anonymisation RGPD)
level_id                          FK levels — onDelete RESTRICT
requested_at                      datetime — date d'entrée au vivier
source                            string — enum 'admin_removed_waitlist' | 'admin_cancelled_registration' | 'user_volunteer'
source_registration_id            FK registrations NULLABLE — onDelete SET NULL
admin_reason                      text NULLABLE — raison saisie par l'admin
created_by                        FK users — onDelete RESTRICT
status                            string — enum Pending | Reassigned | Dismissed (cf. § Enum)
reassigned_to_registration_id     FK registrations NULLABLE — onDelete SET NULL
dismissed_reason                  text NULLABLE
dismissed_at                      datetime NULLABLE
dismissed_by                      FK users NULLABLE — onDelete SET NULL
created_at, updated_at, deleted_at
```

**Index :**
- `(user_id, status)` — recherche "vivier de l'utilisateur"
- `(level_id, status, requested_at)` — recherche FIFO par niveau (admin)
- `(status)` — listings filtrés

**Contraintes :**
- `admin_reason` doit être non-null en BDD si `source = 'admin_cancelled_registration'` (vérification en Action, pas via check constraint MySQL pour rester portable)

### Modèle `App\Models\GlobalWaitlistEntry`

- Trait `SoftDeletes`
- Trait `LogsActivity` (Spatie ActivityLog) — log les changements de `status`, `reassigned_to_registration_id`, `dismissed_*`
- Casts : `requested_at`, `dismissed_at` en datetime ; `status` en `GlobalWaitlistEntryStatus`
- Relations :
  - `user()` BelongsTo
  - `level()` BelongsTo
  - `sourceRegistration()` BelongsTo Registration
  - `reassignedToRegistration()` BelongsTo Registration
  - `createdBy()` BelongsTo User
  - `dismissedBy()` BelongsTo User
- Scopes :
  - `pending()` — `where('status', Pending)`
  - `forLevel(Level $level)`
  - `oldestFirst()` — `orderBy('requested_at', 'asc')`
- Accesseurs :
  - `waitingDays` — diff entre `requested_at` et `now()`

### Enum `App\Enums\GlobalWaitlistEntryStatus`

```text
Pending     — en attente d'une assignation
Reassigned  — devenu une Registration (terminal)
Dismissed   — retiré sans assignation (terminal)
```

Méthodes : `label()`, `color()` (pour badges Filament).

### Enum `App\Enums\GlobalWaitlistSource`

```text
AdminRemovedWaitlist          — admin a retiré depuis la waitlist d'une session
AdminCancelledRegistration    — admin a annulé une inscription confirmée
UserVolunteer                 — utilisateur s'est inscrit volontairement au vivier (futur — non câblé Phase 8)
```

---

## 5. Actions métier (nouvelles)

> **Rappel :** aucune Action existante modifiée. Toutes les nouvelles Actions sont dans `app/Actions/GlobalWaitlist/`.

### 5.1 `MoveToGlobalWaitlistAction`

**Signature :**
```php
public function execute(
    Registration $registration,
    User $admin,
    GlobalWaitlistSource $source,
    ?string $adminReason = null,
    bool $recreditCard = true,
): GlobalWaitlistEntry
```

**Comportement :**

1. Valide : `$registration->status` ∈ {Registered, Waitlist}
2. Valide : si `$source = AdminCancelledRegistration`, alors `$adminReason` est requis (sinon throw `RuntimeException('admin_reason_required')`)
3. `DB::transaction` :
   - Si `Registered` ET `$recreditCard = true` ET carte active : `sessions_remaining++`
     - Si carte inactive : log activity "Recréditation impossible : carte inactive ou expirée" (cohérent avec `CancelRegistrationAction`)
   - Marque `$registration->status = Cancelled`, `cancelled_at`, `cancelled_by = $admin->id`
   - Si était `Waitlist` : décale FIFO les positions suivantes (`waitlist_position > $oldPosition` décrément)
   - Crée la `GlobalWaitlistEntry` :
     ```text
     user_id                = $registration->user_id
     level_id               = $registration->user->level_id  (requis — assumé non null)
     requested_at           = now()
     source                 = $source
     source_registration_id = $registration->id
     admin_reason           = $adminReason
     created_by             = $admin->id
     status                 = Pending
     ```
   - `activity()->performedOn($entry)->causedBy($admin)->log('Entrée vivier global')`
4. `DB::afterCommit` → dispatch `MovedToGlobalPoolNotification` à l'utilisateur

**Codes d'erreur (throw `RuntimeException`) :**
- `cannot_move_to_pool` — statut registration non valide
- `admin_reason_required` — raison manquante alors qu'obligatoire
- `user_level_missing` — utilisateur sans niveau

---

### 5.2 `ReassignFromGlobalWaitlistAction`

**Signature :**
```php
public function execute(
    GlobalWaitlistEntry $entry,
    ConversationTable $targetTable,
    User $admin,
): Registration
```

**Comportement :**

1. Valide : `$entry->status = Pending`
2. Valide : `$targetTable->status = Scheduled` ET `scheduled_at > now()`
3. Valide : `$targetTable->level_id = $entry->level_id` (sinon throw `level_mismatch`)
4. Valide : pas d'inscription active existante de l'utilisateur sur cette table (`already_registered_on_target`)
5. `DB::transaction` :
   - Détermine si table pleine :
     - Si **pleine** : crée Registration `status = Waitlist`, `waitlist_position = max + 1`, `card_id = null`
     - Si **place dispo** : cherche carte active utilisateur
       - Si carte active : crée Registration `status = Registered`, debit `sessions_remaining`, `card_id = $card->id`
       - Si pas de carte active : crée Registration `status = Waitlist` (l'admin doit savoir que la personne sera waitlistée par défaut de carte)
   - Met `$entry->status = Reassigned`, `reassigned_to_registration_id = $newRegistration->id`
   - `activity()->performedOn($entry)->causedBy($admin)->withProperties(['target_table' => $targetTable->id, 'final_status' => ...])->log('Réassignation depuis vivier')`
6. `DB::afterCommit` → dispatch `ReassignedFromGlobalPoolNotification`

**Codes d'erreur :**
- `entry_not_pending`
- `target_table_not_scheduled`
- `target_table_in_past`
- `level_mismatch`
- `already_registered_on_target`

---

### 5.3 `DismissGlobalWaitlistEntryAction`

**Signature :**
```php
public function execute(
    GlobalWaitlistEntry $entry,
    User $actor,
    string $reason,
    bool $byUser = false,
): GlobalWaitlistEntry
```

**Comportement :**

1. Valide : `$entry->status = Pending`
2. Valide : `$reason` non-vide (trim)
3. Si `$byUser = false` (admin action) : pas de contrôle supplémentaire
4. Si `$byUser = true` : valide que `$actor->id = $entry->user_id` (sécurité — utilisateur ne peut retirer que sa propre entrée)
5. `DB::transaction` :
   - Met `$entry->status = Dismissed`, `dismissed_reason = $reason`, `dismissed_at = now()`, `dismissed_by = $actor->id`
   - `activity()->performedOn($entry)->causedBy($actor)->log($byUser ? 'Retrait volontaire du vivier' : 'Retrait du vivier par admin')`
6. `DB::afterCommit` → **Si admin uniquement** : dispatch `DismissedFromGlobalPoolNotification`

**Codes d'erreur :**
- `entry_not_pending`
- `dismiss_reason_required`
- `unauthorized_dismiss` — utilisateur tente de retirer une entrée qui n'est pas la sienne

---

### 5.4 `FindCompatibleSessionsForGlobalEntryAction`

**Signature :**
```php
public function execute(GlobalWaitlistEntry $entry): Collection
```

**Comportement :**

Retourne `Collection<ConversationTable>` :
- Filtre `status = Scheduled`
- Filtre `scheduled_at > now()`
- Filtre `level_id = $entry->level_id`
- Eager load `level`
- Tri par `scheduled_at ASC`
- Inclut tables complètes (l'admin peut quand même réassigner en waitlist — affichage des places restantes dans le label, comme `FindEligibleTargetSessionsAction` le fait déjà)

**Pas de modification de `FindEligibleTargetSessionsAction`** existante. Cette nouvelle Action est dédiée au vivier (logique légèrement différente : pas de filtrage par sessions où l'utilisateur a déjà une registration, car par définition une entrée vivier n'a pas de session associée).

---

## 6. UI Filament admin

### 6.1 Modale "Inscrits" — refonte

**Localisation actuelle :** `ConversationTablesTable::table()` action `voir_inscrits`, `modalContent` = `filament.modals.registrations-list`.

**Cible :** brancher le composant Livewire **existant** `App\Livewire\Admin\RegistrationsManager` (qui contient déjà toute la logique fonctionnelle) en remplaçant le `modalContent`.

```php
->modalContent(fn (ConversationTable $record) => view(
    'filament.modals.registrations-list',
    ['table' => $record]
))
```

devient :

```php
->modalContent(fn (ConversationTable $record) => view(
    'filament.modals.registrations-list',
    ['table' => $record]
))
```

avec la vue `registrations-list.blade.php` réécrite pour faire :

```blade
@livewire('admin.registrations-manager', ['table' => $table], key('registrations-'.$table->id))
```

**Lifting visuel** de `livewire.admin.registrations-manager.blade.php` :

| Élément | Avant | Après |
|---|---|---|
| Couleurs | `bg-blue-50`, `bg-green-50`, `bg-yellow-50` codées en dur | Classes sémantiques `bg-primary/10`, `bg-success/10`, `bg-warning/10` |
| Boutons | Texte brut "Déplacer", "Annuler" | Icônes Heroicon + tooltip + texte sur écrans larges |
| Lignes inscrits | Tableau HTML | Cards arrondies `rounded-xl` avec hover subtil |
| Avatars | Absents | Initiales colorées (2 lettres, fond `bg-accent/20`) générées depuis `full_name` |
| Badges niveau | Absents | Petit badge `text-xs` à droite du nom |
| Header session | Plat | Sticky avec compteurs en pilules colorées (déjà présent — à conserver et nettoyer) |
| Empty states | Texte gris italique | Icône Heroicon + texte court + suggestion |

**Dialog unifié de retrait/annulation** — nouveau composant blade `livewire.admin.partials.remove-or-pool-dialog.blade.php` :

Déclenché par :
- "Retirer" sur une ligne **waitlist** → `$adminReasonRequired = false`
- "Annuler" sur une ligne **inscrit confirmé** → `$adminReasonRequired = true`

Contenu :

```
┌─────────────────────────────────────────────────────────────┐
│  Que faire de [Prénom Nom] ?                                │
│                                                             │
│  ◯ Réorienter vers une autre session                        │
│    [Select : sessions compatibles + places libres affichées]│
│    ↳ FindEligibleTargetSessionsAction (level + future)      │
│                                                             │
│  ◯ Mettre au vivier global                                  │
│    L'utilisateur restera en attente, sans session affectée. │
│    [Textarea : Raison admin (obligatoire pour annulation)]  │
│    [Checkbox : Recréditer la séance (visible si Registered)]│
│                                                             │
│  [Confirmer]  [Annuler]                                     │
└─────────────────────────────────────────────────────────────┘
```

Le radio par défaut :
- Si sessions compatibles existent : "Réorienter" présélectionné
- Si aucune : "Mettre au vivier" présélectionné

Méthodes Livewire à ajouter à `RegistrationsManager` :
- `openRemoveDialog(int $registrationId)`
- `closeRemoveDialog()`
- `confirmRemove()` — orchestre selon le choix radio :
  - "Réorienter" → `MoveRegistrationAction` (existant)
  - "Mettre au vivier" → `MoveToGlobalWaitlistAction` (nouveau)

### 6.2 Nouvelle Resource `GlobalWaitlistPoolResource`

**Emplacement :** `app/Filament/Resources/GlobalWaitlistPool/GlobalWaitlistPoolResource.php`

```text
Slug              : pool   (URL /admin/pool)
Model             : GlobalWaitlistEntry
Navigation label  : Vivier global
Navigation group  : Gestion des inscriptions
Navigation sort   : 11   (juste après "Files d'attente par session" qui est à 10)
Icon              : Heroicon::OutlinedUserGroup
Access            : auth()->user()?->hasRole('admin')
canCreate         : false
canEdit           : false
canDelete         : false
```

**Query par défaut :** `pending()` uniquement (`status = Pending`).

**Colonnes table :**

| Colonne | Source | Format |
|---|---|---|
| En attente depuis | `requested_at` | `d/m/Y H:i` + description `diffForHumans()` |
| Personne | `user.full_name` | searchable |
| Email | `user.email` | toggleable hidden par défaut, searchable |
| Niveau souhaité | `level.code` | badge color info |
| Source | `source` | badge formatté via enum `label()` |
| Raison admin | `admin_reason` | limit 40, tooltip si > 40 |
| Créé par | `createdBy.full_name` | toggleable |
| Position FIFO | accessor calculé | `#1`, `#2`... par niveau |

**Filtres :**
- Par niveau (select)
- Par source (select avec enum cases)
- Par période d'entrée (date range `from` / `to` sur `requested_at`)
- Par "Créé par" (select admins)

**Actions ligne :**

1. **Réassigner** (`Action::make('reassign')`)
   - Icon : `OutlinedArrowRightCircle`
   - Color : `primary`
   - Modal heading : "Réassigner {full_name}"
   - Form :
     ```php
     Select::make('target_table_id')
         ->required()
         ->options(
             app(FindCompatibleSessionsForGlobalEntryAction::class)
                 ->execute($record)
                 ->mapWithKeys(fn (ConversationTable $t) => [
                     $t->id => sprintf(
                         '%s — %s · %s',
                         $t->scheduled_at->translatedFormat('d M H:i'),
                         $t->topic,
                         $t->registered_count < $t->max_participants
                             ? ($t->max_participants - $t->registered_count) . ' place(s) libre(s)'
                             : 'complet, ' . $t->waitlist_count . ' en attente'
                     )
                 ])
                 ->toArray()
         )
     ```
   - Action : appelle `ReassignFromGlobalWaitlistAction`
   - Notif succès : "Personne réassignée vers « {topic} »"

2. **Retirer du vivier** (`Action::make('dismiss')`)
   - Icon : `OutlinedXCircle`
   - Color : `danger`
   - Modal heading : "Retirer {full_name} du vivier"
   - Form :
     ```php
     Textarea::make('reason')->required()->minLength(3)->maxLength(500)
         ->label('Motif')
         ->helperText('Ce motif sera enregistré dans le journal d\'audit.')
     ```
   - Action : appelle `DismissGlobalWaitlistEntryAction`
   - Notif succès : "{full_name} retiré(e) du vivier"

**Pas de bulk actions** (sensibilité métier).

**Default sort :** `requested_at` ASC (FIFO).

**Empty state :**
- Heading : "Aucune personne au vivier"
- Description : "Le vivier est utilisé quand aucune session compatible n'est disponible au moment d'un retrait."
- Icon : `Heroicon::OutlinedUserGroup`

### 6.3 Renommage `WaitlistResource`

Dans `app/Filament/Resources/Waitlist/WaitlistResource.php`, modifier **uniquement** :

```php
protected static ?string $navigationLabel = "Files d'attente par session";
protected static ?string $modelLabel = "Inscription en file d'attente";
protected static ?string $pluralModelLabel = "Files d'attente par session";
protected static ?int $navigationSort = 10;
```

**Slug `waitlist` conservé** — pas de breaking change sur les URLs.

### 6.4 Widget Dashboard admin "Vivier global"

> **Contexte :** la Phase 7 a livré les widgets dashboard du panel admin. On se greffe sur le pattern existant.

**Pré-requis avant de coder :**
1. Lister les widgets existants dans `app/Filament/Widgets/` ou `app/Filament/Resources/*/Widgets/`
2. Identifier le widget le plus proche fonctionnellement (probablement un widget de stats ou de listing court)
3. Répliquer son pattern (extends, traits, structure du fichier)

**Spécification fonctionnelle :**

Type : **Widget table** (pas un stat overview — on veut une mini-liste actionnable, pas un compteur).

**Emplacement :** Dashboard admin Filament — tri à coordonner avec les widgets existants (proposer une position et la valider en Step C).

**Visibilité :** admins uniquement (`canView()` → `auth()->user()?->hasRole('admin')`).

**Titre :** "Vivier global d'attente" (sous-titre : "Personnes en attente sans session")

**Contenu :**

Query : `GlobalWaitlistEntry::pending()->with(['user', 'level'])->oldestFirst()->limit(10)`

Colonnes :

| Colonne | Source | Format |
|---|---|---|
| Personne | `user.full_name` | searchable false (widget) |
| Niveau | `level.code` | badge color info |
| En attente depuis | `waiting_days` (accessor) | `X jour(s)` — color `warning` si > 14 jours, `danger` si > 30 jours |
| Source | `source` | badge via enum `label()` |

**Action par ligne :**
- "Réassigner" → redirige vers `GlobalWaitlistPoolResource` avec l'action `reassign` pré-ouverte sur le record (ou simple lien vers la page d'édition de la ressource)

**Footer du widget :**

Lien `Voir tout le vivier ({count} personnes)` → `/admin/pool`

**Empty state :**

Si `count = 0` : afficher discrètement "Aucune personne en attente au vivier" (texte gris, pas d'encart vide volumineux).

**Polling :** 60 secondes (cohérent avec les autres widgets Phase 7 — à vérifier et aligner).

**Critères de qualité spécifiques au widget :**
- Le widget ne casse pas le layout dashboard si vide
- Le widget ne génère pas plus de 2 requêtes SQL (count + fetch avec eager loading)
- Le widget respecte le breakpoint responsive du dashboard existant

---

## 7. UI utilisateur final

### 7.1 Section "Au vivier d'attente" sur `/espace/inscriptions`

Au-dessus ou à côté des sections existantes (inscriptions confirmées / waitlist) :

```
┌────────────────────────────────────────────────────────────┐
│  ⏳ Au vivier d'attente                                    │
│                                                            │
│  Vous êtes en attente d'une session compatible            │
│  niveau A1, depuis 3 jours.                                │
│                                                            │
│  Notre équipe vous proposera une session dès qu'une        │
│  opportunité se présentera.                                │
│                                                            │
│  [Me retirer du vivier]                                    │
└────────────────────────────────────────────────────────────┘
```

**Implémentation :**
- Nouvelle section dans la vue (composant existant ou ajout dans la page)
- Affiche `GlobalWaitlistEntry::where('user_id', auth()->id())->pending()->get()` (peut être > 1 si plusieurs niveaux, rare mais possible)
- Bouton "Me retirer" → composant Livewire dédié `App\Livewire\Espace\DismissPoolButton`
- Modal de confirmation avec textarea raison optionnelle (côté user, la raison est "À ma demande" par défaut si vide)
- Appelle `DismissGlobalWaitlistEntryAction` avec `byUser = true`

### 7.2 Notifications utilisateur

**`MovedToGlobalPoolNotification`** (mail + database, `ShouldQueue`)

```text
Sujet : Vous êtes au vivier d'attente pour le niveau {level}

Bonjour {firstName},

Votre inscription pour « {topic} » du {date} a été {annulée|retirée} 
par notre équipe.

{Si recrédit :} Votre séance a été recréditée sur votre carte.

Vous avez été placé(e) au vivier d'attente pour le niveau {level}. 
Notre équipe vous proposera une session compatible dès qu'une 
opportunité se présentera.

[Voir mes inscriptions]
```

**`ReassignedFromGlobalPoolNotification`** (mail + database, `ShouldQueue`)

```text
Sujet : Une session vous a été proposée

Bonjour {firstName},

Une session compatible avec votre niveau vient de vous être proposée :

  Session : {topic}
  Date    : {scheduled_at}
  Niveau  : {level}
  Statut  : {Inscrit(e) | En liste d'attente, position #N}

[Voir mes inscriptions]
```

**`DismissedFromGlobalPoolNotification`** (mail + database, `ShouldQueue`)

Déclenchée **uniquement** quand l'admin retire la personne (pas quand l'utilisateur se retire lui-même).

```text
Sujet : Vous avez été retiré(e) du vivier d'attente

Bonjour {firstName},

Notre équipe vous a retiré(e) du vivier d'attente pour le niveau {level}.

{Si raison admin transmise — à valider Arnaud : on transmet ou non ?}
Motif : {admin_reason}

Si vous souhaitez vous réinscrire, n'hésitez pas à nous contacter.
```

⚠ **Question ouverte à confirmer en Step D** : faut-il transmettre la raison admin à l'utilisateur ? Par prudence, **non par défaut** (raison interne) — mais cas à valider avec Arnaud avant implémentation.

---

## 8. Tests Pest attendus

### 8.1 Nouvelles Actions

**`tests/Feature/Actions/GlobalWaitlist/MoveToGlobalWaitlistActionTest.php`**

- ✓ moves a Registered registration to global pool
- ✓ moves a Waitlist registration to global pool
- ✓ recredits sessions_remaining on active card when moving Registered with recreditCard=true
- ✓ does not recredit if card is inactive (logs activity instead)
- ✓ does not recredit if registration was on waitlist
- ✓ shifts FIFO positions on the source session after waitlist removal
- ✓ requires admin_reason when source is AdminCancelledRegistration
- ✓ throws cannot_move_to_pool if registration is Cancelled
- ✓ throws user_level_missing if user has no level
- ✓ dispatches MovedToGlobalPoolNotification after commit
- ✓ logs activity on the new entry

**`tests/Feature/Actions/GlobalWaitlist/ReassignFromGlobalWaitlistActionTest.php`**

- ✓ creates a Registered registration when target has space and user has active card
- ✓ creates a Waitlist registration when target is full
- ✓ creates a Waitlist registration when user has no active card
- ✓ debits card sessions_remaining when registered
- ✓ marks entry as Reassigned with correct reassigned_to_registration_id
- ✓ throws level_mismatch if target table level differs
- ✓ throws target_table_in_past
- ✓ throws target_table_not_scheduled
- ✓ throws entry_not_pending if entry already reassigned
- ✓ throws already_registered_on_target
- ✓ dispatches ReassignedFromGlobalPoolNotification after commit

**`tests/Feature/Actions/GlobalWaitlist/DismissGlobalWaitlistEntryActionTest.php`**

- ✓ admin can dismiss a pending entry with reason
- ✓ user can dismiss their own pending entry
- ✓ user cannot dismiss another user's entry (unauthorized_dismiss)
- ✓ requires dismiss_reason
- ✓ throws entry_not_pending
- ✓ dispatches DismissedFromGlobalPoolNotification only when admin-triggered

**`tests/Feature/Actions/GlobalWaitlist/FindCompatibleSessionsForGlobalEntryActionTest.php`**

- ✓ returns only scheduled future sessions matching the level
- ✓ excludes past sessions
- ✓ excludes cancelled sessions
- ✓ excludes sessions of other levels
- ✓ orders by scheduled_at ascending
- ✓ includes full sessions (admin can still reassign as waitlist)

### 8.2 Filament Resource

**`tests/Feature/Filament/Resources/GlobalWaitlistPoolResourceTest.php`**

- ✓ admin can list pool entries
- ✓ non-admin cannot access
- ✓ shows only pending entries
- ✓ filter by level works
- ✓ filter by source works
- ✓ reassign action creates registration and marks entry as Reassigned
- ✓ dismiss action marks entry as Dismissed with reason

**`tests/Feature/Filament/Widgets/GlobalWaitlistWidgetTest.php`**

- ✓ widget renders for admin
- ✓ widget is hidden / unauthorized for non-admin
- ✓ shows only pending entries
- ✓ shows max 10 entries
- ✓ orders by requested_at ascending (FIFO)
- ✓ empty state renders when no pending entries
- ✓ waiting_days warning color triggers at 14+ days
- ✓ waiting_days danger color triggers at 30+ days

### 8.3 Modale Inscrits + dialog unifié

**`tests/Feature/Livewire/Admin/RegistrationsManagerTest.php`** (étendre l'existant si présent, sinon créer) :

- ✓ openRemoveDialog sets state correctly
- ✓ confirmRemove with "reorient" choice calls MoveRegistrationAction
- ✓ confirmRemove with "pool" choice calls MoveToGlobalWaitlistAction
- ✓ pool route requires admin_reason when registration is Registered (not when Waitlist)

### 8.4 UI utilisateur final

**`tests/Feature/Espace/PoolSectionTest.php`** :
- ✓ user sees their pending entries on /espace/inscriptions
- ✓ user can dismiss their own entry from the section

### 8.5 Dette technique soldée

**`tests/Feature/Actions/AssignLevelActionTest.php`** :
- ✓ assigns level to user
- ✓ logs activity with previous and new level
- ✓ idempotent if same level reassigned
- ✓ throws if level does not exist (or validates upstream)

**`tests/Feature/Actions/RequestLevelInterviewActionTest.php`** :
- ✓ creates an interview request
- ✓ idempotent (does not duplicate if active request exists)
- ✓ notifies admin
- ✓ logs activity

### 8.6 Objectif global

| Avant Phase 8 | Après Phase 8 (cible) |
|---|---|
| 259 tests verts | ~ 305+ tests verts |
| 17/19 Actions testées | **19/19 Actions testées** |
| Widgets dashboard Phase 7 | **+ widget Vivier global** |

---

## 9. Checkpoints STOP/GO

> **Règle d'or :** Claude Code reporte à chaque checkpoint et **attend un GO explicite** d'Arnaud avant de passer à l'étape suivante.

### Step A — Schéma & Modèle

**Livrables :**
- Migration `create_global_waitlist_entries_table`
- Modèle `GlobalWaitlistEntry` avec relations + scopes + traits
- Enums `GlobalWaitlistEntryStatus` et `GlobalWaitlistSource`
- Factory `GlobalWaitlistEntryFactory`
- Test unitaire basique du modèle (factory, casts, scopes)

**Critères STOP/GO :**
- ✅ Migration joue clean (up + down)
- ✅ Modèle hydratable via factory
- ✅ ActivityLog configuré
- ✅ Aucun fichier existant modifié

**Rapport à Arnaud :** capture de la migration + sortie `php artisan migrate:fresh` + résultat des tests modèle.

### Step B — Actions métier

**Livrables :**
- 4 nouvelles Actions dans `app/Actions/GlobalWaitlist/`
- Tests Pest des 4 Actions (sections 8.1 du brief)

**Critères STOP/GO :**
- ✅ Toutes les Actions encapsulées dans `DB::transaction`
- ✅ Tous les events/notifications via `DB::afterCommit`
- ✅ Aucune Action existante modifiée
- ✅ Tous les tests Pest verts

**Rapport à Arnaud :** sortie `php artisan test --filter=GlobalWaitlist` + nombre de tests ajoutés.

### Step C — UI Filament admin

**Livrables :**
- `GlobalWaitlistPoolResource` complète (listing, filtres, actions)
- Renommage `WaitlistResource` (labels uniquement)
- **Widget Dashboard `GlobalWaitlistWidget`** (cf. § 6.4)
- Modale "Inscrits" branchée sur `RegistrationsManager` Livewire
- Lifting visuel `livewire.admin.registrations-manager.blade.php`
- Dialog unifié `remove-or-pool-dialog` + méthodes Livewire associées
- Tests Filament Resource (section 8.2)
- Tests Widget Dashboard (section 8.2)
- Tests Livewire du dialog (section 8.3)

**Critères STOP/GO :**
- ✅ Toutes les classes de couleur sont sémantiques (`bg-primary`, `bg-success`, `bg-warning`, `bg-danger`, `bg-accent`, `bg-surface`) — aucune classe `bg-blue-*`, `bg-green-*`, etc. dans les fichiers nouveaux/modifiés (espace public/membre)
- ✅ Tous les boutons accessibles au clavier
- ✅ Dialog unifié fonctionnel pour les deux cas (retrait waitlist + annulation inscrit)
- ✅ Widget Dashboard intégré sans casser le layout existant
- ✅ Tests verts

**Rapport à Arnaud :** captures d'écran de :
1. Modale Inscrits liftée (session avec inscrits + waitlist)
2. Dialog unifié déclenché depuis un inscrit
3. Dialog unifié déclenché depuis un waitlister
4. Écran `/admin/pool` (vivier global) avec données + sans données
5. Action Réassigner du vivier (modal ouverte)
6. Menu navigation (montrer les 2 sections distinctes)
7. **Dashboard admin avec le widget Vivier global** (avec données + sans données)

### Step D — UI utilisateur final + notifications

**Livrables :**
- Section "Au vivier" sur `/espace/inscriptions`
- Composant Livewire `DismissPoolButton`
- 3 classes Notification + templates mail (mail + database, `ShouldQueue`)
- Tests Espace (section 8.4)

**Critères STOP/GO :**
- ✅ Section invisible si aucune entrée pending
- ✅ Notifications via mail + database
- ✅ Toutes les classes Notification implémentent `ShouldQueue`
- ✅ Tests verts

**Rapport à Arnaud :** captures de la section utilisateur + exemples de rendus mail (Mailpit/log).

⚠ **Point à valider avec Arnaud à ce step :** transmettre la raison admin à l'utilisateur dans `DismissedFromGlobalPoolNotification` ? Recommandation : **non** par défaut.

### Step E — Dette technique + bilan

**Livrables :**
- Tests `AssignLevelActionTest` et `RequestLevelInterviewActionTest` (section 8.5)
- Vérification full test suite green
- Mise à jour `CLAUDE.md` (changelog Phase 8)
- Bilan : nombre total de tests, nombre d'Actions couvertes, fichiers ajoutés/modifiés

**Critères STOP/GO :**
- ✅ Suite complète Pest : 100% verts
- ✅ 19/19 Actions métier ont leur test dédié
- ✅ Aucune régression sur les 259 tests pré-existants

**Rapport à Arnaud :** sortie complète `php artisan test` + bilan synthétique.

---

## 10. Contraintes transversales (rappels)

Ces principes sont des invariants du projet, à respecter sans exception :

- ❗ **Aucune modification des Actions existantes** sans direction explicite d'Arnaud
- ❗ **Toutes les couleurs en classes sémantiques** (`bg-primary`, `text-accent`, `bg-surface`, etc.) — jamais `bg-blue-500` etc. dans les vues publiques ou espace membre
- ❗ **Théming Filament admin = inchangé** (le theming Phase 7 ne touche pas le panel admin)
- ❗ **`ShouldQueue` obligatoire** sur toutes les notifications
- ❗ **Soft deletes uniquement** — aucun `forceDelete` métier
- ❗ **Events via `DB::afterCommit`** pour éviter les races
- ❗ **FIFO strict** sur les waitlists par session — pas d'évolution Phase 8
- ❗ **Pas de hardcoded business settings** — tout réglage admin passe par Spatie Settings
- ❗ **Activity log systématique** sur les changements d'état métier

---

## 11. Fichiers de référence

**Source-of-truth à consulter avant d'écrire le moindre code :**
- `PROJECT_BRIEF.md`
- `FILAMENT_5_BRIEF.md`
- `CLAUDE.md`
- Les Actions existantes `CancelRegistrationAction`, `MoveRegistrationAction`, `PromoteFromWaitlistAction`, `FindEligibleTargetSessionsAction` (lecture seule — comprendre les patterns avant de répliquer)

**Composant existant à brancher tel quel (avec lifting visuel) :**
- `app/Livewire/Admin/RegistrationsManager.php`
- `resources/views/livewire/admin/registrations-manager.blade.php`

**Widgets dashboard Phase 7 à étudier pour répliquer le pattern :**
- Tous les fichiers de `app/Filament/Widgets/` (ou équivalent — à localiser)
- Identifier le widget de type "table listing" le plus proche fonctionnellement
- Ne pas en modifier — uniquement copier le pattern

---

**Fin du brief Phase 8.**  
*Phase 9 = deployment, ops, runbooks — explicitement scopée à plus tard.*
