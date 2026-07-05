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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id(); // Clave primaria
            // Relación con la tabla socios
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->date('fecha_pago'); // Fecha en que se efectuó el pago
            $table->decimal('importe', 8, 2); // Importe cobrado (ej: 99999.99)
            $table->string('metodo_pago'); // Método utilizado: 'efectivo', 'tarjeta', 'transferencia', etc.
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
