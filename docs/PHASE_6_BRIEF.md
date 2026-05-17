# Brief Phase 6 — Annulations admin et automation

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`, et les briefs `PHASE_3_BRIEF.md`, `PHASE_4_BRIEF.md`, `PHASE_5_BRIEF.md`. Il est la source de vérité de la Phase 6.
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **Cette phase est phasée en cinq étapes (A à E) avec des checkpoints STOP**, comme les phases précédentes.

---

## Préambule — Méthodologie de phasing (rappel important)

Cette phase suit la même méthode que Phases 4 et 5 :

1. **Tu lis intégralement ce brief avant de coder.** Tu confirmes ta compréhension et tu signales tout point ambigu avant de commencer.
2. **Tu attends mon GO A explicite** avant de démarrer la première étape.
3. **À chaque STOP**, tu produis un rapport au format demandé et tu **attends mon GO suivant écrit**. Pas de "j'ai aussi fait l'étape suivante en avance". Pas de "ça marchait bien donc j'ai continué". **Sauter un STOP = rollback du travail post-STOP.**
4. **Si tu trouves un écart entre ce brief et la réalité du code**, tu le signales explicitement avant d'agir (comme le rename `waitlist_auto_promote` qui avait été tranché en Phase 5).
5. **Tu commits étape par étape** avec un message clair (ex: `feat: CancelSessionAction enrichie avec notifications`).

---

## 0. État du projet à l'entrée de Phase 6

À ce stade :

- **186 tests Pest verts** (cible à confirmer au démarrage par `php artisan test`)
- Phase 5 livrée : auto-promotion FIFO, notifications email, modale Filament admin actionnable, BusinessDayService avec fériés belges
- Stack : Laravel 11+, Filament v5, Livewire, Pest, Spatie Settings + Activity Log + Holidays, Mollie SDK
- Tous les Models, Enums, Migrations sont en place

### Ce qui existe déjà (à NE PAS recréer)

| Élément | Localisation | État |
|--------|--------------|------|
| `Actions\Session\CancelSessionAction` | `app/Actions/Session/` | **Existe avec signature `(ConversationTable, User $admin, string $reason)` et logique threshold** — à enrichir en étape A pour ajouter notifications + traitement explicite des registrations Waitlist + activity log enrichi sur cas carte expirée |
| `Actions\Session\MarkAttendanceAction` | `app/Actions/Session/` | Complet, sera utilisé tel quel (UI à brancher en étape C) |
| `Actions\Card\ExtendCardValidityAction` | `app/Actions/Card/` | Existe, sera réutilisée par CancelSessionAction |
| `Actions\Registration\CancelRegistrationAction` | `app/Actions/Registration/` | Complet, NE PAS modifier |
| `Actions\Registration\PromoteFromWaitlistAction` | `app/Actions/Registration/` | Complet, NE PAS modifier |
| `Notifications\UserPromotedFromWaitlistNotification` | `app/Notifications/` | Phase 5, ne pas modifier |
| `Notifications\RegistrationCancelledByAdminNotification` | `app/Notifications/` | Phase 5, ne pas modifier |
| `Settings\CardSettings` | `app/Settings/` | Complet (avec `expiration_warning_days`) — **rien à étendre en Phase 6** |
| `Settings\BookingSettings` | `app/Settings/` | À étendre aux étapes **C et D uniquement** (les clés `post_cancellation_card_extension_days` et `post_cancellation_extension_threshold_days` existent déjà — NE PAS les recréer) |
| `Models\Card`, méthodes `isActive()`, `hasSessionsRemaining()` | `app/Models/Card.php` | Complets |
| `Enums\CardStatus`, `SessionStatus`, `RegistrationStatus` | `app/Enums/` | Complets |
| `Services\BusinessDay\BusinessDayService` | `app/Services/BusinessDay/` | Complet (Phase 4) |
| `Filament\Pages\ManageBookingSettings` | `app/Filament/Pages/` | Existe — à compléter aux étapes C et D pour exposer les nouvelles clés |

### Ce qui MANQUE (à créer dans cette phase)

| Élément | Étape |
|--------|-------|
| Notification `SessionCancelledNotification` | A |
| UI Filament : action "Annuler la session" sur ConversationTable (avec champ raison) | A |
| Action `ExpireCardAction` + commande `cards:expire` | B |
| Action `SendExpirationWarningsAction` + commande `cards:warn-expiration` | B |
| Notification `CardExpirationWarningNotification` | B |
| Migration : colonne JSON `reminders_sent` sur `cards` | B |
| Commande `attendance:mark-no-shows` | C |
| Setting `BookingSettings::auto_mark_noshow_after_days` (default 7) | C |
| UI Filament : interface de saisie des présences | C |
| Notification `SessionReminderNotification` | D |
| Setting `BookingSettings::session_reminder_hours_before` (default 24) | D |
| Migration : colonne `reminded_at` sur `registrations` (avec index) | D |
| Commande `sessions:send-reminders` | D |
| Schedule (Laravel 11+ via `bootstrap/app.php`) | toutes |
| Tests d'intégration end-to-end | E |

### Action requise au démarrage

Tu lances `php artisan test`. Tu rapportes :
- Le nombre total de tests verts (cible attendue : 186)
- Toute incohérence ou test qui échoue

Tu attends mon GO A avant de toucher quoi que ce soit.

---

## 1. Décisions tranchées (à appliquer sans discussion)

1. **Extension de validité carte après annulation session** : on **réutilise les settings existants** `BookingSettings::post_cancellation_card_extension_days` (default 30) pour la durée d'extension, et `BookingSettings::post_cancellation_extension_threshold_days` (default 30) pour le seuil de déclenchement. **L'extension n'est PAS systématique** : elle ne s'applique que si la carte expire dans `threshold` jours ou moins au moment de l'annulation. Cette logique existe déjà dans `CancelSessionAction` et doit être préservée. Pas de calcul "prorata" complexe.

2. **Si un user avait sa carte expirée entre l'inscription et l'annulation de la session par l'admin** : on **NE recrédite PAS la séance** (la carte est expirée, pas de retour en arrière) et on **N'étend PAS non plus la validité** (la carte est déjà morte, pas de "résurrection"). On consigne simplement un activity log explicite "Recréditation impossible : carte expirée" et le user reçoit une notification d'information (variante `expired_no_compensation`). Cette décision est cohérente avec le comportement actuel de `CancelRegistrationAction` qui ne recrédite pas non plus les cartes inactives.

3. **Auto-marquage NoShow** : une session `Scheduled` dont les présences n'ont PAS été saisies dans les `auto_mark_noshow_after_days` jours suivant la session voit toutes ses registrations `Registered` restantes passer en `NoShow`, et la session passe en `Completed`. Default : **7 jours** après la session. **Pas de recréditation** dans ce cas.

4. **Rappel session aux users** : envoyé `session_reminder_hours_before` heures avant la session. Default : **24 heures**. Une seule fois par registration (idempotent via colonne `reminded_at` sur `registrations`).

5. **Alerte expiration carte aux users** : envoyée X jours avant `expires_at`. La valeur X vient de `CardSettings::expiration_warning_days` (existant, TagsInput, default `[30, 7]`). Une notification par seuil franchi, idempotent via colonne JSON `reminders_sent` sur `cards`.

6. **Les commandes cron tournent quotidiennement** sauf `sessions:send-reminders` qui tourne toutes les heures (sinon on rate la fenêtre de 24h pour les sessions matinales).

7. **Toutes les notifications de cette phase utilisent `ShouldQueue`** comme en Phase 5. Pas d'envoi synchrone.

8. **Toutes les vues créées en Phase 6 utilisent des classes Tailwind sémantiques** (`bg-primary`, `text-accent`, `border-surface`) plutôt que des couleurs littérales (`bg-emerald-700`, `text-orange-500`). Les couleurs littérales sont autorisées uniquement pour les états système (rouge erreur, vert succès, jaune warning, gris neutre). C'est une consigne préventive pour la Phase 7 (theming).

9. **Le paramètre `$reason` de `CancelSessionAction` est OBLIGATOIRE et conservé tel quel.** Il est utilisé pour : remplir `cancellation_reason` sur la table, l'activity log, et le corps du mail envoyé aux users. La modale Filament d'annulation comporte donc un Textarea "Raison de l'annulation" obligatoire.

---

## 2. Étape A — Annulation de session par l'admin

### A.1 — Vérification préalable de `CancelSessionAction`

L'action existe déjà dans `app/Actions/Session/CancelSessionAction.php` avec la signature :

```php
public function execute(ConversationTable $table, User $admin, string $reason): ConversationTable
```

Sa logique actuelle :
- Met `table.status = Cancelled`, `cancelled_at`, `cancellation_reason`
- Pour chaque registration `Registered` avec `card_id` non null : recrédit + extension conditionnelle (si la carte expire dans `threshold` jours)
- Activity log sur la table

**Ce qui manque et doit être ajouté en étape A** :

1. **Traitement explicite des registrations `Waitlist`** : actuellement seules les `Registered` sont traitées. Il faut aussi passer toutes les `Waitlist` en `Cancelled` (avec `cancelled_at` et `cancelled_by`) afin qu'elles soient notifiées.
2. **Cas carte expirée** : actuellement le code recrédite quand même (bug latent — `increment` sans check de l'état de la carte). Il faut **ne PAS recréditer** si la carte n'est pas active (`! $card->isActive()`), et logger explicitement "Recréditation impossible : carte expirée" sur la registration. Comportement aligné avec `CancelRegistrationAction`.
3. **Notifications** : dispatcher `SessionCancelledNotification` à chaque user impacté (Registered ET Waitlist), via `DB::afterCommit()`.

**Contrat attendu après enrichissement** (signature inchangée) :

```php
public function execute(ConversationTable $table, User $admin, string $reason): ConversationTable
```

**Logique cible** (dans une `DB::transaction`) :

1. Si `$table->status !== SessionStatus::Scheduled` → throw `RuntimeException('session_not_cancellable')`
2. Si `$table->scheduled_at->isPast()` → throw `RuntimeException('session_already_passed')`
3. Récupérer toutes les `Registration` du table avec status ∈ {Registered, Waitlist}, eager load `card` et `user`
4. Construire un tableau `$notifications = []` qui collecte `[Registration, compensation_type]` pour dispatch après commit
5. Pour chaque registration :
   - **Si status = Registered** ET `registration->card_id` non null :
     - Si `$card->isActive()` :
       - `$card->increment('sessions_remaining')`
       - Si `now()->lt($card->expires_at)` ET `now()->diffInDays($card->expires_at) <= $threshold` :
         - Étendre via `app(ExtendCardValidityAction::class)->execute($card, $extensionDays, $admin)`
         - `compensation_type = 'recredit_and_extend'`
       - Sinon :
         - `compensation_type = 'recredit_only'`
     - Sinon (carte non active) :
       - **PAS de recréditation, PAS d'extension**
       - Activity log sur la registration : "Recréditation impossible : carte expirée"
       - `compensation_type = 'expired_no_compensation'`
   - **Si status = Waitlist** :
     - `compensation_type = 'waitlist_notice'`
   - Quel que soit le cas : `$registration->update(['status' => Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $admin->id])`
   - Push dans `$notifications`
6. Mettre `$table->update(['status' => SessionStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason])` (déjà fait par le code actuel — vérifier qu'on ne double pas)
7. Activity log sur la table (déjà fait — vérifier qu'on garde l'enrichissement existant `registrations_credited`)
8. **Dans `DB::afterCommit`** : pour chaque entrée de `$notifications`, dispatcher `$registration->user->notify(new SessionCancelledNotification($table, $registration, $compensationType, $reason))`

### A.2 — Notification `SessionCancelledNotification`

Channels : `['mail', 'database']`, `ShouldQueue`.

**Constructeur** : `($table, $registration, string $compensationType, string $reason)`.

**Mail** :
- Sujet : "Session du {date} annulée"
- Corps : mention de la raison (`$reason` injecté), puis bloc adapté selon `$compensationType` :
  - `recredit_and_extend` : "Votre séance a été recréditée sur votre carte et la validité de cette dernière a été prolongée de {N} jours."
  - `recredit_only` : "Votre séance a été recréditée sur votre carte. La validité de votre carte n'a pas été modifiée (elle expire encore dans suffisamment longtemps)."
  - `expired_no_compensation` : "Votre carte étant expirée, la séance n'a pas pu être recréditée."
  - `waitlist_notice` : "Vous étiez en liste d'attente pour cette session, qui a été annulée. Aucune action de votre part n'est requise."

**Database** : stocke `table_id`, `registration_id`, `compensation_type`, `reason`.

### A.3 — UI Filament : action "Annuler la session"

Dans `ConversationTableResource` (côté Tables), ajouter une **TableAction** "Annuler la session" :

- Visible uniquement si `record.status === Scheduled` ET `record.scheduled_at > now()`
- Couleur danger (rouge), icône heroicon adaptée
- **Form modal** avec un seul champ obligatoire :
  - `Textarea::make('reason')->label('Raison de l\'annulation')->required()->rows(3)->maxLength(500)`
- Confirmation modale avec un texte explicite :
  > "Annuler cette session ? Tous les utilisateurs inscrits ainsi que les utilisateurs en liste d'attente seront notifiés. Les séances seront recréditées sur les cartes encore actives, et la validité des cartes proches de l'expiration sera prolongée."
- Au clic confirmation : `app(CancelSessionAction::class)->execute($record, auth()->user(), $data['reason'])`
- Toast Filament de succès ("Session annulée et utilisateurs notifiés.") ou d'erreur (avec le message de la RuntimeException) selon le résultat

### A.4 — Tests à écrire

`tests/Feature/Actions/CancelSessionActionTest.php` (à enrichir, **les tests existants doivent rester verts**) :

```
- (existant) recredits all confirmed registrations when session is cancelled
- (existant) extends validity only for cards expiring within threshold and not yet expired
- (existant) marks session as cancelled
- (NOUVEAU) does NOT recredit if registration card is not Active (expired/inactive)
- (NOUVEAU) cancels Waitlist registrations as well (status -> Cancelled, no card touch)
- (NOUVEAU) throws session_not_cancellable when table is already Cancelled or Completed
- (NOUVEAU) throws session_already_passed when table is in the past
- (NOUVEAU) dispatches SessionCancelledNotification to all impacted users (Notification::fake)
- (NOUVEAU) sends correct compensation_type for each of the 4 cases
   (recredit_and_extend / recredit_only / expired_no_compensation / waitlist_notice)
