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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->foreignId('solicitante_id')->constrained('users')->onDelete('cascade'); // Recepcionista
            $table->string('tipo', 50); // excepcion_medica, ingreso_excepcional, baja_inscripcion, descuento_cuota, cambio_grupo, otro
            $table->text('descripcion');
            $table->string('estado', 20)->default('pendiente'); // pendiente, aprobada, rechazada, cancelada
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete(); // Dueña
            $table->text('respuesta_duena')->nullable();
            $table->timestamp('fecha_decision')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'estado']);
            $table->index(['estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
