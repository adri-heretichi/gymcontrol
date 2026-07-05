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
        Schema::table('users', function (Blueprint $table) {
            // Agregamos la columna 'rol' de tipo string, con valor por defecto 'recepcionista', después de la columna 'password'
            $table->string('rol')->default('recepcionista')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminamos la columna 'rol' en caso de revertir esta migración
            $table->dropColumn('rol');
        });
    }
};