- (NOUVEAU) logs activity on table AND on each registration with expired card
- (NOUVEAU) the reason string is persisted on table.cancellation_reason and passed in the notification
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~196)                     ║
### ║  2. Diff de CancelSessionAction                           ║
### ║  3. Démo tinker du scénario complet :                     ║
### ║     - Table avec : 1 user carte active proche expir,      ║
### ║       1 user carte active loin expir, 1 user carte        ║
### ║       expirée, 1 user en waitlist                         ║
### ║     - Annule la session avec raison "test"                ║
### ║     - Vérifie : 4 registrations Cancelled,                ║
### ║       carte 1 recréditée+étendue, carte 2 recréditée      ║
### ║       seulement, carte 3 inchangée, waitlist intact,      ║
### ║       4 notifications faked envoyées avec les bons        ║
### ║       compensation_type                                   ║
### ║                                                           ║
### ║  Tu attends mon GO B écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape B — Expiration automatique des cartes

### B.1 — `ExpireCardAction`

`app/Actions/Card/ExpireCardAction.php` :

```php
public function execute(): int  // retourne le nombre de cartes expirées
```

**Logique** :
- `Card::where('status', CardStatus::Active->value)->where('expires_at', '<', now())->get()`
- Pour chaque carte : `$card->update(['status' => CardStatus::Expired])`
- Activity log : `activity()->performedOn($card)->log('Carte expirée automatiquement')` (causer = null = système)
- Retourne le count

