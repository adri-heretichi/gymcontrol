<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Socio - {{ $socio->nombre }} {{ $socio->apellido }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .header-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 3px 0 0 0;
            font-weight: bold;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #4f46e5;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .grid-table td {
            padding: 6px 8px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            color: #475569;
            width: 30%;
        }
        .value {
            color: #0f172a;
            font-weight: 500;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .badge-info {
            background-color: #e0e7ff;
            color: #3730a3;
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
        .photo-box {
            width: 90px;
            height: 90px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
            background-color: #f8fafc;
            border-radius: 8px;
        }
        .photo-text {
            font-size: 10px;
            color: #94a3b8;
            line-height: 90px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="header-title">Ficha de Socio</h1>
                    <div class="header-subtitle">GymControl - Control de accesos y membresías</div>
                </td>
                <td class="text-right" style="font-size: 9px; color: #64748b;">
                    <strong>Fecha de emisión:</strong> {{ now()->format('d/m/Y H:i') }}<br>
                    <strong>ID Socio:</strong> #{{ $socio->id }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Sección Datos Personales -->
    <div class="section-title">Datos Personales</div>
    <table style="width: 100%;">
        <tr>
            <td style="width: 80%; vertical-align: top; padding: 0;">
                <table class="grid-table">
                    <tr>
                        <td class="label">Apellido y Nombre:</td>
                        <td class="value">{{ $socio->apellido }}, {{ $socio->nombre }}</td>
                    </tr>
                    <tr>
                        <td class="label">DNI:</td>
                        <td class="value">{{ $socio->dni }}</td>
                    </tr>
                    <tr>
                        <td class="label">Token / PIN:</td>
                        <td class="value" style="font-family: monospace;">{{ $socio->token }}</td>
                    </tr>
                    <tr>
                        <td class="label">Sexo:</td>
                        <td class="value" style="text-transform: capitalize;">{{ $socio->sexo }}</td>
                    </tr>
                    <tr>
                        <td class="label">Correo Electrónico:</td>
                        <td class="value">{{ $socio->correo ?? 'No registrado' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Celular:</td>
                        <td class="value">{{ $socio->celular ?? 'No registrado' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 20%; text-align: right; vertical-align: top; padding: 0;">
                @if($socio->foto)
                    <!-- Intentar renderizar la imagen local si existe -->
                    <img src="{{ storage_path('app/public/' . $socio->foto) }}" style="width: 90px; height: 90px; border-radius: 8px; object-cover: cover; border: 1px solid #cbd5e1;" alt="Foto Socio">
                @else
                    <div class="photo-box">
                        <span class="photo-text">SIN FOTO</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Sección Membresía y Apto Físico -->
    <div class="section-title">Estado de Membresía y Salud</div>
    <table class="grid-table">
        <tr>
            <td class="label">Estado de Cuenta:</td>
            <td class="value">
                @if($socio->estado === 'activo')
                    <span class="badge badge-success">ACTIVO</span>
                @else
                    <span class="badge badge-danger">INACTIVO</span>
                @endif
            </td>
            <td class="label">Apto Físico:</td>
            <td class="value">
                @if($socio->aptoFisicoVigente())
                    <span class="badge badge-success">VIGENTE</span>
                @else
                    <span class="badge badge-danger">VENCIDO / INEXISTENTE</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Membresía Asignada:</td>
            <td class="value">{{ $socio->membresia?->nombre ?? 'Ninguna' }}</td>
            <td class="label">Vencimiento Cuota:</td>
            <td class="value">{{ $socio->fecha_vencimiento ? $socio->fecha_vencimiento->format('d/m/Y') : 'N/A' }}</td>
        </tr>
    </table>

    <!-- Sección Últimos Pagos -->
    <div class="section-title">Historial Reciente de Pagos (Últimos 10)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha de Pago</th>
                <th>Membresía</th>
                <th>Método de Pago</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse($socio->pagos as $pago)
                <tr>
                    <td>{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $pago->socio?->membresia?->nombre ?? 'Plan anterior' }}</td>
                    <td style="text-transform: capitalize;">{{ $pago->metodo_pago }}</td>
                    <td class="text-right" style="font-weight: bold;">${{ number_format($pago->importe, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #64748b; font-style: italic;">No hay pagos registrados para este socio.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Sección Últimas Asistencias -->
    <div class="section-title">Asistencias Recientes en Sala (Últimas 15)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora de Ingreso</th>
                <th>Hora de Salida</th>
                <th>Tiempo de Permanencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($socio->asistencias as $asistencia)
                <tr>
                    <td>{{ $asistencia->fecha->format('d/m/Y') }}</td>
                    <td style="font-family: monospace;">{{ $asistencia->hora_ingreso }}</td>
                    <td style="font-family: monospace;">{{ $asistencia->hora_salida ?? 'En sala' }}</td>
                    <td>{{ $asistencia->permanencia_formateada }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #64748b; font-style: italic;">No se registran asistencias para este socio.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        GymControl © {{ date('Y') }} - Ficha de Socio - Impreso por {{ auth()->user()->name }}
    </div>

</body>
</html>
