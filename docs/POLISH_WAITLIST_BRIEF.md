# Brief — Polish UI cartes & Liste d'attente globale

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`, et tous les briefs précédents (`PHASE_3` → `PHASE_7` + `CORRECTION_PHASES_1_2` + `HOTFIX_CSP`). Il livre la dernière passe de polish avant la Phase 8 (déploiement).
> À Claude Code : **lis intégralement avant la moindre ligne de code**. **3 étapes (A à C) avec checkpoints STOP**, méthodologie classique.

---

## Préambule — Méthodologie de phasing (rappel)

1. Tu lis intégralement le brief avant de coder.
2. Tu attends mon GO A explicite avant de démarrer.
3. À chaque STOP, tu rapportes au format demandé et tu attends mon GO suivant écrit.
4. Si tu trouves un écart entre ce brief et la réalité du code, tu signales avant d'agir.
5. Tu commits étape par étape.

---

## 0. État du projet à l'entrée

- Phases 1-7 livrées + brief correction Phase 1-2 + hotfix CSP appliqués
- **320 tests Pest verts** (cible à confirmer au démarrage par `php artisan test`)
- Designs de carte actuels : **les 4 sont visuellement cassés** (vérifié manuellement par Arnaud sur les 4 designs dans `ManageThemeSettings`). Ce brief les remplace tous.
- Modale "Inscrits" sur `ConversationTableResource` : **HTML sans styling** (texte brut empilé sans table ni boutons). À restyler complètement.
- Liste d'attente : actuellement par-session (FIFO Phase 5), pas de vue globale. Nouvelle feature à créer.

### Action requise au démarrage

`php artisan test` → confirme le total (cible 320). Signale tout écart avec ce brief sur l'état réel du code.

---

## 1. Décisions tranchées

### Designs de carte

1. **Les 4 partials Blade existants sont à supprimer intégralement** et remplacés par les nouveaux fournis ci-dessous dans le brief (HTML clé en main).
2. **Default `ThemeSettings::card_design` passe de `stamp` à `wallet`** (Arnaud a tranché — Wallet est le plus "pro B2B" des 4).
3. **Pas de migration forcée** : les setups existants gardent leur valeur. Le default `wallet` ne s'applique qu'aux nouvelles installations ou aux reset.
4. **Format strict 540×330 pixels** pour les 4 designs (responsive : la carte garde son aspect-ratio mais peut être scalée par CSS dans le conteneur parent si l'espace mobile l'exige).
5. **Logo "TC"** : monogramme placeholder en typo serif dans un carré bleu marine (ou variation selon le design). Pas de vrai logo pour l'instant — sera remplacé plus tard si Arnaud fournit un fichier.
6. **Nombre de séances par défaut affiché** : 10. Pour les autres totaux (5, 12, 20...), voir section A.7 ci-dessous.

### Modale "Inscrits"

7. **Restyling complet** en table Tailwind avec sections claires, boutons stylisés et hiérarchie visuelle. Pas de changement logique métier — uniquement présentation.

### Liste d'attente globale

8. **Nouvelle Resource Filament `WaitlistResource`** (pas un widget) listant toutes les inscriptions en `status = Waitlist` toutes sessions confondues.
9. **Action "Réorienter"** : Select des sessions futures du **même niveau exact** que l'inscription. Chaque option affiche son statut places libres / en attente.
10. **Position dans la nouvelle waitlist** : à la queue (FIFO préservé, cohérent avec promotion FIFO de Phase 5).
11. **Notification user** : oui, mail "Réorientation vers session X".
12. **Tri par défaut** sur les inscriptions waitlist les plus anciennes en haut.
13. **L'action `MoveRegistrationAction`** existe déjà depuis Phase 5 — la réutiliser, **ne pas créer de doublon**.

### Style des nouvelles vues

14. **Toutes les nouvelles vues** continuent d'utiliser les classes Tailwind sémantiques (`bg-primary`, `text-accent`, `border-surface`) plutôt que des couleurs littérales. **Exception unique** : les couleurs système (rouge erreur, vert succès, ambre warning, gris neutre) restent en classes littérales.

---

## 2. Étape A — Refonte des 4 designs de carte virtuelle

### A.1 — Le composant `<x-card-display>` (existant, à NE PAS modifier)

Le composant `resources/views/components/card-display.blade.php` créé en Phase 7 fait déjà le switch sur `ThemeSettings::card_design`. **Tu le gardes tel quel**. Tu remplaces uniquement les 4 partials qu'il inclut.

### A.2 — Variables PHP partagées par les 4 partials

Chaque partial commence par le même bloc `@php` pour calculer ses variables. Reproduis-le identiquement dans les 4 :

```blade
@props(['card'])

