# GymControl 🏋️‍♂️

**Sistema Inteligente de Control de Asistencia para Gimnasios**

GymControl es una plataforma web completa desarrollada con **Laravel 12** y **Livewire 3** para la gestión integral de gimnasios. Permite automatizar el control de ingreso y egreso de socios, validando en tiempo real que cada persona cumpla con los requisitos necesarios para acceder al establecimiento.

---

## 🌐 Sistema en producción

**URL:** https://gymcontrol-5i6n.onrender.com  
**Terminal pública:** https://gymcontrol-5i6n.onrender.com/terminal

> El servidor puede tardar ~30 segundos en responder la primera vez (plan gratuito de Render).

**Credenciales de prueba:**

| Rol | Email | Contraseña |
|---|---|---|
| Administrador | admin@gymcontrol.com | password |
| Recepcionista | recepcionista@gymcontrol.com | password |

---

## 📌 ¿Qué problema resuelve?

El trabajo práctico solicitaba desarrollar un sistema de control de asistencia. El equipo decidió aplicarlo en el contexto de un gimnasio, donde el control de acceso requiere verificar simultáneamente tres condiciones:

| Condición | Descripción |
|---|---|
| ✅ Estado activo | El socio debe estar dado de alta en el sistema |
| ✅ Membresía vigente | La cuota mensual debe estar paga y al día |
| ✅ Apto físico vigente | El certificado médico debe estar dentro de su período de validez |

Si alguna condición falla, el sistema deniega el acceso e informa el motivo exacto.

---

## ✨ Funcionalidades principales

### 🖥️ Terminal pública de autogestión
Una de las características más destacadas del sistema es la **terminal pública de acceso** disponible en `/terminal`. Esta pantalla funciona sin necesidad de que el recepcionista esté presente y permite que el propio socio registre su entrada y salida de dos formas:

- **Token PIN:** el socio tipea su número de identificación de 4 dígitos en la tablet de la entrada y presiona Enter
- **Código QR:** el socio escanea el QR de su tarjeta credencial con la cámara de su celular

El sistema responde instantáneamente con una pantalla de color:
- 🟢 **Verde:** ingreso autorizado — muestra el nombre del socio
- 🔵 **Azul:** salida registrada — muestra el tiempo que estuvo en sala
- 🔴 **Rojo:** acceso denegado — muestra el motivo exacto

Cuando un socio entra o sale por la terminal, el panel del recepcionista se actualiza automáticamente en tiempo real sin recargar la página.

### 📊 Panel administrativo en tiempo real
El dashboard muestra KPIs críticos actualizados automáticamente cada 5 segundos: socios en sala, recaudación del mes, membresías próximas a vencer y últimos accesos.

### 🧾 Restricciones por tipo de membresía
| Plan | Restricción |
|---|---|
| Pase Libre Mensual | Sin restricciones de horario ni visitas |
| Pase 3 Veces por Semana | Máximo 3 visitas por semana · Máximo 2 horas por día |
| Pase Diario | Máximo 2 horas acumuladas por día |

El horario del gimnasio es de **5:00 a 00:00 hs**, todos los días. El sistema bloquea el acceso fuera de ese horario.

### 📄 Generación de documentos PDF
- Ficha completa del socio exportable
- Tarjeta credencial tipo DNI (54×86mm) con nombre y código QR para imprimir
- Reportes de asistencias y pagos con filtros por período

### 🌤️ Integración con API externa
Muestra el clima actual de **Formosa, Argentina** en tiempo real en la pantalla de asistencias y en la terminal pública, usando la API gratuita de Open-Meteo sin necesidad de registro ni API key.

### 🔌 API REST
Endpoints autenticados con Laravel Sanctum para integración con aplicaciones móviles, lectores de código de barras u otros sistemas externos.

### 👥 Dos roles de usuario
- **Administrador:** acceso completo a todos los módulos, reportes financieros y gestión de operadores
- **Recepcionista:** acceso operativo — registro de pagos, aptos físicos e historial de asistencias

---

## 🏗️ Tecnologías utilizadas

