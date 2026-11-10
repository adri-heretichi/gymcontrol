<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 – Pagos por disciplina e independencia de cobertura.
 *
 * Agrega a "pagos":
 *   - operador_id     : Usuario que registró el cobro.
 *   - referencia      : Número de recibo, comprobante o nota administrativa.
 *   - estado          : 'activo' | 'anulado' (corrección interna auditada).
 *   - anulado_por     : Usuario que ejecutó la anulación interna.
 *   - anulado_at      : Marca de tiempo de anulación.
 *   - motivo_anulacion: Razón documentada de la anulación.
 *
 * Agrega a "pago_detalles":
 *   - estado_cobertura: 'vigente' | 'vencida' | 'suspendida' (calculado o marcado).
 *
 * Los campos históricos (importe, metodo_pago, fecha_pago, socio_id) se conservan intactos.
 * No se modifica ni elimina ningún dato histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----- Tabla: pagos -----
        Schema::table('pagos', function (Blueprint $table) {
            // Operador que registró el cobro (nullable para compatibilidad con pagos históricos)
            $table->foreignId('operador_id')
                ->nullable()
                ->after('socio_id')
                ->constrained('users')
                ->nullOnDelete();

            // Referencia administrativa libre (nro. recibo, nota, etc.)
            $table->string('referencia', 100)->nullable()->after('metodo_pago');

            // Estado del pago: activo o anulado internamente (solo la dueña puede anular)
            $table->string('estado', 20)->default('activo')->after('referencia');

            // Auditoría de anulación interna
            $table->foreignId('anulado_por')->nullable()->after('estado')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable()->after('anulado_por');
            $table->text('motivo_anulacion')->nullable()->after('anulado_at');

            $table->index(['socio_id', 'estado']);
            $table->index(['operador_id', 'fecha_pago']);
        });

        // ----- Tabla: pago_detalles -----
        Schema::table('pago_detalles', function (Blueprint $table) {
            // Estado de cobertura calculado en consulta, pero almacenado para reportes históricos
            $table->string('estado_cobertura', 20)->default('vigente')->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('pago_detalles', function (Blueprint $table) {
            $table->dropColumn('estado_cobertura');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['socio_id', 'estado']);
            $table->dropIndex(['operador_id', 'fecha_pago']);
            $table->dropForeign(['operador_id']);
            $table->dropForeign(['anulado_por']);
            $table->dropColumn([
                'operador_id',
                'referencia',
                'estado',
                'anulado_por',
                'anulado_at',
                'motivo_anulacion',
            ]);
        });
    }
};