**Pas de notification ici** — la notification d'expiration est ENVOYÉE AVANT l'expiration (étape B.3 ci-dessous).

### B.2 — Commande `cards:expire`

`app/Console/Commands/ExpireCardsCommand.php` :

```php
protected $signature = 'cards:expire';
protected $description = 'Marque comme expirées les cartes dont la validité a dépassé la date du jour.';

public function handle(ExpireCardAction $action): int
{
    $count = $action->execute();
    $this->info("{$count} cartes expirées.");
    return self::SUCCESS;
}
```

### B.3 — Notification d'expiration imminente

**Notification** : `app/Notifications/CardExpirationWarningNotification.php`
- Channels : `['mail', 'database']`, `ShouldQueue`
- Mail :
  - Sujet : "Votre carte expire bientôt"
  - Corps : "Votre carte de N séances expire dans X jours (le {date}). Il vous reste Y séances. Pensez à les utiliser avant l'expiration."
  - CTA : lien vers `/agenda`
- Database : stocke `card_id`, `days_until_expiration`, `sessions_remaining`

**Migration** : ajouter une colonne `reminders_sent` JSON à `cards` (default `'[]'`, cast en `array` côté Model). Cette colonne stocke les seuils déjà notifiés (ex: `[30, 7]` signifie qu'on a déjà envoyé les 2 alertes).

**Action** : `app/Actions/Card/SendExpirationWarningsAction.php` :

```php
public function execute(): int  // nombre d'alertes envoyées
```

**Logique** :
- Récupère `CardSettings::expiration_warning_days` (default `[30, 7]`)
- Si vide : retourne 0
- Pour chaque seuil dans la liste, calcule la fenêtre cible : `now() + threshold days`
- Pour chaque carte `Active` qui expire dans cette fenêtre `[now() + (threshold-0.5)d, now() + (threshold+0.5)d]` ET dont `reminders_sent` ne contient PAS ce seuil :
  - `$card->user->notify(new CardExpirationWarningNotification($card, $threshold))`
  - `$card->update(['reminders_sent' => [...$card->reminders_sent, $threshold]])`
- Retourne le count

### B.4 — Commande `cards:warn-expiration`

`app/Console/Commands/SendCardExpirationWarningsCommand.php` :

```php
protected $signature = 'cards:warn-expiration';
protected $description = 'Envoie les alertes d\'expiration imminente aux utilisateurs concernés.';
```

Wraps `SendExpirationWarningsAction`.

### B.5 — Schedule (Laravel 11+)

Dans `bootstrap/app.php`, dans le bloc `withSchedule` :

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('cards:expire')->dailyAt('03:00');
    $schedule->command('cards:warn-expiration')->dailyAt('09:00');
    // ... les autres viendront aux étapes C et D
})
```

### B.6 — Tests à écrire

`tests/Feature/Actions/Card/ExpireCardActionTest.php` :

```
- expires only active cards past their expiration date
- does not touch already expired cards
- does not touch cards with future expires_at
- logs activity on each expired card
- returns the correct count
```

`tests/Feature/Actions/Card/SendExpirationWarningsActionTest.php` :

```
- sends warning for cards in the threshold window (30 days)
- sends multiple warnings as different thresholds are crossed (30 then 7)
- does NOT send the same threshold twice (idempotency via reminders_sent)
- does not send for already expired cards
- does not send if expiration_warning_days is empty
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~206)                     ║
### ║  2. Sortie de `php artisan schedule:list`                 ║
### ║  3. Démo tinker :                                         ║
### ║     - Crée une carte qui expire demain                    ║
### ║     - Lance `cards:expire` → carte reste Active            ║
### ║     - Recule expires_at à hier en DB                      ║
### ║     - Relance `cards:expire` → carte passe Expired         ║
### ║                                                           ║
### ║  Tu attends mon GO C écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Étape C — Présences et NoShow automatiques

