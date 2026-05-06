<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique()->comment('Numéro séquentiel ex: FAC-2026-00001');
            $table->dateTime('issued_at');
            $table->decimal('total_ht', 10, 2);
            $table->decimal('total_vat', 10, 2);
            $table->decimal('total_ttc', 10, 2);
            $table->json('billing_snapshot')->comment('Snapshot immuable : société destinataire + société émettrice');
            $table->string('pdf_path')->nullable()->comment('storage/invoices/...');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
