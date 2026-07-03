# PHASE 9.6 — Multi-membres d'une même société

**Type** : Phase fonctionnelle complète (pas un patch).
**Durée estimée** : 6 à 8 heures de dev.
**Prérequis** : Phases 1 à 9.5 livrées (475 tests Pest verts), Patch 8.1 appliqué.
**Phase suivante** : Phase 10 — Déploiement et runbooks (inchangée).

---

## Contexte et objectifs

### Pourquoi cette phase

Le système actuel suppose qu'une `Company` est créée à l'inscription par **un seul** employé. Si un second employé de la même société tente de s'inscrire, deux scénarios échouent :

1. **Au checkout** : un `User` sans `company_id` (admin TableConvo, ou compte créé via Filament sans rattachement) tombe sur `HttpException 422` levée par `CreateOrderFromCartAction::execute()` ligne 24 (`abort_unless($user->company, ...)`). L'erreur est affichée brute, sans redirection ni message exploitable.

2. **À l'inscription** : si un employé B tape la TVA de la société déjà enregistrée par A, le `RegisteredUserController` rejette via anti-hijacking *« Une société est déjà enregistrée avec ce numéro de TVA. Contactez un administrateur. »* — friction max, aucun chemin self-service.

### Ce que livre cette phase

Une vraie gestion **multi-membres** d'une même `Company`, avec deux mécanismes complémentaires :

- **C1 — Auto-trust par domaine email** : si l'email du nouveau membre est sur le même domaine pro que celui enregistré pour la company, rattachement immédiat avec simple notification au `company_admin`.
- **C3 — Demande d'adhésion** : si pas de match domaine (email perso type gmail, ou employé d'un sous-traitant), le user soumet une demande explicite que le `company_admin` approuve ou rejette depuis son espace membre.

Plus :

- Garde-fou middleware sur `/panier` et `/panier/checkout` (fix du bug initial).
- Pré-remplissage des données société depuis VIES lors de la création.
- Nouveau rôle Spatie `company_admin` distinct du `admin` TableConvo.
- Succession automatique du `company_admin` (transfert au plus ancien membre actif).
- **Souveraineté super admin** : le super admin TableConvo peut **à tout moment** réassigner le `company_admin` d'une society depuis Filament.
- Masquage des CTA d'achat (panier/tarifs/cartes/inscriptions) pour les admins TableConvo dans la navigation publique.

### Hors-scope explicite

- Intégration API/dump BCE/KBO : pas dans cette phase. On utilise uniquement VIES (déjà branché). Si la couverture VIES s'avère trop limitée à l'usage, on backlogue une future phase pour intégrer le dump CSV Open Data BCE.
- Migration de Phase 10 (deployment) : reste séparée et postérieure.

---

## Conventions et rappels (à respecter sans exception)

- **Logique métier dans des Actions** (`app/Actions/`), un dossier par bounded context.
- **`ShouldQueue` obligatoire** sur toutes les nouvelles notifications.
- **Soft deletes partout**, pas de suppression dure (incluant `CompanyJoinRequest`).
- **Activity log** (Spatie ActivityLog) sur toutes les opérations sensibles : création company, demande d'adhésion, approbation/rejet, transfert company_admin, réassignation super admin.
- **Event dispatch via `DB::afterCommit`** quand un événement déclenche une notification depuis une transaction.
- **Classes Tailwind sémantiques** (`bg-primary`, `text-accent`, `bg-accent/10`) ; pas de couleurs littérales (`bg-blue-500`) sauf le flash success `emerald-*` déjà toléré ailleurs.
- **Theming hors panel Filament** : les vues publiques et l'espace membre sont themable ; le panel admin Filament ne l'est pas.
- **Defense in depth** : on conserve tous les `abort_unless` / `abort_if` existants dans les Actions. On n'y touche pas.
- **Test Pest** pour chaque Action, chaque controller, chaque notification, chaque policy ajustée.
- **Branche dédiée** : `feature/phase-9.6-multi-members`. Pas de merge automatique vers `main`.
- **Langue de travail** : français pour communications et libellés UI ; anglais pour code/noms de classes/méthodes (convention Laravel).
- **Cible de tests** : passer de 475 à environ 540 tests verts (~+65 nouveaux tests). Le compteur exact est indicatif, ce qui compte c'est zéro régression.

---

## Étape A — Fondations (modèles, migrations, services socles)

### A.1 — Migration : enrichissement de `companies`

Ajouter à la table `companies` :

```php
$table->string('email_domain')->nullable()->index()->after('billing_email');
```

**Sémantique** : domaine pro réservé pour l'auto-trust C1. `null` si la company a été créée avec un email perso (gmail, hotmail, etc. — voir blocklist § A.4).

### A.2 — Migration et modèle `CompanyJoinRequest`

Table `company_join_requests` :

```php
$table->id();
$table->foreignId('company_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->index();
$table->text('message')->nullable();
$table->timestamp('requested_at');
$table->timestamp('resolved_at')->nullable();
$table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
$table->text('rejection_reason')->nullable();
$table->softDeletes();
$table->timestamps();
$table->unique(['user_id', 'company_id', 'status'], 'company_join_requests_unique_pending')
      ->where('status', 'pending'); // ou contrainte applicative via Pest test si dialecte ne supporte pas
```

**Note SQLite** : la contrainte unique partielle peut ne pas être supportée selon la version. Si conflit dialect MySQL ↔ SQLite (env tests), garder la contrainte côté MySQL via raw SQL dans la migration et reproduire la garantie via un `static::saving()` hook côté modèle. Cf. audit Phase 9.5 (item searchable sur OrderResource — même pattern de divergence à gérer).

Modèle `App\Models\CompanyJoinRequest` :

- `HasFactory`, `SoftDeletes`, `LogsActivity` (Spatie).
- Casts `requested_at` et `resolved_at` en datetime, `status` en enum PHP backed `App\Enums\CompanyJoinRequestStatus`.
- Relations : `company()`, `user()`, `resolvedBy()`.
- Scopes : `pending()`, `resolved()`, `forCompany($companyId)`.

