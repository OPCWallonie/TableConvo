# Brief Filament 5 — Mise à jour du PROJECT_BRIEF.md

> Ce document **complète et corrige** le `PROJECT_BRIEF.md` initial qui mentionnait Filament 3.
> Le projet utilise **Filament 5.6** (imposé par Laravel 13).
> À Claude Code : **lis ce document avant toute action sur Filament**. La syntaxe Filament 3 que tu pourrais avoir en mémoire est **largement obsolète**.

---

## 1. Versions confirmées du projet

- Laravel 13.x (PHP 8.3+ requis)
- **Filament 5.6** (et non Filament 3 comme indiqué dans le brief initial)
- Livewire 4.x (requis par Filament 5)
- Tailwind CSS v4 (requis par Filament 4+)

---

## 2. Filament 5 vs Filament 3 — CE QUI A CHANGÉ

**Important** : Filament 5 est quasi identique à Filament 4 côté API (le saut majeur v3→v4 a apporté tous les changements ; v4→v5 = Livewire 4 uniquement). Donc la doc Filament 4 s'applique aussi.

Les ressources sur https://filamentphp.com/docs/5.x/ font foi.

### 2.1 Architecture des Resources : Schemas et Tables séparés

En **Filament 3**, tout était dans la classe `Resource` :
```php
// FILAMENT 3 (OBSOLÈTE — NE PAS UTILISER)
class UserResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('email'),
        ]);
    }
}
```

En **Filament 5**, le générateur crée des **classes dédiées** :
```
app/Filament/Resources/Users/
├── UserResource.php
├── Schemas/
│   └── UserForm.php          ← formulaire dans sa propre classe
├── Tables/
│   └── UsersTable.php        ← table dans sa propre classe
└── Pages/
    ├── ListUsers.php
    ├── CreateUser.php
    └── EditUser.php
```

Et la `UserResource` se contente de **pointer vers ces classes** :
```php
// FILAMENT 5
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }
}
```

Et `UserForm.php` :
```php
namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')->required()->email(),
            // ...
        ]);
    }
}
```

**À noter** :
- Le paramètre injecté est maintenant un `Schema` (pas un `Form`). Le `Schema` est unifié pour forms, infolists et même certaines tables.
- Méthode `->components([...])` (pas `->schema([...])` comme en v3 sur `$form`).

### 2.2 Namespace unifié pour les Actions

En Filament 3, les actions étaient éclatées entre plusieurs namespaces selon le contexte (table, form, page). C'était un cauchemar d'imports.

En Filament 5, **tout est sous `Filament\Actions`** :

```php
// FILAMENT 3 (OBSOLÈTE)
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Pages\Actions\Action as PageAction;

// FILAMENT 5 (CORRECT)
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
```

Une seule action peut maintenant être utilisée partout (table, form, page, widget).

### 2.3 Renommages de méthodes dans les tables

Plusieurs méthodes ont été renommées :

| Filament 3        | Filament 5         |
|-------------------|--------------------|
| `->actions([])`   | `->recordActions([])` |
| `->bulkActions([])` | `->toolbarActions([])` (avec `BulkActionGroup`) |

Exemple v5 :
```php
return $table
    ->columns([...])
    ->filters([...])
    ->recordActions([
        EditAction::make(),
    ])
    ->toolbarActions([
        BulkActionGroup::make([
            DeleteBulkAction::make(),
        ]),
    ]);
```

### 2.4 Navigation icons

```php
// FILAMENT 3
protected static ?string $navigationIcon = 'heroicon-o-users';

// FILAMENT 5 (typage modifié)
protected static \BackedEnum | string | null $navigationIcon = 'heroicon-o-users';
```

L'enum `Heroicon` est utilisable :
```php
use Filament\Support\Icons\Heroicon;

protected static \BackedEnum | string | null $navigationIcon = Heroicon::OutlinedUsers;
```

### 2.5 Composants Schema partagés

