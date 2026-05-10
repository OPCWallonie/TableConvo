# Brief projet — Plateforme de gestion de tables de conversation en néerlandais

> Ce document est le **cadre de référence** du projet. Toutes les décisions ci-dessous ont été validées par le client.
> **À Claude Code : ne pas réinterpréter, ne pas "améliorer" sans demander.** Si une ambiguïté apparaît, poser la question avant d'implémenter.

---

## 1. Contexte et objectif

Une école/société organise des **tables de conversation en néerlandais** (présentiel uniquement) à destination d'**entreprises** (B2B). Les sessions ont lieu sur des créneaux récurrents (typiquement le mercredi). Les entreprises achètent des **cartes virtuelles de 10 sessions** que leurs employés consomment en s'inscrivant aux tables.

**Cible** : entreprises belges, employés néerlandophiles à différents niveaux (CECRL A1 à C2).

**Modèle commercial** :
- 1 carte = 10 sessions = 250 € (25 €/session)
- Validité de la carte : 12 mois
- Pas de droit de rétractation (B2B)

---

## 2. Règles métier — À NE PAS RÉINTERPRÉTER

### 2.1 Cartes
- Une carte appartient à **un seul utilisateur** (pas de partage entre collègues).
- L'utilisateur achète avec son compte, mais la **facture est au nom de la société** (numéro de TVA obligatoire).
- Une carte expirée = sessions restantes **perdues**.
- **Exception** : si l'admin annule une session ET que la carte expire dans les 30 jours suivant cette annulation, la validité est prolongée de 30 jours (paramètre).

### 2.2 Inscriptions
- L'utilisateur **doit avoir un niveau attribué** pour s'inscrire à une table.
- À sa **première tentative d'inscription** sans niveau, on bloque avec message + alerte mail à l'admin pour entretien téléphonique.
- L'admin attribue le niveau manuellement dans le back-office, ce qui débloque les inscriptions.
- Une inscription doit avoir lieu **au moins 24h avant la session** (paramétrable).
- Une inscription peut être annulée **jusqu'à 3 jours ouvrables avant la session** (paramétrable). Calcul en jours ouvrables = exclut samedi, dimanche **et jours fériés belges**.
- Au-delà de cette limite, la session est **perdue** (no-show inclus).
- Si l'admin annule une session, **toutes les sessions consommées par les inscrits sont recréditées** sur leurs cartes respectives.

### 2.3 Anti-monopolisation
- **Maximum 1 inscription par semaine** par utilisateur (paramétrable).
- **Maximum 3 inscriptions futures simultanées** par utilisateur (paramétrable).
- À vérifier au moment de la tentative d'inscription, message d'erreur clair si dépassé.

### 2.4 Liste d'attente
- Quand une table est complète, l'utilisateur peut s'inscrire en **liste d'attente** (`waitlist_position` chronologique).
- Quand une place se libère (annulation, déplacement admin), **alerte mail à l'admin** avec la liste d'attente classée par ordre d'arrivée.
- **L'admin promeut manuellement** depuis la liste d'attente — pas de promotion automatique pour le moment.
- Prévoir un paramètre `waitlist_auto_promote` (défaut `false`) pour activer la promotion auto plus tard sans refactor.

### 2.5 Validation des inscriptions par l'admin
- Inscription **automatique** côté utilisateur si toutes les règles passent.
- **L'admin peut à tout moment déplacer une inscription** entre tables, ou la basculer en liste d'attente, sans validation préalable du système.
- Toute action de l'admin sur une inscription = **entrée dans le journal d'audit** + notification mail au membre concerné.

