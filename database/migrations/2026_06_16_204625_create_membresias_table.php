<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones para crear la tabla.
     */
    public function up(): void
    {
        Schema::create('membresias', function (Blueprint $table) {
            $table->id(); // Clave primaria
            $table->string('nombre'); // Nombre del plan de membresía
            $table->decimal('precio', 8, 2); // Precio con formato decimal (ej: 9999.99)
            $table->integer('horas_mensuales')->nullable(); // Límite de horas permitidas al mes (null = ilimitado)
            $table->string('estado')->default('activo'); // Estado del plan ('activo' o 'inactivo')
            $table->timestamps(); // Columnas de control: created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones eliminando la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('membresias');
    }
};
