<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencias - GymControl</title>
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
        .badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-info {
            background-color: #e0e7ff;
            color: #3730a3;
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
                    <h1 class="header-title">Reporte de Asistencias</h1>
                    <div class="header-subtitle">GymControl - Registro de accesos y permanencia</div>
                </td>
                <td class="text-right" style="font-size: 9px; color: #64748b;">
                    <strong>Fecha de emisión:</strong> {{ now()->format('d/m/Y H:i') }}<br>
                    <strong>Registros:</strong> {{ count($asistencias) }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Filtros Aplicados -->
    <div class="filter-badge">
        <table>
            <tr>
                <td style="width: 50%;"><strong>Rango de fechas:</strong> {{ $fechaDesde->format('d/m/Y') }} al {{ $fechaHasta->format('d/m/Y') }}</td>
                <td style="width: 50%;">
                    @if(isset($filtros['estado']) && $filtros['estado'] !== '')
                        <strong>Estado:</strong> {{ $filtros['estado'] === 'en_sala' ? 'En Sala' : 'Finalizados' }}
                    @else
                        <strong>Estado:</strong> Todos
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

    <!-- Tabla Principal -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Socio</th>
                <th>DNI</th>
                <th>Membresía</th>
                <th>Fecha</th>
                <th>Ingreso</th>
                <th>Salida</th>
                <th>Permanencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($asistencias as $asistencia)
                <tr>
                    <td style="font-weight: bold;">{{ $asistencia->socio?->apellido }}, {{ $asistencia->socio?->nombre }}</td>
                    <td>{{ $asistencia->socio?->dni }}</td>
                    <td>
                        <span class="badge badge-info">
                            {{ $asistencia->socio?->membresia?->nombre ?? 'Sin Plan' }}
                        </span>
                    </td>
                    <td>{{ $asistencia->fecha->format('d/m/Y') }}</td>
                    <td style="font-family: monospace;">{{ $asistencia->hora_ingreso }}</td>
                    <td style="font-family: monospace;">
                        @if($asistencia->hora_salida)
                            {{ $asistencia->hora_salida }}
                        @else
                            <span class="badge badge-success">En sala</span>
                        @endif
                    </td>
                    <td>{{ $asistencia->permanencia_formateada }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b; font-style: italic; padding: 15px;">
                        No se encontraron asistencias que coincidan con los filtros aplicados en el período indicado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        GymControl © {{ date('Y') }} - Historial de Asistencias - Generado por {{ auth()->user()->name }}
    </div>

</body>
</html>
