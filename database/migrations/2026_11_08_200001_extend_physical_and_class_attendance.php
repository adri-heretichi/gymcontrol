<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->date('fecha_salida')->nullable()->after('hora_salida');
            $table->foreignId('operador_ingreso_id')->nullable()->after('dispositivo_origen')
                ->constrained('users')->nullOnDelete();
            $table->string('metodo_salida', 30)->nullable()->after('operador_ingreso_id');
            $table->foreignId('operador_salida_id')->nullable()->after('metodo_salida')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('asistencias_clases', function (Blueprint $table) {
            $table->foreignId('inscripcion_id')->nullable()->after('socio_id')
                ->constrained('inscripciones')->nullOnDelete();
            $table->time('hora_inicio_programada')->nullable()->after('fecha');
            $table->time('hora_fin_programada')->nullable()->after('hora_inicio_programada');
            $table->string('origen_registro', 30)->nullable()->after('marcado_por');
            $table->time('hora_marcado')->nullable()->change();
            $table->unique(
                ['socio_id', 'grupo_id', 'fecha', 'hora_inicio_programada'],
                'asistencias_clases_unique_session'
            );
        });

        Schema::create('configuraciones_gimnasio', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('tolerancia_tardanza_minutos')->nullable();
            $table->foreignId('actualizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_gimnasio');

        Schema::table('asistencias_clases', function (Blueprint $table) {
            $table->dropUnique('asistencias_clases_unique_session');
            $table->dropConstrainedForeignId('inscripcion_id');
            $table->dropColumn(['hora_inicio_programada', 'hora_fin_programada', 'origen_registro']);
            $table->time('hora_marcado')->nullable(false)->change();
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operador_ingreso_id');
            $table->dropConstrainedForeignId('operador_salida_id');
            $table->dropColumn(['fecha_salida', 'metodo_salida']);
        });
    }
};
