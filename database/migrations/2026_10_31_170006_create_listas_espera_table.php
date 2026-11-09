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
        Schema::create('listas_espera', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('socios')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->unsignedSmallInteger('posicion');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->string('estado', 20)->default('esperando'); // esperando, notificado, promovido, cancelado
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['grupo_id', 'estado', 'posicion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listas_espera');
    }
};
