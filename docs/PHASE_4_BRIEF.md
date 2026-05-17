# Brief Phase 4 — Tables de conversation et inscriptions

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md` et `PHASE_3_BRIEF.md`. Il est la source de vérité de la Phase 4.
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **Cette phase est phasée en sept étapes (A à G) avec des checkpoints STOP**. Le non-respect des STOP est considéré comme une violation directe de la consigne et entraînera un rollback systématique.

---

## 0. État du projet à l'entrée de Phase 4 — IMPORTANT

Beaucoup de briques métier ont déjà été créées lors des phases précédentes (parfois en avance sur le phasing). **Tu ne dois PAS les recréer.** Tu dois les enrichir si nécessaire.

### Ce qui existe déjà (à ne PAS recréer)

| Élément | Localisation | État |
|--------|--------------|------|
| `Models\ConversationTable` | `app/Models/ConversationTable.php` | Avec relations, soft deletes, méthode `isFull()` |
| `Models\Registration` | `app/Models/Registration.php` | Avec relations + soft deletes |
| `Models\Level` | `app/Models/Level.php` | Avec `code`, `name`, `sort_order` |
| `Enums\RegistrationStatus` | `app/Enums/` | Registered, Waitlist, Cancelled, Attended, NoShow |
| `Enums\SessionStatus` | `app/Enums/` | Scheduled, Cancelled, Completed |
| `Actions\Registration\RegisterUserToTableAction` | `app/Actions/Registration/` | Existe, **basique** — à enrichir si nécessaire |
| `Actions\Registration\CheckRegistrationRulesAction` | `app/Actions/Registration/` | Existe, couvre déjà la majorité des règles |
| `Actions\Registration\MoveRegistrationAction` | `app/Actions/Registration/` | Existe (utilisé en Phase 5) — ne pas y toucher |
| `Actions\Registration\PromoteFromWaitlistAction` | `app/Actions/Registration/` | Existe (utilisé en Phase 5) — ne pas y toucher |
| `Actions\Session\CancelSessionAction` | `app/Actions/Session/` | Existe (utilisé en Phase 6) — ne pas y toucher |
| `Actions\Session\MarkAttendanceAction` | `app/Actions/Session/` | Existe (utilisé en Phase 6) — ne pas y toucher |
| `Actions\Card\ExtendCardValidityAction` | `app/Actions/Card/` | Existe — ne pas y toucher |
| Tests Pest associés | `tests/Feature/Actions/` | Existent et passent |
| `Settings\BookingSettings` | `app/Settings/` | Tous les seuils déjà définis |

### Ce qui MANQUE (à créer dans cette phase)

| Élément | Détail |
|--------|--------|
| `Services\BusinessDay\BusinessDayService` | **N'existe pas encore.** À créer en premier. |
| `Actions\Registration\CancelRegistrationAction` | **N'existe pas.** À créer (annulation par l'user, avec deadline). |
| `Filament\Resources\ConversationTableResource` | **N'existe pas.** À créer pour l'admin. |
| Route + Controller `/agenda` (public) | **N'existe pas.** |
| Composants Livewire `RegisterButton`, `RegistrationsList` | **N'existent pas.** |
| Routes + vues `/espace/inscriptions` | **N'existent pas.** |

### Action requise au démarrage

**Avant tout code**, tu fais un `php artisan test` pour confirmer que tout passe (~100 tests d'après Phase 3). Si des tests échouent, tu les répares AVANT de toucher à Phase 4. Tu rapportes le nombre de tests qui passent, puis tu attends mon GO pour démarrer l'étape A.

---

## 1. Décisions tranchées (à appliquer sans discussion)

1. **Niveau requis pour s'inscrire = correspondance EXACTE.** L'user doit avoir le même `level_id` que la table. Pas de "supérieur ou égal". C'est déjà ce que fait `CheckRegistrationRulesAction` (`wrong_level`), on garde.
2. **Si user n'a pas de niveau** → inscription refusée avec raison `no_level`. **En plus**, tu déclenches une notification à l'admin (`NotifyAdminOfLevelInterviewNeeded`) — mais une seule fois par user (ne pas spammer si l'user retente plusieurs fois).
3. **Semaine de référence pour `max_registrations_per_week` = semaine ISO** (lundi 00:00 → dimanche 23:59). C'est déjà l'implémentation actuelle (`startOfWeek` / `endOfWeek` Carbon par défaut), on confirme.
4. **Auto-inscription en liste d'attente** quand la table est pleine : autorisée en Phase 4. L'user peut explicitement choisir « M'inscrire en liste d'attente » via un bouton secondaire. La promotion manuelle reste en Phase 5.
5. **Annulation par l'user** : possible jusqu'à `cancellation_deadline_business_days` jours ouvrables avant la session (défaut: 3). Au-delà, refus.
6. **Quand l'user annule à temps** → la séance est recréditée sur la carte (sessions_remaining +1). C'est le scénario classique. Si la carte est expirée entre-temps, on ne recrédite pas et on logge un warning.
7. **Annulation = soft delete + status `Cancelled`**, pas de delete dur. La registration reste visible dans l'historique.
8. **Le BusinessDayService est la SEULE source de vérité pour les jours ouvrables.** Aucun calcul de jour ouvrable ne doit être fait ailleurs (pas de `now()->subDays(3)` sans passer par le service).

---

## 2. Étape A — BusinessDayService

### Objectif

Service unique qui répond à toutes les questions liées aux jours ouvrables avec prise en compte des **fériés belges**.

### Implémentation

**Choix de lib** : tu installes `spatie/holidays` (`composer require spatie/holidays`). C'est la lib la plus propre et la mieux maintenue pour ça en 2026. Elle supporte la Belgique nativement.

Si pour une raison X tu ne peux pas l'installer, tu codes les fériés belges à la main (algorithme de Gauss pour Pâques) — mais préviens-moi avant.

### API du service

```php
namespace App\Services\BusinessDay;