Enum `App\Enums\CompanyJoinRequestStatus` :

```php
enum CompanyJoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
```

### A.3 — Rôle Spatie `company_admin`

**Ce n'est pas un rôle global**. C'est un rôle qu'un user porte **dans le contexte d'une company**. Or Spatie Permission gère des rôles globaux par défaut. Deux options :

- **Option simple (retenue)** : créer un rôle global `company_admin` que Spatie distribue, mais l'autorisation effective vérifie en plus `$user->company_id === $targetCompany->id` au niveau policy. Le rôle global signifie *« peut administrer SA company »* — pas n'importe laquelle.

- **Option complexe** (Spatie teams) : non retenue ici, ajoute trop de surface pour un gain marginal sur ce périmètre.

Donc : seeder Spatie ajouté pour le rôle `company_admin` (guard `web`).

**Mise à jour `RolePermissionSeeder`** ou équivalent : ajouter le rôle `company_admin` à côté des rôles existants (`admin` TableConvo, `member`).

### A.4 — Service `EmailDomainService`

`app/Services/EmailDomain/EmailDomainService.php`, méthodes :

```php
public function extract(string $email): ?string
// 'arnaud@acme-sa.be' → 'acme-sa.be'
// retour null si format invalide

public function isGenericProvider(string $domain): bool
// gmail.com / hotmail.com / outlook.com / yahoo.com / yahoo.fr / proton.me /
// protonmail.com / icloud.com / me.com / mac.com / live.com / live.be /
// hotmail.be / hotmail.fr / orange.fr / wanadoo.fr / laposte.net / skynet.be /
// telenet.be / voo.be / scarlet.be / belgacom.net
// (liste à compléter dans le service via une constante privée)

public function isAcceptableCompanyDomain(string $email): bool
// extract() + ! isGenericProvider()

public function findCompanyByDomain(string $domain): ?Company
// Company::where('email_domain', strtolower($domain))->first()
```

La blocklist est une constante privée du service. Pas un setting admin pour cette phase (peut le devenir plus tard).

### A.5 — Enrichissement de `VatValidationService`

Le service `VatValidationService` existe déjà. Il faut **ajouter** (pas remplacer) une méthode qui retourne les données VIES enrichies :

```php
public function lookup(string $vatNumber): ?VatLookupResult
// Retourne null si VIES KO, sinon un DTO VatLookupResult :
//   - name: string (raison sociale telle que VIES la connaît, "---" si non communiquée)
//   - address: string (adresse formatée brute, "---" si non communiquée)
//   - vatNumber: string (normalisé)
//   - validatedAt: Carbon
```

DTO `App\Services\Vat\VatLookupResult` (readonly class PHP 8.4).

**Important** : VIES retourne parfois `name === '---'` pour les entreprises qui refusent la publication. Le DTO le reflète tel quel ; la couche UI affichera "(non communiqué)" si la valeur est `---`.

Ne pas casser les méthodes existantes (`normalize`, `isFormatValid`, `validate`). Elles restent identiques. La méthode `validate()` peut être refactorisée pour réutiliser `lookup()` en interne, mais sa signature publique ne change pas.

### A.6 — Adaptation du modèle `User`

Ajouter au modèle `User` :

```php
public function isCompanyAdmin(?Company $company = null): bool
{
    if (! $this->hasRole('company_admin')) {
        return false;
    }
    // Si une company cible est précisée, on vérifie que c'est la sienne.
    $company ??= $this->company;
    return $company && $this->company_id === $company->id;
}

public function companyJoinRequests()
{
    return $this->hasMany(CompanyJoinRequest::class);
}
```

Et au modèle `Company` :

```php
public function members()
{
    return $this->hasMany(User::class);
}

public function admins()
{
    return $this->members()->role('company_admin');
}

public function joinRequests()
{
    return $this->hasMany(CompanyJoinRequest::class);
}

public function pendingJoinRequests()
{
    return $this->joinRequests()->where('status', 'pending');
}
```

### A.7 — Tests Étape A

`tests/Unit/Services/EmailDomainServiceTest.php` :

- extract retourne bon domaine pour email valide
- extract retourne null pour format invalide
- isGenericProvider true pour gmail/hotmail/etc.
- isGenericProvider false pour acme-sa.be
- isAcceptableCompanyDomain combine les deux
- findCompanyByDomain retourne la company si match, null sinon
- findCompanyByDomain est case-insensitive

`tests/Unit/Services/VatLookupTest.php` :

- lookup retourne null si VIES KO (mock du HTTP client)
- lookup retourne un VatLookupResult populé si VIES OK
- lookup gère le cas `name === '---'`

`tests/Unit/Models/CompanyJoinRequestTest.php` :

- factory crée un request en status pending
- scope pending filtre correctement
- relations company/user/resolvedBy fonctionnent
- soft delete fonctionne

`tests/Feature/Models/UserCompanyAdminTest.php` :

- isCompanyAdmin() false si pas de rôle
- isCompanyAdmin() true si rôle et same company
- isCompanyAdmin($otherCompany) false même si rôle (souveraineté contextuelle)

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Rapporte :                                               ║
### ║  1. Total tests verts (cible ~490, +15 nouveaux)         ║
### ║  2. Schema dump : nouvelle table company_join_requests   ║
### ║     + nouvelle colonne email_domain sur companies         ║
### ║  3. Confirmation que le rôle company_admin est            ║
### ║     bien créé (php artisan tinker → Role::all()->pluck)  ║
### ║                                                           ║
### ║  Attends mon GO B écrit avant de continuer.               ║
### ╚══════════════════════════════════════════════════════════╝

---

## Étape B — Logique métier (Actions, Notifications, Middleware)

### B.1 — Middleware `EnsureUserHasCompany`

