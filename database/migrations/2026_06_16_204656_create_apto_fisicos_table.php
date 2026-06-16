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
        Schema::create('aptos_fisicos', function (Blueprint $table) {
            $table->id(); // Clave primaria
            // Clave foránea que relaciona el apto físico con el socio correspondiente
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->string('archivo'); // Nombre o ruta del archivo JPG o PDF
            $table->date('fecha_emision'); // Fecha en que se emitió el certificado médico
            $table->date('fecha_vencimiento'); // Fecha de vencimiento del certificado médico
            $table->string('estado')->default('vigente'); // Estado del apto ('vigente' o 'vencido')
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('aptos_fisicos');
    }
};
