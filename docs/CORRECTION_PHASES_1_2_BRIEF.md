# Brief de correction des Phases 1 & 2 — Dette technique à résorber

> Ce brief **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`. Il est destiné à résorber la dette technique identifiée lors des reviews des Phases 1 et 2, avant de démarrer la Phase 7 (theming, widgets, audit).
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **Ce brief comporte trois étapes (1 à 3) avec checkpoints STOP**, comme les briefs de phase classiques.

---

## Préambule — Pourquoi un brief de correction dédié

Les reviews architecturales menées après Phases 1 et 2 ont identifié plusieurs points qui n'ont pas été corrigés à l'époque (priorisation des phases ultérieures). Plutôt que de les diluer dans la Phase 7 (qui sera dense : widgets, audit, theming, sécurité), on les traite ici dans un brief court, focalisé, avec sa propre validation. Cela évite que Claude Code se disperse en Phase 7 et permet de marquer un état "production-ready en termes de qualité de base" avant d'attaquer les fonctionnalités finales.

Méthodologie inchangée : tu lis le brief, tu signales les écarts éventuels avec le code actuel, tu attends mon GO 1 explicite, tu produis un rapport à chaque STOP, et tu attends mon GO suivant écrit.

---

## 0. État du projet à l'entrée de ce brief

À ce stade :
- **220 tests Pest verts** (cible à confirmer au démarrage par `php artisan test`)
- Phases 1 à 6 livrées
- Stack : Laravel 11+, Filament v5, Livewire, Pest, Spatie suite, Mollie SDK
- Méthodologie STOP/GO éprouvée

### Points de dette technique à corriger

| ID | Catégorie | Sévérité | Étape |
|----|-----------|----------|-------|
| 1 | `Company::firstOrCreate` sur VAT public → hijacking possible | **Critique sécurité** | 1 |
| 2 | Policies absentes sur User et Company | **Élevée sécurité** | 1 |
| 3 | Routes Breeze legacy `/profile` actives en parallèle de l'espace membre | **Moyenne** | 2 |
| 4 | `AnonymizeUserAction` manquante sur suppression de compte | **Élevée RGPD** | 2 |
| 5 | Status check manquant dans `CheckRegistrationRulesAction` | **Moyenne logique métier** | 3 |
| 6 | Strings littérales au lieu d'enums typés dans certains filtres status | **Faible cleanliness** | 3 |
| 7 | `cmixin/business-time` installé — vérifier qu'il est bien utilisé | **À vérifier** | 3 |
| 8 | Test concurrence mal nommé / couverture Actions à compléter | **Faible qualité tests** | 3 |

### Action requise au démarrage

Tu lances `php artisan test`. Tu rapportes :
- Le nombre total de tests verts (cible attendue : 220)
- Tu vérifies pour chaque point de la checklist ci-dessus s'il est **réellement encore à corriger**, **partiellement corrigé**, ou **déjà corrigé** depuis (les phases ultérieures peuvent en avoir résolu certains à l'aveugle)
- Tu rapportes l'état réel constaté avant de toucher au code

Tu attends mon GO 1 avant de toucher quoi que ce soit.

---

## 1. Étape 1 — Sécurité (sévérité critique)

### 1.1 — Fix `Company::firstOrCreate` sur VAT public

**Le problème** : à l'inscription utilisateur, le code fait probablement quelque chose comme :

```php
$company = Company::firstOrCreate(
    ['vat_number' => $request->vat_number],
    [...autres données...]
);
```

Comme un numéro de TVA est **public** (registres publics belges/européens), n'importe qui peut s'inscrire en utilisant le VAT d'une entreprise existante et **se rattacher à son compte** sans validation. C'est une faille de hijacking.

**Localisation présumée** : `app/Actions/Auth/RegisterCompanyAction.php` ou équivalent dans `app/Http/Controllers/Auth/RegisteredUserController.php`. À toi de localiser exactement.

**Fix attendu** :

1. **Création stricte uniquement** : remplacer `firstOrCreate` par `create` sur la company.
2. **Si une company avec ce VAT existe déjà** : le user **NE peut PAS** s'auto-rattacher. Trois comportements possibles, à implémenter cumulativement :
   - Si aucun user n'existe encore sur cette company : refuser l'inscription, message clair "Une société avec ce numéro TVA est déjà enregistrée. Contactez l'administrateur du compte ou notre support."
   - Si l'admin de la company a activé un mécanisme d'invitation explicite (out of scope pour cette correction — placeholder TODO documenté en commentaire)
   - Sinon : refuser, idem.
3. **Activity log** sur la tentative refusée (pour détecter d'éventuelles attaques répétées).
4. **Rate limiting** sur la route d'inscription (5 tentatives par IP / 10 minutes) si pas déjà en place.

### 1.2 — Policies User et Company

**Le problème** : actuellement, l'autorisation est gérée ad hoc (`hasRole('admin')` checks éparpillés). Pas de Policy Laravel structurée pour User et Company → toute future feature qui consulte/modifie ces modèles risque de manquer un check.

**Fix attendu** :

Créer `app/Policies/UserPolicy.php` et `app/Policies/CompanyPolicy.php` couvrant les méthodes standard :

```php
class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function view(User $authUser, User $target): bool
    {
        return $authUser->id === $target->id || $authUser->hasRole('admin');
    }

    public function update(User $authUser, User $target): bool
    {
        return $authUser->id === $target->id || $authUser->hasRole('admin');
    }

    public function delete(User $authUser, User $target): bool
    {
        // Un user peut demander sa suppression (RGPD), un admin peut supprimer
        return $authUser->id === $target->id || $authUser->hasRole('admin');
    }

    public function anonymize(User $authUser, User $target): bool
    {
        return $authUser->id === $target->id || $authUser->hasRole('admin');
    }
}
```

`CompanyPolicy` similaire, avec en plus une méthode `manageMembers` (admin uniquement, ou owner de la company si concept introduit plus tard).

**Enregistrement** : Laravel 11 utilise l'auto-discovery des policies. Vérifier que `app/Providers/AuthServiceProvider.php` est cohérent (ou inexistant — auto-discovery alors).

**Filament** : sur `UserResource` et `CompanyResource`, vérifier que `static function canAccess(): bool` ou les méthodes Filament correspondantes délèguent bien à la Policy plutôt que de redéclarer leur propre logique.

### 1.3 — Tests à écrire

`tests/Feature/Security/CompanyHijackingTest.php` :

```
- registering a new user with a VAT number that already exists fails
- the failure message is the expected user-facing message
- the failure leaves an activity log entry
- a legitimate registration with a fresh VAT works as before
- rate limiting kicks in after 5 attempts (if implemented as part of this fix)
```

`tests/Feature/Policies/UserPolicyTest.php` :

```
- a user can view, update, delete themselves
- a user cannot view, update, delete another user
- an admin can do all of the above on any user
```

`tests/Feature/Policies/CompanyPolicyTest.php` : équivalent pour Company.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP 1 — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~232, soit +12)            ║
### ║  2. Diff de l'action d'inscription (avant/après)          ║
### ║  3. Confirmation que les Policies sont bien chargées      ║
### ║     par auto-discovery (preuve via Tinker :               ║
### ║     `Gate::policy(User::class)` ou équivalent)            ║
### ║  4. Démo Tinker du scénario de hijacking refusé           ║
### ║                                                           ║
### ║  Tu attends mon GO 2 écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 2. Étape 2 — RGPD & cleanup routes

### 2.1 — Suppression des routes Breeze legacy `/profile`

**Le problème** : Breeze a installé un set de routes `/profile` (édition profil, mot de passe, suppression compte) qui coexistent maintenant avec l'espace membre `/espace`. Doublon, surface d'attaque inutile, expérience utilisateur incohérente.

**Fix attendu** :

1. Identifier les routes `/profile*` dans `routes/web.php` (probablement importées depuis `routes/auth.php` ou un fichier dédié).
2. **Supprimer** les routes obsolètes (édition profil, suppression). **Conserver** les routes liées à l'authentification pure si elles sont encore utilisées (login, logout, password reset).
3. Vérifier que toute redirection ou lien `<a href="{{ route('profile.edit') }}">` est mis à jour vers la route équivalente de `/espace`.
4. Supprimer les controllers correspondants (`ProfileController` côté Breeze) **uniquement si** plus aucune route ne pointe dessus.
5. Supprimer les vues Blade Breeze inutilisées (`resources/views/profile/*`) idem.

**Attention** : ne pas casser l'auth (login/logout). Tester intégralement après.

### 2.2 — `AnonymizeUserAction` (RGPD)

**Le problème** : la mémoire du projet stipule que la suppression de compte doit faire un soft delete + anonymisation des données personnelles, en gardant les factures pour obligation légale (7 ans). Cette action n'existe pas.

**Création** : `app/Actions/User/AnonymizeUserAction.php`

```php
class AnonymizeUserAction
{
    public function execute(User $user, User $performedBy): void
    {
        DB::transaction(function () use ($user, $performedBy) {
            // 1. Anonymiser les données personnelles directes du user
            $user->update([
                'name' => 'Utilisateur anonymisé #' . $user->id,
                'first_name' => null,
                'last_name' => null,
                'email' => 'anonymized-' . $user->id . '@anonymized.local',
                'phone' => null,
                // tout autre champ identifiant
            ]);

            // 2. Soft delete
            $user->delete();

            // 3. Activity log explicite
            activity()
                ->performedOn($user)
                ->causedBy($performedBy)
                ->log('Compte anonymisé (RGPD)');

            // 4. Les factures, orders, registrations passées sont CONSERVÉES
            //    (obligation légale 7 ans) — leurs snapshots immuables contiennent
            //    déjà les données nécessaires à la traçabilité comptable.
            //    Aucune action sur ces entités.
        });
    }
}
```

**Branchement** :
- Dans l'espace membre, route `DELETE /espace/compte` qui appelle cette Action après confirmation par mot de passe.
- Dans Filament `UserResource`, action "Anonymiser" en plus de "Supprimer", visible uniquement si Policy `anonymize` autorise.

**Email de confirmation** : envoyer une notification "Votre compte a été anonymisé" à l'ancienne adresse email (capturée AVANT anonymisation), avec mention que les factures resteront conservées 7 ans pour obligation légale.

### 2.3 — Tests à écrire

`tests/Feature/Actions/User/AnonymizeUserActionTest.php` :

```
- anonymizes user personal fields (name, email, phone all replaced with placeholders)
- soft deletes the user (deleted_at not null)
- preserves orders, invoices, registrations linked to the user
- preserves invoice snapshots which still contain pre-anonymization data
- creates an activity log with the correct causer
- sends a confirmation email to the original email address (Notification::fake)
```

`tests/Feature/Member/AccountDeletionTest.php` :

```
- a user can delete their own account from /espace/compte after confirming password
- after deletion, the user can no longer log in
- after deletion, accessing /espace redirects to login
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP 2 — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~241, soit +9)             ║
### ║  2. `php artisan route:list | grep -i profile`            ║
### ║     doit retourner uniquement les routes auth (ou rien)   ║
### ║  3. Démo Tinker du flux complet d'anonymisation :         ║
### ║     - User avec 1 order + 1 invoice + 2 registrations     ║
### ║     - Anonymisation                                       ║
### ║     - Vérifier : nom/email anonymisés, soft deleted,      ║
### ║       order/invoice/registrations intacts, snapshot       ║
### ║       invoice contient toujours le nom d'origine          ║
### ║                                                           ║
### ║  Tu attends mon GO 3 écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape 3 — Cleanup technique

### 3.1 — `CheckRegistrationRulesAction` : status check manquant

**Le problème** : l'action ne vérifie pas (ou pas systématiquement) que la session est bien `Scheduled` ET dans le futur avant d'autoriser une inscription. Un utilisateur peut potentiellement s'inscrire à une session déjà annulée ou complétée si l'UI a un bug ou si l'appel passe par une route exploitable.

**Fix attendu** :

Au début de `execute()` (ou dans une méthode dédiée appelée en tête), ajouter :

```php
if ($table->status !== SessionStatus::Scheduled) {
    throw new RuntimeException('session_not_open_for_registration');
}

if ($table->scheduled_at->isPast()) {
    throw new RuntimeException('session_already_passed');
}
```

Tests à ajouter dans `CheckRegistrationRulesActionTest.php` :

```
- throws session_not_open_for_registration when table is Cancelled
- throws session_not_open_for_registration when table is Completed
- throws session_already_passed when scheduled_at is in the past
```

### 3.2 — Strings littérales remplacées par enums typés

**Le problème** : à plusieurs endroits du code (probablement dans des `where()`, `whereIn()`, des filtres Filament, des conditions Livewire), des status sont passés en string littérale (`'registered'`, `'scheduled'`, `'paid'`) au lieu des enums typés (`RegistrationStatus::Registered`, `SessionStatus::Scheduled`, `OrderStatus::Paid`).

**Fix attendu** :

1. Faire un `grep` ciblé : `grep -rn "'registered'\|'scheduled'\|'paid'\|'cancelled'\|'completed'\|'no_show'\|'attended'\|'waitlist'\|'expired'\|'active'" app/`
2. Pour chaque occurrence, remplacer par l'enum correspondant en utilisant `->value` quand un string DB est attendu (ex: `where('status', RegistrationStatus::Registered->value)`) ou la valeur typée directement quand le contexte le permet.
3. **Exclusion** : les fichiers de migration, les seeders historiques, et les chaînes utilisées comme exception messages (ex: `throw new RuntimeException('session_not_cancellable')`) restent string et c'est normal.
4. Vérifier que les filtres Filament utilisent `->options(SessionStatus::class)` quand un enum est passé (Filament v5 supporte les enums nativement dans les Select/Filter).

Pas de tests dédiés à ajouter — la couverture existante doit rester verte. Si un test casse, c'est qu'on a touché à la sémantique, à investiguer.

### 3.3 — Vérification de l'usage de `cmixin/business-time`

**Le problème** : le package est installé (`composer.json`) et listé comme requis dans la mémoire projet, mais il n'est peut-être pas réellement utilisé (Phase 4 a créé `BusinessDayService` qui peut l'utiliser ou pas).

**Action attendue** :

1. Vérifier `app/Services/BusinessDay/BusinessDayService.php`. Si le service utilise `cmixin/business-time` (via le trait `Cmixin\BusinessTime\BusinessTime` ou `Carbon::mixin(BusinessTime::class)`), c'est OK, rien à faire. Tu signales simplement "déjà utilisé".
2. Sinon, vérifier ce qu'il utilise. Trois cas :
   - Il réimplémente le calcul de jours ouvrables à la main → **refactor** pour utiliser `cmixin/business-time` (moins de code, fériés gérés natifs).
   - Il utilise un autre package (ex: `spatie/holidays` seul) → vérifier que c'est suffisant. Si oui, **désinstaller** `cmixin/business-time` (`composer remove cmixin/business-time`) et mettre à jour `CLAUDE.md` pour retirer la mention.
   - Il utilise les deux → garder l'un, retirer l'autre, justifier le choix.

Le but est que la stack technique reste cohérente avec ce qui est réellement utilisé.

### 3.4 — Test de concurrence mal nommé

**Le problème** : la mémoire projet mentionne un "concurrency test" au nom trompeur. À toi de l'identifier (`grep -rn "concurrency\|concurrent\|race" tests/`) et de :

1. Soit renommer le test pour qu'il décrive ce qu'il teste réellement.
2. Soit l'enrichir si le test ne couvre pas vraiment ce que son nom suggère (ex: si le nom dit "test concurrent invoice generation" mais le test ne fait qu'un seul appel séquentiel, ajouter un vrai test de concurrence avec deux appels parallèles via `pcntl_fork` ou un `database_lock` simulé).

Le scénario le plus probable est le test de génération de numéro de facture (lock pessimiste) — il est mentionné dans la memory comme "correctement implémenté" mais "test mal nommé".

### 3.5 — Couverture des Actions métier

**Action** : faire un `php artisan test --coverage` ciblé sur `app/Actions/**` et identifier les Actions à <80% de couverture.

Pour chacune des Actions mal couvertes, ajouter au minimum un test heureux + un test d'échec connu. Pas besoin de viser 100% partout, mais aucune Action métier ne doit avoir <60% de couverture.

Si Pest n'est pas configuré pour mesurer la couverture (Xdebug ou pcov manquant), tu signales "couverture non mesurable dans cet environnement" et tu listes les Actions par inspection visuelle des fichiers de tests existants (à comparer avec la liste des Actions présentes dans `app/Actions/`).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP 3 — RÉCAP DE FIN DE CORRECTION                     ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~250)                     ║
### ║  2. Réponses aux questions de validation ci-dessous       ║
### ║  3. Liste des Actions métier et leur niveau de couverture ║
### ║     (mesuré ou estimé visuellement)                       ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Validation de fin de correction

Avant de déclarer ce brief de correction terminé, tu fournis un récap répondant à TOUTES ces questions :

1. Le `firstOrCreate` sur VAT public a-t-il été remplacé partout, et un test prouve-t-il qu'une tentative de hijacking est refusée ?
2. Les Policies User et Company sont-elles enregistrées et utilisées par les ressources Filament concernées ?
3. Toutes les routes Breeze legacy `/profile*` non liées à l'auth pure ont-elles été supprimées ?
4. `AnonymizeUserAction` existe-t-elle, est-elle testée, et garantit-elle que les snapshots de factures préservent les données pré-anonymisation ?
5. `CheckRegistrationRulesAction` rejette-t-elle bien les inscriptions sur sessions Cancelled, Completed, ou passées ?
6. Le `grep` des strings littérales status retourne-t-il uniquement des occurrences attendues (migrations, seeders, exception messages) ?
7. `cmixin/business-time` est-il réellement utilisé, ou bien retiré de `composer.json` si redondant ?
8. Aucune Action métier n'a une couverture <60% (mesurée ou estimée).

---

## 5. Premier prompt à donner à Claude Code

```
Lis intégralement docs/CORRECTION_PHASES_1_2_BRIEF.md, CLAUDE.md et 
FILAMENT_5_BRIEF.md avant toute action.

Confirme que tu as compris :
1. L'objectif de ce brief : résorber la dette technique des Phases 1-2 
   AVANT d'attaquer la Phase 7
2. Les 8 points de la checklist initiale (étape 0 du brief) — pour chaque 
   point, vérifie l'état réel du code et signale s'il est déjà corrigé, 
   partiellement corrigé, ou encore à faire
3. La méthodologie STOP/GO (3 étapes, GO X explicite à chaque checkpoint)

Lance ensuite `php artisan test` et rapporte le total de tests verts 
(cible attendue : 220).

Fais le diagnostic état réel des 8 points avant de me proposer un GO 1.
Indique tout point ambigu du brief avant de démarrer.
Attends mon GO 1 explicite avant de toucher au code.
```

---

**Fin du brief de correction Phases 1-2.**