@php
    $cardType = $card->cardType;
    $total = $cardType->sessions_count;
    $remaining = $card->sessions_remaining;
    $used = $total - $remaining;

    $isExpired = $card->status === \App\Enums\CardStatus::Expired
        || $card->expires_at->isPast();

    $warnDays = app(\App\Settings\CardSettings::class)->expiration_warning_days;
    $maxWarn = collect($warnDays)->max() ?? 30;
    $expiresSoon = !$isExpired
        && $card->expires_at->diffInDays(now()) <= $maxWarn;

    $statusLabel = $isExpired ? 'Expirée' : ($expiresSoon ? 'Expire bientôt' : null);
    $expiryFormatted = $card->expires_at->format('d.m.Y');
    $expiryLong = $card->expires_at->translatedFormat('d F Y');
    $cardTypeName = $cardType->name;
@endphp
```

`translatedFormat` (Carbon) produit "10 novembre 2026" en français — utilisé par le design Wallet.

### A.3 — Partial Wallet (default) — `resources/views/components/cards/wallet.blade.php`

```blade
{{-- Bloc @php de A.2 ci-dessus --}}

<div class="tc-wallet" style="width:540px;height:330px;background:var(--color-primary);background-image:radial-gradient(circle at 110% -10%,rgba(255,255,255,.18) 0%,transparent 55%);border-radius:18px;padding:26px 32px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#fff;position:relative;overflow:hidden;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="position:absolute;bottom:-40px;right:-40px;width:200px;height:200px;border:1px solid rgba(255,255,255,.08);border-radius:50%;"></div>
    <div style="position:absolute;bottom:-80px;right:-80px;width:280px;height:280px;border:1px solid rgba(255,255,255,.06);border-radius:50%;"></div>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;position:relative;z-index:1;">
        <div style="display:flex;align-items:center;gap:11px;">
            <div style="width:38px;height:38px;background:#fff;color:var(--color-primary);display:flex;align-items:center;justify-content:center;border-radius:8px;font-family:Georgia,serif;font-size:16px;font-weight:500;letter-spacing:1px;">TC</div>
            <div>
                <div style="font-size:13px;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;">TableConvo</div>
                <div style="font-size:10.5px;opacity:.7;letter-spacing:.5px;margin-top:1px;">{{ $cardTypeName }} · {{ $total }} séances</div>
            </div>
        </div>
        @if($statusLabel)
            <div style="font-size:10px;color:#fffbe6;background:{{ $isExpired ? 'rgba(220,38,38,.7)' : 'rgba(217,119,6,.65)' }};padding:4px 10px;border-radius:99px;letter-spacing:.5px;white-space:nowrap;">{{ $statusLabel }}</div>
        @endif
    </div>

    <div style="margin-bottom:22px;position:relative;z-index:1;">
        <div style="font-size:10px;opacity:.6;letter-spacing:1.8px;text-transform:uppercase;margin-bottom:4px;">Séances restantes</div>
        <div style="font-size:54px;font-weight:500;line-height:1;letter-spacing:-2px;">{{ $remaining }}<span style="font-size:24px;opacity:.55;font-weight:400;"> / {{ $total }}</span></div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:18px;position:relative;z-index:1;">
        @for($i = 1; $i <= $total; $i++)
            <div style="flex:1;height:10px;background:{{ $i <= $used ? 'var(--color-accent)' : 'rgba(255,255,255,.22)' }};border-radius:99px;"></div>
        @endfor
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1;">
        <div style="font-size:11px;opacity:.6;letter-spacing:.5px;">Valable jusqu'au</div>
        <div style="font-size:14px;font-weight:500;letter-spacing:.5px;white-space:nowrap;">{{ $expiryLong }}</div>
    </div>
</div>
```

### A.4 — Partial Stamp — `resources/views/components/cards/stamp.blade.php`

```blade
{{-- Bloc @php de A.2 ci-dessus --}}

