<?php

namespace App\Actions\Card;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Notifications\CardExpirationWarningNotification;
use App\Settings\CardSettings;

class SendExpirationWarningsAction
{
    public function __construct(private readonly CardSettings $settings) {}

    public function execute(): int
    {
        $thresholds = $this->settings->expiration_warning_days;

        if (empty($thresholds)) {
            return 0;
        }

        $count = 0;

        foreach ($thresholds as $threshold) {
            $windowStart = now()->addDays($threshold)->subHours(12);
            $windowEnd   = now()->addDays($threshold)->addHours(12);

            $cards = Card::where('status', CardStatus::Active->value)
                ->whereBetween('expires_at', [$windowStart, $windowEnd])
                ->with('user')
                ->get();

            foreach ($cards as $card) {
                $sent = $card->reminders_sent ?? [];

                if (in_array($threshold, $sent)) {
                    continue;
                }

                $card->user->notify(new CardExpirationWarningNotification($card, $threshold));
                $card->update(['reminders_sent' => [...$sent, $threshold]]);
                $count++;
            }
        }

        return $count;
    }
}
