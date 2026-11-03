<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333333; padding: 15px; }
        .title-area { border-bottom: 4px solid #ff6600; padding-bottom: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #000000; color: #ffffff; padding: 8px; font-weight: bold; text-align: left; }
        td { padding: 7px; border: 1px solid #cccccc; }
        .total-box { margin-top: 15px; text-align: right; font-size: 14px; font-weight: bold; color: #000000; }
    </style>
</head>
<body>
    <div class="title-area">
        <span style="font-size: 20px; font-weight: bold;">REPORTE GENERAL DE COBROS</span><br>
        <span style="font-size: 12px; color: #555;">Gimnasio Fénix — Período: {{ $fechaDesde }} al {{ $fechaHasta }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Socio</th>
                <th>Fecha de Pago</th>
                <th>Método de Pago</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->socio->nombre }} {{ $pago->socio->apellido }}</td>
                    <td>{{ $pago->fecha_pago }}</td>
                    <td>{{ strtoupper($pago->metodo_pago) }}</td>
                    <td>${{ number_format($pago->importe, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #777;">No se registraron movimientos en este período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total-box">
        Monto Total Recaudado: ${{ number_format($sumaTotal, 2, ',', '.') }}
    </div>
</body>
</html>
