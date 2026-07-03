# Brief Phase 10 — Module M0 : Audit pré-déploiement Pi

> Ce document **complète** `CLAUDE.md`, `FILAMENT_5_BRIEF.md`, et les briefs `docs/PHASE_3_BRIEF.md` à `docs/PHASE_9_5_BRIEF.md`. Il est la source de vérité du Module M0 de la Phase 10.
> À Claude Code : **lis intégralement avant la moindre action**. **Ce module est strictement un audit — AUCUNE modification de fichier autorisée.**
> **Prérequis** : Phases 1 à 9.6 + Patch 8.1 livrées ET mergées dans `main`. Si `git log --oneline -1` sur `main` ne montre PAS un commit de merge Phase 9.6 (ou postérieur), tu STOPpes immédiatement et tu le signales.
> **Version** : 2 — régénéré le 2026-07-02, ancré sur l'état réel du repo (commit `791c5d3` de la branche 9.6). Remplace la v1 du 2026-05-20 (jamais exécutée, jamais commitée).

---

## 0. État du projet à l'entrée de M0 et règles d'exécution

### 0.1 État vérifié (2026-07-02, audit direct du repo)

- Phases 1 à 9.6 + Patch 8.1 livrées. Branche `feature/phase-9.6-multi-members` mergée dans `main` (prérequis — voir bandeau ci-dessus).
- **612 tests Pest verts** attendus (475 baseline Phase 9.5 + 137 Phase 9.6). Cible à confirmer au lancement.
- Stack réelle (confirmée dans `composer.json`) : **PHP ^8.3 · Laravel ^13.7 · Filament ^5.0 · Livewire ^4.3 · Pest ^4.7** · Spatie Permission ^7.4 / Settings ^3.8 / ActivityLog ^5.0 / CSP ^3.24 / Holidays ^2.0 · Mollie SDK · DomPDF.
- Aucun fichier `.env` réel dans l'historique git (vérifié) — seul `.env.example` est versionné, sans secret.
- 4 commandes console applicatives dans `app/Console/Commands/` : `ExpireCardsCommand`, `MarkNoShowsCommand`, `SendCardExpirationWarningsCommand`, `SendSessionRemindersCommand`.
- Scheduler enregistré dans `bootstrap/app.php` via `->withSchedule()` :
  - `cards:expire` → `dailyAt('03:00')`
  - `attendance:mark-no-shows` → `dailyAt('04:00')`
  - `cards:warn-expiration` → `dailyAt('09:00')`
  - `sessions:send-reminders` → `hourly()`
- Webhook Mollie : `POST /webhooks/mollie` (`routes/web.php` ligne ~50), exclusion CSRF via `validateCsrfTokens(except: [...])` dans `bootstrap/app.php`.
- **Aucune route healthcheck** n'existe (vérifié) — la question 9 du § 7 est donc déjà tranchée : elle sera ajoutée en M3.

### 0.2 Cible de la Phase 10 (rappel du cadre)

- **Phase 10 = UAT/staging sur Raspberry Pi 4B** (ARM64, Debian 12 Bookworm, MariaDB 10.11, PHP 8.4 via sury.org, SSD USB3 128 Go pour MySQL). 3–4 testeurs séquentiels, aucune charge réelle. Période : jusqu'à septembre 2026.
- **Phase 11 = vraie production sur VPS via Laravel Forge** (Hetzner FRA1 ou Infomaniak Public Cloud), septembre 2026.
- Domaine UAT : `praten.embuidvo.be` (IP résidentielle fixe, DNS chez Infomaniak).
- Un `deploy.sh` existe déjà sur le Pi à `/var/www/tableconvo/deploy.sh`.

### 0.3 Règles d'exécution STOP/GO

1. **Tu lis intégralement ce brief avant toute action.** Tu confirmes ta compréhension et signales tout point ambigu avant de commencer.
2. **Tu attends mon GO A explicite** avant de démarrer l'étape A.
3. **À chaque STOP**, tu produis un rapport au format § 3 et tu **attends mon GO écrit**. Sauter un STOP = rollback.
4. **Tout écart entre ce brief et la réalité du code doit apparaître EN TÊTE de ton rapport** — jamais découvert sous interrogatoire.
5. **M0 est un audit pur : tu ne modifies AUCUN fichier, tu ne crées AUCUN commit.** Exception unique : à la toute fin, si je GO l'écriture du rapport persistant `docs/PHASE_10_M0_REPORT.md`, tu le crées, tu le commites et tu le pushes — c'est le SEUL commit autorisé du module.
6. **Push systématique** : tout commit autorisé est immédiatement suivi d'un `git push`.

---

## 1. Objectif du M0

Inventorier tous les écueils potentiels **avant** de toucher au Pi : configuration, secrets, scheduler, queue, webhook, CSP, assets, URLs hardcodées. Le livrable est un état des lieux exhaustif qui déterminera si on enchaîne directement sur M1 (runbook setup Pi) ou si un brief de correction intermédiaire est nécessaire.

