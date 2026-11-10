<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  margin: 0;
  padding: 0;
  width: 54mm;
  height: 86mm;
  overflow: hidden;
  background: #0a0a0a;
}
.tarjeta {
  position: absolute;
  top: 0;
  left: 0;
  width: 54mm;
  height: 86mm;
  background: #0a0a0a;
  border: 1.5pt solid #d4a72c;
  overflow: hidden;
  font-family: Arial, sans-serif;
}
.header {
  text-align: center;
  padding-top: 4mm;
  padding-bottom: 2mm;
}
.icono {
  font-size: 14pt;
  color: #d4a72c;
  line-height: 1;
}
.linea {
  border: none;
  border-top: 0.5pt solid #d4a72c;
  width: 70%;
  margin: 1.5mm auto;
}
.nombre {
  font-size: 9pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  text-align: center;
  padding: 1mm 2mm;
  letter-spacing: 0.5pt;
}
.qr-contenedor {
  text-align: center;
  padding: 2mm;
  margin: 0 3mm;
  border: 1pt solid #d4a72c;
  background: #ffffff;
}
.qr {
  width: 34mm;
  height: 34mm;
}
.footer {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 54mm;
  height: 12mm;
  background: #000000;
  border-top: 1pt solid #d4a72c;
  text-align: center;
  padding-top: 3mm;
}
.footer-texto {
  font-size: 9pt;
  font-weight: bold;
  color: #d4a72c;
  text-transform: uppercase;
  letter-spacing: 1pt;
}
.estrella {
  color: #d4a72c;
  font-size: 7pt;
  display: block;
  margin-bottom: 1mm;
}
.logo-fenix {
  width: 16mm;
  height: 16mm;
  object-fit: cover;
  border: 0.5pt solid #d4a72c;
}
.marca-secundaria {
  color: #d4a72c;
  font-size: 6pt;
  font-weight: bold;
  letter-spacing: 0.5pt;
}
</style>
</head>
<body>
<div class="tarjeta">
  <div class="header">
    <img class="logo-fenix" src="{{ $logoData }}" alt="Gimnasio Fénix">
    <hr class="linea">
    <p class="nombre">{{ $socio->nombre }} {{ $socio->apellido }}</p>
    <hr class="linea">
  </div>
  <div style="text-align:center; padding: 2mm;">
    <div class="qr-contenedor" style="display:inline-block;">
      @if($svgData)
        <img class="qr" src="{{ $svgData }}">
      @else
        <p>Credencial no emitida</p>
      @endif
    </div>
  </div>
  <div class="footer">
    <span class="estrella">&#9733;</span>
    <span class="footer-texto">FÉNIX</span>
    <span class="marca-secundaria">SISTEMA GYMCONTROL</span>
  </div>
</div>
</body>
</html>
