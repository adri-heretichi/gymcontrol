<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Pagos y Facturación - GymControl</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 10px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .header-subtitle {
            font-size: 9px;
            color: #64748b;
            margin: 2px 0 0 0;
            font-weight: bold;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 15px;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .summary-box-title {
            font-size: 8px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .summary-box-value {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .summary-box-success {
            color: #059669;
        }
        .filter-badge {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 5px 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 9px;
        }
        .filter-badge table {
            width: 100%;
        }
        .filter-badge td {
            padding: 2px 5px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 9px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="header-title">Reporte de Pagos y Facturación</h1>
                    <div class="header-subtitle">GymControl - Control financiero global</div>
                </td>
                <td class="text-right" style="font-size: 9px; color: #64748b;">
                    <strong>Fecha de emisión:</strong> {{ now()->format('d/m/Y H:i') }}<br>
                    <strong>Transacciones:</strong> {{ count($pagos) }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Filtros Aplicados -->
    <div class="filter-badge">
        <table>
            <tr>
                <td style="width: 50%;"><strong>Período consultado:</strong> {{ $fechaDesde->format('d/m/Y') }} al {{ $fechaHasta->format('d/m/Y') }}</td>
                <td style="width: 50%;">
                    @if(isset($filtros['metodo_pago']) && $filtros['metodo_pago'] !== '')
                        <strong>Método:</strong> <span style="text-transform: capitalize;">{{ $filtros['metodo_pago'] }}</span>
                    @else
                        <strong>Método:</strong> Todos los métodos
                    @endif
                </td>
            </tr>
            @if(isset($filtros['buscar']) && $filtros['buscar'] !== '')
                <tr>
                    <td colspan="2"><strong>Búsqueda:</strong> "{{ $filtros['buscar'] }}"</td>
                </tr>
            @endif
        </table>
    </div>

    <!-- Cuadros Resumen Ejecutivo -->
    <table class="summary-grid" style="border-spacing: 10px 0; margin-left: -10px; margin-right: -10px;">
        <tr>
            <td style="width: 25%; padding: 0;">
                <div class="summary-box">
                    <div class="summary-box-title">Total Recaudado</div>
                    <div class="summary-box-value summary-box-success">${{ number_format($sumaTotal, 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0;">
                <div class="summary-box">
                    <div class="summary-box-title">Efectivo</div>
                    <div class="summary-box-value">${{ number_format($metodos['efectivo'], 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0;">
                <div class="summary-box">
                    <div class="summary-box-title">Tarjeta</div>
                    <div class="summary-box-value">${{ number_format($metodos['tarjeta'], 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 25%; padding: 0;">
                <div class="summary-box">
                    <div class="summary-box-title">Transferencia</div>
                    <div class="summary-box-value">${{ number_format($metodos['transferencia'], 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tabla Principal -->
    <table class="data-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th>Socio</th>
                <th>DNI</th>
                <th>Membresía</th>
                <th>Fecha de Pago</th>
                <th>Método de Pago</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td style="font-weight: bold;">{{ $pago->socio?->apellido }}, {{ $pago->socio?->nombre }}</td>
                    <td>{{ $pago->socio?->dni }}</td>
                    <td>{{ $pago->socio?->membresia?->nombre ?? 'Plan anterior' }}</td>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td style="text-transform: capitalize;">{{ $pago->metodo_pago }}</td>
                    <td class="text-right" style="font-weight: bold; font-size: 11px;">${{ number_format($pago->importe, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">
                        No se registraron cobros que coincidan con los filtros aplicados en el período indicado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        GymControl © {{ date('Y') }} - Reporte Financiero - Generado por {{ auth()->user()->name }}
    </div>

</body>
</html>