`app/Http/Middleware/EnsureUserHasCompany.php` :

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    // Admin TableConvo : redirigé vers le panel admin
    if ($user && $user->hasRole('admin')) {
        return redirect()->route('filament.admin.pages.dashboard')
            ->with('status', 'admin_no_purchase');
    }

    if ($user && ! $user->company) {
        return redirect()->route('espace.profil')
            ->with('status', 'company_missing');
    }

    return $next($request);
}
```

Alias `ensure.company` dans `bootstrap/app.php`. Application sur `/panier` (GET et POST) **avant** le throttle.

**Vérifier le nom exact** de la route dashboard Filament via `php artisan route:list | grep filament.admin` avant de coder en dur. Adapter si la route s'appelle différemment dans ce projet.

### B.2 — Actions métier

Toutes dans `app/Actions/Company/`.

**`CreateCompanyFromMemberSpaceAction`** — création self-service d'une society par un user sans company (typiquement après inscription comme admin Filament invité, ou cas edge) :

- Garde-fou : `abort_if($user->hasRole('admin'), 403)` + `abort_if($user->company, 409)`.
- Validation VIES via `VatValidationService::lookup()`.
- Anti-hijacking : si `Company::where('vat_number', ...)->exists()`, **on ne crée pas — on lance l'action `RequestCompanyJoinAction` à la place** ou on retourne un signal `company_exists` au controller pour qu'il redirige vers le flow "rejoindre".
- Calcul du `email_domain` via `EmailDomainService::isAcceptableCompanyDomain($user->email)` ; si oui, store ; sinon null.
- `DB::transaction` : création Company + update `user->company_id` + assignation du rôle `company_admin` au user.
- Activity log : `'Société créée depuis espace membre'` avec context `member_space`.
- Retour : la `Company` créée.

**`RequestCompanyJoinAction`** — un user soumet une demande d'adhésion à une company existante (par TVA) :

- Garde-fous : `abort_if($user->hasRole('admin'))`, `abort_if($user->company)`, `abort_if(! $company)`.
- Vérifier qu'aucune demande pending n'existe déjà pour ce user sur cette company (sinon `abort 409 'already_requested'`).
- Création du `CompanyJoinRequest` (status pending, requested_at = now).
- Notification au(x) `company_admin` de la company (`CompanyJoinRequestedNotification`, dispatch via `DB::afterCommit`).
- Activity log.
- Retour : le `CompanyJoinRequest`.

**`AutoJoinCompanyByEmailDomainAction`** — appelée à l'inscription publique ou au moment où un user sans company essaie de créer/rejoindre, **si** son email match un `email_domain` d'une company existante :

- Garde-fous identiques.
- `DB::transaction` : update `user->company_id` (PAS de rôle company_admin — c'est un membre standard) + log activity `'Auto-rattachement par domaine email'`.
- Notification au(x) `company_admin` (`CompanyAutoJoinedNotification`, info-only).
- Retour : `Company`.

**`ApproveCompanyJoinRequestAction`** — un `company_admin` approuve une demande :

- Garde-fous : `$actor->isCompanyAdmin($joinRequest->company)`, `$joinRequest->status === pending`.
- `DB::transaction` : update join request (status approved, resolved_at, resolved_by) + update `targetUser->company_id`.
- Notification au demandeur (`CompanyJoinApprovedNotification`).
- Activity log.

**`RejectCompanyJoinRequestAction`** — un `company_admin` rejette une demande (avec raison optionnelle) :

- Garde-fous identiques.
- Update status rejected + rejection_reason.
- Notification au demandeur (`CompanyJoinRejectedNotification`).
- Activity log.

**`CancelCompanyJoinRequestAction`** — le demandeur annule sa propre demande tant qu'elle est pending :

- Garde-fous : `$actor->id === $joinRequest->user_id`, `$joinRequest->status === pending`.
- Update status cancelled.
- Pas de notification (le demandeur sait ce qu'il fait).
- Activity log.

**`TransferCompanyAdminAction`** — succession automatique : appelée par un observer ou par un service quand le seul `company_admin` quitte la company. Désigne le membre le plus ancien restant.

- Si plus aucun membre éligible (cas pathologique : la company se vide), notification à l'admin TableConvo via channel mail+database (`CompanyAdminVacantNotification`, à créer aussi).
- Sinon : retire le rôle au partant (si encore présent), assigne à l'élu, log activity, notifie le nouveau admin (`CompanyAdminAssignedNotification`).

**`AssignCompanyAdminAction`** — réassignation **forcée** par le super admin TableConvo :

- Garde-fou strict : `abort_unless($actor->hasRole('admin'), 403)` — seul l'admin TableConvo peut.
- Paramètres : `Company $company`, `User $newAdmin`, `User $actor` (le super admin).
- Vérifications : `$newAdmin->company_id === $company->id` (le user doit être membre de cette company).
- `DB::transaction` : retire le rôle `company_admin` à tous les autres membres de la company (s'il y en avait), assigne à `$newAdmin`.
- Notification au nouveau (`CompanyAdminAssignedNotification`).
- Notification optionnelle à l'ancien (`CompanyAdminRevokedNotification`, à créer également).
- Activity log avec `causer = $actor`, mention explicite "forcé par super admin".

### B.3 — Notifications

Toutes implémentent `ShouldQueue`. Toutes utilisent les channels `['mail', 'database']`.

À créer dans `app/Notifications/Company/` :

- `CompanyJoinRequestedNotification` — destinataire : company_admin. Contenu : un nouveau membre potentiel demande à rejoindre, lien vers `/espace/societe/membres`.
- `CompanyJoinApprovedNotification` — destinataire : demandeur. Lien vers `/espace/dashboard`.
- `CompanyJoinRejectedNotification` — destinataire : demandeur. Affiche la raison si fournie.
- `CompanyAutoJoinedNotification` — destinataire : company_admin. Info-only.
- `CompanyAdminAssignedNotification` — destinataire : nouveau company_admin. Mention si c'est succession auto ou décision super admin.
- `CompanyAdminRevokedNotification` — destinataire : ancien company_admin. Info-only.
- `CompanyAdminVacantNotification` — destinataire : tous les users avec rôle `admin` TableConvo. Cas pathologique à intervention manuelle.

Pour le mail, layout Markdown `vendor.notifications.email` (déjà customisé phases précédentes).

### B.4 — Observers

`UserObserver::deleted` (et `UserObserver::softDeleted` si distinct) — quand un user est soft-deleted :

- Si ce user était `company_admin` ET qu'il était le **seul** company_admin de sa company → déclencher `TransferCompanyAdminAction`.

Idem si un user change de `company_id` (rare mais possible via réassignation super admin) : si le partant était company_admin de l'ancienne company, déclencher la succession.

### B.5 — Policies

**`CompanyPolicy`** (existante) — ajouter :

- `manageMembers(User $actor, Company $company)` : true si `$actor->isCompanyAdmin($company)` OR `$actor->hasRole('admin')`.
- `reassignAdmin(User $actor, Company $company)` : true si `$actor->hasRole('admin')` (super admin **uniquement**). Le company_admin ne peut PAS s'auto-démettre via cette policy (il peut transférer son rôle via une action dédiée si on l'ajoute plus tard).

**`CompanyJoinRequestPolicy`** (nouvelle) :

- `view(User $actor, CompanyJoinRequest $req)` : actor est le demandeur OR un company_admin de la company cible OR super admin.
- `approve` / `reject` : actor est company_admin de la company cible OR super admin.
- `cancel` : actor est le demandeur.

### B.6 — Tests Étape B

Dossier `tests/Feature/Actions/Company/` :

- `CreateCompanyFromMemberSpaceActionTest` — 6 tests : succès, anti-hijacking (renvoi signal company_exists), VIES KO, format TVA KO, domaine email pro stocké, domaine email perso → null.
- `RequestCompanyJoinActionTest` — 5 tests : succès + notification, demande dupliquée rejetée, garde-fous (admin / déjà rattaché / company null).
- `AutoJoinCompanyByEmailDomainActionTest` — 4 tests.
- `ApproveCompanyJoinRequestActionTest` — 4 tests : succès, notification, garde-fous, statut != pending.
- `RejectCompanyJoinRequestActionTest` — 3 tests.
- `CancelCompanyJoinRequestActionTest` — 3 tests.
- `TransferCompanyAdminActionTest` — 5 tests : succession nominale, plus de candidat éligible → notif super admin, multi-membres prend le plus ancien, ne touche pas si plusieurs company_admins existaient.
- `AssignCompanyAdminActionTest` — 5 tests : succès, garde-fou super admin only, vérification appartenance, retrait des anciens admins, notifications.

Dossier `tests/Feature/Middleware/` :

- `EnsureUserHasCompanyTest` — 5 tests.

Dossier `tests/Feature/Policies/` :

- `CompanyPolicyMultiMembersTest` — manageMembers et reassignAdmin (8 tests).
- `CompanyJoinRequestPolicyTest` — 6 tests.

`tests/Feature/Observers/UserObserverCompanyAdminTest.php` — succession déclenchée à la soft-deletion (3 tests).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Rapporte :                                               ║
### ║  1. Total tests verts (cible ~545, +55 cumul)            ║
### ║  2. Liste des 8 Actions créées avec leur namespace        ║
### ║  3. Liste des 7 notifications créées                      ║
### ║  4. Confirmation : aucune Action existante (Phase 1-9.5) ║
### ║     n'a été modifiée                                      ║
### ║                                                           ║
### ║  Attends mon GO C écrit avant de continuer.               ║
### ╚══════════════════════════════════════════════════════════╝

---

## Étape C — UI espace membre

### C.1 — Routes

Dans `routes/web.php`, groupe `espace.*` :

```php
// Création self-service (user sans company, non-admin)
Route::get('/societe/creer', [Member\CompanyController::class, 'create'])
    ->name('societe.creer');
