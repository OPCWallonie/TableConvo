# Brief Phase 7 — Stats, audit, theming et finitions

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`, et les briefs `PHASE_3_BRIEF.md` à `PHASE_6_BRIEF.md` ainsi que `CORRECTION_PHASES_1_2_BRIEF.md`. Il est la source de vérité de la Phase 7.
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **Cette phase est phasée en cinq étapes (A à E) avec des checkpoints STOP**, comme les phases précédentes.
> **Prérequis** : le brief de correction Phases 1-2 doit avoir été traité et validé avant de démarrer cette phase. Si ce n'est pas le cas, tu STOPpes immédiatement et tu me le signales.

---

## Préambule — Méthodologie de phasing (rappel)

Cette phase suit la même méthode que les Phases 4, 5 et 6 :

1. **Tu lis intégralement ce brief avant de coder.** Tu confirmes ta compréhension et tu signales tout point ambigu avant de commencer.
2. **Tu attends mon GO A explicite** avant de démarrer la première étape.
3. **À chaque STOP**, tu produis un rapport au format demandé et tu **attends mon GO suivant écrit**. Sauter un STOP = rollback du travail post-STOP.
4. **Si tu trouves un écart entre ce brief et la réalité du code**, tu le signales explicitement avant d'agir.
5. **Tu commits étape par étape** avec un message clair (ex: `feat: Filament dashboard widgets`).

---

## 0. État du projet à l'entrée de Phase 7

À ce stade :
- Phases 1 à 6 livrées + brief de correction Phases 1-2 traité
- **259 tests Pest verts** (cible confirmée — brief de correction Phases 1-2 traité)
- Stack : Laravel 11+, Filament v5, Livewire, Pest, Spatie suite, Mollie SDK
- Toute la logique métier est en place (cartes, sessions, registrations, paiements, annulations, automation, RGPD basique, sécurité fondamentale)

### Périmètre Phase 7

Cette phase est orientée **finition** et **valeur perçue** :

- **A — Dashboard et stats** : widgets Filament pour le pilotage opérationnel
- **B — Audit côté admin** : visualisation et exploitation des activity logs
- **C — Theming** : couleurs personnalisables + carte virtuelle avec choix de design
- **D — Sécurité** : CSP headers, rate limiting généralisé, 2FA admin optionnel
- **E — Tests, polish et validation** : montée en couverture, walkthrough complet

**Hors périmètre Phase 7** (réservé Phase 8) : déploiement, configuration serveur, supervisor, queue worker en prod, SMTP, cron Linux, monitoring, sauvegardes, README de déploiement, runbooks ops.

### Ce qui existe déjà (à NE PAS recréer)

| Élément | État |
|---------|------|
| `Spatie\Activitylog` | Installé et utilisé partout depuis Phase 1 — il faut juste le **visualiser** côté admin en étape B |
| `Spatie\LaravelSettings` | Pages Filament en place (CardSettings, BookingSettings, etc.) — étape C ajoutera `ThemeSettings` |
| Composants Blade espace membre | À adapter en étape C pour utiliser le composant `<x-card-display>` au lieu de leur rendu actuel de carte |
| Mockups carte virtuelle (4 designs) | Disponibles à 80% — à reprendre, finaliser et intégrer en étape C |

### Action requise au démarrage

Tu lances `php artisan test`. Tu rapportes :
- Le nombre total de tests verts (cible confirmée : 259)
- Tout test qui échoue
- Confirmation que le brief de correction Phases 1-2 a bien été traité (questions 1 à 8 de sa section 4 toutes répondues OUI). Si non, STOP immédiat.

Tu attends mon GO A avant de toucher quoi que ce soit.

---

## 1. Décisions tranchées (à appliquer sans discussion)

1. **Widgets dashboard** : 4 widgets en première itération, pas plus. Privilégier la lisibilité à l'exhaustivité. Mesures additionnelles ajoutables ultérieurement.

2. **Activity Log côté admin** : on **expose** les logs existants via une Resource Filament en lecture seule. **Aucune création/édition manuelle de logs** — ils sont alimentés par les Actions métier uniquement.

3. **Theming** : on supporte **4 designs de carte au choix admin** (`stamp`, `wallet`, `editorial`, `swiss`) + **3 couleurs personnalisables** (primary, accent, surface) via ColorPicker Filament. Default `stamp` pour la carte (à confirmer si tes collègues tranchent autrement). Les couleurs par défaut sont les couleurs actuelles du projet (à extraire du code existant en étape C).

4. **Garde-fou WCAG** : sur la page de theming, calcul live du ratio de contraste entre les couleurs choisies et les couleurs systèmes (texte sur primary, texte sur surface). Si ratio < 4.5:1 → **avertissement visuel** (badge orange), pas de blocage. L'admin reste libre de sauvegarder, mais il est informé.

5. **Sécurité** :
   - **CSP headers** via `spatie/laravel-csp` (nouveau package à installer en étape D)
   - **Rate limiting** sur `/login`, `/register`, `/panier/checkout`, et `DELETE /espace/compte`
   - **2FA admin** : optionnel, désactivé par défaut, activable depuis le profil Filament admin

6. **CSS variables** : injection au niveau du layout principal Blade (`resources/views/layouts/app.blade.php` ou équivalent) via une balise `<style>` inline générée à partir des `ThemeSettings`. **Pas de fichier CSS recompilé** par changement de thème — c'est inline pour rester compatible avec la prod sans build step.

7. **Aucun changement de composant Filament admin existant** ne doit casser le rendu admin actuel. Les CSS variables sont scopées au front public + espace membre, pas à l'admin Filament (qui a son propre theming via Filament Panel Builder).

8. **Toutes les vues créées en Phase 7** continuent de respecter la convention sémantique de Phase 6 (classes `bg-primary`, `text-accent`, etc.). Les tests doivent vérifier que le rendu ne contient pas de couleurs littérales en hexadécimal hardcodées dans les classes.

---

## 2. Étape A — Dashboard et widgets stats

### A.1 — Widgets à créer

Quatre widgets dans `app/Filament/Widgets/`, affichés sur le dashboard Filament admin par défaut.

#### A.1.1 — `OperationalStatsWidget` (StatsOverviewWidget)

Hérite de `Filament\Widgets\StatsOverviewWidget`. Affiche 4 stats en cartes :

- **Sessions à venir cette semaine** : `ConversationTable::where('status', SessionStatus::Scheduled)->whereBetween('scheduled_at', [now(), now()->endOfWeek()])->count()`
- **Inscriptions en cours** : `Registration::where('status', RegistrationStatus::Registered)->whereHas('conversationTable', fn($q) => $q->where('scheduled_at', '>', now()))->count()`
- **Cartes actives** : `Card::where('status', CardStatus::Active)->count()`
- **Revenus du mois (HT)** : `Order::where('status', OrderStatus::Paid)->whereMonth('paid_at', now()->month)->sum('total_ht')` formaté en EUR

Chaque stat avec une icône heroicon, une description succincte, et une couleur sémantique (success/warning/danger selon contexte).

#### A.1.2 — `SessionFillRateChartWidget` (ChartWidget)

Hérite de `Filament\Widgets\ChartWidget`. Type bar.

- Axe X : 12 dernières semaines (label "S{numéro}").
- Axe Y : taux de remplissage moyen des sessions Completed de chaque semaine, en pourcentage.
- Calcul : pour chaque session Completed, `(registrations_attended / max_participants) * 100`. Moyenne pondérée sur la semaine.
- Couleur : `--color-primary` via CSS variable, fallback sur une couleur Tailwind sémantique.

#### A.1.3 — `NoShowRateWidget` (StatsOverviewWidget, single stat)

Affiche le taux de no-show sur les **30 derniers jours** :

- Numérateur : `Registration::where('status', RegistrationStatus::NoShow)->whereHas('conversationTable', fn($q) => $q->where('scheduled_at', '>', now()->subDays(30)))->count()`
- Dénominateur : `Registration::whereIn('status', [Registered, NoShow, Attended])->whereHas('conversationTable', fn($q) => $q->where('scheduled_at', '>', now()->subDays(30)))->count()`
- Format : pourcentage avec 1 décimale, badge couleur (vert si <10%, orange 10-20%, rouge >20%)

#### A.1.4 — `RevenueChartWidget` (ChartWidget)

Type line. 12 derniers mois.

- Axe X : 12 derniers mois (label "MMM YY").
- Axe Y : revenus HT en EUR par mois (sum des orders Paid).

### A.2 — Authorization

Tous les widgets : `public static function canView(): bool { return auth()->user()?->hasRole('admin') ?? false; }`.

### A.3 — Tests à écrire

`tests/Feature/Filament/Widgets/OperationalStatsWidgetTest.php` :

```
- displays correct count of upcoming sessions this week
- displays correct count of active registrations
- displays correct count of active cards
- displays correct revenue for current month (only Paid orders counted)
- excludes orders from previous months
- non-admin users cannot view the widget
```

`tests/Feature/Filament/Widgets/SessionFillRateChartWidgetTest.php` :

```
- computes fill rate per week correctly
- ignores Cancelled and Scheduled sessions (only Completed)
- handles weeks with no sessions (returns 0 or null gracefully)
```

`tests/Feature/Filament/Widgets/NoShowRateWidgetTest.php` :

```
- computes no-show rate correctly with mixed registrations
- returns 0 if no registrations in last 30 days
- color thresholds respected (green / orange / red)
```

`tests/Feature/Filament/Widgets/RevenueChartWidgetTest.php` :

```
- aggregates Paid orders by month
- excludes Pending and Failed orders
- 12-month window respected
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~265)                     ║
### ║  2. Capture/description de chaque widget rendu sur le     ║
### ║     dashboard admin (avec données de test seedées)        ║
### ║  3. Confirmation que les widgets sont scopés admin only   ║
### ║                                                           ║
### ║  Tu attends mon GO B écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape B — Audit côté admin (Activity Log)

