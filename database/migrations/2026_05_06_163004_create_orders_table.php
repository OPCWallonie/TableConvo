<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('company_snapshot')->comment('Snapshot immuable : nom, TVA, adresse au moment de l\'achat');
            $table->decimal('total_ht', 10, 2);
            $table->decimal('total_vat', 10, 2);
            $table->decimal('total_ttc', 10, 2);
            $table->string('status')->default('pending')->comment('pending, paid, failed, refunded');
            $table->string('mollie_payment_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