Route::post('/societe', [Member\CompanyController::class, 'store'])
    ->name('societe.store')
    ->middleware('throttle:company-creation');

// Rejoindre par TVA (user sans company, non-admin)
Route::get('/societe/rejoindre', [Member\CompanyJoinRequestController::class, 'create'])
    ->name('societe.rejoindre');
Route::post('/societe/rejoindre/lookup', [Member\CompanyJoinRequestController::class, 'lookup'])
    ->name('societe.rejoindre.lookup')
    ->middleware('throttle:company-creation');
Route::post('/societe/rejoindre', [Member\CompanyJoinRequestController::class, 'store'])
    ->name('societe.rejoindre.store')
    ->middleware('throttle:company-creation');

// Gestion membres et demandes (company_admin uniquement)
Route::get('/societe/membres', [Member\CompanyMembersController::class, 'index'])
    ->name('societe.membres')
    ->middleware('can:manageMembers,App\Models\Company');
Route::post('/societe/demandes/{joinRequest}/approuver',
    [Member\CompanyMembersController::class, 'approve'])
    ->name('societe.demandes.approuver');
Route::post('/societe/demandes/{joinRequest}/rejeter',
    [Member\CompanyMembersController::class, 'reject'])
    ->name('societe.demandes.rejeter');

// Annuler sa propre demande
Route::post('/societe/ma-demande/annuler',
    [Member\CompanyJoinRequestController::class, 'cancel'])
    ->name('societe.ma-demande.annuler');