### C.1 — Étendre `BookingSettings`

Ajouter dans `app/Settings/BookingSettings.php` :

```php
public int $auto_mark_noshow_after_days = 7;
```

Migration `database/settings/{date}_add_auto_mark_noshow_to_booking_settings.php` :

```php
$this->migrator->add('booking.auto_mark_noshow_after_days', 7);
```

**Page Filament** : ajouter le champ correspondant dans `ManageBookingSettings` (section "Présences et clôture automatique"), TextInput numeric avec helper text français.

### C.2 — UI Filament : saisie des présences

Sur `ConversationTableResource`, ajouter une **TableAction** "Saisir les présences" visible uniquement si :
- `record.status === Scheduled`
- `record.scheduled_at < now()` (la session est passée)

Au clic, ouvre une modale avec un composant Livewire `Admin\AttendanceManager` qui :
- Liste les `Registration` `Registered` de la table
- Pour chaque registration : un Toggle "Présent / Absent" (default Présent)
- Bouton "Valider les présences" → appelle `app(MarkAttendanceAction::class)->execute($table, $attendedUserIds, auth()->user())`
- Toast de succès, fermeture de la modale, refresh de la table Filament

L'action `MarkAttendanceAction` existe déjà depuis Phase 1 et fait déjà le bon travail (passe la session en `Completed`, marque les présents `Attended` et les absents `NoShow`). Pas besoin de la modifier.

