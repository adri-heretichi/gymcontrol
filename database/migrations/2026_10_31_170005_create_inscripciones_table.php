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
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('restrict');
            $table->date('fecha_inscripcion');
            $table->string('estado', 20)->default('pendiente'); // pendiente, aprobada, baja, rechazada
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->string('motivo_baja')->nullable();
            $table->timestamps();

            // Evitar que el mismo socio se inscriba duplicadamente al mismo grupo
            $table->unique(['socio_id', 'grupo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