```

### C.2 — Rate limiter `company-creation`

Dans `AppServiceProvider::boot()` :

```php
RateLimiter::for('company-creation', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});
```

Ajouter `RateLimiter::clear('company-creation|...')` dans le `beforeEach` global de `Pest.php` si nécessaire pour éviter du flaky.

### C.3 — Controllers

**`Member\CompanyController`** (refonte partielle si déjà existant suite au patch envisagé, sinon création) :

- `create()` — affiche le formulaire de création, redirige si déjà rattaché ou admin.
- `store(CreateCompanyRequest)` — orchestre :
  1. Lookup TVA → si existante chez TableConvo, on **redirige** vers `/societe/rejoindre?vat=...` (paramètre pré-rempli).
  2. Sinon `CreateCompanyFromMemberSpaceAction`.

**`Member\CompanyJoinRequestController`** (nouveau) :

- `create(Request)` — affiche le formulaire "rejoindre par TVA". Si `?vat=...` en query, le champ est pré-rempli et un lookup est déclenché auto au load (via Livewire ou simple JS fetch).
- `lookup(Request)` — endpoint JSON pour lookup AJAX : valide format, appelle VIES, cherche company en local. Retourne :
  - `{ status: 'unknown' }` : TVA pas chez TableConvo → propose création.
  - `{ status: 'exists', company: { name, address }, can_auto_join: bool }` : trouvée. `can_auto_join` = true si le domaine email du user match `email_domain` de la company.
  - `{ status: 'invalid_format' }` ou `{ status: 'vies_failed' }`.
- `store(StoreCompanyJoinRequest)` — orchestre :
  1. Re-lookup pour éviter race condition.
  2. Si pas trouvée → erreur (l'user doit utiliser le flow création).
  3. Si trouvée ET `can_auto_join` → `AutoJoinCompanyByEmailDomainAction` (rattachement immédiat, flash success).
  4. Si trouvée mais pas auto-join → `RequestCompanyJoinAction` (demande en attente, flash info).
- `cancel(Request)` — annule la demande pending du user en cours.

**`Member\CompanyMembersController`** (nouveau, réservé company_admin) :

- `index()` — liste des membres + liste des demandes pending. Vue avec actions inline.
- `approve(CompanyJoinRequest)` — appelle `ApproveCompanyJoinRequestAction`.
- `reject(Request, CompanyJoinRequest)` — appelle `RejectCompanyJoinRequestAction` avec raison optionnelle.

### C.4 — Form Requests

`CreateCompanyRequest` (Member namespace) :

```php
public function authorize(): bool {
    $u = $this->user();
    return $u && ! $u->hasRole('admin') && ! $u->company;
}

public function rules(): array {
    return [
        'company_name'  => ['required', 'string', 'max:255'],
        'vat_number'    => ['required', 'string', 'max:30'],
        'street'        => ['required', 'string', 'max:255'],
        'postal_code'   => ['required', 'string', 'max:20'],
        'city'          => ['required', 'string', 'max:100'],
        'billing_email' => ['nullable', 'email', 'max:255'],
    ];
}
```

`StoreCompanyJoinRequest` :

```php
public function authorize(): bool {
    $u = $this->user();
    return $u && ! $u->hasRole('admin') && ! $u->company;
}

public function rules(): array {
    return [
        'vat_number' => ['required', 'string', 'max:30'],
        'message'    => ['nullable', 'string', 'max:1000'],
    ];
}
```

### C.5 — Vues

**`resources/views/espace/societe/creer.blade.php`** — formulaire de création.

- Layout `<x-app-layout>`.
- Titre + texte d'intro expliquant *« Si votre entreprise existe déjà chez TableConvo, vous pouvez la rejoindre depuis cette page : [lien rejoindre]. »*
- Bouton "Lookup VIES" qui pré-remplit nom + adresse depuis la réponse VIES enrichie (action POST AJAX vers un endpoint dédié OU intégration Livewire — au choix du dev, mais cohérent avec le reste).
- Bouton submit "Créer ma société".
- Composants Breeze : `x-input-label`, `x-text-input`, `x-input-error`.
- Classes sémantiques uniquement.

**`resources/views/espace/societe/rejoindre.blade.php`** :

- Champ TVA + bouton "Rechercher".
- Affichage conditionnel selon retour lookup :
  - Inconnue → CTA "Créer cette société" pointant vers `/espace/societe/creer?vat=...`.
  - Trouvée + auto-join possible → message *« Acme SA. Votre email pro correspond — vous pouvez rejoindre immédiatement. »* + bouton "Rejoindre Acme SA".
  - Trouvée sans auto-join → message *« Acme SA. Votre demande sera transmise à l'administrateur de la société. »* + textarea message optionnel + bouton "Envoyer la demande".
- Si l'user a déjà une demande pending : affichage du status avec bouton "Annuler ma demande".

**`resources/views/espace/societe/membres.blade.php`** (réservé company_admin) :

- Section 1 : liste des demandes pending avec boutons inline "Approuver" / "Rejeter" (le rejet ouvre une modale avec champ raison).
- Section 2 : liste des membres actuels (avec badge "Administrateur" pour les company_admins).
- Pas d'actions destructives ici dans cette phase : pas de retrait de membre, pas de transfert manuel par le company_admin. Si demande à l'avenir → backlog.

**Modification `resources/views/espace/profil.blade.php`** :

- Bannière flash `company_missing` en haut (si redirection depuis middleware).
- Bannière flash `company_created` (vert) après création.
- Bloc conditionnel quand `$user->company === null && ! $user->hasRole('admin')` : encart proposant **deux** CTA cote à cote :
  - "Créer ma société" → `/espace/societe/creer`
  - "Rejoindre une société existante" → `/espace/societe/rejoindre`
- Si le user a une demande pending visible : encart de rappel "Demande en attente pour [Company X]" avec bouton "Annuler".

**Modification `resources/views/layouts/navigation.blade.php`** :

```blade
@auth
    @unless (Auth::user()->hasRole('admin'))
        <x-nav-link :href="route('tarifs')" :active="request()->routeIs('tarifs')">Tarifs</x-nav-link>
        <x-nav-link :href="route('espace.dashboard')" ...>Tableau de bord</x-nav-link>
        <x-nav-link :href="route('espace.inscriptions')" ...>Mes inscriptions</x-nav-link>
        <x-nav-link :href="route('espace.cartes')" ...>Mes cartes</x-nav-link>
        @can('manageMembers', Auth::user()->company)
            <x-nav-link :href="route('espace.societe.membres')" ...>Mes membres</x-nav-link>
        @endcan
    @else
        <x-nav-link :href="url('/admin')">Panel admin</x-nav-link>
    @endunless