| Capa | Tecnología |
|---|---|
| Framework | Laravel 12 (PHP 8.3) |
| UI Reactiva | Livewire 3 + Alpine.js |
| Autenticación | Laravel Breeze |
| API REST | Laravel Sanctum |
| Estilos | Tailwind CSS + Vite |
| PDF | barryvdh/laravel-dompdf |
| QR | simplesoftwareio/simple-qrcode |
| Base de datos desarrollo | MySQL (XAMPP) |
| Base de datos producción | PostgreSQL (Render) |
| Contenedor | Docker (php:8.3-fpm-alpine) |
| Hosting | Render.com |
| API externa | Open-Meteo (clima Formosa) |

---

## 🗃️ Modelo de datos

El sistema cuenta con 7 tablas relacionadas entre sí:

```
users ──────────────────── personal_access_tokens
membresias ─────────────── socios ─┬─ pagos
                                    ├─ asistencias
                                    └─ aptos_fisicos
```

Relaciones implementadas con Eloquent ORM. Claves foráneas con `ON DELETE CASCADE` y `ON DELETE RESTRICT` según corresponda.

---

## ⚙️ Instalación local

### Requisitos previos
- PHP 8.2+
- Composer
- Node.js 18+ y NPM
- MySQL (XAMPP o similar)
- Git

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/Gabriela-Hrtch/gymcontrol
cd gymcontrol

# 2. Instalar dependencias
composer install
npm install && npm run build

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate
```

Editá el `.env` con tus datos locales:

```env
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gymcontrol
DB_USERNAME=root
DB_PASSWORD=
TERMINAL_SOLO_RED_LOCAL=true
```

```bash
# 4. Base de datos — crear "gymcontrol" en phpMyAdmin y ejecutar:
php artisan migrate --seed

# 5. Storage y QRs
php artisan storage:link
php artisan tokens:normalizar

# 6. Arrancar
php artisan serve
```

> **Zona horaria:** En el `php.ini` de XAMPP configurar `date.timezone = America/Argentina/Buenos_Aires`

Sistema disponible en **http://localhost:8000**

---

## 🔌 API REST

**Base URL:** `/api/v1`  
**Autenticación:** Bearer Token (Laravel Sanctum)

### Login

```json
POST /api/v1/auth/login
{
  "email": "admin@gymcontrol.com",
  "password": "password"
}
```

### Endpoints principales

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| POST | /api/v1/auth/login | Pública | Login — devuelve token Bearer |
| POST | /api/v1/auth/logout | Bearer | Cierra sesión |
| GET | /api/v1/socios | Bearer | Lista socios paginados |
| POST | /api/v1/socios | Bearer + Admin | Crea un nuevo socio |
| PUT | /api/v1/socios/{id} | Bearer + Admin | Actualiza datos del socio |
| DELETE | /api/v1/socios/{id} | Bearer + Admin | Baja lógica del socio |
| GET | /api/v1/membresias | Bearer | Lista planes activos |
| POST | /api/v1/control-acceso/scan | Bearer | Registra ingreso/salida por token |

---

## 🐳 Deploy en Render

El proyecto incluye un `Dockerfile` optimizado para producción basado en `php:8.3-fpm-alpine`.

**Variables de entorno requeridas en Render:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.onrender.com
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
TERMINAL_SOLO_RED_LOCAL=false
```

---

## 📦 Módulos del sistema

| Módulo | Descripción |
|---|---|
| Socios | Alta, baja lógica, edición y ficha completa con QR y tarjeta PDF |
| Membresías | Planes con restricciones de acceso por tipo |
| Aptos Físicos | Certificados médicos con control de vigencia |
| Pagos | Cobros con actualización automática de fecha de vencimiento |
| Terminal pública | Acceso autogestionado por Token PIN y QR desde celular |
| Control de acceso | Panel del operador con socios en sala en tiempo real |
| Historial | Registro de ingresos y egresos con exportación PDF |
| Dashboard | KPIs en tiempo real actualizados cada 5 segundos |
| Operadores | Gestión de usuarios del sistema (solo Administrador) |
| API REST | Endpoints para integración con sistemas externos |

---

## 👥 Equipo de desarrollo

| Nombre | Rol | Área |
|---|---|---|
| Diego Cardozo | Desarrollo Backend | Lógica del sistema, base de datos y gestión de información |
| Gabriela Lopez | Desarrollo Frontend | Diseño de interfaz y experiencia de usuario |
| Adriana Heretichi | Desarrollo Full Stack | Integración entre interfaz, funcionalidades y control de asistencia |

---

## 📄 Licencia

Proyecto académico — 2026

---

*Desarrollado con Laravel · Livewire · Tailwind CSS*
