<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     */
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id(); // Clave primaria
            // Relación con la tabla socios
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->date('fecha'); // Fecha del día de la asistencia
            $table->time('hora_ingreso'); // Hora de entrada del socio
            $table->time('hora_salida')->nullable(); // Hora de salida (puede estar vacía al ingresar)
            $table->integer('tiempo_permanencia')->nullable(); // Tiempo total de permanencia en minutos (se calcula al salir)
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
