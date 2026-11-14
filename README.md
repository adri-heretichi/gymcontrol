# GymControl — Gimnasio Fénix

Sistema de gestión de socios y control de acceso con Laravel 12, Livewire 3, PHP, MySQL/MariaDB, Tailwind CSS y Vite. Laravel Reverb, Echo y colas actualizan los paneles autenticados en tiempo real.

## Módulos y reglas

El menú contiene exactamente ocho módulos, visibles según los permisos del operador:

1. Panel Administrativo: Dashboard, indicadores, estadísticas, accesos rápidos y Solicitudes como sección adicional.
2. Socios: registro manual, ficha e inscripciones en varias disciplinas compatibles.
3. Membresías: disciplinas, precios, grupos y horarios.
4. Aptos Físicos: gestión médica exclusiva de la dueña.
5. Pagos: cobros y coberturas independientes por inscripción.
6. Control de Acceso: ingresos, salidas e Historial de asistencias.
7. Operadores: administración por la dueña.
8. Auditoría: trazabilidad y revisión de operaciones.

Recepción no administra operadores ni resuelve solicitudes o decisiones médicas. La terminal pública está en `/terminal`; el acceso mediante QR requiere confirmaciones explícitas.

| Disciplina | Cuota mensual inicial |
|---|---:|
| Krav Maga Kids | $30.000 |
| Kapap Krav Maga | $35.000 |
| Kickboxing Muay Thai | $35.000 |
| Funcional | $35.000 |

Los grupos admiten hasta 15 inscripciones confirmadas y horarios configurables de lunes a viernes, dentro de los turnos 08:00–12:00 y 15:00–20:00. Un socio puede tener varias inscripciones sin superposición. Cada una conserva su importe, cobertura y vencimiento. Una cobertura futura o el pago de otra disciplina no habilitan el acceso. No se registran cobros sin confirmación del dinero recibido.

La dueña decide individualmente si el certificado médico es requerido y, cuando corresponde, debe aprobarlo. El PIN tiene seis dígitos y el QR es persistente: se emiten cuando se cumplen los requisitos y no se regeneran al vencer una cuota. Las permanencias superiores a 24 horas requieren revisión; se permiten salidas válidas que cruzan medianoche y se excluyen las anomalías del promedio.

## Requisitos en Windows

- Git, Visual Studio Code y XAMPP con MariaDB/MySQL para la instalación habitual.
- PHP 8.2 o superior compatible con `composer.lock`; Composer 2.
- Extensiones PHP: bcmath, ctype, curl, dom, fileinfo, gd, mbstring, openssl, pdo_mysql, tokenizer, xml y zip. Habilitar pdo_sqlite y sqlite3 para las pruebas aisladas.
- Node.js 22.12 o superior con npm para Vite 7.

Verificar en PowerShell que PHP corresponde a XAMPP. Si es necesario, agregar `C:\xampp\php` al PATH y abrir una terminal nueva:

```powershell
Get-Command php
php --version
php --ini
composer --version
node --version
npm.cmd --version
```

No hace falta iniciar Apache si se utiliza `php artisan serve`.

## Clonar y configurar una instalación nueva

La rama del equipo es `develop`. Antes de su integración definitiva, usar la rama candidata indicada por el equipo en lugar de `develop`; eso no implica que el código ya esté publicado en `develop`.

```powershell
git clone --branch develop --single-branch https://github.com/adri-heretichi/gymcontrol.git gymcontrol-fenix
Set-Location gymcontrol-fenix
code .
Copy-Item .env.example .env
```

No copiar `.env`, vendor, node_modules ni bases de otra instalación. Configurar la conexión aislada ANTES de ejecutar comandos de base de datos.

### MariaDB/MySQL con XAMPP

Iniciar MySQL desde XAMPP. En phpMyAdmin o en el cliente MySQL autenticado, crear una base NUEVA:

```sql
CREATE DATABASE gymcontrol_fenix_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Si el nombre ya existe, elegir otro; no sobrescribirlo. Configurar `.env` con el nombre nuevo y las credenciales de tu servidor:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gymcontrol_fenix_local
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=cookie
CACHE_STORE=array
TERMINAL_SOLO_RED_LOCAL=true
```

Nunca usar `gymcontrol` si contiene los datos históricos de otra instalación. No publicar contraseñas.

### Alternativa SQLite aislada

Para probar sin MySQL, crear un archivo nuevo en esta copia:

```powershell
New-Item -ItemType File -Path database/instalacion-local.sqlite
```

Configurar `.env` con la ruta absoluta real y barras `/`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=C:/ruta/gymcontrol-fenix/database/instalacion-local.sqlite
```

No definir `DB_URL`. Habilitar pdo_sqlite y sqlite3 en el PHP utilizado por la terminal.

### Dependencias, clave y migraciones

Desde la carpeta nueva, con la conexión aislada ya configurada:

```powershell
composer install
composer check-platform-reqs
npm.cmd ci
php artisan key:generate
php artisan config:clear
php artisan migrate:status
php artisan migrate
```

En una base nueva, `migrate:status` puede indicar que aún falta la tabla de migraciones; `migrate` crea el esquema. La clave generada corresponde exclusivamente a esta instalación; nunca copiar ni reemplazar APP_KEY de una instalación existente.

## Operadores originales y seeders

`UserSeeder` reproduce los dos operadores del proyecto original, sin variables de aprovisionamiento adicionales:

| Nombre | Correo | Rol |
|---|---|---|
| Administrador General | admin@gymcontrol.com | admin |
| Recepcionista de Turno | recep@gymcontrol.com | recepcionista |

La contraseña inicial original de ambos operadores es `password`, almacenada mediante `Hash::make()`. Esta contraseña predeterminada es exclusiva del entorno académico/local y no debe utilizarse en producción. No hay un seeder independiente de roles.

Verificar nuevamente la carpeta y base nuevas y ejecutar:

```powershell
php artisan db:seed --class=DatabaseSeeder
```

El comportamiento original utiliza `updateOrCreate`: volver a ejecutar UserSeeder actualiza los nombres, roles y contraseñas de esos correos. Ejecutar DatabaseSeeder únicamente en una instalación nueva y aislada; no ejecutarlo sobre la base histórica `gymcontrol` ni sobre cuentas existentes que deban conservar sus contraseñas.

DatabaseSeeder ejecuta únicamente UserSeeder y DisciplinaSeeder. Una instalación nueva comienza con dos operadores, cuatro disciplinas y **cero socios, grupos, inscripciones, pagos, certificados, asistencias y credenciales de socios**. El catálogo es idempotente y no sobrescribe precios, descripciones o estados de disciplinas existentes, incluso si su precio está vacío.

Los seeders históricos de socios, membresías y movimientos quedan fuera de la ejecución normal; no ejecutarlos sobre bases existentes. No se precargan los ocho grupos ficticios: sus grupos y horarios se configuran desde Membresías según lo autorizado por la dueña.

## Iniciar Laravel, Vite, Reverb y el worker

Configurar la comunicación únicamente local en `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
REVERB_APP_ID=local-gymcontrol
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1
```

Generar valores propios para REVERB_APP_KEY y REVERB_APP_SECRET, sin cambiar contraseñas de operadores. Ejecutar dos veces el comando siguiente y guardar cada resultado en su campo de `.env`:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Conservar las referencias VITE_REVERB_* de `.env.example`. La clave pública, host, puerto y esquema del cliente deben coincidir con el servidor. Nunca exponer REVERB_APP_SECRET ni APP_KEY mediante VITE_*. Si cambia la configuración, reiniciar los procesos afectados y recompilar los recursos.

Abrir cuatro terminales PowerShell ubicadas en esta instalación:

```powershell
# Terminal 1: aplicación
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
# Terminal 2: recursos de desarrollo
npm.cmd run dev -- --host=127.0.0.1
```

```powershell
# Terminal 3: WebSocket local
php artisan reverb:start --host=127.0.0.1 --port=8080
```

```powershell
# Terminal 4: cola de notificaciones
php artisan queue:work database --queue=realtime,default --tries=3
```

Si un puerto está ocupado, elegir uno libre y ajustar las variables correspondientes. No cerrar procesos de otras instalaciones. Para probar recursos compilados, detener el servidor de Vite y ejecutar `npm.cmd run build`.

Abrir **http://127.0.0.1:8000/login** e ingresar con uno de los operadores originales. Sus contraseñas son distintas del PIN y QR de los socios. Los documentos médicos permanecen en almacenamiento privado: no es necesario publicar esa carpeta ni ejecutar normalizadores de credenciales para instalar el sistema.

## Primer uso

1. Ingresar como dueña y comprobar los ocho módulos, Dashboard y Solicitudes integrado.
2. En Membresías, verificar las disciplinas y crear grupos con horarios autorizados y cupo máximo 15.
3. Registrar manualmente un socio desde Socios; seleccionar una o varias disciplinas y sus grupos sin superposición.
4. Confirmar cada cobro real por separado. Si falta un pago, conservar el registro preliminar sin habilitar esa inscripción.
5. Resolver como dueña el requisito médico y aprobar el certificado si corresponde. Recepción puede solicitar revisión, sin aprobar documentación médica.
6. Emitir credenciales únicamente cuando se cumplen los requisitos; comprobar ingreso dentro del horario de la inscripción pagada y registrar la salida.
7. Consultar Historial y Auditoría. Tramitar correcciones históricas con evidencia, sin cerrar como actual una permanencia antigua.

Consultar también [el checklist de pruebas](CHECKLIST_PRUEBAS_GYMCONTROL_FENIX.md). No utilizar registros históricos como datos de prueba.

## Pruebas automatizadas aisladas

Desde esta copia, en una terminal PowerShell separada:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:APP_CONFIG_CACHE=Join-Path (Get-Location) 'bootstrap/cache/pruebas-config-inexistente.php'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml
```