### 2.6 Niveaux
- Niveaux gérés en table dédiée (pas en enum) : A1, A2, B1, B2, C1, C2.
- Une table de conversation est rattachée à **un niveau**.
- Un utilisateur ne peut s'inscrire qu'aux tables de **son niveau** (l'admin peut éventuellement faire un override manuel).

---

## 3. Stack technique imposée

- **PHP 8.3+** / **Laravel 11+** (dernière version stable au moment de la création)
- **MySQL 8** (ou MariaDB 10.11+)
- **Blade + Livewire 3** côté front (espace public + espace membre)
- **Filament 3** côté admin (back-office)
- **Tailwind CSS** (déjà fourni par Filament/Breeze)
- **Pest** pour les tests
- **Mollie** pour les paiements (CB uniquement, VISA + Mastercard)

**Langue de l'interface : français uniquement.**
La langue cible (néerlandais) est mentionnée dans la description des tables.

---

## 4. Packages à installer

```bash
composer require laravel/breeze --dev
composer require livewire/livewire
composer require filament/filament:"^3.2"
composer require spatie/laravel-permission
composer require spatie/laravel-settings
composer require spatie/laravel-activitylog
composer require mollie/laravel-mollie
composer require barryvdh/laravel-dompdf
composer require spatie/holidays
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
```

À considérer en option :
- `spatie/laravel-medialibrary` si gestion d'avatars/documents
- `spatie/laravel-csp` pour les Content Security Policy
- `spatie/laravel-backup` pour les sauvegardes auto

---

## 5. Modèle de données

### Tables principales

**`users`** (étend la table Laravel par défaut)
- `id`, `first_name`, `last_name`, `email` (unique), `phone`, `password`
- `level_id` (nullable, FK vers `levels`)
- `level_assigned_at` (nullable)
- `company_id` (FK vers `companies`)
- `email_verified_at`, `remember_token`
- timestamps + soft deletes

**`companies`**
- `id`, `name`, `vat_number` (unique, format BE0XXXXXXXXX)
- `street`, `postal_code`, `city`, `country` (défaut "Belgique")
- `billing_email`
- timestamps + soft deletes

**`levels`**
- `id`, `code` (A1, A2, B1, B2, C1, C2), `name`, `description`, `sort_order`, `is_active`
- timestamps

**`card_types`** (catalogue produit)
- `id`, `name`, `sessions_count`, `price`, `validity_months`, `is_active`
- timestamps

**`cards`** (instance achetée par un user)
- `id`, `user_id`, `card_type_id`
- `sessions_total` (snapshot), `sessions_remaining`, `price_paid` (snapshot)
- `purchased_at`, `expires_at`
- `status` (enum: active, expired, refunded)
- `order_id` (FK vers l'ordre d'achat)
- timestamps + soft deletes

**`conversation_tables`**
- `id`, `level_id`, `topic`, `description`
- `scheduled_at` (datetime), `duration_minutes`, `max_participants`
- `location`
- `animator_id` (nullable, FK users — préparé pour le rôle futur)
- `status` (enum: scheduled, cancelled, completed)
- `cancelled_at`, `cancellation_reason` (nullable)
- timestamps + soft deletes

**`registrations`**
- `id`, `user_id`, `conversation_table_id`, `card_id` (nullable si waitlist)
- `status` (enum: registered, waitlist, cancelled, attended, no_show)
- `waitlist_position` (nullable)
- `registered_at`, `cancelled_at`, `cancelled_by` (nullable, FK user — admin ou self)
- timestamps + soft deletes

**`orders`**
- `id`, `user_id`, `company_snapshot` (JSON : nom, TVA, adresse au moment de l'achat)
- `total_ht`, `total_vat`, `total_ttc`
- `status` (enum: pending, paid, failed, refunded)
- `mollie_payment_id`, `paid_at`
- timestamps + soft deletes

**`order_items`**
- `id`, `order_id`, `card_type_id`, `quantity`
- `unit_price_ht`, `vat_rate`, `vat_amount`, `total_ht`, `total_ttc`
- timestamps

**`invoices`**
- `id`, `order_id` (unique), `invoice_number` (unique, séquentiel)
- `issued_at`, `total_ht`, `total_vat`, `total_ttc`
- `billing_snapshot` (JSON figé : société destinataire ET société émettrice)
- `pdf_path` (stocké dans `storage/invoices/`)
- timestamps

**Tables Spatie** (créées par les packages)
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `settings` (Spatie Settings)
- `activity_log` (Spatie Activity Log)

### Relations clés
- `User` belongsTo `Company`, belongsTo `Level`, hasMany `Cards`, hasMany `Registrations`, hasMany `Orders`
- `Card` belongsTo `User`, belongsTo `CardType`, belongsTo `Order`, hasMany `Registrations`
- `ConversationTable` belongsTo `Level`, hasMany `Registrations`
- `Registration` belongsTo `User`, belongsTo `ConversationTable`, belongsTo `Card`
- `Order` belongsTo `User`, hasMany `OrderItems`, hasOne `Invoice`, hasMany `Cards`

### Règles techniques
- **Soft deletes partout** sur User, Company, Card, ConversationTable, Registration, Order, Invoice. Aucune suppression dure pour raisons légales.
- **Snapshots JSON** sur `orders.company_snapshot` et `invoices.billing_snapshot` : immuables après création.
- **Numérotation factures** : table `invoice_counters` (id, year, last_number) avec verrou pessimiste lors de la génération pour éviter les conflits concurrents.

---

## 6. Architecture & organisation

### Structure des dossiers
```
app/
├── Actions/                  ← UNE action métier = UNE classe avec execute()
│   ├── Card/
│   │   ├── PurchaseCardAction.php
│   │   ├── ExpireCardAction.php
│   │   └── ExtendCardValidityAction.php
│   ├── Registration/
│   │   ├── RegisterUserToTableAction.php
│   │   ├── CancelRegistrationAction.php
│   │   ├── PromoteFromWaitlistAction.php
│   │   ├── MoveRegistrationAction.php
│   │   └── CheckRegistrationRulesAction.php
│   ├── Session/
│   │   ├── CancelSessionAction.php  (recrédite toutes les cartes)
│   │   └── MarkAttendanceAction.php
│   ├── Invoice/
│   │   ├── GenerateInvoiceAction.php
│   │   └── GenerateInvoiceNumberAction.php
│   └── User/
│       ├── AssignLevelAction.php
│       └── RequestLevelInterviewAction.php
├── Enums/
│   ├── RegistrationStatus.php
│   ├── CardStatus.php
│   ├── OrderStatus.php
│   └── SessionStatus.php
├── Filament/
│   ├── Resources/
│   ├── Pages/
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Member/
│   │   ├── Public/
│   │   └── PaymentWebhookController.php
│   ├── Livewire/
│   │   ├── Cart/
│   │   ├── Agenda/
│   │   ├── Member/
│   │   └── Checkout/
│   ├── Middleware/
│   └── Requests/
├── Mail/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Mollie/MollieService.php
│   ├── Vat/VatValidationService.php  (VIES API)
│   ├── BusinessDay/BusinessDayService.php  (jours fériés BE)
│   └── Pdf/InvoicePdfService.php
└── Settings/
    ├── BookingSettings.php
    ├── CardSettings.php
    ├── InvoicingSettings.php
    ├── CompanySettings.php
    ├── EmailSettings.php
    └── SessionDefaultsSettings.php
```

### Règles de codage
- **Une Action = une opération métier**. Pas de logique métier dans les contrôleurs ni dans les composants Livewire.
- **Validation via Form Requests** uniquement.
- **Authorization via Policies** sur tous les modèles + Spatie Permission par-dessus.
- **PHP 8.3 features** : property promotion, readonly, enums.
- **PHPStan/Larastan niveau 6 minimum** (config à fournir).
- **Pas de logique business dans les models**, uniquement des relations, accessors/mutators et scopes.

### Séparation admin / membre
- L'admin tourne sur `/admin` via **Filament**.
- L'espace membre tourne sur `/espace` via Blade + Livewire.
- Les deux partagent les mêmes Models et Actions.

---

## 7. Liste exhaustive des paramètres

À implémenter via `spatie/laravel-settings`. Une classe par groupe.

### `BookingSettings`
- `registration_deadline_hours: int` (défaut 24)
- `cancellation_deadline_business_days: int` (défaut 3)
- `max_registrations_per_week: int` (défaut 1)
- `max_future_registrations: int` (défaut 3)
- `post_cancellation_card_extension_days: int` (défaut 30)
- `post_cancellation_extension_threshold_days: int` (défaut 30)
- `waitlist_auto_promote: bool` (défaut false)

### `CardSettings`
- `default_validity_months: int` (défaut 12)
- `default_sessions_count: int` (défaut 10)
- `default_price_per_card: float` (défaut 250.00)
- `expiration_warning_days: array` (défaut [30, 7])

### `InvoicingSettings`
- `invoice_number_prefix: string` (défaut "FAC")
- `invoice_number_format: string` (défaut "{prefix}-{year}-{number:05d}")
- `invoice_number_yearly_reset: bool` (défaut true)
- `default_vat_rate: float` (défaut 21.00)
- `vat_exempt: bool` (défaut false)
- `vat_exempt_legal_mention: string` (mention légale si exonéré)
- `payment_terms_days: int` (défaut 0 = paiement immédiat)

### `CompanySettings` (le vendeur, l'école)
- `company_name`, `vat_number`, `rpm`, `legal_form`
- `street`, `postal_code`, `city`, `country`
- `iban`, `bic`, `bank_name`
- `email_contact`, `phone`, `website`
- `logo_path`

### `EmailSettings`
- `from_email`, `from_name`, `reply_to`
- `admin_notifications_email`
- `notifications_enabled: array` (toggle par type de notif)

### `SessionDefaultsSettings`
- `default_duration_minutes: int` (défaut 90)
- `default_location: string`
- `default_max_participants: int` (défaut 8)

### `MollieSettings`
- `api_key: string` (sensible — préférer .env)
- `test_mode: bool`

---

## 8. Routes

### Publiques (`routes/web.php`)
```
GET  /                       → home (présentation, CTA achat)
GET  /agenda                 → liste des tables disponibles (filtrable par niveau)
GET  /tables/{id}            → détail d'une table (description, niveau, etc.)
GET  /tarifs                 → présentation cartes
GET  /contact, /cgv, /confidentialite
```

### Auth (Breeze standard)
```
GET/POST /login, /register, /logout, /password/*
```

### Espace membre (middleware: auth)
```
GET  /espace                 → dashboard
GET  /espace/cartes          → mes cartes (active + historique)
GET  /espace/inscriptions    → à venir / passées
GET  /espace/historique      → toutes les transactions
GET  /espace/factures        → liste
GET  /espace/factures/{id}/pdf → téléchargement PDF
GET  /espace/profil          → coordonnées + société
PUT  /espace/profil          → MAJ coordonnées
GET  /espace/donnees         → export RGPD (JSON)
```

### Achat (auth requis)
```
GET  /achat                  → catalogue card_types
GET  /panier                 → composant Livewire
POST /panier/checkout        → crée order + redirige Mollie
GET  /paiement/retour/{order} → retour Mollie (vérifie statut)
POST /webhooks/mollie        → webhook (CSRF exempt + signature check)
```

### Admin
Géré automatiquement par Filament sur `/admin`.

---

## 9. Jobs et tâches planifiées

### Commande planifiée quotidienne (`schedule:run`)
```php
$schedule->command('app:send-day-before-reminders')->dailyAt('09:00');
$schedule->command('app:send-card-expiration-warnings')->dailyAt('09:30');
$schedule->command('app:expire-cards')->dailyAt('00:05');
$schedule->command('app:mark-no-shows')->dailyAt('23:59');
```

### Jobs déclenchés à l'événement
- `SendRegistrationConfirmation` (queue) — après inscription
- `SendCancellationConfirmation` (queue) — après annulation
- `NotifyAdminOfLevelInterviewNeeded` — première inscription user sans niveau
- `NotifyAdminOfWaitlistOpening` — quand une place se libère
- `NotifyParticipantsOfSessionCancellation` — broadcast aux inscrits
- `GenerateInvoiceJob` (queue) — après confirmation Mollie
- `SendInvoiceByEmail` (queue) — PDF en pièce jointe

### Notifications utilisateur (channel mail + database)
- Achat de carte confirmé (avec facture en PJ)
- Inscription confirmée
- Rappel J-1 d'une session
- Inscription annulée (par lui ou par l'admin)
- Session annulée par l'admin (recrédit signalé)
- Promotion depuis la liste d'attente
- Carte expirant dans 30 jours / 7 jours
- Niveau attribué après entretien

---

## 10. Conformité et sécurité

### RGPD
- Bannière cookies (Cookieconsent JS si pas de tracking, sinon CookieBot)
- Politique de confidentialité accessible
- Bouton "Exporter mes données" dans l'espace membre (JSON)
- Bouton "Supprimer mon compte" → soft delete + anonymisation des données personnelles (garder factures pour obligation légale 7 ans)

### Légal Belgique
- Validation TVA via API VIES (https://ec.europa.eu/taxation_customs/vies/)
- Numéros de facture séquentiels SANS trou
- Mentions légales obligatoires sur factures : RPM, TVA, IBAN, conditions de paiement
- Conservation factures 7 ans minimum

### Sécurité
- HTTPS forcé en prod (`TrustProxies` middleware)
- Webhook Mollie : exempt CSRF + vérification signature côté Mollie
- Rate limiting sur `/login`, `/register`, `/panier/checkout`
- CSP headers
- Logs Mollie isolés (ne pas logger les données sensibles)
- 2FA optionnel pour les admins (Filament le supporte)

---

## 11. Tests (Pest)

### Couverture minimum
- **100% des Actions métier** (RegisterUserToTableAction, CancelRegistrationAction, etc.)
- Tests d'**intégration** sur les flux critiques :
  - Achat carte → paiement Mollie → génération facture
  - Inscription → annulation J-2 ouvrables refusée → annulation J-3 ouvrables OK
  - Admin annule session → toutes les cartes recréditées + extension si applicable
- Tests sur **règles anti-monopolisation**
- Tests sur **calcul jours ouvrables** (incluant fériés belges)

### Données de test
- `DatabaseSeeder` complet pour env local : admin, niveaux, card_types, settings, fériés belges, quelques tables fictives, quelques users avec cartes.

---

## 12. Pièges à éviter — Innovations interdites

À Claude Code : ne pas dévier des points suivants sans validation explicite.

1. **Pas de partage de cartes entre utilisateurs** d'une même société. C'est un user, une carte.
2. **Pas de promotion automatique** depuis la liste d'attente. Manuelle uniquement (paramètre prévu pour plus tard).
3. **Numérotation des factures** : un compteur dédié verrouillé en transaction, **jamais** basé sur l'ID auto-increment.
4. **Snapshots immuables** sur orders et invoices : ne JAMAIS modifier les snapshots après création.
5. **Jours ouvrables** : passer par `BusinessDayService`, ne pas recoder le calcul. Inclure les fériés belges (Carnaval, Pâques, Ascension, Pentecôte, fête nationale, Toussaint, Armistice, Noël, etc.).
6. **Pas de delete dur** sur les modèles métier. Soft deletes partout.
7. **Pas de logique business dans les Models** ni dans les contrôleurs. Toute opération = une Action.
8. **Pas de calcul de TVA en dur** : passer par les settings (`default_vat_rate`, `vat_exempt`).
9. **Webhook Mollie idempotent** : recevoir 2x le même webhook ne doit pas générer 2 factures.
10. **Pas d'utilisation d'enum string libre** : utiliser des enums PHP 8 typés (`RegistrationStatus`, etc.).

---

## 13. Phases d'implémentation suggérées

### Phase 1 — Fondations
1. Init Laravel + Breeze + Livewire + Filament + Tailwind
2. Installation et config de tous les packages listés
3. Création des migrations dans le bon ordre (companies, users update, levels, card_types, cards, conversation_tables, registrations, orders, order_items, invoices, invoice_counters)
4. Models avec relations + soft deletes
5. Enums
6. Seeders (admin, levels, card_types, settings)

### Phase 2 — Auth et profil
1. Inscription avec création company associée
2. Validation TVA via VIES
3. Espace membre minimal (profil)
4. Filament : ressources User et Company

### Phase 3 — Catalogue et achat
1. Filament : ressource CardType
2. Page publique tarifs
3. Composant Livewire panier
4. Intégration Mollie (création paiement + redirection)
5. Webhook Mollie + GenerateInvoiceAction
6. Génération PDF facture (DOMPDF)

### Phase 4 — Tables et inscriptions
1. Filament : ressource ConversationTable
2. Page agenda public
3. Composant Livewire inscription
4. Actions : RegisterUserToTableAction, CancelRegistrationAction, CheckRegistrationRulesAction
5. BusinessDayService avec fériés belges
6. Espace membre : mes inscriptions

### Phase 5 — Liste d'attente et gestion admin
1. Logique waitlist
2. Filament : interface de déplacement d'inscriptions
3. Action MoveRegistrationAction
4. Notifications admin

### Phase 6 — Annulations admin et automation
1. CancelSessionAction (avec recrédit + extension)
2. ExpireCardAction (commande planifiée)
3. MarkNoShowsCommand
4. Tous les rappels automatisés

### Phase 7 — Stats, audit, finitions ✅ TERMINÉE (2026-05-10)
1. Widgets Filament (taux remplissage, revenus, no-show, etc.) ✅
2. Activity Log intégré (ActivityLogResource + RelationManagers User/Card) ✅
3. Apparence/theming (ThemeSettings, 4 designs cartes, CSS variables, WCAG) ✅
4. CSP Spatie v3, rate limiting (login/checkout/account-deletion) ✅
5. Tests Pest complets — 320 tests verts ✅

**Non implémenté en Phase 7 (backlog Phase 8)** :
- 2FA admin (Filament 5 sans natif, estimation > 2h)

---

### Phase 8 — Déploiement (à venir)

1. Configuration serveur production (HTTPS, TrustProxies, env prod)
2. 2FA pour les admins (`DanHarrin/filament-two-factor-authentication` ou équivalent)
3. `php artisan storage:link` + S3/bucket pour PDF factures
4. Mise en place monitoring (Sentry ou equivalent)
5. Tests de charge minimal (checkout, webhooks Mollie)
6. Sauvegarde automatique (`spatie/laravel-backup`)

---

## 14. Questions encore ouvertes

À trancher avant Phase 3 :
- **Régime TVA exact** : 21% standard ou exonération article XX du Code TVA pour cours de langue ? À confirmer avec le comptable du client.
- **IBAN, BIC, RPM** du vendeur à fournir pour les settings.
- **Logo** et identité visuelle.
- **CGV** rédigées (le client s'en occupe, pas le développeur).

À trancher plus tard (post-MVP) :
- Activation du rôle "animateur"
- Activation de la promotion automatique liste d'attente
- Multi-localisation des sessions
- Newsletter

---

**Fin du brief.** Ce document est la source de vérité du projet. En cas de divergence avec un échange ultérieur, c'est ce document qui fait foi sauf mise à jour explicite.
