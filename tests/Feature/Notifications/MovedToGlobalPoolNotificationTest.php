<?php

use App\Enums\GlobalWaitlistSource;
use App\Models\ConversationTable;
use App\Models\GlobalWaitlistEntry;
use App\Models\Level;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\MovedToGlobalPoolNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────

function makeMovedToPoolNotifSetup(): array
{
    $level = Level::factory()->create(['code' => 'B2', 'sort_order' => 4]);
    $admin = User::factory()->create();
    $user  = User::factory()->create(['first_name' => 'Marie', 'level_id' => $level->id]);
    $table = ConversationTable::factory()->create(['level_id' => $level->id, 'topic' => 'La nature en Flandre']);

    return compact('level', 'admin', 'user', 'table');
}

// ─── 4 variantes : cancelled × recredited ───────────────────

it('mail subject contains the level code', function () {
    ['level' => $level, 'admin' => $admin, 'user' => $user] = makeMovedToPoolNotifSetup();

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'    => $user->id,
        'level_id'   => $level->id,
        'created_by' => $admin->id,
        'source'     => GlobalWaitlistSource::AdminRemovedWaitlist,
    ]);

    $notification = new MovedToGlobalPoolNotification($entry);
    $mail = $notification->toMail($user);

    expect($mail)->toBeInstanceOf(MailMessage::class);
    expect($mail->subject)->toContain('B2');
});

it('mail mentions topic and date when cancelled (was_cancelled=true, was_recredited=false)', function () {
    ['level' => $level, 'admin' => $admin, 'user' => $user, 'table' => $table] = makeMovedToPoolNotifSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => 'cancelled',
    ]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'                => $user->id,
        'level_id'               => $level->id,
        'created_by'             => $admin->id,
        'source'                 => GlobalWaitlistSource::AdminCancelledRegistration,
        'source_registration_id' => $registration->id,
    ]);

    $notification = new MovedToGlobalPoolNotification($entry, wasRecredited: false);
    $rendered = (string) $notification->toMail($user)->render();

    expect($rendered)->toContain('La nature en Flandre');
    expect($rendered)->not->toContain('recréditée');
});

it('mail mentions recrediting when was_recredited=true (cancelled case)', function () {
    ['level' => $level, 'admin' => $admin, 'user' => $user, 'table' => $table] = makeMovedToPoolNotifSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => 'cancelled',
    ]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'                => $user->id,
        'level_id'               => $level->id,
        'created_by'             => $admin->id,
        'source'                 => GlobalWaitlistSource::AdminCancelledRegistration,
        'source_registration_id' => $registration->id,
    ]);

    $notification = new MovedToGlobalPoolNotification($entry, wasRecredited: true);
    $rendered = (string) $notification->toMail($user)->render();

    expect($rendered)->toContain('recréditée');
});

it('mail omits recrediting when was_recredited=false (waitlisted case)', function () {
    ['level' => $level, 'admin' => $admin, 'user' => $user, 'table' => $table] = makeMovedToPoolNotifSetup();

    $registration = Registration::factory()->create([
        'user_id'               => $user->id,
        'conversation_table_id' => $table->id,
        'status'                => 'cancelled',
        'waitlist_position'     => 1,
    ]);

    $entry = GlobalWaitlistEntry::factory()->pending()->create([
        'user_id'                => $user->id,
        'level_id'               => $level->id,
        'created_by'             => $admin->id,
        'source'                 => GlobalWaitlistSource::AdminRemovedWaitlist,
        'source_registration_id' => $registration->id,
    ]);

    $notification = new MovedToGlobalPoolNotification($entry, wasRecredited: false);
    $rendered = (string) $notification->toMail($user)->render();

    expect($rendered)->toContain('La nature en Flandre');
    expect($rendered)->not->toContain('recréditée');
});