### C.3 — Commande `attendance:mark-no-shows`

`app/Console/Commands/MarkNoShowsCommand.php` :

```php
protected $signature = 'attendance:mark-no-shows';
protected $description = 'Marque automatiquement no_show les registrations restantes des sessions Scheduled passées depuis plus de N jours.';
```

**Logique** :
- Récupère `BookingSettings::auto_mark_noshow_after_days` (default 7)
- `$cutoff = now()->subDays($days)`
- `ConversationTable::where('status', SessionStatus::Scheduled)->where('scheduled_at', '<', $cutoff)->get()`
- Pour chaque table :
  - Pour chaque `Registration` `Registered` : `$reg->update(['status' => RegistrationStatus::NoShow])`
  - `$table->update(['status' => SessionStatus::Completed])`
  - Activity log : "Session auto-clôturée et présences non saisies marquées NoShow"
- Affiche un récap (X sessions traitées, Y registrations marquées NoShow)

### C.4 — Schedule

Ajouter dans `bootstrap/app.php` :
```php
$schedule->command('attendance:mark-no-shows')->dailyAt('04:00');
```

### C.5 — Tests à écrire

`tests/Feature/Commands/MarkNoShowsCommandTest.php` :

```
- marks remaining Registered as NoShow for sessions older than N days
- updates table status to Completed
- ignores sessions already Completed
- ignores sessions still within the grace period (< N days)
- preserves Attended and Cancelled registrations (only Registered becomes NoShow)
- logs activity on each affected table
```