<div class="tc-stamp" style="width:540px;height:330px;background:#f6f1e6;background-image:radial-gradient(circle at 18% 28%,rgba(217,119,6,.05) 0%,transparent 45%),radial-gradient(circle at 82% 75%,rgba(37,99,235,.04) 0%,transparent 50%);border:0.5px solid #e8dec5;border-radius:14px;padding:22px 30px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#1a2b4e;position:relative;overflow:hidden;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="position:absolute;top:14px;right:16px;width:74px;height:74px;border:1.5px solid rgba(217,119,6,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;transform:rotate(8deg);opacity:.35;">
        <div style="text-align:center;font-family:Georgia,serif;color:var(--color-accent);">
            <div style="font-size:9px;letter-spacing:1.5px;">CONVERSATION</div>
            <div style="font-size:18px;font-weight:500;line-height:1;margin:2px 0;">★</div>
            <div style="font-size:9px;letter-spacing:1.5px;">CARD</div>
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;">
        <div style="width:38px;height:38px;background:#1a2b4e;color:#f6f1e6;display:flex;align-items:center;justify-content:center;border-radius:6px;font-family:Georgia,serif;font-size:16px;font-weight:500;letter-spacing:1px;">TC</div>
        <div>
            <div style="font-size:13px;font-weight:500;letter-spacing:1.8px;text-transform:uppercase;">TableConvo</div>
            <div style="font-size:10.5px;color:#6b7894;letter-spacing:.5px;margin-top:1px;">{{ $cardTypeName }}</div>
        </div>
    </div>

    @php
        // Calcul des dimensions de grille selon le total
        $cols = $total <= 5 ? $total : 5;
        $rows = ceil($total / 5);
        $rowHeight = $rows == 1 ? 62 : ($rows == 2 ? 62 : ($rows == 3 ? 40 : 30));
        $rotations = [-9, -5, -12, -7, -10, -6, -11, -8, -4, -13, -6, -10, -8, -11, -5, -9, -7, -12, -6, -8];
    @endphp

    <div style="display:grid;grid-template-columns:repeat({{ $cols }},1fr);grid-template-rows:repeat({{ $rows }},{{ $rowHeight }}px);gap:9px;margin-bottom:20px;">
        @for($i = 1; $i <= $total; $i++)
            @if($i <= $used)
                <div style="background:rgba(217,119,6,.04);border:0.5px solid rgba(217,119,6,.2);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                    <svg viewBox="0 0 24 24" width="32" height="32" style="transform:rotate({{ $rotations[($i-1) % count($rotations)] }}deg);opacity:.82;color:var(--color-primary);"><path d="M5 12 L10 17 L19 7" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            @else
                <div style="background:#fff;border:0.5px solid rgba(217,119,6,.3);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#6b7894;font-size:15px;font-family:Georgia,serif;">{{ $i }}</div>
            @endif
        @endfor
    </div>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:0.5px dashed #e8dec5;padding-top:12px;">
        <div>
            <div style="font-size:10px;color:#6b7894;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:3px;">Séances restantes</div>
            <div style="font-family:Georgia,serif;color:var(--color-primary);line-height:1;"><span style="font-size:26px;font-weight:500;">{{ $remaining }}</span><span style="font-size:14px;color:#6b7894;"> / {{ $total }}</span></div>
        </div>
        <div style="text-align:right;white-space:nowrap;">
            <div style="font-size:10px;color:#6b7894;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:3px;">Validité</div>
            <div style="font-size:14px;color:#1a2b4e;font-family:Georgia,serif;line-height:1;">{{ $expiryFormatted }}</div>
            @if($statusLabel)
                <div style="display:inline-block;margin-top:5px;font-size:10px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};background:{{ $isExpired ? 'rgba(220,38,38,.1)' : 'rgba(217,119,6,.1)' }};padding:2px 7px;border-radius:3px;letter-spacing:.5px;line-height:1.4;">{{ $statusLabel }}</div>
            @endif
        </div>
    </div>
</div>
```

### A.5 — Partial Editorial — `resources/views/components/cards/editorial.blade.php`

```blade
{{-- Bloc @php de A.2 ci-dessus --}}

<div class="tc-edit" style="width:540px;height:330px;background:#fbf9f4;border:1.5px solid #1a2b4e;border-radius:0;padding:26px 32px;box-sizing:border-box;font-family:var(--font-sans),-apple-system,sans-serif;color:#1a2b4e;position:relative;box-shadow:6px 6px 0 0 #1a2b4e;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;border-bottom:1px solid #1a2b4e;padding-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;background:#1a2b4e;color:#fbf9f4;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:17px;font-weight:500;letter-spacing:1px;">TC</div>
            <div>
                <div style="font-family:Georgia,serif;font-size:18px;font-weight:500;letter-spacing:.5px;">TableConvo</div>
                <div style="font-size:10px;letter-spacing:2.5px;text-transform:uppercase;margin-top:2px;opacity:.65;">{{ $cardTypeName }} · Édition limitée</div>
            </div>
        </div>
        @if($statusLabel)
            <div style="font-size:10px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};background:transparent;border:1px solid {{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};padding:3px 9px;letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap;">{{ $statusLabel }}</div>
        @endif
    </div>

    <div style="display:flex;align-items:flex-end;gap:16px;margin-bottom:22px;">
        <div style="font-family:Georgia,serif;font-size:88px;font-weight:500;line-height:.85;color:var(--color-primary);letter-spacing:-4px;">{{ $remaining }}</div>
        <div style="padding-bottom:8px;">
            <div style="font-family:Georgia,serif;font-size:22px;color:#1a2b4e;opacity:.45;font-style:italic;">/ {{ $total }}</div>
            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;margin-top:4px;opacity:.6;">séances restantes</div>
        </div>
    </div>

    @php $progressPercent = $total > 0 ? round(($remaining / $total) * 100) : 0; @endphp
    <div style="height:3px;background:rgba(26,43,78,.1);position:relative;margin-bottom:18px;">
        <div style="position:absolute;top:0;left:0;height:100%;width:{{ $progressPercent }}%;background:var(--color-primary);"></div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;opacity:.7;">
        <span>{{ $used }} séance{{ $used > 1 ? 's' : '' }} utilisée{{ $used > 1 ? 's' : '' }}</span>
        <span style="font-family:Georgia,serif;font-size:13px;letter-spacing:.5px;text-transform:none;opacity:1;color:#1a2b4e;white-space:nowrap;">Expire le {{ $expiryFormatted }}</span>
    </div>
</div>
```

### A.6 — Partial Swiss — `resources/views/components/cards/swiss.blade.php`

```blade
{{-- Bloc @php de A.2 ci-dessus --}}

<div class="tc-swiss" style="width:540px;height:330px;background:#fff;border:0.5px solid #e5e5e5;border-radius:4px;padding:30px 36px;box-sizing:border-box;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#000;position:relative;display:flex;flex-direction:column;justify-content:space-between;{{ $isExpired ? 'opacity:.55;filter:grayscale(.4);' : '' }}">

    <div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;background:#000;color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:13px;font-weight:500;letter-spacing:.5px;">TC</div>
                <div style="font-size:13px;font-weight:500;letter-spacing:.5px;">TableConvo</div>
            </div>
            @if($statusLabel)
                <div style="font-family:'JetBrains Mono','Courier New',monospace;font-size:11px;color:{{ $isExpired ? '#dc2626' : 'var(--color-accent)' }};letter-spacing:.5px;white-space:nowrap;">! {{ $statusLabel }}</div>
            @endif
        </div>
        <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:6px;">
            <div style="font-size:72px;font-weight:300;line-height:1;letter-spacing:-3px;color:var(--color-primary);">{{ $remaining }}</div>
            <div style="font-size:22px;font-weight:300;color:#999;letter-spacing:-1px;">/ {{ $total }}</div>
        </div>
        <div style="font-size:11px;color:#999;letter-spacing:.8px;text-transform:uppercase;font-weight:500;">Séances restantes</div>
    </div>

    <div>
        <div style="display:flex;gap:3px;margin-bottom:18px;">
            @for($i = 1; $i <= $total; $i++)
                <div style="flex:1;height:4px;background:{{ $i <= $used ? 'var(--color-primary)' : '#e5e5e5' }};"></div>
            @endfor
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-family:'JetBrains Mono','Courier New',monospace;font-size:11px;color:#666;letter-spacing:.5px;">
            <span>{{ strtolower(\Illuminate\Support\Str::slug($cardTypeName, '.')) }}.{{ $total }}</span>
            <span style="white-space:nowrap;">exp {{ $card->expires_at->format('Y-m-d') }}</span>
        </div>
    </div>
</div>
```

### A.7 — Adaptation au nombre de séances

Pour `Wallet` et `Swiss` : la boucle `@for` génère exactement N barres/pastilles avec `flex:1`. Plus il y a de séances, plus les barres sont fines. Pas de limite.

Pour `Stamp` : la grille s'adapte automatiquement :
- 1-5 séances : 1 ligne, N colonnes
- 6-10 séances : 2 lignes, 5 colonnes (hauteur 62px)
- 11-15 séances : 3 lignes, 5 colonnes (hauteur 40px)
- 16-20 séances : 4 lignes, 5 colonnes (hauteur 30px)
- >20 séances : limite à 4 lignes, ajuster la hauteur si besoin

Le calcul dans le `@php` du partial Stamp gère déjà les 4 premiers cas. Si tu as un cas client réel avec >20, tu signales avant de coder.

Pour `Editorial` : pas de représentation graphique des séances individuelles, juste un compteur + une barre de progression en pourcentage. Aucune adaptation nécessaire.

### A.8 — Migration du default `card_design`

Migration `database/settings/{date}_set_wallet_as_default_card_design.php` :

```php
public function up(): void
{
    if ($this->migrator->exists('theme.card_design')) {
        // Setting déjà défini, ne pas écraser
        return;
    }
    $this->migrator->add('theme.card_design', 'wallet');
}
```

Cette migration est défensive : elle ne fait rien si `theme.card_design` existe déjà (cas des projets actifs où l'admin a peut-être déjà choisi un design). Elle ne s'exécute réellement que sur un nouveau setup ou un reset.

Mettre aussi à jour `ThemeSettings::$card_design = 'wallet';` (valeur par défaut PHP) pour cohérence.

### A.9 — Suppression des anciens partials

Supprimer les fichiers cassés :
- `resources/views/components/cards/stamp.blade.php` (ancienne version)
- `resources/views/components/cards/wallet.blade.php` (ancienne version)
- `resources/views/components/cards/editorial.blade.php` (ancienne version)
- `resources/views/components/cards/swiss.blade.php` (ancienne version)

Puis créer les 4 nouveaux selon A.3 → A.6.

### A.10 — Tests

Les tests `CardDisplayTest.php` (de Phase 7) doivent **rester verts** sans modification — ils testent que le bon partial est rendu selon `ThemeSettings::card_design`, pas le contenu visuel.

Tests additionnels à ajouter dans `tests/Feature/Components/CardDisplayTest.php` :

```
- wallet design renders the correct used vs remaining bars count
- stamp design renders 'Expire bientôt' badge when expiring within warning threshold
- stamp design renders 'Expirée' badge in red when card is expired
- editorial design renders the progress bar with correct percentage
- swiss design renders monospace expiration date in ISO format
- expired cards have reduced opacity (visual degradation)
- editorial does not render any session-level visual element
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — CHECKPOINT 1                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible ~327, soit +7)               ║
### ║  2. Confirmation que les 4 anciens partials sont          ║
### ║     supprimés et remplacés                                ║
### ║  3. Walkthrough textuel des 4 designs sur                 ║
### ║     /admin/manage-theme-settings (preview chaque design)  ║
### ║     avec une carte de test (10 séances, 3 restantes,      ║
### ║     expire dans 15 jours)                                 ║
### ║  4. Vérification que les CSS variables --color-primary    ║
### ║     et --color-accent sont bien consommées par les 4      ║
### ║     designs (changer la couleur primary depuis            ║
### ║     ManageThemeSettings → effet visible)                  ║
### ║                                                           ║
### ║  Tu attends mon GO B écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 3. Étape B — Restyling modale "Inscrits"

