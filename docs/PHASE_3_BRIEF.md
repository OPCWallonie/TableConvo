# Brief Phase 3 — Catalogue, achat, paiement, facturation

> Ce document **complète** `CLAUDE.md` et `FILAMENT_5_BRIEF.md`. Il est la source de vérité de la Phase 3.
> À Claude Code : **lis intégralement avant d'écrire la moindre ligne**. Aucune des décisions ci-dessous ne se discute sans validation explicite de l'utilisateur.

---

## 0. État du projet à l'entrée de Phase 3

### Ce qui existe déjà — NE PAS REFAIRE

Les briques suivantes sont déjà implémentées et testées. **Tu les réutilises telles quelles** :

- `app/Settings/CompanySettings.php` (vendeur — vide à l'heure actuelle, sera renseignée via Filament)
- `app/Settings/InvoicingSettings.php` avec `default_vat_rate = 21.00`
- `app/Settings/CardSettings.php`, `BookingSettings.php`, `SessionDefaultsSettings.php`
- `app/Models/InvoiceCounter.php` + migration
- `app/Actions/Invoice/GenerateInvoiceNumberAction.php` (verrou pessimiste, séquentiel par année, format configurable)
- `app/Actions/Invoice/GenerateInvoiceAction.php` (idempotente sur l'order, snapshot complet)
- `app/Models/Order.php`, `OrderItem.php`, `Invoice.php`, `Card.php`, `CardType.php` + migrations
- `app/Enums/OrderStatus.php`, `CardStatus.php`
- Packages Mollie installés : `mollie/laravel-mollie` v4.1, `mollie/mollie-api-php` v3.11
- DomPDF installé : `barryvdh/laravel-dompdf`
- 70 tests verts au démarrage de la phase

### Décisions tranchées (à appliquer sans discussion)

1. **TVA : 21% standard pour tout** (B2B Belgique). Pas de gestion intracom au MVP. Le `default_vat_rate` reste à 21.00, configurable dans les settings pour le futur.
2. **Mollie : pas encore d'abonnement souscrit.** Tu DOIS prévoir un **mode stub** activable quand `MollieSettings::api_key` est vide, qui simule un paiement réussi instantanément pour pouvoir tester le flow complet en dev/staging sans clé API réelle.
3. **CGV : pas rédigées.** Tu prévois un **upload de PDF** dans les settings + une page `/cgv` qui sert ce PDF. Si pas de PDF uploadé, la page affiche un placeholder "À paraître".
4. **Tous les paramètres société et opérationnels passent par un écran de paramétrage Filament.** Aucune valeur sensible n'est en dur dans le code.

---

## 1. Settings à créer ou compléter

### Nouveau : `app/Settings/MollieSettings.php`

```php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MollieSettings extends Settings
{
    public string $api_key = '';
    public bool $test_mode = true;
    public string $webhook_secret = '';  // optionnel, pour signature future

    public static function group(): string
    {
        return 'mollie';
    }
}
```

Migration associée dans `database/settings/`.

### Nouveau : `app/Settings/LegalSettings.php`

```php
class LegalSettings extends Settings
{
    public string $cgv_pdf_path = '';        // storage path relatif (disk public)
    public string $privacy_pdf_path = '';    // idem

    public static function group(): string
    {
        return 'legal';
    }
}
```

### À compléter : `EmailSettings`

Si elle n'existe pas encore, la créer maintenant (utilisée par les jobs de facturation) :

```php
class EmailSettings extends Settings
{
    public string $from_email = '';
    public string $from_name = '';
    public string $reply_to = '';
    public string $admin_notifications_email = '';

    public static function group(): string
    {
        return 'email';
    }
}
```

---

## 2. Pages Filament de paramétrage

**Tu crées une page Filament dédiée par groupe de settings**, organisées dans le menu Filament sous une rubrique "Paramètres".

Approche recommandée : utiliser le plugin `filament/spatie-laravel-settings-plugin` si disponible en v5, sinon créer des pages custom héritant de `Filament\Pages\Page` avec un schema mappé sur les classes Settings.

Pages à créer :

| Page | Settings | Champs spéciaux |
|------|----------|-----------------|
| `ManageCompanySettings` | `CompanySettings` | logo (FileUpload, disk public), pas de validation TVA bloquante |
| `ManageInvoicingSettings` | `InvoicingSettings` | `default_vat_rate` avec suffix "%", `vat_exempt_legal_mention` en RichEditor |
| `ManageMollieSettings` | `MollieSettings` | `api_key` champ password (toggle reveal), `test_mode` Toggle, message d'aide indiquant le mode stub si vide |
| `ManageLegalSettings` | `LegalSettings` | `cgv_pdf_path` FileUpload PDF only, `privacy_pdf_path` idem |
| `ManageBookingSettings` | `BookingSettings` | tous les champs avec helper text français |
| `ManageCardSettings` | `CardSettings` | `expiration_warning_days` TagsInput typé int |
| `ManageSessionDefaultsSettings` | `SessionDefaultsSettings` | défauts pour création de tables |
| `ManageEmailSettings` | `EmailSettings` | tous email |

Chaque page :
- Navigation group : "Paramètres"
- Icône heroicon adaptée
- Authorization : `static function canAccess(): bool { return auth()->user()?->hasRole('admin') ?? false; }`
- Bouton "Enregistrer" qui appelle `$settings->save()`
- Notification de succès en français

---

## 3. Filament Resource : `CardTypeResource`

Resource standard sur le model `CardType`. Champs :

- `name` (TextInput, required, max 255)
- `sessions_count` (TextInput numeric, required, min 1)
- `price` (TextInput numeric, required, step 0.01, suffix "€")
- `validity_months` (TextInput numeric, required, min 1)
- `is_active` (Toggle, default true)

Table :
- Colonnes : name, sessions_count, price (formaté €), validity_months, is_active (IconColumn)
- Filtre : `is_active`
- Actions : Edit, Delete (soft), avec bulk
- Pas de modification de prix sur un CardType qui a déjà été acheté ? **Non, on autorise la modification** mais ça n'affecte pas les cartes déjà vendues (elles ont un snapshot `price_paid`).

Navigation group : "Catalogue".

---

## 4. Page publique `/tarifs`

Contrôleur : `App\Http\Controllers\Public\PricingController@index`

Vue Blade `resources/views/public/tarifs.blade.php` :
- Liste tous les `CardType` actifs triés par `price` ASC
- Carte produit pour chacun : nom, nb sessions, prix TTC, durée de validité, bouton "Acheter" → `route('achat.show', $cardType)`
- Mention légale en bas : "Prix TTC, TVA 21% incluse. Paiement sécurisé via Mollie."

Design : sobre, Tailwind, cohérent avec le reste de l'espace public. **Pas de design exotique**, on reste sur le visuel existant.

---

## 5. Flow d'achat — Livewire

### Choix d'architecture : panier en SESSION, pas en DB

Le panier n'a pas vocation à être persistant entre sessions. On le stocke dans la session Laravel pour simplicité. Une seule ligne par CardType (quantité ajustable).

### Composant `App\Livewire\Cart\CartComponent`

État :
- `items: array` chargé depuis session(`cart.items`, [])
  - Format : `['card_type_id' => quantity, ...]`

Méthodes publiques :
- `addItem(int $cardTypeId, int $qty = 1)` : ajoute ou incrémente
- `removeItem(int $cardTypeId)` : retire
- `updateQuantity(int $cardTypeId, int $qty)` : remplace, qty 0 = remove
- `clear()` : vide le panier
- Computed : `totalHt`, `totalVat`, `totalTtc` calculés à partir de `InvoicingSettings::default_vat_rate`

Vue Livewire : `resources/views/livewire/cart/cart-component.blade.php`
- Tableau récapitulatif (CardType, quantité editable, sous-total)
- Totaux HT / TVA / TTC
- Checkbox "J'accepte les CGV" obligatoire (lien vers `/cgv`)
- Bouton "Procéder au paiement" → POST `/panier/checkout` (désactivé si CGV non cochée ou panier vide)

### Pages d'achat

- `GET /achat` : redirige vers `/tarifs` (ou alias)
- `GET /achat/{cardType}` : page détail produit + bouton "Ajouter au panier" (Livewire)
- `GET /panier` : affiche le `CartComponent`
- `POST /panier/checkout` : `CheckoutController@store`

### `CheckoutController@store`

Logique :
1. Valide : panier non vide, CGV acceptées (`request()->boolean('cgv_accepted')`)
2. Vérifie que l'user est authentifié et a une `company` rattachée
3. Délègue à `App\Actions\Order\CreateOrderFromCartAction`
4. Redirige vers l'URL de paiement Mollie retournée par l'action

---

## 6. Action `CreateOrderFromCartAction`

```php
namespace App\Actions\Order;

class CreateOrderFromCartAction
{
    public function __construct(
        private readonly MollieService $mollie,
        private readonly InvoicingSettings $invoicing,
    ) {}

    public function execute(User $user, array $cartItems): array
    {
        // Retourne ['order' => Order, 'checkout_url' => string]
    }
}
```

Dans une transaction DB :
1. Snapshot de la `Company` du user (nom, vat_number, adresse complète) → `company_snapshot` JSON
2. Crée l'Order avec `status = OrderStatus::Pending`, totaux calculés depuis `InvoicingSettings::default_vat_rate`
3. Crée les `OrderItem` avec snapshot du `unit_price_ht` (HT), `vat_rate`, `vat_amount`, `total_ht`, `total_ttc` figés à l'instant T
4. Appelle `MollieService::createPayment($order)` qui retourne l'URL de checkout
5. Stocke `mollie_payment_id` sur l'Order
6. Vide le panier en session
7. Retourne l'order + l'URL

**Calcul des montants** : tous les prix CardType sont stockés en TTC. Le HT et la TVA sont déduits par division : `HT = TTC / (1 + vat_rate/100)`. Arrondi à 2 décimales (utiliser `round(..., 2, PHP_ROUND_HALF_UP)`).

---

## 7. Service Mollie

### `App\Services\Mollie\MollieService`

```php
class MollieService
{
    public function __construct(private readonly MollieSettings $settings) {}

    public function isStubMode(): bool
    {
        return empty($this->settings->api_key);
    }

    public function createPayment(Order $order): array
    {
        // Retourne ['payment_id' => string, 'checkout_url' => string]
    }

    public function fetchPayment(string $paymentId): array
    {
        // Retourne ['status' => 'paid'|'open'|'failed'|'canceled'|'expired', 'paid_at' => ?Carbon]
    }
}
```

### Mode stub (CRITIQUE — Mollie pas encore souscrit)

Si `isStubMode()` est `true` :
- `createPayment` retourne :
  - `payment_id` = `'stub_'.Str::random(20)`
  - `checkout_url` = `route('paiement.stub', ['order' => $order])` (page locale qui simule le checkout)
- `fetchPayment` retourne `['status' => 'paid', 'paid_at' => now()]` pour tout `payment_id` qui commence par `stub_`

Page stub : `resources/views/payment/stub.blade.php`
- Affiche "Mode test — Mollie non configuré"
- Affiche les détails de l'order
- Bouton "Simuler paiement réussi" → POST `/paiement/stub/{order}/confirm` qui appelle directement le webhook handler en local
- Bouton "Simuler paiement échoué" → marque l'order failed et redirige

### Mode réel (quand l'api_key est renseignée)

Utiliser le SDK Laravel-Mollie : `Mollie::api()->payments()->create([...])` avec :
- `amount` : `['currency' => 'EUR', 'value' => number_format($order->total_ttc, 2, '.', '')]`
- `description` : `"Commande #{$order->id} — TableConvo"`
- `redirectUrl` : `route('paiement.retour', ['order' => $order])`
- `webhookUrl` : `route('webhooks.mollie')` (full URL — attention au domaine en local, désactivable si nécessaire)
- `metadata` : `['order_id' => $order->id]`

---

## 8. Webhook Mollie

### Route

```php
// routes/web.php
Route::post('/webhooks/mollie', [PaymentWebhookController::class, 'mollie'])
    ->name('webhooks.mollie')
    ->withoutMiddleware([VerifyCsrfToken::class]);
```

(Adapter selon Laravel 11+ : utiliser `bootstrap/app.php` pour exclure du CSRF.)

### `PaymentWebhookController@mollie`

Logique stricte d'idempotence :

```
1. Récupérer $request->input('id') = mollie_payment_id
2. Trouver Order::where('mollie_payment_id', $id)->first(); 404 si introuvable
3. Si $order->status !== Pending : return response('OK', 200) — idempotence
4. DB::transaction:
   a. Lock pessimiste sur l'order (lockForUpdate)
   b. Re-vérifier status après lock (double-check pattern)
   c. Appeler MollieService::fetchPayment($id)
   d. Selon status retourné :
      - 'paid' → ProcessPaidOrderAction::execute($order)
      - 'failed'/'canceled'/'expired' → $order->update(['status' => Failed])
      - 'open' → ne rien faire
5. Return response('OK', 200)
```

**JAMAIS** de logique métier dans le controller. Tout passe par `ProcessPaidOrderAction`.

### `App\Actions\Order\ProcessPaidOrderAction`

```php
public function execute(Order $order): void
{
    DB::transaction(function () use ($order) {
        // 1. Marquer l'order comme payée
        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        // 2. Pour chaque OrderItem, créer N cartes (selon quantity)
        foreach ($order->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Card::create([
                    'user_id' => $order->user_id,
                    'card_type_id' => $item->card_type_id,
                    'order_id' => $order->id,
                    'sessions_total' => $item->cardType->sessions_count,
                    'sessions_remaining' => $item->cardType->sessions_count,
                    'price_paid' => $item->unit_price_ht * (1 + $item->vat_rate / 100),
                    'purchased_at' => now(),
                    'expires_at' => now()->addMonths($item->cardType->validity_months),
                    'status' => CardStatus::Active,
                ]);
            }
        }

        // 3. Générer la facture (action déjà existante, idempotente)
        $invoice = app(GenerateInvoiceAction::class)->execute($order);

        // 4. Dispatcher le job de génération PDF + email
        SendInvoiceByEmailJob::dispatch($invoice);
    });

    // 5. Notifier l'utilisateur (channel mail + database)
    $order->user->notify(new OrderPaidNotification($order));
}
```

---

## 9. Génération PDF facture

### Service `App\Services\Pdf\InvoicePdfService`

Méthode `generate(Invoice $invoice): string` qui :
1. Charge le template Blade `resources/views/pdfs/invoice.blade.php` avec `$invoice` et son `billing_snapshot`
2. Utilise DomPDF : `Pdf::loadView('pdfs.invoice', ['invoice' => $invoice])->output()`
3. Retourne le contenu binaire ou le stream

### Template `pdfs/invoice.blade.php`

Mentions OBLIGATOIRES (loi belge) — toutes lues depuis `$invoice->billing_snapshot` (immuable) :

**En-tête (issuer)** :
- Logo (si `CompanySettings::logo_path` non vide)
- Raison sociale + forme juridique
- Adresse complète
- Numéro TVA (BE0XXXXXXXXX)
- RPM (Registre des Personnes Morales) avec ville
- IBAN + BIC

**Destinataire (recipient)** :
- Raison sociale
- Adresse
- Numéro TVA

**Corps** :
- Numéro de facture (séquentiel)
- Date d'émission
- Date d'échéance (= date émission + `payment_terms_days`)
- Tableau des items : description, quantité, PU HT, TVA %, total HT, total TTC
- Totaux : total HT, TVA, total TTC

**Pied** :
- Mention "Facture acquittée le {date}" si déjà payée
- Conditions générales : "Voir CGV sur [domaine]/cgv"
- Si `vat_exempt = true` → mention légale `vat_exempt_legal_mention`

### Stockage

PDF généré et stocké dans `storage/app/private/invoices/{year}/{invoice_number}.pdf` à la première génération. La regénération à la volée est possible mais le snapshot empêche toute divergence.

### Job `SendInvoiceByEmailJob`

- Génère le PDF si non déjà présent
- Envoie un mail (Mailable `InvoicePaidMail`) à `$invoice->order->user->email` avec PDF en pièce jointe
- Si `CompanySettings::email_contact` ou `EmailSettings::admin_notifications_email` configuré, envoie une copie BCC

### Route téléchargement

`GET /espace/factures/{invoice}/pdf` — controller `Member\InvoiceController@download` :
- Policy : seul l'owner ou un admin peut télécharger
- Renvoie le fichier en download (regénère si manquant sur disque)

---

## 10. Pages de retour Mollie

### `GET /paiement/retour/{order}`

Controller `PaymentReturnController@show` :
- Vérifie que l'user est bien le owner de l'order (Policy)
- Affiche une page d'attente : "Votre paiement est en cours de traitement..."
- Page Livewire avec polling toutes les 2 secondes pendant max 30 secondes pour vérifier le statut
- Selon statut final :
  - `Paid` → redirige vers `/espace/cartes` avec flash success
  - `Failed`/`Canceled`/`Expired` → page erreur avec lien retour panier
  - Toujours `Pending` après 30s → page "Vérification en cours, vous recevrez un email"

**Important** : la page de retour ne fait PAS la logique de création de cartes. Seul le webhook le fait. La page de retour ne sert qu'à informer l'utilisateur.

---

## 11. Tests Pest à écrire

### Tests obligatoires (pas négociable)

`tests/Feature/Cart/CartComponentTest.php`
- Ajouter un item au panier
- Modifier la quantité
- Supprimer un item
- Vider le panier
- Calcul des totaux HT/TVA/TTC avec `default_vat_rate = 21`

`tests/Feature/Actions/CreateOrderFromCartActionTest.php`
- Crée Order + OrderItems avec totaux corrects
- Snapshot de la Company correctement copié
- Vide le panier après création
- En mode stub, retourne une URL `/paiement/stub/...`

`tests/Feature/Webhooks/MollieWebhookTest.php`
- Webhook reçu pour un order Pending → marque Paid + crée cartes + crée invoice
- Webhook reçu 2× pour le même order → idempotent (pas de doublons de cartes ni d'invoice)
- Webhook avec mollie_payment_id inconnu → 404
- Webhook avec status `failed` → marque Failed, pas de carte créée

`tests/Feature/Actions/ProcessPaidOrderActionTest.php`
- Crée le bon nombre de cartes (selon quantity)
- Cartes ont `expires_at` = now + validity_months
- Cartes ont `sessions_remaining` = `sessions_count`
- Génère 1 facture
- Dispatche le job email

`tests/Feature/MollieServiceTest.php`
- Mode stub si api_key vide
- `createPayment` en stub retourne payment_id `stub_...` et URL locale
- `fetchPayment` en stub retourne `paid` pour les ID `stub_...`

`tests/Feature/PdfGenerationTest.php`
- Génère un PDF non vide
- Le PDF contient le numéro de facture, le numéro TVA issuer, le numéro TVA recipient, l'IBAN

`tests/Feature/Filament/SettingsPagesTest.php`
- Un admin peut accéder à toutes les pages de settings
- Un user normal reçoit 403
- Sauvegarder une page met à jour la valeur en DB

### Cible à la fin de Phase 3

**Au moins 100 tests verts**. On part de 70 — il faut donc ajouter une trentaine de tests minimum sur cette phase.

---

## 12. Pièges spécifiques Phase 3 — INTERDICTIONS

À Claude Code : ne dévie pas des points suivants sans validation explicite.

1. **NE PAS refaire `GenerateInvoiceAction` ni `GenerateInvoiceNumberAction`.** Elles existent et sont testées. Tu les appelles, point.
2. **NE PAS créer les cartes au moment de l'order.** Les cartes sont créées UNIQUEMENT après confirmation du paiement par le webhook. Avant ça, l'order est `Pending` et il n'y a aucune carte.
3. **NE PAS modifier les snapshots** (`company_snapshot`, `billing_snapshot`) après création de l'order/invoice. Ils sont immuables, c'est leur raison d'être.
4. **NE PAS appeler la facturation depuis la page de retour Mollie.** Toute logique post-paiement passe par le webhook. La page de retour ne fait que de l'affichage.
5. **NE PAS faire de calcul de TVA en dur.** Toujours `InvoicingSettings::default_vat_rate`.
6. **Le webhook DOIT être idempotent** : recevoir 2× le même webhook ne crée pas 2 factures, ni 2× les cartes. Vérification par status de l'order + lock pessimiste.
7. **NE PAS oublier d'exclure le webhook du middleware CSRF.** Sans ça, Mollie reçoit un 419 et marque le webhook comme échoué.
8. **NE PAS générer les numéros de facture autrement que via `GenerateInvoiceNumberAction`.** Pas de `Invoice::max('id')+1`, pas de `Str::uuid()`, rien d'autre.
9. **NE PAS stocker la clé API Mollie en clair dans la DB.** Utiliser le cast `encrypted` sur la propriété `MollieSettings::api_key` (Spatie Settings supporte les casts).
10. **Mode stub OBLIGATOIRE** : tant que la clé API est vide, l'application doit pouvoir simuler un cycle d'achat complet en local sans accès réseau à Mollie. Sans ce mode, le développement est bloqué.
11. **NE PAS implémenter de gestion de remboursements / refunds** dans cette phase. Hors scope MVP.
12. **NE PAS implémenter la logique intracom (autoliquidation TVA)** dans cette phase. 21% pour tout le monde.
13. **CGV** : checkbox obligatoire au checkout, lien vers `/cgv`. La page `/cgv` sert le PDF uploadé via `LegalSettings::cgv_pdf_path`. Si pas de PDF, page placeholder "Conditions générales à paraître". **Pas de génération de CGV par Claude Code.**

---

## 13. Ordre d'implémentation recommandé

Pour permettre à l'utilisateur de tester progressivement :

1. **Settings** : créer `MollieSettings`, `LegalSettings`, `EmailSettings` (si manquante) + migrations
2. **Pages Filament de settings** : toutes en une fois, avec policy admin
3. **CardTypeResource Filament** : permet à l'utilisateur de seeder le catalogue depuis l'admin
4. **Page publique `/tarifs`** : vérifier que ça remonte les CardType
5. **CartComponent Livewire** + pages `/achat` et `/panier`
6. **CreateOrderFromCartAction** + tests
7. **MollieService en mode stub** + page stub + tests
8. **PaymentWebhookController + ProcessPaidOrderAction** + tests
9. **InvoicePdfService + template Blade + Job + Mailable** + tests
10. **Page de retour Mollie** (Livewire avec polling)
11. **Espace membre /espace/factures** : liste + téléchargement
12. **Final : `php artisan test` doit passer sans aucun fail**

À chaque étape, commit séparé avec un message clair.

---

## 14. Validation de fin de phase

Avant de déclarer Phase 3 terminée, tu dois fournir un récap qui répond aux questions suivantes :

- Combien de tests passent au total ? (cible : 100+)
- Toutes les pages de settings sont-elles accessibles via Filament et fonctionnelles ?
- Le mode stub Mollie permet-il de boucler un cycle complet achat → carte créée → facture PDF générée ?
- Le webhook est-il idempotent (preuve par test) ?
- Les snapshots sont-ils bien immuables (preuve par test) ?
- Y a-t-il un test qui prouve qu'une commande non payée NE CRÉE PAS de carte ?

---

**Fin du brief Phase 3.**