class BusinessDayService
{
    public function isBusinessDay(\DateTimeInterface $date): bool;
    public function isHoliday(\DateTimeInterface $date): bool;
    public function addBusinessDays(\DateTimeInterface $date, int $days): Carbon;
    public function subBusinessDays(\DateTimeInterface $date, int $days): Carbon;
    public function businessDaysBetween(\DateTimeInterface $from, \DateTimeInterface $to): int;
}
```

### Fériés belges officiels (référence)

```
- 1er janvier        (Jour de l'an) — fixe
- Lundi de Pâques    — mobile
- 1er mai            (Fête du Travail) — fixe
- Ascension          (jeudi, 39 jours après Pâques) — mobile
- Lundi de Pentecôte (50 jours après Pâques) — mobile
- 21 juillet         (Fête nationale) — fixe
- 15 août            (Assomption) — fixe
- 1er novembre       (Toussaint) — fixe
- 11 novembre        (Armistice) — fixe
- 25 décembre        (Noël) — fixe
```

**N'inclus PAS** : Carnaval (pas un férié officiel), 27 septembre (Communauté française, pas un férié commercial), 26 décembre (jour férié dans certains pays mais pas en Belgique au sens légal — cependant beaucoup d'entreprises sont fermées, à débattre plus tard).

### Tests OBLIGATOIRES (`tests/Feature/Services/BusinessDayServiceTest.php`)

Tu écris ces tests AVANT de coder l'implémentation (TDD). Au minimum :

```
- isBusinessDay returns false for Saturday/Sunday
- isBusinessDay returns false for January 1st 2026
- isBusinessDay returns false for May 1st 2026
- isBusinessDay returns false for July 21st 2026
- isBusinessDay returns false for November 11th 2026
- isBusinessDay returns false for December 25th 2026
- isBusinessDay returns false for Easter Monday 2026 (April 6th)
- isBusinessDay returns false for Ascension 2026 (May 14th)
- isBusinessDay returns false for Pentecost Monday 2026 (May 25th)
- isBusinessDay returns true for a regular Wednesday
- subBusinessDays handles weekends correctly
  → subBusinessDays('2026-05-04 (Mon)', 3) === '2026-04-29 (Wed)'
- subBusinessDays skips holidays
  → subBusinessDays('2026-05-06 (Wed)', 3) === '2026-04-30 (Thu)' (skip 1 May + WE)
- addBusinessDays skips holidays
  → addBusinessDays('2026-04-30 (Thu)', 3) === '2026-05-06 (Wed)'
- businessDaysBetween counts correctly
  → businessDaysBetween('2026-05-04', '2026-05-08') === 4 (Mon to Thu inclus, Fri exclu)
```

Tu vérifies les dates des fériés mobiles (Pâques, Ascension, Pentecôte) via une source officielle (par exemple https://www.opcwallonie.be ou les données de spatie/holidays) avant d'écrire les tests.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Avant de continuer, tu rapportes :                       ║
### ║  1. Le nombre de tests verts dans                         ║
### ║     BusinessDayServiceTest                                ║
### ║  2. Le nombre total de tests verts dans le projet         ║
### ║  3. La méthode utilisée (spatie/holidays ou maison)       ║
### ║                                                           ║
### ║  Tu attends mon "GO B" écrit avant de continuer.          ║
### ║  Si tu continues sans GO, c'est une violation directe.    ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape B — Compléter `CheckRegistrationRulesAction`

L'action existe et couvre déjà : `session_not_available`, `no_level`, `wrong_level`, `deadline_passed` (24h), `table_full`, `weekly_limit_reached`, `future_limit_reached`, `already_registered`, `no_active_card`.

### Ce que tu ajoutes / vérifies

1. **Vérifier la cohérence avec `BusinessDayService`** : la `registration_deadline_hours` est en heures (24h par défaut), pas en jours ouvrables. C'est volontaire. **Tu n'utilises PAS le BusinessDayService ici.** Pour l'inscription, c'est un délai en heures glissantes.

2. **Cas waitlist déjà géré** par le paramètre `$forWaitlist`. Tu vérifies que la logique est correcte :
   - Si `$forWaitlist === true` → la règle `table_full` est ignorée
   - Si `$forWaitlist === true` → la règle `no_active_card` est ignorée (l'inscription waitlist ne consomme pas de carte tant qu'on n'est pas promu)
   - Toutes les autres règles s'appliquent (niveau, deadline, limites hebdo/futur, déjà inscrit)

3. **Cas du user qui s'auto-inscrit en waitlist alors qu'il a déjà une registration `Registered` sur la même table** → bloqué par la règle `already_registered`. ✓

### Tests additionnels à compléter dans `RegisterUserToTableActionTest`

Si pas déjà couverts, ajoute :
- Inscription en waitlist autorisée même sans carte active (carte requise au moment de la promotion, pas à l'auto-waitlist)
- Inscription en waitlist refusée si déjà inscrit en `Registered` sur la même table
- Inscription en waitlist refusée si déjà en `Waitlist` sur la même table
- Inscription bloquée si `level_id` user ≠ `level_id` table

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Les modifications faites à CheckRegistrationRulesAction│
### ║     (diff exact ou "aucune modification nécessaire")      ║
### ║  2. Les tests ajoutés et leur résultat                    ║
### ║                                                           ║
### ║  Tu attends mon "GO C" écrit avant de continuer.          ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Étape C — `CancelRegistrationAction`

Action critique. **Elle utilise `BusinessDayService` pour la deadline.**

### API

```php
namespace App\Actions\Registration;

class CancelRegistrationAction
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly BusinessDayService $businessDays,
    ) {}

    /**
     * @throws \DomainException avec un code dans le message
     */
    public function execute(Registration $registration, User $cancelledBy): Registration;
}
```

### Logique

```
1. Si $registration->status !== Registered ET !== Waitlist
   → throw 'cannot_cancel'