En v5, les composants de layout (`Section`, `Grid`, `Tabs`, `Wizard`, etc.) sont dans `Filament\Schemas\Components\` et peuvent être utilisés indifféremment dans forms, infolists et certaines tables. Plus de duplication.

```php
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
```

---

## 3. Commandes de génération

```bash
# Créer une Resource avec son model, sa migration et sa factory
php artisan make:filament-resource Card --model --migration --factory

# Créer une Resource depuis une migration existante (auto-détecte les colonnes)
php artisan make:filament-resource Card --generate

# Créer un widget de stats
php artisan make:filament-widget RevenueOverview --stats-overview

# Créer un widget chart
php artisan make:filament-widget RegistrationsChart --chart

# Créer une page custom
php artisan make:filament-page Settings
```

---

## 4. Intégration avec Spatie Permission

Filament 5 s'intègre nativement aux **Policies Laravel**. Avec `spatie/laravel-permission`, on procède en 3 étapes :

1. Créer une Policy par modèle (`UserPolicy`, `CardPolicy`, etc.).
2. Dans chaque policy, vérifier les permissions Spatie : `return $user->can('view-cards');`
3. Activer dans le `AdminPanelProvider` : `->authMiddleware(['auth', 'role:admin'])`

Pour l'accès au panel lui-même, le model `User` doit implémenter `FilamentUser` :
```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
}
```

---

## 5. Pièges spécifiques à éviter

1. **Ne jamais utiliser `Filament\Forms\Form` ou `Filament\Tables\Actions\*`** dans les nouveaux fichiers. C'est de la syntaxe v3 obsolète.
2. **Toujours injecter `Schema $schema`** dans les méthodes `form()` et `infolist()`, pas `Form` ni `Infolist`.
3. **`->components([])` sur un Schema**, pas `->schema([])` comme en v3.
4. **`recordActions()` et `toolbarActions()`** sur une Table, pas `actions()` ni `bulkActions()`.
5. **Tailwind v4** : si tu fais un theme custom, le CSS d'import a changé. Voir https://filamentphp.com/docs/5.x/styling
6. **Pas de plugins Filament 3 ou 4** dans `composer.json` sans vérifier qu'ils ont une version compatible v5. La plupart des plugins majeurs ont été mis à jour, mais certains traînent.
7. **Livewire 4** : si tu écris des composants Livewire customs (côté espace membre), suivre la syntaxe Livewire 4 (https://livewire.laravel.com/docs/upgrading).

---

## 6. Documentation officielle à consulter en priorité

- **Resources** : https://filamentphp.com/docs/5.x/resources/overview
- **Forms & Schemas** : https://filamentphp.com/docs/5.x/forms/overview et https://filamentphp.com/docs/5.x/schemas/overview
- **Tables** : https://filamentphp.com/docs/5.x/tables/overview
- **Actions** : https://filamentphp.com/docs/5.x/actions/overview
- **Upgrade guide v3 → v4** : https://filamentphp.com/docs/5.x/upgrade-guide (pour comprendre toutes les ruptures)
- **Authorization** : https://filamentphp.com/docs/5.x/users/overview

**À Claude Code** : avant d'écrire une Resource, lance `php artisan make:filament-resource <Name> --generate` et **regarde le squelette généré**. C'est la source de vérité pour la syntaxe v5 du projet.

---

## 7. Action immédiate à effectuer

1. Vérifier que tous les fichiers Filament déjà créés en Phase 1 utilisent bien la syntaxe v5 (Schema injecté, namespace `Filament\Actions`, méthodes `recordActions()`/`toolbarActions()`, classes `Schemas/` et `Tables/` séparées).
2. Si du code Filament 3 a été généré par mégarde, le refactorer **avant Phase 2**. Sinon la dette technique va s'accumuler.
3. Mettre à jour le `PROJECT_BRIEF.md` initial pour remplacer toutes les mentions de "Filament 3" par "Filament 5" et signaler que ce présent document fait foi sur la syntaxe Filament.

Fin du brief Filament 5.