### B.1 — Resource Filament `ActivityLogResource`

`app/Filament/Resources/ActivityLogResource.php` — lecture seule, pas de Create/Edit/Delete.

Le model est `Spatie\Activitylog\Models\Activity`.

**Colonnes de la table** :
- `created_at` (formaté `d/m/Y H:i:s`, sortable, default sort desc)
- `description` (la description du log, traduisible si possible)
- `subject_type` (badge avec le nom du model raccourci, ex: "User", "Card")
- `subject_id`
- `causer_name` (accessor à créer si pas déjà : nom du user qui a déclenché, ou "Système" si null)
- `event` (created/updated/deleted/custom — badge de couleur)

**Filtres** :
- Sur `subject_type` (Select avec les types présents en DB)
- Sur `causer_id` (Select user)
- Sur `event` (Select)
- Sur `created_at` (date range filter)

**Action de ligne** : "Voir détails" → modale qui affiche `properties` (JSON formatté lisible : avant/après, contexte) et `causer_id`/`subject_id` cliquables pour rebondir vers les Resources concernées.

**Authorization** : admin uniquement.

**Pagination** : 50 par page (les logs sont volumineux).

### B.2 — Page timeline par entité

Dans `UserResource` (et idéalement aussi `CardResource`, `ConversationTableResource`, `OrderResource`), ajouter une **RelationManager** ou un **custom tab** "Historique" qui affiche les activity logs liés à cette entité (`subject_type = User::class AND subject_id = $record->id`).