Las migraciones y datos ficticios de los tests se ejecutan solo en SQLite en memoria. Cerrar esa terminal al terminar para no reutilizar sus variables en los servidores locales. Para compilar:

```powershell
npm.cmd run build
```

## Verificar notificaciones

Una conexión TCP o un handshake WebSocket solo confirman conectividad. Los tests con eventos simulados comprueban permisos y enrutamiento, pero no prueban la entrega real entre navegadores.

Para comprobar el recorrido completo, abrir dos sesiones separadas (dueña y recepción), verificar conexión y suscripciones autorizadas, realizar una operación permitida sobre la base local de prueba y comprobar que el worker procesa el evento y el panel receptor se actualiza sin recargar. Comprobar además que recepción no recibe información médica ni eventos privados de otra recepcionista. Si no se observó ese recorrido, registrar la prueba de extremo a extremo como pendiente.

## Seguridad y colaboración

Trabajar en la rama indicada por el equipo e integrar mediante Pull Requests hacia develop. No modificar main ni usar la base histórica para verificar instalaciones. No ejecutar migrate:fresh, migrate:refresh, seeders históricos ni normalizadores contra una instalación existente.

No publicar .env, claves privadas, bases SQLite, respaldos, logs, certificados ni credenciales de socios. Esta guía es para pruebas locales y no requiere configurar servicios de producción.

Se conservan exportación PDF, tarjeta de socio, API autenticada con Laravel Sanctum e integración meteorológica con Open-Meteo. Las credenciales de API se gestionan privadamente.

## Equipo de desarrollo

| Integrante | Área |
|---|---|
| Diego Cardozo | Backend, lógica del sistema y gestión de información |
| Gabriela Lopez | Frontend, diseño de interfaz y experiencia de usuario |
| Adriana Heretichi | Integración y desarrollo Full Stack |

Proyecto académico — 2026. Laravel · Livewire · Tailwind CSS.
