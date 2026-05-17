# Brief de correction urgent — CSP cassant Alpine, Livewire et Filament

> Hotfix prioritaire avant de continuer sur Phase 8 (déploiement). La CSP mise en place en Phase 7 étape D est trop stricte et bloque le JavaScript côté navigateur.
> À Claude Code : **lis intégralement avant la moindre ligne de code**. Pas de méthodologie STOP/GO multi-étapes ici — c'est un fix court et focalisé, à livrer en une seule passe.

---

## 0. État du projet

- Phase 7 livrée : 320 tests Pest verts
- Mais en production navigateur : **plusieurs flux critiques sont cassés** à cause de la CSP trop stricte
- Les tests Pest n'ont pas attrapé ces régressions car ils s'exécutent côté serveur uniquement, sans simuler le comportement JavaScript dans un vrai navigateur

## 1. Symptômes observés

Console navigateur (Safari/Chrome) sur `localhost:8000/achat/1` :

```
Refused to load https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap
because it does not appear in the style-src directive of the Content Security Policy.

Alpine Expression Error: Refused to evaluate a string as JavaScript because
'unsafe-eval' or 'trusted-types-eval' is not an allowed source of script in the
following Content Security Policy directive: "script-src 'self' 'unsafe-inline'".
Expression: "{ open: false }"
Expression: "open = ! open"
Expression: "addToCart"

Livewire Expression Error: Refused to evaluate a string as JavaScript...
Expression: "addToCart"

Detected multiple instances of Alpine running
```

Conséquences utilisateur :
1. **Bouton "Ajouter au panier" ne réagit pas** — le `wire:click="addToCart"` Livewire est bloqué par CSP
2. **Tous les dropdowns Alpine sont cassés** (menu utilisateur, navigation mobile, etc.)
3. **Le panel Filament admin (`/admin`) affiche une page blanche** — Filament v5 utilise Alpine massivement (modales, dropdowns, tables, ColorPicker du theming)
4. **La police Figtree (Bunny Fonts) ne charge pas** — fallback typographique disgracieux

## 2. Cause racine

Trois problèmes distincts, par ordre de priorité :

### 2.1 — `'unsafe-eval'` manquant dans `script-src`

Alpine.js et Livewire utilisent `eval()` en interne pour évaluer dynamiquement les expressions JavaScript dans les attributs HTML (`x-data`, `x-show`, `wire:click`, `wire:model`, etc.). Sans `'unsafe-eval'` dans la directive `script-src` de la CSP, **toute interaction Alpine et Livewire côté navigateur est bloquée**.

C'est un compromis pragmatique et bien documenté : ajouter `'unsafe-eval'` est la pratique standard dans les projets Laravel + Livewire + Alpine + Filament en production. Le risque résiduel XSS est marginal vu l'authentification en place et les autres protections (CSRF, validation côté serveur).

### 2.2 — `https://fonts.bunny.net` manquant dans `style-src`

Le rapport STOP D mentionnait `font-src 'self' https://fonts.bunny.net`, mais Bunny Fonts charge AUSSI une feuille de style CSS (`/css?family=figtree:...`) qui doit être autorisée dans `style-src`. Sans ça, la police par défaut du projet ne charge pas.

### 2.3 — CSP appliquée au panel Filament admin

Le rapport STOP D mentionnait : *"Middleware AddCspHeaders ajouté au groupe web et au panel Filament (AdminPanelProvider)"*. C'est inhabituel — la CSP custom n'est généralement pas appliquée au panel admin Filament, car Filament a ses propres besoins JS qui dépassent parfois les directives strictes. Même avec `'unsafe-eval'` ajouté, le scope `/admin` ne nécessite pas la CSP custom — autant la retirer pour éviter des régressions silencieuses futures.

### 2.4 — Bonus : doublon Alpine

Le warning `Detected multiple instances of Alpine running` indique qu'Alpine est chargé deux fois :
- Une fois automatiquement par Livewire 3 (qui l'embarque nativement depuis cette version)
- Une fois manuellement par le bundle Vite (`resources/js/app.js` qui fait probablement `import Alpine from 'alpinejs'; Alpine.start();`)

Ce n'est pas lié à la CSP mais ça génère des comportements imprévisibles et doit être nettoyé. Solution : **supprimer l'import manuel d'Alpine** dans `app.js` puisque Livewire 3 fournit déjà Alpine.

## 3. Fix attendu

### 3.1 — Mise à jour de `AppCspPreset`

Localisation : `app/Support/Csp/AppCspPreset.php`

Modifications :

```php
->addDirective(Directive::SCRIPT, [
    Keyword::SELF,
    Keyword::UNSAFE_INLINE,
    Keyword::UNSAFE_EVAL,           // ← AJOUTÉ — requis pour Alpine et Livewire
])
->addDirective(Directive::STYLE, [
    Keyword::SELF,
    Keyword::UNSAFE_INLINE,
    'https://fonts.bunny.net',      // ← AJOUTÉ — requis pour Bunny Fonts CSS
])
->addDirective(Directive::FONT, [
    Keyword::SELF,
    'https://fonts.bunny.net',      // déjà présent, vérifier
])
// le reste (default-src 'self', frame-ancestors 'none') inchangé
```

Vérifier le nom exact des constantes `Keyword::*` ou `Directive::*` selon la version de `spatie/laravel-csp` v3 installée. Si certaines constantes n'existent pas, utiliser les chaînes littérales équivalentes (`'unsafe-eval'`, etc.).

### 3.2 — Retirer la CSP du panel Filament admin

Localisation : `app/Providers/Filament/AdminPanelProvider.php` (ou `AdminPanelServiceProvider.php` selon le nom utilisé).

Identifier la ligne qui ajoute `\Spatie\Csp\AddCspHeaders::class` dans la chaîne des middlewares du panel et la **supprimer**. La CSP reste active sur le groupe `web` (front public + espace membre), c'est suffisant pour la sécurité utilisateur.

### 3.3 — Nettoyer le doublon Alpine

Localisation : `resources/js/app.js`

Si ce fichier contient :

```js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

→ **supprimer ces trois lignes**. Livewire 3 fournit déjà Alpine globalement, le réimporter cause le doublon.

Si `app.js` est minimal ou inexistant après suppression, le laisser tel quel (Vite peut compiler un bundle vide sans souci).

Rebuild des assets : `npm run build` (ou `npm run dev` selon le contexte de dev d'Arnaud).

### 3.4 — Tests à ajouter

Localisation : `tests/Feature/Security/CspHeadersTest.php`

Compléter le fichier existant avec :

```php
it('CSP allows unsafe-eval for Alpine and Livewire compatibility', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("'unsafe-eval'");
});