2. Si la session est déjà passée OU annulée OU completed
   → throw 'session_unavailable'
3. Calcul de la deadline d'annulation :
   $deadline = $businessDays->subBusinessDays(
       $registration->conversationTable->scheduled_at,
       $settings->cancellation_deadline_business_days
   );
   → $deadline représente le moment AVANT lequel l'annulation doit avoir lieu.
   → Précisément : on doit annuler au plus tard à 23:59:59 le jour J - N jours ouvrables.
   
4. Si now() > $deadline :
   - Si l'utilisateur est admin (cancelledBy a le role admin) → autorisé quand même
   - Sinon → throw 'deadline_passed'

5. Dans une DB::transaction :
   a. Marquer la registration comme cancelled (status=Cancelled, cancelled_at=now, cancelled_by=$cancelledBy->id)
   b. Si la registration avait un card_id (donc était Registered, pas Waitlist) :
      - Charger la carte
      - Si carte->status === Active ET carte->expires_at > now : carte->increment('sessions_remaining')
      - Sinon : ne rien recréditer, logger via activity()
   c. activity()->performedOn($registration)->causedBy($cancelledBy)->log('Inscription annulée')

6. Retourner la registration fraîchement chargée
```

### Précisions sur le calcul de deadline

C'est subtil. Exemples :
- Session le **jeudi 7 mai 2026 à 14h00**, `cancellation_deadline_business_days = 3` :
  - subBusinessDays(jeudi 7 mai, 3) = lundi 4 mai
  - L'user peut annuler **jusqu'au lundi 4 mai 23:59:59**
  - À partir de mardi 5 mai 00:00, l'annulation est refusée

- Session le **lundi 18 mai 2026 à 18h00**, `cancellation_deadline_business_days = 3` :
  - subBusinessDays(lundi 18 mai, 3) = mercredi 13 mai (en sautant WE)
  - L'user peut annuler jusqu'au mercredi 13 mai 23:59:59

- Session le **lundi 4 mai 2026** (vendredi 1 mai férié juste avant), `cancellation_deadline_business_days = 3` :
  - subBusinessDays(lundi 4 mai, 3) = mardi 28 avril (saute le WE et le 1 mai férié)
  - L'user peut annuler jusqu'au mardi 28 avril 23:59:59

**Implémentation pratique** : `subBusinessDays` retourne une date à 00:00 (début de journée). Tu mets le `endOfDay()` dessus pour avoir 23:59:59.59. Comme ça `now()->gt($deadline)` fait bien la bonne comparaison.

### Tests OBLIGATOIRES (`tests/Feature/Actions/CancelRegistrationActionTest.php`)

```
- cancels a registered registration before deadline
- recredits sessions_remaining on the card
- does not recredit if card is expired
- does not recredit if card is not active
- throws deadline_passed when too late (user)
- admin can cancel even after deadline
- throws cannot_cancel for a registration already Cancelled
- throws cannot_cancel for an Attended registration
- throws session_unavailable if session already cancelled
- handles waitlist cancellation (no card to recredit)
- logs activity with the correct user as causer
- properly handles deadlines that fall right after a Belgian holiday
  → exemple concret avec subBusinessDays autour du 1 mai ou Pâques
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — CHECKPOINT 3 (CRITIQUE)                        ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Code complet de CancelRegistrationAction              ║
### ║  2. Code complet du test (toutes les assertions)          ║
### ║  3. Nombre de tests verts                                 ║
### ║  4. Une démo manuelle dans tinker:                        ║
### ║     - Crée un user avec carte active                      ║
### ║     - Inscris-le à une table dans 5 jours                 ║
### ║     - Calcule manuellement la deadline                    ║
### ║     - Affiche-la                                          ║
### ║                                                           ║
### ║  Tu attends mon "GO D" écrit. Je vais relire le code      ║
### ║  ligne par ligne avant de te laisser continuer.           ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Étape D — Filament Resource `ConversationTableResource`

