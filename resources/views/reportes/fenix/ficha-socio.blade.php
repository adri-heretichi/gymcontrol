<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333333; line-height: 1.4; padding: 10px; }
        .header-table { width: 100%; border-bottom: 3px solid #ff6600; padding-bottom: 10px; margin-bottom: 20px; }
        .header-title { font-size: 22px; font-weight: bold; color: #000000; }
        .section-banner { background: #000000; color: #ffffff; padding: 6px 10px; font-size: 13px; font-weight: bold; margin-top: 15px; text-transform: uppercase; }
        .info-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .info-table td { padding: 6px; border: 1px solid #eeeeee; font-size: 12px; }
        .info-table td.label { font-weight: bold; background-color: #f9f9f9; width: 30%; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-title">FICHA DE SOCIO — GIMNASIO FÉNIX</td>
            <td style="text-align: right; font-size: 11px; color: #666;">GymControl v1.0</td>
        </tr>
    </table>

    <div class="section-banner">Datos Personales</div>
    <table class="info-table">
        <tr><td class="label">Nombre Completo:</td><td>{{ $socio->nombre }} {{ $socio->apellido }}</td></tr>
        <tr><td class="label">Documento (DNI):</td><td>{{ $socio->dni }}</td></tr>
        <tr><td class="label">Celular:</td><td>{{ $socio->celular ?? '-' }}</td></tr>
        <tr><td class="label">Correo Electrónico:</td><td>{{ $socio->correo ?? '-' }}</td></tr>
    </table>

    <div class="section-banner">Situación Administrativa</div>
    <table class="info-table">
        <tr><td class="label">Estado Actual:</td><td>{{ ucfirst($socio->estado) }}</td></tr>
        <tr><td class="label">Vencimiento de Cuota:</td><td>{{ $socio->fecha_vencimiento ?? 'Sin vencimiento asignado' }}</td></tr>
    </table>
</body>
</html>
