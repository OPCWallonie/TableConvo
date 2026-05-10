# TableConvo — Plateforme de gestion de tables de conversation

Plateforme B2B de gestion de tables de conversation en néerlandais. Les entreprises achètent des cartes virtuelles de sessions que leurs employés utilisent pour s'inscrire aux tables.

**Stack** : Laravel 13 · Filament 5 · Livewire 4 · Tailwind CSS · MySQL 8 · Pest

---

## Phases d'implémentation

| Phase | Description | Statut |
|-------|-------------|--------|
| 1 | Fondations : migrations, models, enums, seeders | ✅ Terminée |
| 2 | Auth, inscription société, espace membre, Filament User/Company | ✅ Terminée |
| 3 | Catalogue cartes, achat, Mollie, factures PDF | ✅ Terminée |
| 4 | Tables de conversation, agenda, inscriptions, BusinessDayService | ✅ Terminée |
| 5 | Liste d'attente, déplacement d'inscriptions admin | ✅ Terminée |
| 6 | Annulation sessions admin, expiration cartes, rappels automatisés | ✅ Terminée |
| 7 | Widgets stats, Activity Log, theming, CSP, rate limiting, tests | ✅ Terminée |
| 8 | Déploiement, 2FA admin, monitoring | 🔜 À venir |

Briefs de phase détaillés dans `docs/` : `PHASE_3_BRIEF.md` … `PHASE_7_BRIEF.md`.

---

## Pré-requis

- PHP 8.4+ (composer.json enforce `>=8.4`)
- MySQL 8 ou MariaDB 10.11+
- Node.js 20+ pour Vite/Tailwind

## Installation locale (MAMP)

```bash
cp .env.example .env
# éditer .env : DB_HOST=127.0.0.1, DB_PORT=8889, DB_DATABASE=tableconvo

composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
```

## Tests

```bash
php -d memory_limit=512M vendor/bin/pest --no-coverage
```

320 tests · couverture Actions métier 100% · intégration flux critiques incluse.

## Sécurité

- CSP via `spatie/laravel-csp` (`AppCspPreset`) sur web + panel Filament
- Rate limiting : login (5/min), register (5/10min), checkout (10/min), account-deletion (3/min)
- Webhook Mollie : CSRF exempt + idempotence garantie
- Soft deletes partout — aucune suppression physique des données métier