@endauth
```

Idem dans le menu responsive. Le lien "Mes membres" n'apparaît que pour les company_admins.

### C.6 — Tests Étape C

`tests/Feature/Member/CompanyControllerTest.php` — 8 tests.
`tests/Feature/Member/CompanyJoinRequestControllerTest.php` — 12 tests (couvrant les 4 statuts de lookup, les 3 flows store, le cancel).
`tests/Feature/Member/CompanyMembersControllerTest.php` — 6 tests.
`tests/Feature/Navigation/MultiMembersNavigationTest.php` — 4 tests sur l'affichage conditionnel du lien "Mes membres".

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — CHECKPOINT 3                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Rapporte :                                               ║
### ║  1. Total tests verts (cible ~575, +85 cumul)            ║
### ║  2. Walkthrough manuel des 3 flows :                     ║
### ║     - User pro même domaine → auto-join immédiat          ║
### ║     - User email perso → demande, admin approuve          ║
### ║     - User refus → demande, admin rejette avec raison     ║
### ║  3. Vérification : pas de couleurs Tailwind littérales    ║
### ║     hors du flash success                                 ║
### ║                                                           ║
### ║  Attends mon GO D écrit avant de continuer.               ║
### ╚══════════════════════════════════════════════════════════╝

---

## Étape D — Inscription publique & Filament super admin

### D.1 — Refonte du flow d'inscription publique (`RegisteredUserController`)

Le flow actuel : un user s'inscrit en saisissant ses infos + une nouvelle Company. Si la TVA existe → rejet.

Le nouveau flow doit gérer 4 cas :

1. **TVA non chez TableConvo** → comportement actuel (création User + Company atomique).
2. **TVA chez TableConvo + email pro match `email_domain`** → création User + auto-rattachement + notif company_admin. Pas de création de Company.
3. **TVA chez TableConvo + email perso ou domaine différent** → création User SANS company, puis création automatique d'un `CompanyJoinRequest` pending vers la company détectée, puis redirection vers une page d'attente. Notification au company_admin.
4. **TVA invalide** → rejet comme actuellement.

**Important** : on **ne supprime PAS l'anti-hijacking**. Il ne s'agit plus de rejeter mais d'orienter. L'activity log "tentative de hijacking" reste pertinent uniquement si la TVA est valide mais que les autres infos saisies (nom, adresse) **divergent significativement** de celles connues — backlogger cette détection avancée, ne pas l'implémenter ici.

Adaptation du `RegisteredUserController::store` :

- Validation classique.
- Lookup TVA via service.
- Switch sur les 4 cas ci-dessus.
- Si cas 2 → auth login + redirect dashboard avec flash `auto_joined`.
- Si cas 3 → auth login + redirect vers `/espace/profil` avec flash `request_pending`.
- Si cas 1 → comportement actuel (création atomique avec rôle company_admin assigné).
- Si cas 4 → erreur de validation comme aujourd'hui.

**Pas de refactoring de l'extraction VAT vers une Action partagée dans cette phase non plus.** Duplication assumée, backlog technique noté.

Ajustement de la vue `auth.register` :

- Ajout d'un endpoint AJAX pour lookup TVA pendant la saisie (debounce) → indique en temps réel si la company est connue chez TableConvo. Permet d'afficher avant submit : *« Acme SA est déjà enregistrée. Si vous travaillez là, l'inscription vous rattachera automatiquement (ou créera une demande d'adhésion). »*
- Si lookup indique company existante : grise les champs nom/adresse company (deviennent en lecture seule, pré-remplis depuis la company connue).
- Si lookup indique company nouvelle mais VIES OK : pré-remplit nom/adresse depuis VIES.

### D.2 — Filament — UserResource

Ajouter une action `setAsCompanyAdmin` sur la table (et sur le formulaire) :

- Visible uniquement si `$record->company_id !== null && auth()->user()->hasRole('admin')`.
- Confirmation modale.
- Action appelle `AssignCompanyAdminAction(company: $record->company, newAdmin: $record, actor: auth()->user())`.
- Notification de succès Filament.

Ajouter dans la table un badge ou une colonne indiquant le rôle effectif : `admin` (super) / `company_admin` (de sa company) / `member`.

### D.3 — Filament — CompanyResource

Sur la page de vue/edit d'une Company :

- Section "Administrateur de la société" : affiche le user actuel en `company_admin` (ou "Aucun" si vacant) + bouton "Réassigner" qui ouvre un select des membres de cette company.
- Section "Membres" : liste tous les `$company->members` avec leur rôle.
- Section "Demandes d'adhésion en cours" : liste les pending `CompanyJoinRequest`. Lecture seule pour le super admin (il n'approuve pas à la place du company_admin, mais il les voit pour debug et support).

Action `reassignAdmin` :

- Confirmation modale.
- Appelle `AssignCompanyAdminAction`.
- Notification Filament.

### D.4 — Filament — Optionnel : CompanyJoinRequestResource (lecture seule)

Petite ressource Filament en read-only pour le super admin pour debug / support. Pas d'actions de modification. Filtres : status, company, date.

Si trop de scope → noter en backlog et passer.

### D.5 — Tests Étape D

`tests/Feature/Auth/RegistrationMultiMembersTest.php` — 10 tests couvrant les 4 cas + edge cases.

`tests/Feature/Filament/Resources/UserResourceCompanyAdminTest.php` — 5 tests : super admin peut réassigner, autre user 403, action non visible pour user sans company, action appelle bien l'Action, activity log produit.

`tests/Feature/Filament/Resources/CompanyResourceMembersTest.php` — 4 tests.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP D — CHECKPOINT 4                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Rapporte :                                               ║
### ║  1. Total tests verts (cible ~595, +105 cumul)           ║
### ║  2. Walkthrough manuel inscription publique :             ║
### ║     - Cas 1 (nouvelle company)                            ║
### ║     - Cas 2 (email pro match → auto-join)                 ║
### ║     - Cas 3 (email perso → join request créé auto)        ║
### ║  3. Walkthrough Filament : super admin réassigne          ║
### ║     company_admin sur une Company donnée                  ║
### ║  4. Confirmation : aucune régression sur les tests        ║
### ║     d'inscription publique existants                      ║
### ║                                                           ║
### ║  Attends mon GO E écrit avant la dernière étape.          ║
### ╚══════════════════════════════════════════════════════════╝

---

## Étape E — Tests d'intégration, documentation, push

### E.1 — Test d'intégration end-to-end

`tests/Feature/Integration/MultiMembersFullJourneyTest.php` :

Un scénario complet en plusieurs sous-tests qui rejoue le parcours complet :

1. Employé A s'inscrit avec `arnaud@acme-sa.be` + TVA Acme SA → Company créée, A est company_admin, `email_domain = 'acme-sa.be'`.
2. Employé B s'inscrit avec `marie@acme-sa.be` + même TVA → auto-rattaché, A reçoit `CompanyAutoJoinedNotification`.
3. Employé C s'inscrit avec `freelance@gmail.com` + même TVA → join request créé en pending, A reçoit `CompanyJoinRequestedNotification`.
4. A approuve C → C est rattaché, reçoit `CompanyJoinApprovedNotification`.
5. Employé D s'inscrit avec `sous-trait@autre-boite.com` + même TVA → join request pending, A reçoit notif.
6. A rejette D avec raison "vous n'êtes pas membre de notre équipe" → D reçoit `CompanyJoinRejectedNotification`.
7. Super admin TableConvo réassigne le company_admin de Acme SA à B → A et B reçoivent les notifs adéquates.
8. A est soft-deleted → succession auto déclenchée → comme A n'est plus admin (B l'est), pas de transfert. Si on soft-delete B aussi, succession transfère à C (le plus ancien restant).

### E.2 — Test régression

```bash
php artisan test
```

Cible : 595+ tests verts, **zéro régression** sur les 475 existants.

### E.3 — Documentation `CLAUDE.md`

Mise à jour de la section Routes :

```
GET  /espace/societe/creer       → formulaire création société (user sans company)
POST /espace/societe             → création
GET  /espace/societe/rejoindre   → formulaire rejoindre par TVA
POST /espace/societe/rejoindre/lookup → lookup AJAX
POST /espace/societe/rejoindre   → soumission demande / auto-join
POST /espace/societe/ma-demande/annuler → annulation
GET  /espace/societe/membres     → gestion membres (company_admin)
POST /espace/societe/demandes/{id}/approuver
POST /espace/societe/demandes/{id}/rejeter
```

Mise à jour de la section Rôles :

```
- admin : super admin TableConvo (peut tout, panel Filament)
- company_admin : administrateur d'UNE company spécifique ; peut approuver/rejeter
  les demandes d'adhésion pour SA company uniquement. Peut être réassigné à tout
  moment par un super admin depuis Filament.
