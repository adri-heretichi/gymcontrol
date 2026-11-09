<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->char('pin_revocado_hash', 64)->nullable()->index();
            $table->char('qr_token_revocado_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropIndex(['pin_revocado_hash']);
            $table->dropIndex(['qr_token_revocado_hash']);
            $table->dropColumn(['pin_revocado_hash', 'qr_token_revocado_hash']);
        });
    }
};
