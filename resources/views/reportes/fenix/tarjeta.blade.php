<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: 54mm 86mm; margin: 0; }
        body { font-family: 'Helvetica', Arial, sans-serif; background-color: #000000; color: #ffffff; text-align: center; margin: 0; padding: 10px; }
        .marca-principal { color: #d4af37; font-size: 16px; font-weight: bold; letter-spacing: 1px; margin-top: 5px; }
        .nombre-socio { font-size: 13px; margin: 15px 0 10px 0; color: #ff6600; text-transform: uppercase; font-weight: bold; }
        .qr-box { background: #ffffff; padding: 6px; display: inline-block; border-radius: 4px; margin-top: 5px; }
        .qr-box img { width: 38mm; height: 38mm; display: block; }
        .marca-secundaria { font-size: 8px; color: #888888; margin-top: 15px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="marca-principal">GIMNASIO FÉNIX</div>
    <div class="nombre-socio">{{ $socio->nombre }} {{ $socio->apellido }}</div>
    <div class="qr-box">
        <img src="data:image/svg+xml;base64,{{ base64_encode($svgData) }}" alt="QR de Acceso">
    </div>
    <div class="marca-secundaria">Powered by GymControl</div>
</body>
</html>
