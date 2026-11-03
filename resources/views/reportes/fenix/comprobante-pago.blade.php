<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; padding: 5px; color: #000000; font-size: 12px; }
        .align-center { text-align: center; }
        .divider { border-top: 1px dashed #000000; margin: 10px 0; }
        .total-amount { font-size: 15px; font-weight: bold; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="align-center">
        <strong>GIMNASIO FÉNIX</strong><br>
        Formosa, Argentina<br>
        <span style="font-size: 10px;">Control de Operaciones: GymControl</span>
    </div>
    
    <div class="divider"></div>
    <div class="align-center"><strong>COMPROBANTE DE PAGO</strong></div>
    <div class="divider"></div>

    <table style="width: 100%; font-size: 11px;">
        <tr><td><strong>Fecha:</strong></td><td style="text-align: right;">{{ $pago->fecha_pago }}</td></tr>
        <tr><td><strong>Socio:</strong></td><td style="text-align: right;">{{ $pago->socio->nombre }} {{ $pago->socio->apellido }}</td></tr>
        <tr><td><strong>DNI:</strong></td><td style="text-align: right;">{{ $pago->socio->dni }}</td></tr>
        <tr><td><strong>Método:</strong></td><td style="text-align: right;">{{ strtoupper($pago->metodo_pago) }}</td></tr>
    </table>

    <div class="divider"></div>
    <div class="align-center total-amount">
        TOTAL ABONADO: ${{ number_format($pago->importe, 2, ',', '.') }}
    </div>
    <div class="divider"></div>
    
    <div class="align-center" style="font-size: 9px; color: #555;">
        Documento interno de control no válido como factura fiscal.
    </div>
</body>
</html>