Format léger : liste verticale, dernier en haut, max 50 entrées, avec lien "Voir tous les logs" → redirige vers la `ActivityLogResource` filtrée sur cette entité.

Pour cette première itération : implémenter au minimum sur `UserResource` et `CardResource`. Les autres pourront suivre dans une itération ultérieure.

### B.3 — Index de performance

Vérifier qu'il existe des index sur la table `activity_log` pour les colonnes les plus filtrées. Spatie ActivityLog crée déjà les bons index par défaut (`subject_type, subject_id`, `causer_type, causer_id`). Si une migration projet a recréé la table sans ces index, ajouter une migration de correction.

### B.4 — Tests à écrire

`tests/Feature/Filament/Resources/ActivityLogResourceTest.php` :

```
- admin can list activity logs
- admin can filter by subject_type
- admin can filter by causer_id
- admin can filter by event
- admin can filter by date range
- non-admin users get 403 on the resource
- view details modal displays properties JSON readably
```

`tests/Feature/Filament/Resources/UserResourceTimelineTest.php` :

```
- timeline shows the activity logs for a given user
- timeline excludes logs for other users
- timeline limit of 50 entries respected
- "Voir tous les logs" link points to the filtered ActivityLogResource
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~277)                     ║
### ║  2. Walkthrough manuel de la Resource ActivityLogResource ║
### ║     avec utilisation des filtres et de la modale          ║
### ║  3. Walkthrough de la timeline sur UserResource           ║
### ║                                                           ║
### ║  Tu attends mon GO C écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Étape C — Theming et carte virtuelle

C'est l'étape la plus visuelle de la phase. Lis attentivement.

### C.1 — `ThemeSettings`

`app/Settings/ThemeSettings.php` :

```php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ThemeSettings extends Settings
{
    public string $color_primary = '#0f766e';     // teal-700, à confirmer avec la couleur actuelle du projet
    public string $color_accent = '#f59e0b';      // amber-500, idem
    public string $color_surface = '#fafaf7';     // beige clair, idem
    public string $card_design = 'stamp';         // stamp | wallet | editorial | swiss

    public static function group(): string
    {
        return 'theme';
    }
}
```

**Important** : avant de fixer ces defaults, tu **inspectes le code Blade existant** pour récupérer les couleurs actuelles du projet et tu remplaces les exemples ci-dessus en conséquence. Le but est qu'à la migration, l'apparence visuelle reste identique au pixel près.

Migration `database/settings/{date}_create_theme_settings.php` :

```php
$this->migrator->add('theme.color_primary', '#0f766e');
$this->migrator->add('theme.color_accent', '#f59e0b');
$this->migrator->add('theme.color_surface', '#fafaf7');
$this->migrator->add('theme.card_design', 'stamp');
```

### C.2 — Page Filament `ManageThemeSettings`

Sur le modèle des autres pages de settings.

**Section "Couleurs"** :
- 3 `ColorPicker` (composant natif Filament) pour les 3 couleurs.
- Helper text expliquant le rôle de chaque couleur ("Primaire : boutons d'action, liens, états actifs", etc.).

**Section "Contraste WCAG"** : un bloc en lecture seule qui calcule live le ratio de contraste. Implémentation suggérée : un Livewire component léger ou une `Placeholder` Filament avec `->content(fn () => $this->computeContrastRatios())`.
- Ratio "Texte foncé sur fond surface" : doit être ≥ 4.5:1
- Ratio "Texte clair sur fond primary" : doit être ≥ 4.5:1
- Si ratio < 4.5:1 → badge orange "Contraste insuffisant pour respecter WCAG AA". Pas de blocage, juste un avertissement.

**Section "Design de carte virtuelle"** :
- `Select::make('card_design')->options(['stamp' => 'Tampon (skeuomorphique)', 'wallet' => 'Wallet (moderne)', 'editorial' => 'Éditorial (haut de gamme)', 'swiss' => 'Swiss (minimaliste)'])->required()`.
- Sous le select, un **aperçu live** de la carte avec le design choisi et les couleurs configurées. Implémentation : composant Livewire `CardDesignPreview` qui re-render quand le select change.

### C.3 — Composant Blade `<x-card-display>`

`resources/views/components/card-display.blade.php` :

```blade
@props(['card', 'design' => null])