---

## 2. Décisions tranchées (à appliquer sans discussion)

1. **Mollie reste en mode stub pour l'UAT Pi.** Les testeurs valident l'UX, pas le paiement réel. Bascule vers une clé Mollie test mode → Phase 11.
2. **Mail driver reste `log` en M0.** La bascule vers Brevo est l'objet du module M6, pas du M0.
3. **MariaDB 10.11 est le SGBD du Pi** (drop-in MySQL 8 pour Laravel). Aucune migration de SGBD à prévoir.
4. **APP_KEY** : une nouvelle clé sera générée pour l'environnement Pi en M1. Jamais de réutilisation de la clé de dev.
5. **Le repo est public par choix assumé pendant le développement.** Conséquence pour l'audit : la détection de tout secret réel dans le code ou l'historique est un finding CRITIQUE à remonter immédiatement.

---

## 3. Format de rapport obligatoire (7 sections fixes)

Chaque rapport STOP contient exactement ces 7 sections, dans cet ordre :

1. **Écarts brief/réalité** (en tête, même si "aucun")
2. **Périmètre couvert** (liste des points d'audit traités dans cette étape)
3. **Findings** (tableau : point audité · constat · gravité `OK / MINEUR / BLOQUANT` · action recommandée + module cible)
4. **Extraits de preuve** (sorties de commandes ou extraits de fichiers, bruts, tronqués si > 20 lignes)
5. **Commandes auxiliaires exécutées** (OBLIGATOIRE : toute commande lancée hors de celles prescrites par le brief, même triviale, avec justification)
6. **Hypothèses non vérifiables** (ce qui nécessite le Pi physique ou un accès que tu n'as pas)
7. **Questions pour l'architecte** (ou "aucune")

---

## 4. Étape A — Audit configuration et code applicatif

### A.1 — Baseline tests
```
php -d memory_limit=512M vendor/bin/pest --no-coverage
```
Rapporte le total exact de tests verts. Cible : **612**. Tout écart (au-dessus ou en dessous) est un finding à expliquer.

### A.2 — Inventaire configuration
- Liste toutes les variables consommées dans `config/*.php` via `env()` (nom + défaut + criticité pour le Pi).
- Compare avec `.env.example` : toute variable consommée mais absente de l'example est un finding MINEUR.

### A.3 — `env()` runtime hors `config/`
```
grep -rn "env(" app/ routes/ resources/views/ database/ bootstrap/ --include="*.php"
```
Tout appel `env()` hors `config/` est un finding (BLOQUANT si le config cache le rendrait `null` en prod). **Tu listes, tu ne corriges PAS** — même si la correction est triviale.

### A.4 — Scheduler
Vérifie que les 4 commandes du § 0.1 sont bien enregistrées dans `bootstrap/app.php`, avec exactement ces signatures et horaires. Vérifie que chaque commande a au moins un test Pest dédié.

### A.5 — Queue et notifications
- Confirme le driver queue par défaut (`config/queue.php`) et sa compatibilité Supervisor sur Pi (attendu : `database`).
- Liste TOUTES les Notifications du projet et confirme que chacune implémente `ShouldQueue`. Toute exception est un finding.

### A.6 — Webhook Mollie
- Confirme l'exclusion CSRF de `/webhooks/mollie` dans `bootstrap/app.php`.
- Confirme l'idempotence du traitement (relecture du controller/Action, pas de nouveau test à écrire).
- Confirme que le mode stub fonctionne sans clé API configurée.

### A.7 — CSP et rate limiters
- Extrait la configuration CSP actuelle (`AppCspPreset`) et évalue si elle fonctionnera telle quelle sur `https://praten.embuidvo.be` (attention aux directives contenant des hosts hardcodés).
- Liste tous les rate limiters nommés et leurs seuils.

### A.8 — Assets et build
- Confirme que `npm run build` produit un manifest Vite valide.
- Confirme la stratégie assets Filament 5 (publiés vs servis) et tout prérequis de `php artisan filament:*` au déploiement.
- Rappel piège connu : `inject_assets => true` dans `config/livewire.php` est OBLIGATOIRE (le passer à `false` casse Filament admin — régression documentée Phase 7).

### A.9 — URLs hardcodées et hygiène
```
grep -rniE "localhost|127\.0\.0\.1|\.test|\.local" app/ resources/ routes/ config/ --include="*.php" --include="*.blade.php" --include="*.js"
```
Hors tests et hors défauts `env()` légitimes de `config/`, toute occurrence est un finding.
- Scan secrets : aucun token, clé API, mot de passe réel dans le code versionné (repo public — gravité CRITIQUE si trouvé).

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP A — Rapport complet au format § 3 (7 sections).    ║
### ║  Attends mon GO B écrit avant l'étape B.                  ║
### ╚══════════════════════════════════════════════════════════╝

---

## 5. Étape B — Synthèse et verdict de déployabilité

### B.1 — Consolidation
Consolide tous les findings de l'étape A en un tableau unique trié par gravité (BLOQUANT → MINEUR → OK notable), avec pour chaque finding le module Phase 10 où il sera traité (M1–M10) ou "brief de correction préalable".

### B.2 — Réponses aux 10 questions de validation (§ 7)
Réponds OUI / NON / PARTIEL, ligne par ligne, avec une phrase de justification chacune.

### B.3 — Verdict
Une phrase parmi : « GO M1 sans réserve » / « GO M1 avec réserves (lister) » / « Brief de correction requis avant M1 (lister les bloquants) ».

### ╔══════════════════════════════════════════════════════════╗
### ║  STOP B — FIN DU MODULE M0.                                ║
### ║  Rapport § 3 + tableau B.1 + réponses B.2 + verdict B.3.  ║
### ║  Après mon GO explicite : création, commit et push de     ║
### ║  docs/PHASE_10_M0_REPORT.md (seul commit autorisé).       ║
### ╚══════════════════════════════════════════════════════════╝

---

## 6. Interdictions

1. **NE PAS modifier de fichier** (hors rapport final post-GO, § 0.3 règle 5).
2. **NE PAS corriger les `env()` runtime trouvés en A.3**, même si trivial. Liste seulement.
3. **NE PAS générer de `.env.production`** dans le repo. Le `.env` du Pi sera créé en M1, sur le Pi.
4. **NE PAS écrire de nouveaux tests.** M0 constate, il ne renforce pas.
5. **NE PAS omettre la section 5 du rapport** ("Commandes auxiliaires exécutées").
6. **NE PAS sauter un point d'audit** parce qu'il semble trivial. Tout est documenté, même un OK rapide.
7. **NE PAS compléter en silence une hypothèse fausse du brief.** Si la réalité diffère (ex. 5 commandes scheduler au lieu de 4), signale en section 1 du rapport.
8. **NE PAS utiliser `--parallel`** avec `vendor/bin/pest` (flag non supporté dans ce projet).

---

## 7. Les 10 questions de validation de fin de M0

1. Les **612** tests Pest passent-ils sans erreur au lancement du M0 ?
2. Y a-t-il des `env()` utilisés en runtime hors `config/` ? (oui/non + nombre si oui)
3. Les 4 commandes scheduler (`cards:expire`, `attendance:mark-no-shows`, `cards:warn-expiration`, `sessions:send-reminders`) sont-elles enregistrées dans `bootstrap/app.php` avec les horaires du § 0.1 ?
4. La route `POST /webhooks/mollie` est-elle exclue du middleware CSRF ?
5. Toutes les notifications du projet implémentent-elles `ShouldQueue` ?
6. La CSP actuelle fonctionnera-t-elle sur `https://praten.embuidvo.be` sans modification ?
7. Le driver queue par défaut est-il `database` (compatible Supervisor sur Pi) ?
8. Le mode stub Mollie est-il opérationnel sans clé API configurée ?
9. ~~Le projet a-t-il une route healthcheck ?~~ **Tranché : NON (vérifié le 2026-07-02). À ajouter en M3.** Confirme simplement qu'aucune route `/health` n'est apparue depuis.
10. Y a-t-il des URLs hardcodées (localhost, `*.test`, `*.local`) dans le code applicatif hors tests, ou des secrets réels versionnés ?

---

## 8. Premier prompt à donner à Claude Code

```
Lis intégralement docs/PHASE_10_M0_BRIEF.md, puis CLAUDE.md et
FILAMENT_5_BRIEF.md, avant toute action.

Vérifie D'ABORD le prérequis : `git log --oneline -3` sur main doit
montrer le merge de Phase 9.6 (multi-membres). Si ce n'est pas le
cas, STOPpe et signale-le — ne lance rien d'autre.

Confirme ensuite que tu as compris :
1. Le périmètre M0 : audit pur, AUCUN fichier modifié, AUCUN commit
   (sauf le rapport final docs/PHASE_10_M0_REPORT.md, après mon GO
   explicite, immédiatement suivi d'un push).
2. La cible Phase 10 (Pi UAT jusqu'à septembre) vs Phase 11 (vraie
   prod VPS septembre).
3. Les 5 décisions tranchées du § 2 (Mollie stub, mail log, MariaDB,
   APP_KEY neuve en M1, repo public assumé → secrets = CRITIQUE).
4. Le format de rapport § 3 : 7 sections fixes, dont la section 5
   "Commandes auxiliaires exécutées" obligatoire.
5. La règle : tout écart brief/réalité apparaît EN TÊTE du rapport.

Cite 3 patterns existants du projet que tu vas respecter dans ton
audit (ex. : isolation des secrets via Settings Spatie, logique
métier dans les Actions, inject_assets=true obligatoire pour
Filament).

Lance ensuite :
php -d memory_limit=512M vendor/bin/pest --no-coverage
et rapporte le total exact de tests verts (cible : 612).

Signale tout point ambigu du brief avant de démarrer.
Attends mon GO A explicite avant de commencer l'étape A.
```

---

**Fin du brief M0 (v2).**
