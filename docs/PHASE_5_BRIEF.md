# Brief Phase 5 — Liste d'attente et gestion admin

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`, `PHASE_3_BRIEF.md` et `PHASE_4_BRIEF.md`. Il est la source de vérité de la Phase 5.
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **Cette phase est phasée en cinq étapes (A à E) avec des checkpoints STOP**, comme Phase 4.

---

## 0. État du projet à l'entrée de Phase 5

### Ce qui existe déjà (à NE PAS recréer)

| Élément | Localisation | État |
|--------|--------------|------|
| `Actions\Registration\PromoteFromWaitlistAction` | `app/Actions/Registration/` | **Complet et testé** (4 tests). Vérifie status waitlist, table non pleine, carte active. Décrémente carte, met à jour registration. |
| `Actions\Registration\MoveRegistrationAction` | `app/Actions/Registration/` | **Existe mais BASIQUE** (3 tests). Bloque seulement les Cancelled. **Sera enrichi en étape C.** |
| `Actions\Registration\CancelRegistrationAction` | `app/Actions/Registration/` | Complet (Phase 4). Sera modifié en étape A pour dispatcher un event. |
| `Actions\Session\CancelSessionAction` | `app/Actions/Session/` | Existe — ne pas y toucher (Phase 6). |
| `BookingSettings` | `app/Settings/` | Tous les seuils définis. **Une nouvelle propriété sera ajoutée en étape A.** |
| Modale "Inscrits" Filament | `views/filament/modals/registrations-list.blade.php` | Read-only (Phase 4). **Sera rendue actionnable en étape D.** |
| Notifications email/database | aucune côté registration | À créer en étape B |

### Ce qui MANQUE (à créer dans cette phase)

| Élément | Étape |
|--------|-------|
| Event `RegistrationCancelled` | A |
| Listener `AutoPromoteFromWaitlist` | A |
| Setting `BookingSettings::auto_promote_from_waitlist` (bool, default true) | A |
| Notification `UserPromotedFromWaitlistNotification` | B |
| Notification `RegistrationCancelledByAdminNotification` | B |
| Enrichissements `MoveRegistrationAction` (vérifs métier) | C |
| Actions Filament dans la modale Inscrits (Promouvoir, Déplacer, Annuler) | D |
| Tests d'intégration end-to-end | E |

### Action requise au démarrage

Tu fais un `php artisan test` pour confirmer **156/156**. Si un test échoue, tu rapportes et tu attends mes instructions avant de toucher à Phase 5. **Tu rapportes le total avant de démarrer l'étape A.**

---

## 1. Décisions tranchées (à appliquer sans discussion)

1. **Promotion automatique FIFO activée par défaut**, configurable via `BookingSettings::auto_promote_from_waitlist` (bool, default `true`). Si désactivée → seule la promotion manuelle admin fonctionne.

2. **Si la première personne en waitlist n'a pas de carte active au moment de la promotion auto** → on ne saute PAS au suivant. On laisse la place libre. L'admin peut alors décider manuellement de promouvoir un autre user via la modale Filament.
   - Justification : respect strict de l'ordre FIFO, pas de "qui a le plus de chance" qui dépendrait des cartes. Plus prévisible et plus juste.

3. **L'auto-promotion s'exécute synchronement dans la même transaction que l'annulation.** Pas de queue/job. Raison : on veut que l'utilisateur qui annule voie tout de suite sur la page que la place a été reprise (refresh) ; on veut aussi que les notifs soient envoyées immédiatement.
   - Si une exception lors de la promotion → l'annulation est quand même committée, l'erreur est loggée. La promotion reste possible manuellement.

4. **Notifications obligatoires en Phase 5 :**
   - User promu (auto ou manuel) → email + database notification
   - User dont la registration est annulée par un admin → email + database notification
   - L'admin n'est PAS notifié à chaque promotion auto (volume trop élevé). Une activité est loggée via Spatie ActivityLog.

5. **Déplacement d'une registration** par admin : autorisé même si la nouvelle table est d'un niveau différent (l'admin est responsable). Bloqué si nouvelle table pleine, annulée, complétée, ou si l'user est déjà inscrit sur la nouvelle table.

6. **L'admin peut annuler la registration d'un user après la deadline** — c'est déjà géré dans `CancelRegistrationAction` Phase 4. En Phase 5, on connecte juste l'UI Filament pour qu'il puisse le faire en un clic.

---

## 2. Étape A — Auto-promotion event-driven

### Objectif

Quand une registration `Registered` est annulée, si la table était pleine et qu'il y a une waitlist, la première personne en waitlist (avec carte active) est automatiquement promue.

### Sous-étape A.1 — Setting

Ajouter à `app/Settings/BookingSettings.php` :

```php
public bool $auto_promote_from_waitlist = true;
```

Migration `database/settings/{date}_add_auto_promote_to_booking_settings.php` :

```php
$this->migrator->add('booking.auto_promote_from_waitlist', true);
```

### Sous-étape A.2 — Event

`app/Events/RegistrationCancelled.php` :

```php
namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RegistrationCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Registration $registration,
        public readonly bool $cancelledByAdmin,
    ) {}
}
```

### Sous-étape A.3 — Listener

`app/Listeners/AutoPromoteFromWaitlist.php` :

```php
namespace App\Listeners;