@php
    $design = $design ?? app(\App\Settings\ThemeSettings::class)->card_design;
@endphp

@switch($design)
    @case('stamp')
        @include('components.cards.stamp', ['card' => $card])
        @break
    @case('wallet')
        @include('components.cards.wallet', ['card' => $card])
        @break
    @case('editorial')
        @include('components.cards.editorial', ['card' => $card])
        @break
    @case('swiss')
        @include('components.cards.swiss', ['card' => $card])
        @break
    @default
        @include('components.cards.stamp', ['card' => $card])
@endswitch
```

**4 partials** dans `resources/views/components/cards/` : `stamp.blade.php`, `wallet.blade.php`, `editorial.blade.php`, `swiss.blade.php`.

Chaque partial reçoit `$card` (Model `Card` complet, avec relations `cardType` et `user`) et affiche :
- Le nom du card type
- Le nombre de séances utilisées et restantes
- Une visualisation graphique adaptée au design (poinçons, pastilles, barres, etc.)
- La date d'expiration
- Le statut (actif, expiré, etc.) si non actif

**Les mockups existent à 80%** dans la conversation d'origine. Tu reprends le HTML/CSS de chaque design en l'adaptant pour utiliser les CSS variables (`var(--color-primary)`, etc.) et les classes Tailwind sémantiques. Pas de couleurs hexadécimales hardcodées dans les partials.

**Si tu n'as pas accès aux mockups d'origine** : tu codes les 4 partials de zéro en suivant les descriptions sémantiques :
- **stamp** : carte type papier kraft (texture beige), grille de cases avec tampons rouges en biais sur les cases utilisées, typo Caveat ou Fraunces pour le titre.
- **wallet** : rectangle dégradé de la couleur primary, coins arrondis style Apple Wallet, pastilles dorées (couleur accent) pour les sessions, typo Inter.
- **editorial** : style papeterie, ombre brutaliste (offset 4px noir), typo Fraunces, layout aéré.
- **swiss** : ultra-minimaliste, barres horizontales, typo JetBrains Mono pour le compteur, beaucoup de blanc.

### C.4 — Injection des CSS variables

Dans `resources/views/layouts/app.blade.php` (ou le layout équivalent du front public + espace membre, **PAS** le layout admin Filament), ajouter dans le `<head>` :

```blade
@php
    $theme = app(\App\Settings\ThemeSettings::class);