### Champs du formulaire

- `topic` (TextInput, required, max 255) — sujet de la conversation
- `description` (Textarea, nullable, rows 3)
- `level_id` (Select sur Level, required, options = niveaux actifs triés par sort_order)
- `scheduled_at` (DateTimePicker, required, minDate: today)
- `duration_minutes` (TextInput numeric, default = `SessionDefaultsSettings::default_duration_minutes`)
- `max_participants` (TextInput numeric, default = `SessionDefaultsSettings::default_max_participants`, min 1)
- `location` (TextInput, default = `SessionDefaultsSettings::default_location`)
- `animator_id` (Select sur User, nullable — préparé pour le rôle animateur futur)
- `status` (Select avec enum SessionStatus, default Scheduled)

### Table

Colonnes :
- `scheduled_at` (DateTimeColumn, sortable, format `d/m/Y H:i`)
- `topic`
- `level.code` (Badge)
- Nombre d'inscrits (computed : count des registrations avec status Registered) / max_participants
- Nombre en waitlist (count des registrations avec status Waitlist)
- `status` (Badge avec couleur)

Filtres :
- `level_id` (SelectFilter)
- `status`
- `scheduled_at` (à venir / passées)

Actions :
- Edit, Delete (soft), bulk delete
- **Action custom "Voir inscrits"** → modal avec la liste des registrations (read-only en Phase 4, l'admin gère depuis Phase 5)

Navigation group : "Sessions".

### Authorization

Policy `ConversationTablePolicy` à créer si elle n'existe pas, toutes les méthodes retournent `$user->hasRole('admin')`.

### Pas de tests Filament obligatoires en Phase 4 — basique.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP D — CHECKPOINT 4                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu confirmes :                                           ║
### ║  1. Resource créée et accessible sur /admin               ║
### ║  2. Création d'une table OK depuis le formulaire          ║
### ║  3. Affichage avec compteurs corrects                     ║
### ║                                                           ║
### ║  Tu attends mon "GO E" avant de continuer.                ║
### ╚══════════════════════════════════════════════════════════╝

---

## 6. Étape E — Page agenda public `/agenda`

### Route et controller

```php
Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda');
```

`App\Http\Controllers\Public\AgendaController@index` :
- Liste les `ConversationTable` avec `status = Scheduled` ET `scheduled_at >= now()`
- Triées par `scheduled_at ASC`
- Eager load : `level`, withCount `registrations` (status Registered)
- Filtre optionnel par level (query string `?level=A2`)
- Pagination (15 par page)

### Vue Blade `resources/views/public/agenda.blade.php`

- En-tête avec sélecteur de niveau (boutons par niveau, lien "Tous")
- Liste des tables sous forme de cartes avec :
  - Date + heure + jour de semaine en français
  - Sujet (`topic`)
  - Niveau (badge)
  - Lieu
  - "X / max participants" + indicateur visuel (jauge)
  - Si user authentifié + niveau cohérent + carte active : bouton "M'inscrire" → composant Livewire de l'étape F
  - Si user authentifié mais niveau pas cohérent : message désactivé "Niveau requis : X"
  - Si user pas authentifié : bouton "Se connecter pour s'inscrire"
  - Si table pleine : bouton "M'inscrire en liste d'attente" (secondaire)

Design : cohérent avec `/tarifs`, pas d'extravagance.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP E — CHECKPOINT 5                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Capture d'écran ou description précise de la page        ║
### ║  /agenda avec au moins 3 tables seedées de niveaux        ║
### ║  différents.                                              ║
### ║                                                           ║
### ║  Tu attends mon "GO F" avant de continuer.                ║
### ╚══════════════════════════════════════════════════════════╝

---

## 7. Étape F — Composant Livewire d'inscription

### `App\Livewire\Registration\RegisterButton`

Props publiques :
- `ConversationTable $table` (model binding)

Méthodes :
- `register()` : appelle `RegisterUserToTableAction` ; en cas d'exception `RuntimeException`, capture le code et affiche un message en français correspondant
- `registerToWaitlist()` : appelle `RegisterUserToTableAction` avec `$forWaitlist = true`
- `cancel(Registration $registration)` : appelle `CancelRegistrationAction`

Vue :
- Si user pas authentifié → bouton "Se connecter pour s'inscrire" (lien vers /login avec redirect)
- Si user authentifié et pas inscrit :
  - Si table pas pleine → bouton "M'inscrire" (primaire)
  - Si table pleine → bouton "M'inscrire en liste d'attente" (secondaire)
- Si user déjà inscrit (Registered) → badge "Inscrit" + bouton "Annuler mon inscription"
- Si user en waitlist → badge "En liste d'attente (position N)" + bouton "Quitter la liste d'attente"

### Mapping des codes d'erreur → messages français

| Code | Message utilisateur |
|------|---------------------|
| `session_not_available` | Cette session n'est plus disponible. |
| `no_level` | Vous devez passer un entretien de niveau avant de pouvoir vous inscrire. Un administrateur vous contactera. |
| `wrong_level` | Cette session est réservée au niveau {level}. Votre niveau actuel est {user_level}. |
| `deadline_passed` | Le délai d'inscription est dépassé (24h avant la session). |
| `table_full` | La table est complète. Vous pouvez rejoindre la liste d'attente. |
| `weekly_limit_reached` | Vous avez atteint le maximum d'inscriptions cette semaine ({n} session par semaine). |
| `future_limit_reached` | Vous avez déjà {n} inscriptions à venir. Annulez-en une pour vous inscrire à celle-ci. |
| `already_registered` | Vous êtes déjà inscrit(e) à cette session. |
| `no_active_card` | Vous n'avez pas de carte active. Achetez une carte pour vous inscrire. |
| `cannot_cancel` | Cette inscription ne peut plus être annulée. |
| `session_unavailable` | La session n'est plus disponible. |

### Side-effects

- Quand `no_level` est levé pour la **première fois** pour un user → notifier admin (`NotifyAdminOfLevelInterviewNeeded`).
  - Pour éviter le spam : poser une colonne `interview_requested_at` sur `users` (migration à créer si elle n'existe pas) qui est posée à `now()` au premier déclenchement, et qui empêche les déclenchements ultérieurs.

### Tests Livewire

```
- guest user sees a login button
- authenticated user with active card and matching level can register
- authenticated user with mismatched level sees disabled state with reason
- can cancel own registration before deadline
- cannot cancel after deadline (user with role member)
- waitlist button visible when table is full
- shows current registration status (registered/waitlist) when applicable
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP F — CHECKPOINT 6                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Tests Livewire verts                                  ║
### ║  2. Démo manuelle :                                       ║
### ║     - User A s'inscrit à une table — OK                   ║
### ║     - User A retente — already_registered                 ║
### ║     - User B (mauvais niveau) → message d'erreur          ║
### ║     - User C (sans niveau, première fois) →               ║
### ║       refus + notification admin envoyée (vérifier db)    ║
### ║     - User C retente → refus mais PAS de seconde notif    ║
### ║                                                           ║
### ║  Tu attends mon "GO G" avant la dernière étape.           ║
### ╚══════════════════════════════════════════════════════════╝

---

## 8. Étape G — Espace membre `/espace/inscriptions`

### Route

```php
Route::middleware(['auth', 'verified'])->prefix('espace')->group(function () {
    Route::get('/inscriptions', [Member\RegistrationsController::class, 'index'])->name('espace.inscriptions');
});
```

### Controller `Member\RegistrationsController@index`

- `$upcoming` : registrations de l'user authentifié, avec `status IN (Registered, Waitlist)` et `scheduled_at >= now()`
- `$past` : registrations de l'user, avec `scheduled_at < now()` OU `status IN (Cancelled, Attended, NoShow)`
- Tri : upcoming par `scheduled_at ASC`, past par `scheduled_at DESC`
- Eager load : `conversationTable.level`, `card.cardType`

### Vue Blade `resources/views/espace/inscriptions.blade.php`

Deux sections :
1. **À venir** : liste des registrations avec date, sujet, niveau, statut (badge), bouton "Annuler" (composant Livewire `RegisterButton` réutilisé)
2. **Historique** : liste des registrations passées avec date, sujet, statut (Attended/NoShow/Cancelled — différentes couleurs)

Pas de pagination en Phase 4, simple liste. À paginer en Phase 7 si trop de données.

### Mise à jour de la navigation membre

Vérifier que le lien "Mes inscriptions" pointe bien vers `/espace/inscriptions` dans le layout membre. S'il n'existe pas, l'ajouter.

### Tests

`tests/Feature/Member/RegistrationsControllerTest.php` :
```
- guest is redirected to login
- authenticated user sees only their own registrations
- upcoming and past are correctly separated
- shows correct status badges
```

---

## 9. Récapitulatif des tests à écrire en Phase 4

| Fichier | Tests minimums |
|--------|----------------|
| `BusinessDayServiceTest` | 13 (voir étape A) |
| `CancelRegistrationActionTest` | 12 (voir étape C) |
| `CheckRegistrationRulesActionTest` (compléter) | +4 sur waitlist |
| `RegisterButtonComponentTest` (Livewire) | 7 |
| `RegistrationsControllerTest` | 4 |
| `AgendaControllerTest` | 3 (page accessible, filtre level fonctionne, pagination) |

**Cible fin de Phase 4 : ~140 tests verts** (on part de ~100 après Phase 3).

---

## 10. Pièges spécifiques Phase 4 — INTERDICTIONS

À Claude Code : ne dévie pas des points suivants.

1. **NE PAS recréer les actions qui existent déjà** (cf. section 0). Tu les enrichis si nécessaire, point.
2. **NE PAS calculer de jours ouvrables ailleurs que dans `BusinessDayService`.** Pas de `now()->subDays(3)` dans `CancelRegistrationAction`. Pas de boucle "while not weekend" inline. **Tout passe par le service.**
3. **NE PAS faire de promotion automatique depuis la liste d'attente.** Phase 5. En Phase 4, l'auto-inscription en waitlist est manuelle (l'user choisit) ; la promotion reste manuelle aussi (Phase 5).
4. **NE PAS modifier `RegisterUserToTableAction` pour gérer la waitlist autrement** que via le param `$forWaitlist` qui existe déjà dans `CheckRegistrationRulesAction`. Tu propages le param au besoin.
5. **NE PAS oublier le `card_id` null** quand l'inscription est en waitlist — la registration n'est rattachée à une carte qu'au moment de la promotion (Phase 5).
6. **NE PAS spammer les notifications admin "niveau requis".** Une fois par user max. Utilise `users.interview_requested_at` pour gating.
7. **NE PAS utiliser `level_id` >= ou <=.** Le brief tranche pour une correspondance EXACTE (cf. décision 1).
8. **NE PAS coder en dur les seuils** (24h, 3 jours, max 1/semaine). Tout vient de `BookingSettings`.
9. **NE PAS créer de logique d'annulation côté admin dans cette phase.** Phase 6. En Phase 4, seul l'utilisateur annule ses propres inscriptions.
10. **NE PAS sauter les STOP.** Si tu sautes un STOP, le travail post-STOP est considéré comme à jeter et tu reprends.

---

## 11. Validation de fin de Phase 4

Avant de déclarer Phase 4 terminée, tu fournis un récap qui répond à TOUTES les questions suivantes :

1. Combien de tests passent au total ? (cible : ~140)
2. `BusinessDayService` : combien de tests, et est-ce que les fériés mobiles 2026 sont vérifiés (Pâques, Ascension, Pentecôte) ?
3. `CancelRegistrationAction` : recréditation de carte testée ? Cas carte expirée testé ? Cas admin qui force testée ?
4. Resource Filament : accessible et utilisable (créer une table, voir les inscrits) ?
5. `/agenda` : filtre par niveau fonctionnel ?
6. Composant Livewire : tous les codes d'erreur correctement traduits en messages français ?
7. Notification "niveau requis" : envoyée une seule fois par user (vérifié par test) ?
8. `/espace/inscriptions` : sépare bien à venir / passé ?

---

## 12. Méthode de travail

À chaque étape :
1. Tu commits avec un message clair (ex: `feat: add BusinessDayService with Belgian holidays`)
2. Tu rapportes au STOP correspondant
3. Tu attends mon `GO {lettre}`
4. Tu passes à l'étape suivante

**Pas de "Je continue car ça allait bien"**. Pas de "j'ai aussi fait l'étape suivante en avance". Si tu enfreins ce point, je te demanderai un rollback de tout ce qui suit le dernier STOP validé.

---

**Fin du brief Phase 4.**