### B.1 — Localisation

La modale "Inscrits — {topic} ({date})" est accessible depuis `ConversationTableResource` (probablement via une `TableAction` ou un bouton dans la colonne actions). Elle ouvre un composant Livewire qui liste les registrations groupées par status (Registered + Waitlist).

**Action attendue** : localise le composant exact (probablement `app/Livewire/Admin/RegistrationsManager.php` ou similaire selon le nommage). Reporte le chemin trouvé dans le rapport STOP B.

### B.2 — Refonte attendue

Le composant doit afficher :

#### Header de la modale

```blade
<div class="border-b border-gray-200 pb-4 mb-6">
    <h2 class="text-lg font-semibold text-gray-900">
        Inscrits — {{ $table->topic }}
    </h2>
    <div class="mt-1 flex items-center gap-3 text-sm text-gray-600">
        <span>{{ $table->scheduled_at->translatedFormat('d F Y · H:i') }}</span>
        <span class="text-gray-400">•</span>
        <span>{{ $table->level->code }}</span>
    </div>
    <div class="mt-3 flex items-center gap-4 text-sm">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 font-medium">
            {{ $registeredCount }} / {{ $table->max_participants }} inscrits
        </span>
        @if($waitlistCount > 0)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 font-medium">
                {{ $waitlistCount }} en liste d'attente
            </span>
        @endif
    </div>
</div>
```

