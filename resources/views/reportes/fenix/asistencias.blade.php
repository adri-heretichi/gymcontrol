<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333333; padding: 15px; }
        .title-area { border-bottom: 4px solid #ff6600; padding-bottom: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #ff6600; color: #ffffff; padding: 8px; font-weight: bold; text-align: left; }
        td { padding: 7px; border: 1px solid #cccccc; }
    </style>
</head>
<body>
    <div class="title-area">
        <span style="font-size: 20px; font-weight: bold;">REPORTE DE ASISTENCIAS E INGRESOS</span><br>
        <span style="font-size: 12px; color: #555;">Gimnasio Fénix — Filtrado desde: {{ $fechaDesde }} hasta {{ $fechaHasta }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Socio</th>
                <th>Fecha</th>
                <th>Ingreso</th>
                <th>Salida</th>
                <th>Permanencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($asistencias as $asistencia)
                <tr>
                    <td>{{ $asistencia->socio->nombre }} {{ $asistencia->socio->apellido }}</td>
                    <td>{{ $asistencia->fecha }}</td>
                    <td>{{ $asistencia->hora_ingreso }}</td>
                    <td>{{ $asistencia->hora_out ?? 'En sala' }}</td>
                    <td>{{ $asistencia->permanencia ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #777;">No se encontraron registros de asistencias para el período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
