<?php

namespace App\Livewire\Admin;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardType;
use App\Settings\ThemeSettings;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class CardDesignPreview extends Component
{
    #[Reactive]
    public string $design = 'stamp';

    #[Reactive]
    public string $primaryColor = '#2563eb';

    #[Reactive]
    public string $accentColor = '#d97706';

    #[Reactive]
    public string $surfaceColor = '#f3f4f6';

    public function mount(
        string $design = 'stamp',
        string $primaryColor = '#2563eb',
        string $accentColor = '#d97706',
        string $surfaceColor = '#f3f4f6',
    ): void {
        $this->design       = $design;
        $this->primaryColor = $primaryColor;
        $this->accentColor  = $accentColor;
        $this->surfaceColor = $surfaceColor;
    }

    public function mockCard(): Card
    {
        $type = new CardType(['name' => 'Carte Standard', 'sessions_count' => 10]);

        $card = new Card([
            'sessions_total'     => 10,
            'sessions_remaining' => 7,
            'price_paid'         => 250.00,
            'purchased_at'       => now(),
            'expires_at'         => now()->addMonths(6),
            'status'             => CardStatus::Active,
        ]);
        $card->setRelation('cardType', $type);

        return $card;
    }

    public function render()
    {
        $safeDesign = in_array($this->design, ['stamp', 'wallet', 'editorial', 'swiss'])
            ? $this->design
            : 'stamp';

        return view('livewire.admin.card-design-preview', [
            'card'       => $this->mockCard(),
            'safeDesign' => $safeDesign,
        ]);
    }
}