- member : membre standard rattaché à une company
```

Mise à jour des règles métier :

```
- Multi-membres : C1 (auto-trust par domaine email pro) + C3 (demande d'adhésion)
- Blocklist domaines génériques (gmail.com, hotmail.com, etc.) gérée par
  EmailDomainService — pas un setting admin
- Succession company_admin : auto au plus ancien à la soft-deletion ; si vacant,
  notif super admin via CompanyAdminVacantNotification
- Réassignation forcée : possible par super admin via AssignCompanyAdminAction
  uniquement (pas par le company_admin lui-même)
- VIES enrichi : pré-remplissage nom + adresse au lookup
- BCE/KBO non intégré (backlog si VIES insuffisant à l'usage)
```

### E.4 — `docs/BACKLOG.md`

Ajouter une section :

```markdown
## Post-Phase 9.6 — Multi-membres : raffinements identifiés

- **Extraction VAT shared Action** : le code de validation/lookup VIES est dupliqué
  entre `RegisteredUserController::store` et `CreateCompanyFromMemberSpaceAction`.
  Extraire vers une `ResolveCompanyByVatAction` partagée.
- **Détection hijacking avancée** : si TVA valide mais nom/adresse saisis divergent
  significativement de la company connue, logguer une alerte distincte avant
  d'orienter vers le join request.
- **Transfert volontaire company_admin** : permettre à un company_admin de transférer
  son rôle à un membre de son choix (sans soft-deletion). Page dédiée dans
  `/espace/societe/membres`.
- **Retrait de membre** : permettre à un company_admin de retirer un membre de sa
  company (avec confirmation, RGPD-compliant, sans suppression du compte user).
- **Intégration BCE/KBO** : si la couverture VIES s'avère insuffisante (entreprises
  non listées VIES car non transactions intracommunautaires), intégrer le dump
  Open Data BCE via import quotidien CSV.
- **Multi-rôles intra-company** : aujourd'hui binaire (company_admin vs member).
  Possible évolution : rôle "company_billing" qui voit factures sans gérer membres,
  rôle "company_viewer" en lecture seule, etc.
- **Filament CompanyJoinRequestResource lecture-seule** si pas livré en étape D.4.
```

### E.5 — Commit, push

```bash
git checkout -b feature/phase-9.6-multi-members
git add .
git commit -m "feat(phase 9.6): multi-membres d'une même société

C1 + C3 : auto-trust par domaine email pro + demande d'adhésion.

- Middleware EnsureUserHasCompany sur /panier (fix bug initial)
- Modèle CompanyJoinRequest + rôle Spatie company_admin
- EmailDomainService (blocklist domaines génériques)
- VatValidationService enrichi (lookup nom + adresse VIES)
- 8 Actions Company (création, request, auto-join, approve, reject,
  cancel, transfer admin, assign admin)
- 7 notifications ShouldQueue (channels mail + database)
- UI espace membre : créer / rejoindre par TVA / gérer membres
- Refonte inscription publique : 4 cas (nouvelle, auto-join,
  request, invalide)
- Filament super admin : réassignation company_admin à tout moment
- Navigation : masquage panier/tarifs pour admins TableConvo
- Backlog post-9.6 documenté

Tests : ~600 verts (+125)"

git push origin feature/phase-9.6-multi-members
```

**Pas de merge automatique sur main.** Le merge et déploiement RPi sont pilotés manuellement.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP E — RÉCAP DE FIN DE PHASE                          ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Réponds aux 12 questions de validation ci-dessous.      ║
### ╚══════════════════════════════════════════════════════════╝

---

## Questions de validation finale (12)

1. Total tests Pest verts ≥ 595 ? Zéro régression sur les 475 existants ?
2. Le `abort_unless($user->company, ...)` dans `CreateOrderFromCartAction` est-il strictement inchangé ?
3. Middleware `EnsureUserHasCompany` enregistré comme alias `ensure.company` et appliqué sur `/panier` GET + POST ?
4. Admin TableConvo qui tente `/panier` → redirigé vers `/admin` (PAS vers `/espace/profil`) ?
5. Rôle Spatie `company_admin` créé via seeder, distinct du rôle `admin` ?
6. `EmailDomainService` rejette bien les domaines génériques (test sur gmail.com, hotmail.com a minima) ?
7. Inscription publique : les 4 cas (nouvelle / auto-join / request créé / invalide) sont-ils tous testés et fonctionnels ?
8. Auto-join : le user nouvellement rattaché reçoit le rôle `member` mais PAS `company_admin` (le company_admin reste celui qui a créé la company) ?
9. Soft-deletion d'un user company_admin → succession auto déclenchée au plus ancien membre actif restant ?
10. Si plus aucun candidat éligible à la succession → notification au super admin TableConvo (channel mail + database) ?
11. Réassignation forcée par super admin depuis Filament : autorisée pour `admin`, refusée 403 pour tout autre rôle, action loggée avec `causer = super admin` ?
12. `BACKLOG.md` contient bien les 7 items de raffinement post-9.6 ?

---

## Pièges à éviter (lecture obligatoire avant code)

1. **NE PAS modifier** `CreateOrderFromCartAction`. Le `abort_unless` reste tel quel.
2. **NE PAS supprimer** l'anti-hijacking au lookup TVA — il est transformé en orientation (vers join request) mais le log "tentative" reste pertinent si divergence d'infos significative (backloggé en E.4).
3. **NE PAS extraire** la logique TVA dupliquée dans cette phase (entre `RegisteredUserController` et `CreateCompanyFromMemberSpaceAction`). Backlog explicite.
4. **NE PAS implémenter** Spatie teams pour le rôle `company_admin`. On utilise un rôle global + contrôle d'appartenance applicatif au niveau policy.
5. **NE PAS oublier** le `DB::afterCommit` sur toutes les notifications dispatchées depuis une transaction.
6. **NE PAS oublier** `RateLimiter::clear('company-creation|...')` dans `Pest.php` si flaky.
7. **NE PAS utiliser** de couleurs Tailwind littérales hors du flash success `emerald-*` (toléré).
8. **NE PAS oublier** que les vues `/espace/*` doivent être themables (CSS variables, classes sémantiques) — le panel Filament ne l'est pas.
9. **NE PAS faire de merge** automatique sur `main`. Push uniquement sur la branche feature.
10. **VÉRIFIER** le nom exact de la route dashboard Filament avant de la coder en dur dans le middleware.

---

## Récapitulatif des artefacts livrés

**Migrations** : 2 (companies enriched, company_join_requests created).
**Modèles** : 1 nouveau (`CompanyJoinRequest`), 2 enrichis (`User`, `Company`).
**Enums** : 1 (`CompanyJoinRequestStatus`).
**Services** : 1 nouveau (`EmailDomainService`), 1 enrichi (`VatValidationService`).
**Actions** : 8 nouvelles dans `app/Actions/Company/`.
**Notifications** : 7 nouvelles dans `app/Notifications/Company/`.
**Controllers** : 3 nouveaux dans `app/Http/Controllers/Member/`.
**Form Requests** : 2 nouveaux.
**Form Request modifiés** : 1 (`RegisterRequest` ou équivalent).
**Middleware** : 1 nouveau (`EnsureUserHasCompany`).
**Policies** : 1 nouvelle (`CompanyJoinRequestPolicy`), 1 enrichie (`CompanyPolicy`).
**Observers** : 1 enrichi (`UserObserver`).
**Vues nouvelles** : 3 (`espace/societe/creer`, `espace/societe/rejoindre`, `espace/societe/membres`).
**Vues modifiées** : 3 (`espace/profil`, `layouts/navigation`, `auth/register`).
**Filament** : 2 resources enrichies (`UserResource`, `CompanyResource`), 1 nouvelle optionnelle (`CompanyJoinRequestResource` read-only).
**Routes** : ~10 nouvelles.
**Rate limiter** : 1 nouveau (`company-creation`).
**Tests Pest** : ~125 nouveaux tests (cumul cible ~600).
**Documentation** : `CLAUDE.md` mis à jour, `docs/BACKLOG.md` enrichi.

---

**Fin du brief Phase 9.6 — Multi-membres.**

Tu commences par l'Étape A. Tu attends mon `GO B` avant de passer à l'étape suivante. Pour chaque checkpoint, tu rapportes les éléments demandés dans l'encart STOP correspondant et tu attends ma validation écrite.
