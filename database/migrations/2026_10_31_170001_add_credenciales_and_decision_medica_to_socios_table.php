<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            // Credenciales diferenciadas: PIN (6 dígitos) y QR criptográfico único
            $table->string('pin', 6)->nullable()->after('token')->index();
            $table->string('qr_token', 64)->nullable()->unique()->after('pin');
            $table->string('estado_credencial', 20)->default('sin_emitir')->after('qr_token'); // sin_emitir, activo, revocado
            $table->timestamp('credenciales_emitidas_at')->nullable()->after('estado_credencial');
            $table->timestamp('credenciales_revocadas_at')->nullable()->after('credenciales_emitidas_at');
            $table->string('motivo_revocacion')->nullable()->after('credenciales_revocadas_at');

            // Decisión médica individual exclusiva de la Dueña: pendiente, requerido, no_requerido
            $table->string('decision_medica', 20)->default('pendiente')->after('estado');
            $table->timestamp('decision_medica_at')->nullable()->after('decision_medica');
            $table->foreignId('decision_medica_por')->nullable()->after('decision_medica_at')->constrained('users')->nullOnDelete();
            $table->text('decision_medica_obs')->nullable()->after('decision_medica_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropForeign(['decision_medica_por']);
            $table->dropColumn([
                'pin',
                'qr_token',
                'estado_credencial',
                'credenciales_emitidas_at',
                'credenciales_revocadas_at',
                'motivo_revocacion',
                'decision_medica',
                'decision_medica_at',
                'decision_medica_por',
                'decision_medica_obs',
            ]);
        });
    }
};