@endphp
<style>
    :root {
        --color-primary: {{ $theme->color_primary }};
        --color-accent: {{ $theme->color_accent }};
        --color-surface: {{ $theme->color_surface }};
    }
</style>
```

### C.5 — Configuration Tailwind

Dans `tailwind.config.js`, étendre les couleurs pour qu'elles consomment les CSS variables :

```js
theme: {
  extend: {
    colors: {
      primary: 'var(--color-primary)',
      accent: 'var(--color-accent)',
      surface: 'var(--color-surface)',
    },
  },
},
```

**Vérification** : après cette modification, les classes `bg-primary`, `text-accent`, `border-surface` etc. utilisées dans les vues depuis Phase 6 doivent automatiquement consommer les couleurs définies par les settings. Aucune autre vue ne doit nécessiter de modification.

### C.6 — Branchement dans l'espace membre

Dans `/espace/cartes` (page liste des cartes du user), remplacer l'affichage actuel des cartes par `<x-card-display :card="$card" />`. Idem partout où une carte est affichée individuellement.

### C.7 — Tests à écrire

`tests/Feature/Filament/Pages/ManageThemeSettingsTest.php` :

```
- admin can access the page
- non-admin gets 403
- admin can save a new color (primary)
- admin can save a new card design
- saving an invalid card design value (not in enum list) is rejected
- contrast ratio is computed and displayed
- contrast warning appears when ratio < 4.5:1
```

`tests/Feature/Components/CardDisplayTest.php` :

```
- renders stamp design when ThemeSettings::card_design = 'stamp'
- renders wallet design when ThemeSettings::card_design = 'wallet'
- renders editorial when 'editorial'
- renders swiss when 'swiss'
- design override prop forces a specific design regardless of settings
- renders the correct number of remaining sessions
- shows expired state correctly when card is expired
```

`tests/Feature/Theming/CssVariablesInjectionTest.php` :

```
- a public page contains the CSS variables in the head
- changing ThemeSettings::color_primary reflects on next page load
- admin Filament pages do NOT contain these CSS variables (scope check)
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — CHECKPOINT 3                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~295)                     ║
### ║  2. Captures (en description) des 4 designs de carte      ║
### ║     dans leurs 4 états (neuve, mi-utilisée, presque       ║
### ║     finie, expirée) — idéalement via screenshot manuel    ║
### ║  3. Démo de modification des couleurs primary depuis      ║
### ║     ManageThemeSettings → effet visible immédiat dans     ║
### ║     /espace/cartes                                        ║
### ║  4. Vérification que l'admin Filament n'est pas affecté   ║
### ║     par le changement de couleur                          ║
### ║                                                           ║
### ║  Tu attends mon GO D écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Étape D — Sécurité

