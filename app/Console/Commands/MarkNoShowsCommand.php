<?php

namespace App\Console\Commands;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Models\ConversationTable;
use App\Settings\BookingSettings;
use Illuminate\Console\Command;

class MarkNoShowsCommand extends Command
{
    protected $signature = 'attendance:mark-no-shows';

    protected $description = 'Marque automatiquement no_show les registrations restantes des sessions Scheduled passées depuis plus de N jours.';

    public function handle(BookingSettings $settings): int
    {
        $days   = $settings->auto_mark_noshow_after_days;
        $cutoff = now()->subDays($days);

        $tables = ConversationTable::where('status', SessionStatus::Scheduled->value)
            ->where('scheduled_at', '<', $cutoff)
            ->get();

        $sessionCount = 0;
        $noShowCount  = 0;

        foreach ($tables as $table) {
            $registrations = $table->registrations()
                ->where('status', RegistrationStatus::Registered->value)
                ->get();

            foreach ($registrations as $reg) {
                $reg->update(['status' => RegistrationStatus::NoShow]);
                $noShowCount++;
            }

            $table->update(['status' => SessionStatus::Completed]);

            activity()
                ->performedOn($table)
                ->log('Session auto-clôturée — présences non saisies marquées NoShow');

            $sessionCount++;
        }

        $this->info("{$sessionCount} session(s) traitée(s), {$noShowCount} inscription(s) marquée(s) NoShow.");

        return self::SUCCESS;
    }
}
