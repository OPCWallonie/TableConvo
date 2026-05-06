<?php

namespace App\Actions\Registration;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\User;
use App\Services\BusinessDay\BusinessDayService;
use App\Settings\BookingSettings;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class CancelRegistrationAction
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly BusinessDayService $businessDayService
    ) {}

    public function execute(Registration $registration, User $cancelledBy, bool $adminOverride = false): Registration
    {
        if (! $adminOverride) {
            $deadline = $this->businessDayService->subtractBusinessDays(
                $registration->conversationTable->scheduled_at,
                $this->settings->cancellation_deadline_business_days
            );

            if (now()->gt($deadline)) {
                throw new RuntimeException('cancellation_deadline_passed');
            }
        }

        return DB::transaction(function () use ($registration, $cancelledBy, $adminOverride) {
            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy->id,
            ]);

            if ($registration->card_id) {
                $registration->card->increment('sessions_remaining');
            }

            activity()
                ->performedOn($registration)
                ->causedBy($cancelledBy)
                ->log($adminOverride ? 'Annulation par l\'admin' : 'Annulation par le membre');

            return $registration->fresh();
        });
    }
}