### D.1 — CSP headers

Installer `spatie/laravel-csp` :

```bash
composer require spatie/laravel-csp
php artisan vendor:publish --tag=csp-config
```

Créer une policy CSP custom adaptée au projet : `app/Support/Csp/AppCspPolicy.php` héritant de `Spatie\Csp\Policies\Basic` ou `Strict`.

**Directives à autoriser** :
- `default-src 'self'`
- `script-src 'self'` + 'unsafe-inline' uniquement pour les scripts inline Filament et Livewire (à minimiser)
- `style-src 'self' 'unsafe-inline'` (nécessaire pour les CSS variables inline et Tailwind)
- `img-src 'self' data:` (pour les avatars et SVG inline)
- `font-src 'self' fonts.gstatic.com fonts.googleapis.com` si Google Fonts utilisé
- `connect-src 'self'` + Mollie si appels client-side (à vérifier — si webhook only, pas besoin)
- `frame-ancestors 'none'` (anti-clickjacking)

**Activation** : middleware `\Spatie\Csp\AddCspHeaders::class` dans `bootstrap/app.php` sur le groupe `web`. Pas sur les routes API/webhook.

### D.2 — Rate limiting généralisé

Dans `bootstrap/app.php` ou `app/Providers/AppServiceProvider.php`, définir des rate limiters nommés :

```php
RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
RateLimiter::for('account-deletion', fn (Request $request) => Limit::perMinute(3)->by($request->user()?->id));
```

Appliquer ces limiters via le middleware `throttle:login`, `throttle:register`, etc. sur les routes correspondantes dans `routes/web.php`.

**Vérifier** que les rate limiters de Phase 1-2 (mentionnés dans le brief de correction sur le `register`) ne sont pas dupliqués — fusionner si besoin.

### D.3 — 2FA admin optionnel

Filament v5 supporte la 2FA via `filament/filament-2fa` (si officiel disponible) ou via `pragmarx/google2fa-laravel` + intégration manuelle.

**Approche recommandée pour cette phase** : implémentation **simple** :
- Table `user_two_factor_secrets` (ou colonnes ajoutées sur `users` : `two_factor_secret` encrypted, `two_factor_confirmed_at`).
- Page Filament dans le profil admin pour activer/désactiver la 2FA (génère un QR code, demande confirmation par un code).
- Middleware sur le panel Filament admin qui vérifie la 2FA si elle est activée pour cet user.
- Si l'admin n'a pas activé la 2FA, comportement inchangé (login simple).

**Tests** :
- 2FA désactivée : login normal fonctionne.
- 2FA activée : login demande le code, code valide → admin entre, code invalide → reste sur la page de challenge.
- Désactivation possible avec confirmation par mot de passe + code 2FA.

**Si l'implémentation s'avère trop complexe en moins de 2h**, tu STOPpes et tu me proposes de la pousser à une itération ultérieure. La 2FA n'est pas un bloqueur de mise en production.

### D.4 — Audit des middlewares de sécurité existants

Vérifier que les middlewares standards sont actifs sur les routes appropriées :
- `VerifyCsrfToken` partout sauf webhooks (déjà fait en Phase 3)
- `EncryptCookies` global
- `TrustProxies` configuré pour la prod (à vérifier en Phase 8 mais structure préparée ici)
- Headers de sécurité de base (`X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security` en prod) — `spatie/laravel-csp` en couvre une partie, le reste via un middleware custom léger ou `bepsvpt/secure-headers`.

### D.5 — Tests à écrire

`tests/Feature/Security/CspHeadersTest.php` :

```
- public pages have a Content-Security-Policy header
- the CSP header includes default-src 'self'
- API/webhook routes do NOT have the CSP header
```

`tests/Feature/Security/RateLimitingTest.php` :

```
- 6th login attempt within a minute returns 429
- 6th register attempt within a minute returns 429
- 11th checkout attempt within a minute returns 429
- 4th account deletion attempt within a minute returns 429
- counters reset after the rate limit window
```

`tests/Feature/Security/TwoFactorAuthTest.php` (si implémenté) :