Pour l'UI (composant `AttendanceManager`) : pas de tests obligatoires si l'action sous-jacente est déjà couverte (elle l'est depuis Phase 1).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — CHECKPOINT 3                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~212)                     ║
### ║  2. Walkthrough manuel de la modale "Saisir présences"    ║
### ║  3. Démo tinker de la commande `attendance:mark-no-shows` ║
### ║                                                           ║
### ║  Tu attends mon GO D écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Étape D — Rappels de session

### D.1 — Étendre `BookingSettings`

Ajouter dans `app/Settings/BookingSettings.php` :

```php
public int $session_reminder_hours_before = 24;
```

Migration settings idoine.

**Page Filament** : ajouter le champ correspondant dans `ManageBookingSettings` (section "Rappels de session"), TextInput numeric avec suffix "h" et helper text français.

### D.2 — Migration `reminded_at` sur `registrations`

Ajouter une colonne **nullable timestamp `reminded_at`** sur la table `registrations`. Permet l'idempotence du rappel : si déjà envoyé, on ne renvoie pas.

**Important** : ajouter aussi un **index sur `reminded_at`** dans la même migration. La query D.4 filtre dessus toutes les heures, sans index ça scan la table entière à chaque cron. Dans la migration :

```php
$table->timestamp('reminded_at')->nullable()->index()->after('cancelled_at');
```

### D.3 — Notification `SessionReminderNotification`

Channels : `['mail', 'database']`, `ShouldQueue`.

**Mail** :
- Sujet : "Rappel — Votre session « {topic} » a lieu demain"
- Corps : date/heure, lieu, niveau, lien vers `/espace/inscriptions` pour annuler si besoin

### D.4 — Action `SendSessionRemindersAction`

`app/Actions/Session/SendSessionRemindersAction.php` :

```php
public function execute(): int  // nombre de rappels envoyés
```

**Logique** :
- `$hours = BookingSettings::session_reminder_hours_before`
- Fenêtre cible : `now() + ($hours - 1)h` à `now() + $hours h` (fenêtre d'1h pour ne rien rater avec un cron horaire)
- Pour chaque `Registration` Registered avec `reminded_at IS NULL` ET dont la table.scheduled_at est dans la fenêtre :
  - `$reg->user->notify(new SessionReminderNotification($reg))`
  - `$reg->update(['reminded_at' => now()])`
- Retourne le count

### D.5 — Commande `sessions:send-reminders`

```php
protected $signature = 'sessions:send-reminders';
```

Wraps l'action.

### D.6 — Schedule

```php
$schedule->command('sessions:send-reminders')->hourly();
```

### D.7 — Tests à écrire

```
- sends reminder for registration whose session is in the reminder window
- does not send if already reminded (reminded_at not null)
- does not send for Cancelled or Waitlist registrations
- does not send for sessions outside the window
- updates reminded_at after sending
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP D — CHECKPOINT 4                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~217)                     ║
### ║  2. Sortie complète de `php artisan schedule:list`        ║
### ║                                                           ║
### ║  Tu attends mon GO E écrit avant la dernière étape.       ║
### ╚══════════════════════════════════════════════════════════╝

---

## 6. Étape E — Tests d'intégration

### `tests/Feature/Integration/AdminSessionCancellationFlowTest.php`

```
1. complete session cancellation flow
   - 4 users : carte active proche expir, carte active loin expir,
     carte expirée, en waitlist
   - admin cancels via UI (test via Livewire test sur la TableAction)
     avec un texte de raison
   - all 4 receive their proper SessionCancelledNotification (Notification::fake)
     avec le bon compensation_type
   - table.status = Cancelled, cancellation_reason persistée
   - card 1 : sessions_remaining +1, expires_at +30 days
   - card 2 : sessions_remaining +1, expires_at unchanged
   - card 3 : sessions_remaining unchanged, expires_at unchanged
   - waitlist registration : Cancelled, no card change

2. session is auto-closed after grace period
   - session 8 days in the past, status Scheduled, with 2 Registered
   - run mark-no-shows command
   - table.status = Completed
   - both registrations now NoShow

3. card expiration warning + actual expiration cycle
   - card expiring in exactly 7 days, reminders_sent = []
   - run cards:warn-expiration → notification sent, reminders_sent = [7]
   - run again same day → no second notification
   - simulate 7 days passing → card expires_at < now()
   - run cards:expire → card.status = Expired

4. session reminder fires once and once only
   - registration to a session in 23h
   - run sessions:send-reminders → notification sent, reminded_at set
   - run again 5 minutes later → no second notification
```

### Cible finale

**~221 tests verts** (217 + 4 tests d'intégration).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP E — RÉCAP DE FIN DE PHASE 6                        ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Récap au format de la section 8 ci-dessous,             ║
### ║  réponse aux 8 questions de validation.                   ║
### ╚══════════════════════════════════════════════════════════╝

---

## 7. Pièges spécifiques Phase 6 — INTERDICTIONS

1. **NE PAS recréditer une séance sur une carte expirée**, et **NE PAS étendre la validité d'une carte expirée**. Décision tranchée (point 2). On consigne juste un activity log.
2. **NE PAS étendre systématiquement la validité des cartes actives** lors d'une annulation : l'extension n'a lieu QUE si la carte expire dans `post_cancellation_extension_threshold_days` jours (default 30). C'est le comportement actuel de `CancelSessionAction`, à préserver.
3. **NE PAS oublier le paramètre `string $reason`** de `CancelSessionAction`. Il est obligatoire, persistant, et apparaît dans la notification. La modale Filament doit l'exposer en Textarea required.
4. **NE PAS oublier `DB::afterCommit` pour les notifications.** Pareil que Phase 5.
5. **NE PAS faire les notifications synchrones.** `ShouldQueue` partout.
6. **NE PAS utiliser des couleurs Tailwind littérales** dans les nouvelles vues (cf. décision 8). Classes sémantiques uniquement.
7. **NE PAS oublier l'idempotence** sur les rappels (`reminded_at` pour les sessions, `reminders_sent` JSON pour les cartes). Sans ça, un cron toutes les heures spamme.
8. **NE PAS appeler `auth()->user()` dans les Actions.** Toujours passer le user en paramètre (sauf pour les commandes cron qui n'ont pas d'admin — passer `null` ou un user système et gérer le cas).
9. **NE PAS oublier que `MarkAttendanceAction` existe déjà** depuis Phase 1. Tu la wrappes dans la modale Livewire, tu ne la réécris pas.
10. **NE PAS recréer `CardSettings::expiration_warning_days` ni les clés `post_cancellation_*` de `BookingSettings`.** Elles existent déjà avec leurs pages Filament. Tu les RÉUTILISES.
11. **NE PAS oublier l'index sur la colonne `reminded_at`** (cf. D.2). Sans index, le cron horaire scanne toute la table à chaque exécution.
12. **NE PAS oublier de mettre les commandes dans `bootstrap/app.php`** (pas dans `app/Console/Kernel.php` — Laravel 11+).
13. **NE PAS sauter les STOP.**

---

## 8. Validation de fin de Phase 6

Avant de déclarer Phase 6 terminée, tu fournis un récap répondant à TOUTES ces questions :

1. Combien de tests passent au total ? (cible : ~221)
2. L'annulation d'une session par l'admin notifie-t-elle TOUS les users (Registered + Waitlist) avec le bon `compensation_type` et la raison saisie ?
3. La compensation est-elle différenciée correctement pour les 4 cas (recredit_and_extend / recredit_only / expired_no_compensation / waitlist_notice) ?
4. La commande `cards:expire` marque-t-elle uniquement les cartes effectivement expirées ?
5. Les rappels d'expiration sont-ils idempotents (pas de double envoi pour le même seuil) ?
6. La commande `attendance:mark-no-shows` traite-t-elle correctement les sessions au-delà de la grace period et préserve-t-elle les Attended/Cancelled existants ?
7. Le rappel de session est-il envoyé une seule fois par registration (`reminded_at`) ?
8. `php artisan schedule:list` montre-t-il les 4 commandes planifiées avec les bonnes fréquences ?

---

## 9. Premier prompt à donner à Claude Code (à copier dans la nouvelle conversation)

```
Lis intégralement docs/PHASE_6_BRIEF.md, CLAUDE.md, FILAMENT_5_BRIEF.md et 
les briefs PHASE_3_BRIEF.md, PHASE_4_BRIEF.md, PHASE_5_BRIEF.md avant toute 
action. 

Confirme que tu as compris :
1. L'état du projet à l'entrée de Phase 6 (notamment ce qui existe déjà 
   et ne doit PAS être recréé — en particulier CardSettings et les clés 
   post_cancellation_* de BookingSettings)
2. Les décisions tranchées (notamment : pas d'extension systématique, 
   logique de threshold conservée ; pas de recréditation ni d'extension 
   sur carte expirée ; paramètre $reason obligatoire dans CancelSessionAction)
3. La méthodologie STOP/GO (tu attends mon GO X écrit à chaque checkpoint, 
   sauter un STOP = rollback)

Lance ensuite `php artisan test` et rapporte le total de tests verts.
Indique tout point ambigu du brief avant de démarrer.
Attends mon GO A explicite avant de toucher au code.
```

---

**Fin du brief Phase 6.**
