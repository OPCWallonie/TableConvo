<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sessions_total')->comment('Snapshot du nombre de sessions au moment de l\'achat');
            $table->unsignedInteger('sessions_remaining');
            $table->decimal('price_paid', 8, 2)->comment('Snapshot du prix payé');
            $table->dateTime('purchased_at');
            $table->dateTime('expires_at');
            $table->string('status')->default('active')->comment('active, expired, refunded');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