```
- admin can enable 2FA and receives a secret
- admin login with 2FA enabled requires the OTP code
- valid OTP code logs the admin in
- invalid OTP code blocks login
- admin can disable 2FA after confirming password and OTP
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP D — CHECKPOINT 4                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible : ~310)                     ║
### ║  2. Sortie de `curl -I` sur une page publique pour        ║
### ║     prouver la présence du Content-Security-Policy header ║
### ║  3. Résultat des 4 rate limiters testés manuellement      ║
### ║  4. Statut de la 2FA admin : implémentée OK / partielle / ║
### ║     reportée (si reportée, justification)                 ║
### ║                                                           ║
### ║  Tu attends mon GO E écrit avant la dernière étape.       ║
### ╚══════════════════════════════════════════════════════════╝

---

## 6. Étape E — Tests, polish et validation finale

### E.1 — Tests d'intégration cross-phase

`tests/Feature/Integration/EndToEndUserJourneyTest.php` :

```
1. complete user journey from registration to attended session
   - register a new user with company VAT (clean, not hijacked)
   - admin manually activates the user (if needed by the flow)
   - user buys a card via Mollie stub mode → invoice generated
   - user registers to a session → registration confirmed
   - 23h before the session → reminder email triggered (sessions:send-reminders)
   - admin marks user as Attended → session Completed
   - card sessions_remaining decremented by 1

2. complete admin cancellation journey
   - 4 users registered to a session (mix of card states)
   - admin cancels the session via Filament with a reason
   - all 4 receive their compensation_type-correct notification
   - activity log shows the cancellation properly

3. card lifecycle from purchase to expiration
   - user buys a card
   - card is Active
   - simulate time travel to 30 days before expiry → warning email sent
   - simulate time travel to 7 days before expiry → second warning email sent
   - simulate expiry → cards:expire marks the card Expired