use App\Actions\Registration\PromoteFromWaitlistAction;
use App\Enums\RegistrationStatus;
use App\Events\RegistrationCancelled;
use App\Settings\BookingSettings;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AutoPromoteFromWaitlist
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly PromoteFromWaitlistAction $promote,
    ) {}

    public function handle(RegistrationCancelled $event): void
    {
        if (! $this->settings->auto_promote_from_waitlist) {
            return;
        }

        // Ne traite que les annulations de Registered (pas Waitlist : pas de place libérée)
        // Note : l'event est dispatché AVANT le change de status, sinon on perd l'info.
        // → cf. modification de CancelRegistrationAction ci-dessous.

        $table = $event->registration->conversationTable;

        $nextInLine = $table->registrations()
            ->where('status', RegistrationStatus::Waitlist->value)
            ->orderBy('waitlist_position')
            ->first();

        if (! $nextInLine) {
            return;
        }

        try {
            // Le second arg "admin" : on utilise un user système (id 1 par convention) ou
            // on injecte $event->registration->cancelled_by si null on prend l'admin.
            // Plus simple : on passe le user qui a déclenché l'annulation comme causer
            // de l'activité. Voir signature à adapter dans PromoteFromWaitlistAction
            // si besoin.
            $admin = $event->registration->cancelledBy ?? \App\Models\User::role('admin')->first();
            $this->promote->execute($nextInLine, $admin);
        } catch (RuntimeException $e) {
            // Pas d'admin ? Pas de carte active sur le 1er en waitlist ? On log et on laisse passer.
            Log::warning('AutoPromote skipped', [
                'registration_id' => $nextInLine->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
```

Enregistrement du listener : Laravel 11+ utilise l'auto-discovery par défaut sur `app/Listeners`. Pas besoin de `EventServiceProvider`. Vérifie que ça fonctionne — sinon enregistre manuellement.

### Sous-étape A.4 — Modifier `CancelRegistrationAction` pour dispatcher l'event

L'event doit être dispatché APRÈS le commit de la transaction (sinon le listener ne voit pas la place libérée). Utiliser `DB::afterCommit()` :

```php
return DB::transaction(function () use ($registration, $cancelledBy) {
    $wasRegistered = $registration->status === RegistrationStatus::Registered;
    
    $registration->update([
        'status' => RegistrationStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_by' => $cancelledBy->id,
    ]);

    // ... recréditation carte ...
    // ... activity log ...

    DB::afterCommit(function () use ($registration, $cancelledBy, $wasRegistered) {
        if ($wasRegistered) {
            event(new RegistrationCancelled(
                $registration->fresh(),
                $cancelledBy->hasRole('admin')
            ));
        }
    });

    return $registration->fresh();
});
```

**Important** :
- L'event n'est dispatché QUE si la registration était `Registered` avant l'annulation. Une annulation de waitlist ne libère pas de place et ne doit pas déclencher de promotion.
- Le `cancelledByAdmin` permettra à l'étape B de décider quelle notification envoyer.

### Tests à écrire

`tests/Feature/Listeners/AutoPromoteFromWaitlistTest.php` :

```
- promotes first waitlisted user when registered cancellation frees a spot
  (table de capacité 1 + 1 registered + 1 waitlist → annulation → waitlisted devient Registered)
- does nothing when auto_promote_from_waitlist setting is false
- does nothing when cancelled registration was already on waitlist
  (annulation de waitlist ne libère pas de place)
- skips silently when first waitlisted user has no active card
  (la place reste libre, no exception)
- promotes only the first in waitlist, leaves others unchanged
- decrements card sessions_remaining of the promoted user
- does not promote if table is now somehow full again (defensive check)
```

`tests/Feature/Actions/CancelRegistrationActionTest.php` à compléter :

```
- dispatches RegistrationCancelled event when cancelling a Registered
- does NOT dispatch when cancelling a Waitlist registration
- event is dispatched only after DB commit (use Event::fake then assertDispatched)
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : 156 + ~10 nouveaux ≈ 166) ║
### ║  2. La modification exacte de                             ║
### ║     CancelRegistrationAction (diff)                       ║
### ║  3. Démo tinker : crée table cap 1, register user A,      ║
### ║     waitlist user B, annule user A, vérifie que user B    ║
### ║     est passé en Registered avec card_id renseigné        ║
### ║                                                           ║
### ║  Tu attends mon "GO B" écrit avant de continuer.          ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape B — Notifications

### Sous-étape B.1 — `UserPromotedFromWaitlistNotification`

`app/Notifications/UserPromotedFromWaitlistNotification.php` :

- Channels : `['mail', 'database']`
- Mail :
  - Sujet : "Votre inscription est confirmée !"
  - Corps : "Bonne nouvelle, une place s'est libérée pour la session « {topic} » du {date} à {heure}. Votre inscription est confirmée."
  - CTA : lien vers `/espace/inscriptions`
- Database :
  - Stocke registration_id, table_id, scheduled_at

### Sous-étape B.2 — `RegistrationCancelledByAdminNotification`

`app/Notifications/RegistrationCancelledByAdminNotification.php` :

- Channels : `['mail', 'database']`
- Mail :
  - Sujet : "Votre inscription a été annulée"
  - Corps : "Bonjour, votre inscription à la session « {topic} » du {date} à {heure} a été annulée par notre équipe. Si vous aviez utilisé une séance de votre carte, elle vous a été recréditée."
  - Pas de CTA spécifique

### Sous-étape B.3 — Câblage

**Dans `PromoteFromWaitlistAction`** (à la fin de la transaction, dans `DB::afterCommit`) :

```php
$registration->user->notify(new UserPromotedFromWaitlistNotification($registration));
```

**Dans `CancelRegistrationAction`** (à la fin de la transaction, dans `DB::afterCommit`, en plus du dispatch d'event) :

```php
if ($cancelledBy->hasRole('admin') && $cancelledBy->id !== $registration->user_id) {
    $registration->user->notify(new RegistrationCancelledByAdminNotification($registration));
}
```

**Note importante** : on ne notifie PAS le user si c'est lui qui a annulé sa propre inscription (cas standard). Et un admin qui annule sa propre inscription est traité comme un user normal.

### Tests à écrire

```
- promoted user receives UserPromotedFromWaitlistNotification (mail + database)
- promoted user does NOT receive notification when promote action throws
- user receives RegistrationCancelledByAdminNotification when admin cancels
- user does NOT receive RegistrationCancelledByAdminNotification when self-cancelling
- admin cancelling own registration → no admin notification (treated as self-cancel)
```

Utilise `Notification::fake()` + `Notification::assertSentTo()` / `assertNothingSent()`.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~171)                     ║
### ║  2. Code complet des deux Notifications                   ║
### ║  3. Démo tinker : reproduis le scénario du STOP A et      ║
### ║     vérifie via Notification::fake() (ou via              ║
### ║     queue:work + un email réel sur Mailpit/log) que       ║
### ║     l'email est bien envoyé au user promu                 ║
### ║                                                           ║
### ║  Tu attends mon "GO C" écrit avant de continuer.          ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Étape C — Enrichir `MoveRegistrationAction`

L'action actuelle est trop permissive. Elle bloque uniquement les Cancelled. En Phase 5 on ajoute les vérifications métier nécessaires pour qu'un admin ne puisse pas se tirer une balle dans le pied (ou casser l'intégrité des données).

### Vérifications à ajouter

Dans cet ordre :

1. **Registration.status ∈ {Registered, Waitlist}** (déjà fait, on garde)
2. **Nouvelle table.status === Scheduled** → sinon throw `'target_table_not_scheduled'`
3. **Nouvelle table.scheduled_at > now()** → sinon throw `'target_table_in_past'`
4. **User pas déjà inscrit sur la nouvelle table** (status Registered ou Waitlist) → sinon throw `'user_already_on_target_table'`
5. **Si registration.status === Registered ET nouvelle table.isFull()** → throw `'target_table_full'`
   - Si registration en Waitlist, table pleine OK (on continue à être en waitlist sur la nouvelle table)
6. **Si registration en Waitlist** → recalculer `waitlist_position` sur la nouvelle table (max + 1 sur la nouvelle, position libérée sur l'ancienne avec décalage des suivants)

**Niveau** : on N'IMPOSE PAS la correspondance level user/table. Décision tranchée (cf. point 5 décisions). L'admin est responsable.

### Repositionnement de la waitlist

C'est subtil. Si on déplace la registration #5 (position 3 sur table A) vers table B :
- Sur table A : positions 4, 5, 6 doivent décaler à 3, 4, 5
- Sur table B : la registration prend la position max(B) + 1

Code esquissé :

```php
DB::transaction(function () use ($registration, $newTable, $admin) {
    $oldTable = $registration->conversationTable;
    $oldPosition = $registration->waitlist_position;
    $isWaitlist = $registration->status === RegistrationStatus::Waitlist;

    $newPosition = null;
    if ($isWaitlist) {
        $newPosition = ($newTable->registrations()
            ->where('status', RegistrationStatus::Waitlist->value)
            ->max('waitlist_position') ?? 0) + 1;
    }

    $registration->update([
        'conversation_table_id' => $newTable->id,
        'waitlist_position' => $newPosition,
    ]);

    if ($isWaitlist && $oldPosition !== null) {
        // Décalage des positions suivantes sur l'ancienne table
        $oldTable->registrations()
            ->where('status', RegistrationStatus::Waitlist->value)
            ->where('waitlist_position', '>', $oldPosition)
            ->decrement('waitlist_position');
    }

    activity()->performedOn($registration)->causedBy($admin)
        ->withProperties(['from_table' => $oldTable->id, 'to_table' => $newTable->id])
        ->log("Inscription déplacée de #{$oldTable->id} vers #{$newTable->id}");

    return $registration->fresh();
});
```

### Tests à compléter dans `MoveRegistrationActionTest`

```
- throws target_table_not_scheduled when new table is Cancelled
- throws target_table_in_past when new table is in the past
- throws user_already_on_target_table if user has Registered on target
- throws user_already_on_target_table if user has Waitlist on target
- throws target_table_full when moving Registered to a full table
- DOES NOT throw target_table_full when moving Waitlist to a full table
- recomputes waitlist_position on target table for waitlist moves
- decrements positions of remaining waitlisters on source table
- allows moving across different levels (admin discretion)
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — CHECKPOINT 3                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~180)                     ║
### ║  2. Code complet de MoveRegistrationAction (diff)         ║
### ║  3. Démo tinker du décalage de waitlist :                 ║
### ║     - Table A avec 3 waitlisters (positions 1, 2, 3)      ║
### ║     - Déplace celui en position 2 vers table B            ║
### ║     - Vérifie qu'on a maintenant A: pos 1 et 2 (l'ancien  ║
### ║       3 a décalé), B: pos 1                               ║
### ║                                                           ║
### ║  Tu attends mon "GO D" écrit avant de continuer.          ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Étape D — Modale Filament "Inscrits" actionnable

La modale existe déjà depuis Phase 4 mais en lecture seule. On la rend actionnable.

### Architecture recommandée

Au lieu d'une simple vue Blade dans une modale, **transformer en composant Livewire** : `app/Livewire/Admin/RegistrationsManager.php`. Plus simple pour gérer les actions et le refresh.

Alternative acceptable : utiliser les **Actions Filament** sur la modale existante via les TableAction et ActionGroup. Mais c'est plus contraignant pour le rendu d'une liste à actions multiples par ligne.

**Choix recommandé** : composant Livewire chargé dans la modale. Reste cohérent avec `RegisterButton` Phase 4.

### Fonctionnalités UI

Pour chaque registration affichée dans la liste :

1. **Bouton "Promouvoir"** (visible uniquement si status = Waitlist ET table pas pleine)
   - Confirmation : "Promouvoir {user.full_name} de la liste d'attente ?"
   - Au clic : `app(PromoteFromWaitlistAction::class)->execute($registration, auth()->user())`
   - Refresh de la liste après succès
   - Notification flash de succès ou d'erreur

2. **Bouton "Déplacer"** (visible si status ∈ {Registered, Waitlist})
   - Ouvre une modale secondaire avec un Select des autres tables Scheduled futures
   - Au confirmation : `app(MoveRegistrationAction::class)->execute($registration, $newTable, auth()->user())`
   - Idem refresh + flash

3. **Bouton "Annuler"** (visible si status ∈ {Registered, Waitlist})
   - Confirmation : "Annuler l'inscription de {user.full_name} ? L'utilisateur sera notifié par email et sa séance recréditée si applicable."
   - Au clic : `app(CancelRegistrationAction::class)->execute($registration, auth()->user())`
   - Idem refresh + flash

### Polish

- Afficher la position en waitlist si applicable
- Couleur de fond différente pour Registered (vert pâle) vs Waitlist (orange pâle)
- Compteur en en-tête : "5 inscrits / 8 places — 2 en attente"
- Si table pleine : bouton "Promouvoir" désactivé avec tooltip "Table complète"

### Tests Livewire

Pour gagner du temps, on se contente de tests d'intégration (cf. étape E) plutôt que des tests Livewire dédiés. **Aucun test obligatoire pour cette étape.**

### Important

Le composant doit appeler les Actions, JAMAIS dupliquer leur logique. Si tu te retrouves à écrire `$card->decrement('sessions_remaining')` dans le composant, tu fais fausse route.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP D — CHECKPOINT 4                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (toujours cible ~180)              ║
### ║  2. Walkthrough de l'admin :                              ║
### ║     - Capture ou description de la modale                 ║
### ║     - Confirmation que les 3 actions fonctionnent         ║
### ║       depuis l'interface (test manuel)                    ║
### ║                                                           ║
### ║  Tu attends mon "GO E" écrit avant la dernière étape.     ║
### ╚══════════════════════════════════════════════════════════╝

---

## 6. Étape E — Tests d'intégration de bout en bout

### Objectif

Valider les enchaînements complets en simulant le comportement réel, pas juste les actions isolées.

### Fichier `tests/Feature/Integration/WaitlistFlowTest.php`

Tests minimums :

```
1. complete waitlist auto-promotion flow
   - Setup : table capacité 2, user A Registered, user B Registered, user C Waitlist
   - Action : user A annule
   - Assertions :
     * registration A : status Cancelled, cancelled_at set
     * carte A : sessions_remaining +1
     * registration C : status Registered, card_id renseigné, waitlist_position null
     * carte C : sessions_remaining -1
     * Notification UserPromotedFromWaitlistNotification envoyée à C
     * Pas de notif à A (self cancel)

2. cancellation by admin notifies user but not auto-promote when no waitlist
   - Setup : table capacité 1, user A Registered, pas de waitlist
   - Action : admin annule
   - Assertions :
     * registration A : Cancelled
     * Notification RegistrationCancelledByAdminNotification envoyée à A
     * Aucune autre registration créée

3. waitlist auto-promotion is skipped when first waitlister has no active card
   - Setup : table cap 1, user A Registered, user B Waitlist (sans carte active)
   - Action : user A annule
   - Assertions :
     * registration A : Cancelled
     * registration B : reste en Waitlist
     * place reste libre (table.registrations Registered count = 0)
     * Aucune notif de promotion envoyée

4. moving a waitlist registration to a new table preserves FIFO order
   - Setup : table A waitlist [B(1), C(2), D(3)], table B vide
   - Action : admin déplace C de A vers B
   - Assertions :
     * Table A waitlist : B(1), D(2)  ← D a remonté
     * Table B waitlist : C(1)
     * Pas de notification

5. admin cancellation triggers auto-promotion AND notifies cancelled user
   - Setup : table cap 1, user A Registered, user B Waitlist (avec carte)
   - Action : admin annule A
   - Assertions :
     * A : Cancelled, notif RegistrationCancelledByAdminNotification reçue
     * B : Promoted, notif UserPromotedFromWaitlistNotification reçue
     * Total : 2 notifs distinctes envoyées
```

### Cible finale

**~185 tests verts** (180 + 5 tests d'intégration).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP E — RÉCAP DE FIN DE PHASE 5                        ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Récap au format de la section 9 ci-dessous,             ║
### ║  réponse aux 7 questions de validation.                   ║
### ╚══════════════════════════════════════════════════════════╝

---

## 7. Pièges spécifiques Phase 5 — INTERDICTIONS

1. **NE PAS dispatcher l'event `RegistrationCancelled` AVANT le commit DB.** Le listener verrait la registration encore Registered, la promotion échouerait avec `table_still_full`. Utiliser `DB::afterCommit()`.

2. **NE PAS dispatcher l'event quand on annule une Waitlist.** Pas de place libérée, pas de promotion à déclencher. Le flag `wasRegistered` doit être capturé AVANT le `update()`.

3. **NE PAS écrire la logique de promotion dans le Listener.** Le Listener appelle `PromoteFromWaitlistAction`. Aucune query/update direct dans le Listener.

4. **NE PAS faire de "skip and try next" dans l'auto-promotion** si le 1er en waitlist n'a pas de carte. Décision tranchée (cf. décisions point 2). On laisse la place libre, l'admin arbitre.

5. **NE PAS notifier le user qui annule sa propre inscription.** Vérifier `$cancelledBy->id !== $registration->user_id` ET `$cancelledBy->hasRole('admin')`.

6. **NE PAS oublier le décalage des positions waitlist** quand on déplace une registration depuis une waitlist. Sinon la séquence devient 1, 3, 4 (avec un trou en 2).

7. **NE PAS dupliquer la logique des Actions dans le composant Livewire admin.** Le composant orchestre, les Actions exécutent.

8. **NE PAS oublier d'enregistrer le Listener.** Laravel 11 fait l'auto-discovery sur `app/Listeners/`, mais vérifier dans `bootstrap/app.php` ou via `php artisan event:list` que le binding est bien en place.

9. **NE PAS appeler `auth()->user()` dans une Action.** Toujours passer le user en paramètre. C'est le rôle du composant ou du controller de fournir le user.

10. **NE PAS sauter les STOP.** Si tu sautes, rollback du travail post-STOP comme convenu.

---

## 8. Récapitulatif des tests à écrire

| Fichier | Nouveaux tests |
|--------|----------------|
| `AutoPromoteFromWaitlistTest` | 7 |
| `CancelRegistrationActionTest` (compléter) | +3 (event dispatch) |
| `Notifications/UserPromotedFromWaitlistTest` ou inclus dans Listeners | 2 |
| `Notifications/RegistrationCancelledByAdminTest` ou inclus | 3 |
| `MoveRegistrationActionTest` (compléter) | +9 |
| `Integration/WaitlistFlowTest` | 5 |

**Total Phase 5 : ~29 nouveaux tests, cible ~185 tests verts.**

---

## 9. Validation de fin de Phase 5

Avant de déclarer Phase 5 terminée, tu fournis un récap qui répond à TOUTES ces questions :

1. Combien de tests passent au total ? (cible : ~185)
2. L'auto-promotion FIFO fonctionne-t-elle bout en bout (test d'intégration vert) ?
3. Le setting `auto_promote_from_waitlist = false` désactive-t-il bien le mécanisme ?
4. La promotion auto saute-t-elle silencieusement quand le 1er en waitlist n'a pas de carte (sans avancer dans la liste) ?
5. Le user promu reçoit-il l'email de notification (testé via `Notification::fake()`) ?
6. L'admin qui annule la registration d'un user déclenche-t-il l'envoi de l'email d'annulation ?
7. Le déplacement d'une waitlist préserve-t-il la séquence FIFO sur la table source ?

---

**Fin du brief Phase 5.**
