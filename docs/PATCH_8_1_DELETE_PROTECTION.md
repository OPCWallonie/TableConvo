# PATCH 8.1 — Protection des suppressions Filament contre les violations de contraintes FK

> **Type :** Patch ciblé post-Phase 8  
> **Méthodologie :** STOP/GO simple (étapes A → C)  
> **Durée estimée :** 30-45 minutes  
> **Risque :** très faible — pas de modification de modèle, pas de migration, pas de logique métier

---

## 0. RÈGLES D'EXÉCUTION (rappels)

### 0.1 Avant de coder

1. Lire le brief intégralement
2. Confirmer en début de réponse que ce n'est pas un travail métier mais un patch protecteur — aucune Action métier ne doit être touchée
3. Pas de "petite amélioration" non demandée

### 0.2 STOP/GO

À chaque étape A, B, C : tu livres, tu rapportes, tu attends GO écrit d'Arnaud.

### 0.3 Format de rapport (rappel)

Sections obligatoires :
1. Livrables réalisés
2. Checklist critères
3. Tests
4. Commandes auxiliaires exécutées (toutes, avec exit code)
5. Observations hors périmètre
6. Demande de GO

---

## 1. Contexte & déclencheur

Arnaud a essayé de supprimer un `CardType` dans le panel admin. Erreur SQL renvoyée par MySQL :

```
SQLSTATE[23000]: Integrity constraint violation: 1451
Cannot delete or update a parent row: a foreign key constraint fails
(`tableconvo`.`cards`, CONSTRAINT `cards_card_type_id_foreign` 
FOREIGN KEY (`card_type_id`) REFERENCES `card_types` (`id`))
```

La protection BDD fonctionne (c'est l'effet recherché — on ne doit jamais perdre la référence d'un type de carte vendu). Le bug, c'est que Filament a permis le clic au lieu de bloquer en amont avec un message clair.

**Problème générique :** ce comportement vaut pour **toutes les `DeleteAction`** des Resources Filament dont le modèle est référencé par d'autres entités via FK `RESTRICT` ou `NO ACTION`. Le patch doit traiter ces cas de façon systématique.

---

## 2. Objectif

Faire en sorte qu'aucune `DeleteAction` Filament ne provoque une 500 SQL. À la place :
- Soit la suppression est sûre → elle se déroule normalement
- Soit elle violerait une FK → l'action est **interrompue avant tout SQL**, et une notification Filament `danger` explique pourquoi avec une suggestion d'action alternative

---

## 3. Périmètre

### Inclus

- Audit de **toutes** les Resources Filament admin (`app/Filament/Resources/**/*Resource.php` + leurs classes `Tables/*Table.php` éventuelles)
- Identification des modèles dont la suppression peut être bloquée par une FK
- Patch `DeleteAction` (et `DeleteBulkAction` si présentes) sur ces Resources avec un `->before(...)` qui vérifie les relations et bloque proprement
- Tests Pest dédiés couvrant les cas bloquants ET les cas passants pour chaque Resource patchée

### Exclus

- ❌ Aucune migration BDD
- ❌ Aucune modification de modèle
- ❌ Aucune Action métier modifiée
- ❌ Aucun ajout de champ `is_active` ou équivalent (cette stratégie de "soft disable" est gardée en backlog pour une éventuelle Phase 10)
- ❌ Aucune modification de Resource au-delà du patch `DeleteAction`/`DeleteBulkAction`

---

## 4. Méthode

### Étape A — Audit (lecture seule, pas de code écrit)

1. Lister toutes les Resources dans `app/Filament/Resources/` : produire la liste exhaustive des modèles administrés
2. Pour chaque modèle, identifier les FK sortantes vers lui en parcourant les fichiers `database/migrations/*` (lignes contenant `->foreignId(...)->constrained(...)` ou `references(...)->on(...)`)
3. Pour chaque FK, déterminer le comportement `onDelete` (par défaut MySQL = RESTRICT)
4. Produire un tableau : **Resource → relations potentiellement bloquantes → relation Eloquent à vérifier**

**Exemple attendu de tableau d'audit :**

| Resource | Modèle | Relations bloquantes (RESTRICT/NO ACTION) | Méthode Eloquent à interroger |
|---|---|---|---|
| `CardTypeResource` | `CardType` | `cards.card_type_id` | `$record->cards()->exists()` |
| `LevelResource` | `Level` | `users.level_id`, `conversation_tables.level_id`, `global_waitlist_entries.level_id` | `$record->users()->exists() \|\| $record->conversationTables()->exists() \|\| $record->globalWaitlistEntries()->exists()` |
| ... | ... | ... | ... |

**Critère STOP/GO Étape A :**
- ✅ Liste exhaustive des Resources (aucune oubliée)
- ✅ Tableau d'audit complet avec, pour chaque Resource où la suppression est exposée à l'admin, la liste des relations à vérifier
- ✅ Si une Resource n'a pas de `DeleteAction` exposée (ex : pas d'action delete dans la table, ou la Resource n'a pas de page d'édition) : le mentionner explicitement et ne pas la patcher

**Format du rapport Étape A :**
- Section 1 : tableau d'audit complet en Markdown
- Section 2 : liste des Resources qui n'auront PAS besoin de patch (et pourquoi)
- Section 3 : liste des Resources à patcher en Étape B

**À ce stade, aucun fichier n'a été modifié.** Arnaud lit l'audit, confirme la liste de patch, et donne le GO Étape B.

### Étape B — Implémentation des patches

Pour chaque Resource identifiée en Étape A, modifier la classe `DeleteAction` (et `DeleteBulkAction` si présente) selon le pattern suivant :