```

### E.2 — Polish visuel

Walkthrough manuel de toutes les pages publiques + espace membre, à la recherche de :
- Couleurs littérales hardcodées non détectées (faire un `grep` de `bg-emerald-`, `text-orange-`, etc. — devraient être absents des nouvelles vues)
- Liens cassés
- Toasts manquants ou inadéquats
- Erreurs JS dans la console
- Responsivité mobile basique (le projet n'est pas mobile-first mais doit rester utilisable)

### E.3 — Couverture finale

Lancer `php artisan test --coverage` (si Xdebug/pcov dispo). Vérifier :
- Couverture globale ≥ 75%
- Aucune Action métier <70%
- Aucune Notification non testée (au moins assertSent)
- Aucune commande Console non testée

Si la couverture n'atteint pas la cible, ajouter les tests manquants prioritaires.

**Actions sans test dédié au début de Phase 7 (à couvrir en priorité dans cette étape) :**

- `AssignLevelAction` — appelée par le Filament UserResource (assignation manuelle du niveau par l'admin). Écrire `tests/Feature/Actions/User/AssignLevelActionTest.php` : au moins 3 cas (niveau assigné, `level_assigned_at` renseigné, activity log tracé).
- `RequestLevelInterviewAction` — déclenchée lors de la première tentative d'inscription sans niveau. Écrire `tests/Feature/Actions/User/RequestLevelInterviewActionTest.php` : au moins 2 cas (notification admin envoyée, action idempotente si déjà demandée).

### E.4 — Documentation à jour

Mettre à jour :
- `CLAUDE.md` : retirer les mentions de fonctionnalités "à faire en Phase 7" maintenant terminées, ajouter une section "Phase 8 — Déploiement" en TODO.
- `README.md` du projet : à compléter avec les commandes courantes (`php artisan test`, `php artisan migrate`, etc.) et un index des briefs de phase pour la traçabilité historique.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP E — RÉCAP DE FIN DE PHASE 7                        ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Récap au format de la section 8 ci-dessous,             ║
### ║  réponse aux 10 questions de validation.                  ║
### ╚══════════════════════════════════════════════════════════╝

---

## 7. Pièges spécifiques Phase 7 — INTERDICTIONS

1. **NE PAS toucher au theming admin Filament.** Le thème customisable concerne uniquement le front public + espace membre. Filament admin garde son apparence native.
2. **NE PAS écrire de couleurs hexadécimales hardcodées** dans les nouvelles vues. Toujours `bg-primary`, `text-accent`, etc. (via Tailwind config qui consomme les CSS variables).
3. **NE PAS bloquer la sauvegarde du thème** sur l'avertissement WCAG. Le warning informe, n'empêche pas.
4. **NE PAS supprimer ou modifier d'activity logs existants.** La Resource est en lecture seule. Aucune action de mass-delete.
5. **NE PAS réécrire les widgets pour qu'ils requêtent à chaque rendu sans cache.** Si les calculs de SessionFillRate ou Revenue sont coûteux, mettre un cache de 5 minutes (`Cache::remember('widget.fill_rate', 300, fn () => ...)`).
6. **NE PAS oublier que les CSS variables inline sont régénérées à chaque pageload** (pas de fichier statique compilé). Donc tout changement de thème est visible **immédiatement** sans re-build, ce qui est un avantage à conserver.
7. **NE PAS introduire de dépendance JS lourde** pour les widgets charts. Filament fournit déjà Chart.js via ses widgets natifs — l'utiliser, pas en ajouter une autre.
8. **NE PAS faire la 2FA si elle nécessite plus de 2h de dev.** STOPper et la reporter à une itération future. C'est un bonus, pas un bloqueur.
9. **NE PAS sauter les STOP.**
10. **NE PAS fusionner étape D avec étape E.** Les tests de bout en bout ont besoin que la sécurité soit en place pour être réalistes.

---

## 8. Validation de fin de Phase 7

Avant de déclarer Phase 7 terminée, tu fournis un récap répondant à TOUTES ces questions :

1. Combien de tests passent au total ? (cible : ~310-320)
2. Les 4 widgets dashboard sont-ils visibles, fonctionnels, et restreints à l'admin ?
3. La Resource ActivityLogResource permet-elle de filtrer par subject_type, causer, event, et date range ?
4. La timeline d'audit fonctionne-t-elle sur UserResource et CardResource ?
5. Les `ThemeSettings` permettent-ils de modifier les 3 couleurs et le design de carte sans casser le rendu ?
6. Le composant `<x-card-display>` rend-il bien les 4 designs avec les couleurs configurées ?
7. L'avertissement WCAG s'affiche-t-il correctement quand le contraste est insuffisant, sans bloquer la sauvegarde ?
8. Les CSP headers sont-ils présents sur les routes web et absents des routes webhook/API ?
9. Les 4 rate limiters (login, register, checkout, account deletion) sont-ils actifs et testés ?
10. La 2FA admin est-elle implémentée, partiellement, ou explicitement reportée avec justification ?

---

## 9. Premier prompt à donner à Claude Code

```
Lis intégralement docs/PHASE_7_BRIEF.md, CLAUDE.md, FILAMENT_5_BRIEF.md 
et tous les briefs précédents (PHASE_3 à PHASE_6 + 
CORRECTION_PHASES_1_2_BRIEF) avant toute action.

Confirme que tu as compris :
1. Que le brief de correction Phases 1-2 doit avoir été traité 
   ENTIÈREMENT avant cette phase (vérifie l'état réel et signale-moi 
   si ce n'est pas le cas)
2. Le périmètre Phase 7 : widgets, audit, theming, sécurité non-deploy. 
   Le déploiement est explicitement HORS PERIMETRE (Phase 8 dédiée).
3. Les décisions tranchées (notamment : 4 designs de carte au choix 
   admin, theming UNIQUEMENT sur front public + espace membre, jamais 
   sur Filament admin, WCAG en avertissement non bloquant)
4. La méthodologie STOP/GO (5 étapes, GO X explicite à chaque checkpoint)

Lance ensuite `php artisan test` et rapporte le total de tests verts 
(cible confirmée : 259).

Vérifie également l'état du code par rapport aux 8 points du brief de 
correction Phases 1-2. Si certains ne sont pas traités, signale-le.

Indique tout point ambigu du brief avant de démarrer.
Attends mon GO A explicite avant de toucher au code.
```

---

**Fin du brief Phase 7.**
