<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_waitlist_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('level_id')
                ->constrained()
                ->restrictOnDelete();

            $table->dateTime('requested_at');

            $table->string('source')->comment('admin_removed_waitlist, admin_cancelled_registration, user_volunteer');

            $table->foreignId('source_registration_id')
                ->nullable()
                ->constrained('registrations')
                ->nullOnDelete();

            $table->text('admin_reason')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status')->default('pending')->comment('pending, reassigned, dismissed');

            $table->foreignId('reassigned_to_registration_id')
                ->nullable()
                ->constrained('registrations')
                ->nullOnDelete();

            $table->text('dismissed_reason')->nullable();
            $table->dateTime('dismissed_at')->nullable();

            $table->foreignId('dismissed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['level_id', 'status', 'requested_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_waitlist_entries');
    }
};