```php
use Filament\Notifications\Notification;

DeleteAction::make()
    ->before(function ($record, $action) {
        // Vérifications des relations bloquantes spécifiques au modèle
        $blockingRelations = [];
        
        if ($record->cards()->exists()) {
            $blockingRelations[] = 'cartes vendues';
        }
        // ... etc, selon le tableau d'audit
        
        if (!empty($blockingRelations)) {
            Notification::make()
                ->danger()
                ->title('Suppression impossible')
                ->body(
                    'Cet élément est référencé par : ' . implode(', ', $blockingRelations) . '. ' .
                    'Pour des raisons de traçabilité, la suppression n\'est pas autorisée. ' .
                    'Vous pouvez en revanche le renommer ou contacter le support.'
                )
                ->persistent()
                ->send();
            
            $action->cancel();
        }
    })
```

**Pour `DeleteBulkAction`** (si présente sur la Resource), pattern équivalent qui itère sur `$records` et bloque l'ensemble si au moins un enregistrement est protégé. Pas de demi-mesure (pas de "supprime ceux qui peuvent être supprimés, ignore les autres") — l'admin doit comprendre que l'opération entière est bloquée et identifier laquelle.

### Contraintes implémentation

- **Localisation des patches :** dans la classe `Tables/*Table.php` si elle existe pour la Resource, sinon dans la Resource elle-même
- **Aucune duplication :** si plusieurs Resources ont des relations similaires à vérifier, c'est tolérable de dupliquer le code de vérification (cf. principe Phase 8 § 0.3 — no DRY abstraction au cas où). Pas de helper, pas de trait abstrait.
- **Notifications :** message en français, ton courtois, **toujours** suggérer une alternative explicite (renommer, désactiver via formulaire d'édition, contacter support)
- **`->persistent()`** sur la notification : l'admin doit avoir le temps de lire le message complet, qui peut être long

**Critère STOP/GO Étape B :**
- ✅ Chaque Resource du tableau d'audit a son `DeleteAction` patchée
- ✅ Aucune autre modification de Resource
- ✅ Aucune Action métier touchée
- ✅ Tests Pest existants (439/439) restent verts — exécute `php artisan test --parallel` et colle la sortie

### Étape C — Tests Pest dédiés

Pour chaque Resource patchée, créer un test Pest dans `tests/Feature/Filament/Resources/{ResourceName}DeleteProtectionTest.php` qui couvre :

1. **Cas passant** : l'enregistrement n'a aucune relation bloquante → la suppression réussit
2. **Cas bloquant — une relation** : l'enregistrement a une relation bloquante → la suppression est interrompue, une `Notification::danger` est envoyée, l'enregistrement existe toujours en BDD
3. **Cas bloquant — plusieurs relations** (si la Resource a plusieurs relations potentiellement bloquantes) : toutes sont mentionnées dans le message de notification

**Pattern de test (référence) :**

```php
use Filament\Notifications\Notification;
use function Pest\Livewire\livewire;

it('blocks card type deletion when cards exist', function () {
    $cardType = CardType::factory()->create();
    Card::factory()->for($cardType)->create();
    
    livewire(\App\Filament\Resources\CardType\Pages\EditCardType::class, ['record' => $cardType->getRouteKey()])
        ->callAction('delete')
        ->assertNotified();
    
    expect(CardType::find($cardType->id))->not->toBeNull();
});

it('allows card type deletion when no cards exist', function () {
    $cardType = CardType::factory()->create();
    
    livewire(\App\Filament\Resources\CardType\Pages\EditCardType::class, ['record' => $cardType->getRouteKey()])
        ->callAction('delete');
    
    expect(CardType::find($cardType->id))->toBeNull();
});
```

**Adapter** : selon que l'action delete est sur la page d'édition (`EditCardType`) ou sur la table de liste (`ListCardTypes` → `callTableAction('delete', record: ...)`)

**Critère STOP/GO Étape C :**
- ✅ Un fichier de test par Resource patchée
- ✅ Au moins 2 tests par fichier (passant + bloquant)
- ✅ Sortie `php artisan test --parallel` complète (cible : 439 + (2 × nb Resources patchées) verts)

---

## 5. Format de rapport final (Étape C)

Sections § 0.6 standard, avec en plus :

**Section 5 — Bilan synthétique :**
- Nombre de Resources auditées (total)
- Nombre de Resources patchées
- Nombre de tests ajoutés
- Tests verts : `XXX/XXX`

---

## 6. Ce que tu ne touches PAS

- ❌ Modèles Eloquent (pas de scope `notDeletableIfHasX`, pas de trait, rien)
- ❌ Migrations (pas d'ajout de `onDelete('set null')` ou autre)
- ❌ Actions métier
- ❌ Resources autres que celles identifiées en Étape A
- ❌ Le panel utilisateur final (espace membre `/espace/*`) : seul le panel admin Filament est concerné

---

## 7. Points d'attention

- **Soft deletes :** plusieurs modèles utilisent le trait `SoftDeletes`. Pour ces modèles, la `DeleteAction` standard Filament fait un soft delete (pas un DELETE SQL). Donc la FK n'est PAS violée et le bug n'existe pas. **À identifier en Étape A** et à exclure du périmètre de patch.

  La règle : ne patcher que les `DeleteAction` qui font effectivement un `forceDelete()` ou qui pointent vers un modèle sans soft deletes.

- **Si une Resource expose à la fois `DeleteAction` (soft) et `ForceDeleteAction` (hard) :** patcher uniquement la `ForceDeleteAction`. La soft delete reste autorisée.

- **Si toutes les Resources finissent par utiliser soft deletes :** le patch peut se révéler vide (aucune Resource à toucher). Dans ce cas, rapporter explicitement en Étape A : "Aucun patch nécessaire — tous les modèles administrés utilisent SoftDeletes." Et c'est terminé.

---

**Fin du brief Patch 8.1.**
