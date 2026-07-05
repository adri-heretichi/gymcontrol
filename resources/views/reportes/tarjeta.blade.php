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
  background: #ffffff;
}
.tarjeta {
  position: absolute;
  top: 0;
  left: 0;
  width: 54mm;
  height: 86mm;
  background: #ffffff;
  border: 1.5pt solid #1a2a5e;
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
  color: #1a2a5e;
  line-height: 1;
}
.linea {
  border: none;
  border-top: 0.5pt solid #1a2a5e;
  width: 70%;
  margin: 1.5mm auto;
}
.nombre {
  font-size: 9pt;
  font-weight: bold;
  color: #1a2a5e;
  text-transform: uppercase;
  text-align: center;
  padding: 1mm 2mm;
  letter-spacing: 0.5pt;
}
.qr-contenedor {
  text-align: center;
  padding: 2mm;
  margin: 0 3mm;
  border: 1pt solid #1a2a5e;
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
  background: #1a2a5e;
  text-align: center;
  padding-top: 3mm;
}
.footer-texto {
  font-size: 9pt;
  font-weight: bold;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 1pt;
}
.estrella {
  color: #ffffff;
  font-size: 7pt;
  display: block;
  margin-bottom: 1mm;
}
</style>
</head>
<body>
<div class="tarjeta">
  <div class="header">
    <div class="icono">&#9961;</div>
    <hr class="linea">
    <p class="nombre">{{ $socio->nombre }} {{ $socio->apellido }}</p>
    <hr class="linea">
  </div>
  <div style="text-align:center; padding: 2mm;">
    <div class="qr-contenedor" style="display:inline-block;">
      <img class="qr" src="{{ $svgData }}">
    </div>
  </div>
  <div class="footer">
    <span class="estrella">&#9733;</span>
    <span class="footer-texto">GYMCONTROL</span>
  </div>
</div>
</body>
</html>
