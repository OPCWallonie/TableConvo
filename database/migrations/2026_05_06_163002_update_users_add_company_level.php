<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->foreignId('level_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->timestamp('level_assigned_at')->nullable()->after('level_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('level_id');
            $table->dropColumn('level_assigned_at');
        });
    }
};