#### Section "Inscrits confirmés"

Table Tailwind avec :
- Colonne **Nom** : nom complet (gras) + prénom
- Colonne **Email** (gris, plus petit)
- Colonne **Carte** : badge avec le nom du cardType + statut (Active/Expirée)
- Colonne **Actions** (alignées à droite) :
  - Bouton **Déplacer** : ghost gris, ouvre une modale de choix de session cible
  - Bouton **Annuler** : ghost rouge

```blade
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
        Inscrits confirmés ({{ count($registered) }})
    </h3>
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach($registered as $reg)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $reg->user->full_name }}</div>
                            <div class="text-sm text-gray-500">{{ $reg->user->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $reg->card?->cardType?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="move({{ $reg->id }})"
                                    class="text-sm text-gray-700 hover:text-gray-900 hover:bg-gray-100 px-2.5 py-1 rounded-md font-medium transition">
                                Déplacer
                            </button>
                            <button wire:click="cancel({{ $reg->id }})"
                                    class="text-sm text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                Annuler
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

#### Section "Liste d'attente"

Identique mais avec :
- Préfixe **#1**, **#2**, etc. devant le nom (position dans la file)
- Actions : **Promouvoir** (primary, bg-blue-600 text-white), **Déplacer** (ghost gris), **Retirer** (ghost rouge)

```blade
<div>
    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">
        Liste d'attente ({{ count($waitlist) }})
    </h3>
    @if(count($waitlist) === 0)
        <div class="text-sm text-gray-500 italic py-4 px-4 border border-dashed border-gray-200 rounded-lg">
            Aucune personne en attente.
        </div>
    @else
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($waitlist as $index => $reg)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-gray-400">#{{ $index + 1 }}</span>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $reg->user->full_name }}</div>
                                        <div class="text-sm text-gray-500">{{ $reg->user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button wire:click="promote({{ $reg->id }})"
                                        class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md font-medium transition">
                                    Promouvoir
                                </button>
                                <button wire:click="move({{ $reg->id }})"
                                        class="text-sm text-gray-700 hover:text-gray-900 hover:bg-gray-100 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                    Déplacer
                                </button>
                                <button wire:click="remove({{ $reg->id }})"
                                        class="text-sm text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded-md font-medium transition ml-1">
                                    Retirer
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
```

### B.3 — Aucun changement logique

Tu ne touches PAS à :
- La logique métier des méthodes Livewire (`move`, `cancel`, `promote`, `remove`)
- Les Actions appelées (`MoveRegistrationAction`, `CancelRegistrationAction`, `PromoteFromWaitlistAction`)
- Les notifications

Si tu identifies qu'une de ces méthodes a un bug fonctionnel pendant que tu fais le restyling, tu STOPpes et tu signales sans corriger toi-même.

### B.4 — Tests

Les tests existants du composant Livewire doivent **rester verts**. Si la sélection DOM des tests utilise des sélecteurs CSS spécifiques que tu casses (ex: `assertSeeHtml('<table')`), tu adaptes les tests pour qu'ils restent verts sans changer la sémantique métier testée.

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — CHECKPOINT 2                                   ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible inchangé ~327)               ║
### ║  2. Chemin exact du composant Livewire localisé           ║
### ║  3. Walkthrough manuel de la modale ouverte sur une       ║
### ║     session avec 2 inscrits et 1 waitlist                 ║
### ║  4. Confirmation que les 4 actions (Promouvoir, Déplacer, ║
### ║     Annuler, Retirer) déclenchent bien le comportement    ║
### ║     attendu (pas de régression fonctionnelle)             ║
### ║                                                           ║
### ║  Tu attends mon GO C écrit avant de continuer.            ║
### ╚══════════════════════════════════════════════════════════╝

---

## 4. Étape C — Liste d'attente globale + réorientation

### C.1 — Nouvelle Resource Filament `WaitlistResource`

`app/Filament/Resources/WaitlistResource.php`

Le model utilisé est `Registration` mais avec un scope strict :

```php
public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    return parent::getEloquentQuery()
        ->where('status', \App\Enums\RegistrationStatus::Waitlist)
        ->whereHas('conversationTable', fn ($q) =>
            $q->where('status', \App\Enums\SessionStatus::Scheduled)
              ->where('scheduled_at', '>', now())
        )
        ->with(['user', 'conversationTable.level']);
}
```

**Pas d'opérations de création/édition/suppression** : `canCreate()`, `canEdit()`, `canDelete()` retournent `false`. Lecture + 1 action custom uniquement.

**Slug** : `/admin/waitlist` (au lieu du défaut `/admin/registrations` qui serait trompeur).

**Navigation** : groupe "Gestion des inscriptions" (ou crée le groupe si inexistant). Icône `heroicon-o-clock`.

**Authorization** : admin uniquement (`canAccess()`).

#### Colonnes de la table

```
- created_at : "En attente depuis" (formaté "Il y a X jours" ou date relative), tri DESC par défaut sur les plus anciens en HAUT (donc ascending sur created_at en réalité)
- user.full_name : "Personne"
- user.email : "Email" (toggleable, caché par défaut sur mobile)
- conversationTable.topic : "Session" (lien vers le ConversationTableResource concerné)
- conversationTable.scheduled_at : "Date session" (formaté d/m H:i)
- conversationTable.level.code : "Niveau" (badge)
- position : "Position" (calculée : rang dans la waitlist de la session, accessor)
```

L'accessor `position` sur `Registration` (à créer s'il n'existe pas) :

```php
public function getPositionAttribute(): int
{
    return $this->conversationTable
        ->registrations()
        ->where('status', RegistrationStatus::Waitlist)
        ->where('created_at', '<=', $this->created_at)
        ->count();
}
```

**Tri par défaut** : `created_at ASC` (les plus anciens en haut → ceux qui attendent le plus longtemps prioritaires visuellement).

#### Filtres

```
- conversationTable.level_id : Select des levels (A1, A2, B1, B2, C1, C2)
- created_at : DateRange "En attente depuis le ... au ..."
- conversationTable.scheduled_at : DateRange "Sessions du ... au ..."
```

### C.2 — Action "Réorienter"

Sur chaque ligne de la table `WaitlistResource`, une action `redirect` :

```php
Action::make('redirect')
    ->label('Réorienter')
    ->icon('heroicon-o-arrow-right-circle')
    ->color('primary')
    ->form(fn (Registration $record) => [
        Select::make('target_table_id')
            ->label('Réorienter vers')
            ->required()
            ->options(fn () => $this->getEligibleTargetSessions($record))
            ->helperText('Sessions futures du même niveau. Les places libres apparaissent en premier.'),
    ])
    ->modalHeading(fn (Registration $record) =>
        "Réorienter {$record->user->full_name}"
    )
    ->modalDescription(fn (Registration $record) =>
        "Inscription actuelle : {$record->conversationTable->topic} du {$record->conversationTable->scheduled_at->translatedFormat('d F Y')}. La personne sera placée en queue de la liste d'attente de la session choisie."
    )
    ->action(function (Registration $record, array $data): void {
        $target = ConversationTable::findOrFail($data['target_table_id']);
        app(MoveRegistrationAction::class)->execute(
            $record,
            $target,
            auth()->user(),
            'admin_redirect'  // ou un argument cohérent avec la signature actuelle
        );
        Notification::make()
            ->title('Personne réorientée')
            ->body("{$record->user->full_name} a été réorienté(e) vers « {$target->topic} ».")
            ->success()
            ->send();
    })
```

#### Méthode `getEligibleTargetSessions($registration)`

À placer dans la `WaitlistResource` ou dans un service dédié :

```php
private function getEligibleTargetSessions(Registration $registration): array
{
    return ConversationTable::query()
        ->where('status', SessionStatus::Scheduled)
        ->where('scheduled_at', '>', now())
        ->where('level_id', $registration->conversationTable->level_id)
        ->where('id', '!=', $registration->conversation_table_id)
        ->withCount([
            'registrations as registered_count' => fn ($q) =>
                $q->where('status', RegistrationStatus::Registered),
            'registrations as waitlist_count' => fn ($q) =>
                $q->where('status', RegistrationStatus::Waitlist),
        ])
        ->orderBy('scheduled_at')
        ->get()
        ->mapWithKeys(function ($table) {
            $free = $table->max_participants - $table->registered_count;
            $label = sprintf(
                '%s — %s · %s',
                $table->scheduled_at->translatedFormat('d M H:i'),
                $table->topic,
                $free > 0
                    ? "{$free} place" . ($free > 1 ? 's' : '') . " libre" . ($free > 1 ? 's' : '')
                    : "complet, {$table->waitlist_count} en attente"
            );
            return [$table->id => $label];
        })
        ->toArray();
}
```

Le tri par `scheduled_at` met les sessions les plus proches en premier. Les places libres restent visibles via le label.

### C.3 — Notification utilisateur `RegistrationRedirectedNotification`

`app/Notifications/RegistrationRedirectedNotification.php`

Channels : `['mail', 'database']`, `ShouldQueue`.

Constructor : `(Registration $oldRegistration, Registration $newRegistration, ConversationTable $newTable)`.

Mail :
- Sujet : "Vous avez été réorienté(e) vers une nouvelle session"
- Corps : explication du changement (ancienne session → nouvelle session), position dans la nouvelle waitlist, date de la nouvelle session, lien vers `/espace/inscriptions`

```
Bonjour {$user->first_name},

Nous vous informons que votre inscription en liste d'attente pour la session
« {$oldTable->topic} » du {$oldTable->scheduled_at->translatedFormat('d F Y')}
a été réorientée vers une autre session compatible avec votre niveau.

Nouvelle session : « {$newTable->topic} »
Date : {$newTable->scheduled_at->translatedFormat('d F Y · H:i')}
Niveau : {$newTable->level->code}

Vous êtes actuellement en position {$position} de la liste d'attente.
Vous serez notifié(e) si une place se libère.

Pour consulter vos inscriptions : {$linkToInscriptions}

Cordialement,
L'équipe TableConvo
```

### C.4 — Branchement dans `MoveRegistrationAction`

Vérifier si `MoveRegistrationAction` (existant Phase 5) accepte un argument pour le contexte (`admin_redirect`, `user_request`, etc.) et déclenche la notification appropriée.

Si elle ne le fait pas déjà :
- **Ajouter un argument optionnel `?string $context = null`** à la signature de `execute()`
- Si `$context === 'admin_redirect'` → dispatcher `RegistrationRedirectedNotification`
- Sinon → notification existante (s'il y en a une) ou aucune

**Important** : ne pas casser les appels existants à `MoveRegistrationAction`. Vérifier que tous les call sites continuent de fonctionner après l'ajout de l'argument optionnel.

### C.5 — Tests

`tests/Feature/Filament/Resources/WaitlistResourceTest.php` :

```
- admin can list all waitlist registrations across sessions
- non-admin gets 403
- table is sorted by created_at ASC (oldest first)
- filter by level filters correctly
- filter by waiting period range filters correctly
- only future scheduled sessions are included
- the position accessor returns the correct rank within the session
```

`tests/Feature/Actions/MoveRegistrationActionTest.php` (à enrichir si déjà existant, sinon créer) :

```
- redirecting a waitlist registration to another session keeps it in Waitlist status
- the redirected registration goes to the END of the new waitlist (FIFO)
- the eligible target sessions are filtered by exact same level
- the eligible target sessions exclude the current session and past sessions
- dispatching admin_redirect context sends RegistrationRedirectedNotification
- existing call sites without context argument still work (no notification sent or existing notification)
```

`tests/Feature/Notifications/RegistrationRedirectedNotificationTest.php` :

```
- mail subject contains 'réorienté'
- mail body mentions both old and new session topics
- mail body mentions the new position in waitlist
- notification is queued (ShouldQueue)
```

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP C — RÉCAP DE FIN DE BRIEF                          ║
### ╠══════════════════════════════════════════════════════════╣
### ║  Tu rapportes :                                           ║
### ║  1. Total tests verts (cible ~340, soit +13 sur C         ║
### ║     en plus des +7 sur A et 0 sur B)                      ║
### ║  2. Walkthrough manuel : /admin/waitlist avec quelques    ║
### ║     inscriptions waitlist seedées, démo d'une             ║
### ║     réorientation complète + mail envoyé (Mail::fake)     ║
### ║  3. Réponses aux 8 questions de validation                ║
### ║     (section 6 ci-dessous)                                ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Pièges spécifiques — INTERDICTIONS

1. **NE PAS modifier le composant `<x-card-display>`** (le switch existe déjà depuis Phase 7). Tu remplaces uniquement les 4 partials.
2. **NE PAS oublier de supprimer les 4 anciens partials cassés.** Sinon ils continuent d'exister en dette technique.
3. **NE PAS hardcoder les couleurs dans les nouveaux partials** : utiliser `var(--color-primary)` et `var(--color-accent)`. Exceptions tolérées : les couleurs système (rouge erreur `#dc2626`, gris #fff, etc.) et les nuances spécifiques au design (papier crème `#f6f1e6`, encre `#1a2b4e`).
4. **NE PAS toucher à la logique métier de la modale Inscrits.** C'est uniquement un restyling. Si tu identifies un bug, signale-le sans corriger.
5. **NE PAS créer de doublon de `MoveRegistrationAction`.** Si la signature ne te permet pas de différencier le contexte admin_redirect, ajoute un argument optionnel à l'action existante.
6. **NE PAS oublier `ShouldQueue` sur `RegistrationRedirectedNotification`.**
7. **NE PAS oublier `DB::afterCommit` autour du dispatch de notification** (pattern Phase 5/6).
8. **NE PAS filtrer les sessions cibles par niveau "± 1"** : strict, même niveau exact uniquement. Décision tranchée.
9. **NE PAS oublier l'authorization admin** sur `WaitlistResource` (et les Filament Actions associées).
10. **NE PAS sauter les STOP.**

---

## 6. Validation finale

Avant de déclarer le brief terminé, tu fournis un récap répondant à TOUTES ces questions :

1. Les 4 designs de carte rendent-ils correctement avec une carte de test à 10 séances ?
2. Les 4 designs s'adaptent-ils proprement aux cartes à 5, 12, 20 séances ?
3. Les CSS variables `--color-primary` et `--color-accent` sont-elles bien consommées par les 4 designs (test : change la couleur primary depuis ManageThemeSettings → propagation immédiate) ?
4. Le default `card_design = 'wallet'` est-il effectif sur un nouveau setup, sans écraser une valeur existante ?
5. La modale "Inscrits" affiche-t-elle bien une table Tailwind propre avec 4 boutons stylisés différenciés (Promouvoir / Déplacer / Annuler / Retirer) ?
6. La nouvelle `WaitlistResource` est-elle accessible sur `/admin/waitlist`, filtrable par niveau, triée par ancienneté ?
7. L'action "Réorienter" liste-t-elle bien les sessions futures du même niveau avec leur statut places libres / en attente ?
8. La notification `RegistrationRedirectedNotification` est-elle envoyée à l'utilisateur après réorientation et mentionne-t-elle bien l'ancienne et la nouvelle session ?

---

## 7. Premier prompt à donner à Claude Code

```
Lis intégralement docs/POLISH_WAITLIST_BRIEF.md, CLAUDE.md, FILAMENT_5_BRIEF.md 
et tous les briefs précédents (PHASE_3 → PHASE_7 + CORRECTION_PHASES_1_2 
+ HOTFIX_CSP) avant toute action.

Confirme que tu as compris :
1. L'objectif : finaliser le polish UI (4 designs carte refondus, modale 
   Inscrits restylée) + ajouter la feature liste d'attente globale avec 
   réorientation, avant la Phase 8 (déploiement).
2. Les 13 décisions tranchées (notamment : 4 designs HTML fournis clé en 
   main dans le brief, wallet en default, MoveRegistrationAction existante 
   à réutiliser, niveau strict pour la réorientation, FIFO en queue).
3. La méthodologie STOP/GO (3 étapes A→C, GO X explicite à chaque 
   checkpoint).

Lance ensuite `php artisan test` et rapporte le total de tests verts 
(cible attendue : 320).

Indique tout point ambigu du brief avant de démarrer.
Attends mon GO A explicite avant de toucher au code.
```

---

**Fin du brief.**
