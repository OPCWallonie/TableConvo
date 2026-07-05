# Rapport Phase 10 — Module M0 : Audit pré-déploiement Pi

> Rapport de clôture du Module M0, conformément à `docs/PHASE_10_M0_BRIEF.md` (v2).
> Audit exécuté sans aucune modification de fichier applicatif. Seul commit autorisé du module : le présent document.
> Date d'exécution : 2026-07-05. Verdict validé par l'architecte.

---

## Verdict

# **GO M1 avec réserves**

Trois réserves à traiter, aucune ne bloque le démarrage de M1 (setup Pi / UAT) :

1. **Mollie — clé API déconnectée** (finding #1, BLOQUANT scope Phase 11) : `MollieSettings.api_key` (Filament/DB) ne pilote que le mode stub ; le SDK Mollie réel utilise exclusivement `MOLLIE_KEY` (.env). Sans impact sur l'UAT Pi (Mollie reste stub de bout en bout), mais **un brief de correction dédié est requis avant toute bascule Mollie live en Phase 11**, pour que ce point ne se perde pas.
2. **Documentation `CLAUDE.md` obsolète** sur `waitlist_auto_promote` (§2.4, §7, §12.2 toujours sur "défaut false / manuelle uniquement", alors que `docs/PHASE_5_BRIEF.md` §1 décision 1 a tranché "défaut true" dès mai 2026, décision assumée en code). Un micro-patch documentaire est prévu juste après M0, hors scope du présent audit.
3. **Compléments mineurs** avant M1 : variables manquantes dans `.env.example` (`MOLLIE_KEY`, `MOLLIE_WEBHOOKS_ENABLED`, `MOLLIE_WEBHOOK_SIGNING_SECRETS`, `APP_NAME`, `CSP_REPORT_URI`, `SETTINGS_CACHE_ENABLED`), et vérification de la version Node.js sur le Pi et la machine de build (Vite 8 requiert 20.19+/22.12+, la machine de dev tourne en 20.18.3).

---

## B.1 — Tableau consolidé des findings (17, trié par gravité)

| # | Finding | Gravité | Module cible |
|---|---|---|---|
| 1 | Mollie : `MollieSettings.api_key` (Filament/DB) ne pilote que `isStubMode()` ; le SDK réel (`Mollie::api()`) utilise exclusivement `MOLLIE_KEY` (.env, config vendor non publié) — deux sources de clé déconnectées | **BLOQUANT** | **Brief de correction préalable à la bascule Mollie live (Phase 11)** |
| 2 | `waitlist_auto_promote = true` : comportement voulu (Phase 5, mai 2026) mais `CLAUDE.md` §2.4/§7/§12.2 jamais mis à jour après cette décision | MINEUR (doc) | Micro-patch doc `CLAUDE.md`, juste après M0 |
| 3 | Route `/up` : healthcheck minimal Laravel natif déjà présent (`bootstrap/app.php`, `health: '/up'`), sans diagnostics DB/queue | MINEUR | M3 — enrichir `/up` (pas de nouvelle route) |
| 4 | `MOLLIE_KEY`, `MOLLIE_WEBHOOKS_ENABLED`, `MOLLIE_WEBHOOK_SIGNING_SECRETS` absents de `.env.example` bien que consommés (config vendor non publié) | MINEUR | M1 |
| 5 | `APP_NAME`, `CSP_REPORT_URI`, `SETTINGS_CACHE_ENABLED` absents de `.env.example` | MINEUR | M1 |
| 6 | Node.js local 20.18.3 < minimum Vite 8 (20.19+/22.12+) — build encore fonctionnel mais hors plage supportée | MINEUR | M1 — vérifier/fixer la version Node sur Pi et machine de build |
| 7 | Couverture test hétérogène des command wrappers : 1/4 (`MarkNoShowsCommand`) a un test dédié ; les 3 autres ne sont exercés que via tests d'intégration (signature Artisan) + leurs Actions sous-jacentes (100% couvertes séparément) | MINEUR | Backlog test, hors scope Pi |
| 8 | `->viteTheme('resources/css/app.css')` commenté dans `AdminPanelProvider.php` depuis son introduction (commit `78d5819`) — bug "SVG géant dans modales" documenté Phase 8 non reproduit lors de la vérification visuelle du 2026-07-05 | MINEUR | Micro-patch doc post-M0 : activer ou supprimer la ligne morte |
| 9 | Écart de comptage tests : 475+141=616 réel vs 611 (CLAUDE.md) / 612 (brief M0) documentés | MINEUR (résolu) | Aucune action — comptage clarifié ci-dessous, contre-vérifié par l'architecte |
| 10 | 0 `env()` runtime hors `config/` (grep exhaustif `app/`, `routes/`, `resources/views/`, `database/`, `bootstrap/`) | OK | — |
| 11 | 4 commandes scheduler enregistrées dans `bootstrap/app.php` avec horaires exacts du §0.1 du brief | OK | — |
| 12 | Driver queue par défaut `database` (compatible Supervisor Pi) + 19/19 notifications implémentent `ShouldQueue` | OK | — |
| 13 | Webhook Mollie : CSRF exclu (`validateCsrfTokens(except: ['webhooks/mollie'])`), idempotence robuste (double vérification de statut + `lockForUpdate()` en transaction), stub actif sans clé API | OK | — |
| 14 | CSP (`AppCspPreset`) sans host hardcodé propre à un environnement (seul host externe : `fonts.bunny.net`, légitime) — fonctionnera sans modification sur `https://praten.embuidvo.be` ; 5 rate limiters nommés et cohérents (`register`, `login`, `checkout`, `account-deletion`, `company-creation`) | OK | — |
| 15 | `npm run build` produit un manifest Vite valide ; `inject_assets=true` confirmé actif dans `config/livewire.php` (piège Phase 7 non régressé) | OK | — |
| 16 | Aucune URL hardcodée problématique (seul hit du grep : `anonymized.local`, placeholder RGPD intentionnel dans `AnonymizeUserAction`, pas un environnement réel) ; aucun secret réel dans le code versionné | OK | — |
| 17 | Listener `AutoPromoteFromWaitlist` respecte correctement le flag `waitlist_auto_promote` (retour anticipé si `false`) — le mécanisme lui-même est propre, seule la documentation était en cause (voir finding #2) | OK | — |

---

## B.2 — Réponses aux 10 questions de validation

1. **616/616 tests verts (100%, 0 échec).** Le chiffre cible du brief (612) était une sous-estimation documentaire du delta Phase 9.6 (475+141=616 réel) — voir note de comptage ci-dessous. Aucun problème fonctionnel.
2. **Non** — 0 occurrence de `env()` hors `config/` sur l'ensemble de l'arbre applicatif.
3. **Oui** — les 4 commandes (`cards:expire`, `attendance:mark-no-shows`, `cards:warn-expiration`, `sessions:send-reminders`) sont enregistrées dans `bootstrap/app.php` avec exactement les horaires du §0.1.
4. **Oui** — `POST /webhooks/mollie` est exclue du middleware CSRF via `validateCsrfTokens(except: [...])`.
5. **Oui** — 19/19 notifications du projet implémentent `ShouldQueue`.
6. **Oui** — la CSP actuelle n'a aucun host hardcodé propre à un environnement de dev et fonctionnera sans modification sur `https://praten.embuidvo.be`.
7. **Oui** — le driver queue par défaut est `database`, compatible Supervisor sur Pi.
8. **Oui** — le mode stub Mollie fonctionne sans clé API configurée (`isStubMode()` retourne `true` par défaut).
9. **(Reformulée à la clôture du M0) La route `/up` native existe-t-elle et est-elle candidate à enrichissement M3 ?** **Oui aux deux volets** — la route existe déjà (healthcheck minimal Laravel natif via `health: '/up'`, sans diagnostics métier), et elle sera enrichie en M3 (vérifications DB + queue) plutôt que remplacée par une route distincte. La réponse pré-tranchée initiale du brief ("aucune route healthcheck n'existe") était erronée — corrigée dans ce rapport.
10. **Non sur les deux volets** — aucune URL hardcodée problématique dans le code applicatif hors tests (le seul hit du grep, `anonymized.local`, est un placeholder RGPD intentionnel) ; aucun secret réel versionné dans le code du repo.

---

## Note de référence — Comptage des tests (475 + 141 = 616)

Écart constaté au lancement du M0 : 616 tests verts réels, contre 612 (cible du brief M0 v2) et 611 (`CLAUDE.md`, fin de section Phase 9.6). Reconstitution nominative effectuée via `pest --list-tests` (comptage exact par fichier, pas d'estimation) :

- **Base Phase 9.5 : 475 tests — confirmée exacte.** Calcul : 458 tests dans les fichiers jamais touchés par Phase 9.6, + 17 tests que possédaient au commit `78d5819` (tip Phase 9.5) les 3 fichiers pré-existants ensuite modifiés par Phase 9.6 (`RegistrationTest.php` : 3, `OrderResourceTest.php` : 9, `CompanyHijackingTest.php` : 5). 458 + 17 = 475.
- **Delta réel Phase 9.6 : 141 tests, pas 136 ou 137.** Calcul : 139 tests dans les 24 fichiers de tests entièrement nouveaux de Phase 9.6, + 2 tests nets ajoutés dans les fichiers pré-existants modifiés (`CompanyHijackingTest.php` : 5→7 ; `RegistrationTest.php` et `OrderResourceTest.php` inchangés en nombre, juste un fix Carbon sur ce dernier). 139 + 2 = 141.
- **Total : 475 + 141 = 616**, exactement le compte réel (confirmé par l'exécution `vendor/bin/pest --no-coverage` et par `vendor/bin/pest --list-tests`, et contre-vérifié côté architecte via `git diff` sur les 24 nouveaux fichiers de tests).

**Conclusion : ni `CLAUDE.md` ni le brief M0 n'étaient faux sur la base (475, confirmée) — tous deux ont sous-compté le delta Phase 9.6 de 4 à 5 tests**, vraisemblablement par cumul approximatif des totaux documentés incrémentalement sur les 5 sous-étapes A→E de la Phase 9.6. Aucun test manquant, aucun test fantôme, aucune action requise — cette note sert de référence pour éviter de rouvrir la question dans un futur audit.

---

**Fin du Module M0.** Verdict : **GO M1 avec réserves** (3 réserves listées ci-dessus). Le micro-patch documentaire (`CLAUDE.md`) et le brief M1 (runbook setup Pi) sont hors scope de ce document et arriveront séparément.