it('CSP allows fonts.bunny.net stylesheet in style-src', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain('fonts.bunny.net');
});

it('admin panel does NOT have the custom CSP header', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $response = $this->actingAs($admin)->get('/admin');
    // Soit pas de CSP du tout, soit pas le custom AppCspPreset
    $csp = $response->headers->get('Content-Security-Policy');
    // Si null, c'est OK. Si présent, ne doit pas contenir la signature de AppCspPreset.
    expect($csp)->toBeNull()
        ->or(fn ($csp) => expect($csp)->not->toContain("'unsafe-eval'"));
});
```

Ces tests garantissent qu'une future régression sur la CSP sera détectée.

### 3.5 — Vérification manuelle après fix (obligatoire — ne pas sauter)

Les tests Pest ne suffisent pas pour cette correction. Tu **dois** ouvrir un vrai navigateur après le fix et confirmer :

1. **`localhost:8000/achat/1`** : clic sur "Ajouter au panier" → redirection vers le panier (ou ajout visible). **Console navigateur (F12)** sans aucune erreur CSP.
2. **`localhost:8000/admin`** connecté en tant qu'admin : le panel Filament s'affiche entièrement (pas de page blanche), dropdowns fonctionnels, modales ouvrables.
3. **`localhost:8000/admin/manage-theme-settings`** : le **ColorPicker** s'ouvre et permet de choisir une couleur. C'est le test le plus critique car ColorPicker = Alpine + JS dynamique.
4. **Le menu utilisateur** en haut à droite (dropdown Alpine) : s'ouvre au clic, se ferme au clic extérieur.
5. **La police Figtree** est bien chargée (Network tab → la requête `figtree:...` doit être en `200 OK`, pas bloquée).
6. **Plus de warning** "Detected multiple instances of Alpine running" dans la console.

## 4. Ce que tu rapportes en fin de fix

Une seule passe, rapport unique :

1. **Total tests verts** (cible : 323 = 320 + 3 nouveaux tests CSP)
2. **Diff du fichier `AppCspPreset.php`** (avant/après)
3. **Confirmation que le middleware CSP a été retiré du panel Filament admin** (avec extrait du provider modifié)
4. **Confirmation que `app.js` a été nettoyé** (avec contenu final du fichier)
5. **Résultats des 6 vérifications manuelles** ci-dessus, une par une, avec OK ou KO et capture de la console navigateur si erreur résiduelle

## 5. Pièges à éviter

1. **NE PAS ajouter `'unsafe-eval'` sans `'unsafe-inline'`** — les deux sont nécessaires conjointement pour Alpine.
2. **NE PAS oublier de rebuild les assets** après modification de `app.js` (sinon le navigateur charge l'ancien bundle compilé avec le doublon Alpine).
3. **NE PAS supposer que les tests Pest suffisent** — cette régression a échappé aux 320 tests parce qu'ils ne testent pas le JS côté navigateur. La vérification manuelle des 6 points est obligatoire.
4. **NE PAS modifier les autres directives CSP** (`default-src`, `frame-ancestors`, `font-src`) — elles sont correctes, ne touche qu'à `script-src` et `style-src`.
5. **NE PAS ré-appliquer la CSP au panel Filament admin** « pour être cohérent » — c'est précisément ce qu'il faut éviter.

## 6. Premier prompt à donner à Claude Code

```
Lis intégralement docs/HOTFIX_CSP_BRIEF.md avant toute action.

Confirme que tu as compris :
1. Que la CSP de Phase 7D casse Alpine, Livewire et Filament admin
2. Les 4 modifications à faire (script-src unsafe-eval, style-src bunny.net, 
   retrait CSP du panel Filament, nettoyage doublon Alpine)
3. Que la vérification manuelle dans un vrai navigateur est obligatoire 
   après le fix (les tests Pest ne couvrent pas le JS navigateur)

Procède directement au fix en une seule passe (pas de STOP/GO multi-étapes).
Rapporte selon le format de la section 4 du brief.
```

---

**Fin du brief de hotfix CSP.**
