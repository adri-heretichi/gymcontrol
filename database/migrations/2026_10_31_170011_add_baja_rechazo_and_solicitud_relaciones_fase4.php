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
        // 1. Campos adicionales de trazabilidad para inscripciones (bajas y rechazos)
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->date('fecha_baja')->nullable()->after('motivo_baja');
            $table->foreignId('baja_por')->nullable()->after('fecha_baja')->constrained('users')->nullOnDelete();
            $table->text('motivo_rechazo')->nullable()->after('baja_por');
            $table->foreignId('rechazado_por')->nullable()->after('motivo_rechazo')->constrained('users')->nullOnDelete();
            $table->timestamp('rechazado_at')->nullable()->after('rechazado_por');
        });

        // 2. Relaciones estructuradas en solicitudes para grupos y cambios
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->foreignId('inscripcion_id')->nullable()->after('tipo')->constrained('inscripciones')->nullOnDelete();
            $table->foreignId('grupo_id')->nullable()->after('inscripcion_id')->constrained('grupos')->nullOnDelete();
            $table->foreignId('grupo_destino_id')->nullable()->after('grupo_id')->constrained('grupos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropForeign(['grupo_destino_id']);
            $table->dropForeign(['grupo_id']);
            $table->dropForeign(['inscripcion_id']);
            $table->dropColumn(['grupo_destino_id', 'grupo_id', 'inscripcion_id']);
        });

        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropForeign(['rechazado_por']);
            $table->dropForeign(['baja_por']);
            $table->dropColumn([
                'fecha_baja',
                'baja_por',
                'motivo_rechazo',
                'rechazado_por',
                'rechazado_at',
            ]);
        });
    }
};
