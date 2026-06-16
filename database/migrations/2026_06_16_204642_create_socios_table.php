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
        Schema::create('socios', function (Blueprint $table) {
            $table->id(); // Clave primaria
            // Clave foránea hacia la tabla de membresías
            $table->foreignId('membresia_id')->constrained('membresias')->onDelete('restrict');
            $table->string('apellido');
            $table->string('nombre');
            $table->string('dni')->unique(); // El DNI no se puede repetir
            $table->char('sexo', 1); // 'M' o 'F'
            $table->string('correo')->nullable()->unique(); // Correo es opcional, pero si se carga debe ser único
            $table->string('celular')->nullable(); // Celular es opcional
            $table->string('foto')->nullable(); // Nombre/ruta de la imagen guardada
            $table->string('token')->unique(); // Token identificador de acceso
            $table->string('qr')->nullable(); // Ruta de la imagen del código QR generado
            $table->date('fecha_alta'); // Fecha en que se unió al gimnasio
            $table->date('fecha_vencimiento')->nullable(); // Fecha límite de vigencia de su membresía
            $table->string('estado')->default('activo'); // Estado del socio ('activo' o 'inactivo')
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::dropIfExists('socios');
    }
};
