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
        Schema::create('pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->onDelete('cascade');
            $table->foreignId('inscripcion_id')->nullable()->constrained('inscripciones')->nullOnDelete();
            $table->foreignId('membresia_id')->nullable()->constrained('membresias')->nullOnDelete();
            $table->string('concepto', 150);
            $table->date('fecha_inicio_cobertura');
            $table->date('fecha_fin_cobertura');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->index(['inscripcion_id', 'fecha_fin_cobertura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_detalles');
    }
};
