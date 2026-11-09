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
        // 1. Diferenciar accesos físicos agregando método y dispositivo a asistencias
        Schema::table('asistencias', function (Blueprint $table) {
            $table->string('metodo_acceso', 30)->default('manual_operador')->after('tiempo_permanencia'); // pin_terminal, qr_movil, pin_recepcion, manual_operador
            $table->string('dispositivo_origen', 50)->nullable()->after('metodo_acceso');
        });

        // 2. Crear tabla independiente para asistencias a clases/disciplinas
        Schema::create('asistencias_clases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->nullable()->constrained('asistencias')->nullOnDelete();
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('restrict');
            $table->date('fecha');
            $table->time('hora_marcado');
            $table->string('estado', 30)->default('presente'); // presente, tarde, ausente_justificado, cancelado
            $table->foreignId('marcado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'fecha']);
            $table->index(['grupo_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias_clases');

        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn(['metodo_acceso', 'dispositivo_origen']);
        });
    }
};
