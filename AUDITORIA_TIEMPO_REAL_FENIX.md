# Auditoría de sincronización de GymControl Fénix

Estado final verificado el 15/11/2026: 437 pruebas Laravel y 2.124 aserciones aprobadas. Las cifras inferiores de secciones previas corresponden a etapas conservadas del trabajo.

Fecha: 2026-11-14. Base: develop a7cc3200805e1833b2e60205037a60ddae3b2661. Cambios exclusivamente locales en clon temporal, sin commit, push, PR ni despliegue. SQLite aislado; MySQL original y sus datos no se utilizaron.

## Qué existía y qué faltaba

Ya existían Reverb, Echo, canales autorizados, cola y emisiones puntuales de acceso, pagos, inscripciones, solicitudes y decisiones médicas. Dashboard y algunas pantallas escuchaban eventos; muchos listados, fichas, formularios y operadores no recuperaban sus datos. Los accesos denegados se guardaban en auditoría sin producir asistencias; la ubicación de los eventos recientes dificultaba verlos. No equivalen a la tabla de últimos movimientos efectivos.

## Correcciones

Observador común de 16 modelos, agrupación por petición y audiencia después de commit, descarte en rollback y cola realtime. Nuevo evento gym.state-changed contiene únicamente UUID y áreas invalidadas. Cada pantalla consulta otra vez sus datos con sus permisos; no se transportan entidades, PIN, QR, contraseñas ni datos clínicos. La terminal sólo recibe availability. Solicitudes se distribuyen a dueña y solicitante.

Treinta componentes Livewire y navegación recuperan información dirigida sin ejecutar altas, cobros ni accesos al recibir mensajes. Se conservan filtros, paginación y borradores. Echo agrupa mensajes, elimina duplicados, serializa consultas de cada componente y recupera estado al reconectar o regresar a la ventana. Se conserva una conexión en navegación Livewire. Hay reintentos limitados de lectura; su fallo HTTP no se inyectó expresamente en Chrome.

Dashboard original conservado. Eventos recientes visibles arriba en dashboard y control de acceso, incluyendo rechazos persistidos aunque la terminal esté cerrada. Se corrigió el enfoque que podía desplazar la pantalla y el nombre de navegación que Alpine mantenía desactualizado. Auditoría de edición de operadores y estado de socio sin datos secretos.

## Matriz

Todos los recursos de la columna Evento corresponden a gym.state-changed, salvo clock que es una recuperación local de lectura al cambiar la fecha. “Implementado” no equivale a una prueba visual individual aprobada.

| Módulo | Operación | Evento Reverb | Administración | Recepción | Terminal | Prueba sin F5 | Resultado |
|---|---|---|---|---|---|---|---|
| Panel | Indicadores de socios, cuotas, cupos y accesos | members/groups/payments/access | Actualiza estadísticas | Indicadores operativos | Señal genérica cuando corresponde | Sí: 1,2,3,7 | Verificado en Chrome |
| Socios | Alta desde recepción | members | Listado actualizado | Listado actualizado | availability | Sí: 1 | Verificado en Chrome |
| Socios | Edición de datos | members | Listado y ficha | Listado y ficha | availability | Sí: 11 | Verificado; recepción recuperó el cambio tras reconectar |
| Socios | Estado y credenciales | members/access | Datos autorizados | Datos autorizados | availability; próxima validación consulta servidor | Automática; sin caso visual individual | Implementado; sin PIN/QR en mensajes |
| Membresías | Inscripción aprobada y cupos | members/groups/payments | Contadores y disponibilidad | Contadores y disponibilidad | availability | Sí: 2 | Verificado; un único registro |
| Membresías | Baja, aprobación, rechazo o cambio de grupo | members/groups/payments | Listas y contadores | Listas y contadores autorizados | availability | Automática; sin cada variante en Chrome | Observación de modelos y reglas existentes conservadas |
| Membresías | Lista de espera: alta, cancelación, promoción y orden | groups | Disponibilidad/lista | Disponibilidad/lista autorizada | availability | Sin prueba visual individual | Implementado; no certificado visualmente cada caso |
| Membresías | Disciplinas y precios | groups | Catálogo | Catálogo autorizado | availability | Sin CRUD visual individual | Cuatro disciplinas y precios originales conservados |
| Grupos | Cupo y datos del grupo | groups | Grupos, membresías, auditoría | Grupos y membresías | availability | Sí: 10 | Verificado; cupo restaurado a 15 |
| Grupos | Alta, horarios, estado y baja | groups | Vistas dependientes | Vistas autorizadas | availability | Sin cada variante visual individual | Implementado; horarios y validaciones conservados |
| Pagos | Registro por inscripción | payments/members/access | Ingresos y cuotas | Cuotas e historial autorizado | availability | Sí: 3 | Verificado; pago único y disciplinas independientes |
| Pagos | Anulación y cobertura | payments/members/access | Historial/indicadores | Historial/indicadores | availability | Automática; sin anulación visual individual | Reglas existentes conservadas |
| Pagos | Vencimiento por fecha | clock; no escritura ni evento artificial | Consulta al cambio de fecha | Consulta al cambio de fecha | Próxima operación valida en servidor | No se esperó al cambio de día | Implementado; observación real de medianoche pendiente |
| Aptos físicos | Decisión médica de dueña | medical sólo dueña; members/access operativos | Decisión y pendientes | Estado operativo, sin detalle clínico | availability | Sí: 4 | Pantallas verificadas; validación real autorizada posterior pendiente |
| Aptos físicos | Alta/edición de certificado | medical; members/access | Pantallas médicas autorizadas | Sólo estado operativo | availability | Automática; sin cada variante visual | Sin documentos ni observaciones en broadcasting |
| Solicitudes | Recepción envía solicitud | requests | Pendientes actualizados | Sólo solicitante | No recibe solicitudes | Sí: 5 | Verificado por canal individual |
| Solicitudes | Dueña resuelve solicitud | requests y áreas afectadas | Pendientes actualizados | Resultado para solicitante | Sólo availability si cambia operación | Sí: 6 | Rechazo verificado; aprobación no ejecutada visualmente en esta ronda |
| Control de acceso | Intento denegado por PIN | access/audit | Evento reciente y contador | Evento reciente y motivo | Resultado local | Sí: 7 | Verificado; persistencia sin asistencia y sin duplicación |
| Control de acceso | Ingreso autorizado | access/audit | Presencia, asistencia y evento | Presencia, asistencia y evento | Resultado de validación real | No: sábado sin actividad | PENDIENTE; no se alteró reloj ni horario |
| Control de acceso | Salida | access/audit | Permanencias y movimientos | Permanencias y movimientos | Resultado de salida | No: sin permanencia abierta válida | PENDIENTE visual; reglas de salida cubiertas por suite existente |
| Control de acceso | QR, manual, correcciones, tardanzas y ausencias | access/audit | Vistas dependientes | Vistas autorizadas | Reglas existentes | Automática; sin cada caso visual | No se cambiaron reglas ni se repiten movimientos al notificar |
| Operadores | Editar nombre y navegación | operators/audit; permissions | Listado y navegación | Sólo información de sesión propia | availability genérica | Sí: 13 | Verificado; nombres originales restaurados |
| Operadores | Alta, rol, contraseña y edición | operators/audit; permissions | Acceso exclusivo de dueña | No obtiene acceso a gestión | Sin datos de operador | Automática; sin CRUD visual completo | Sin contraseñas en eventos ni auditoría |
| Auditoría | Nuevos movimientos auditados | audit | Listado actualizado | Sin acceso | Sin acceso | Sí: 10; permisos 12 | Verificado; se conserva restricción original |
| Conectividad | Desconexión y recuperación | Suscripción y consulta del estado actual | Recuperación dirigida | Recuperación dirigida | Consulta próxima operación | Sí: 11 | Verificado; no depende de reproducir mensajes perdidos |
| Conectividad | Navegación entre módulos | Misma conexión y canales | Controlador compartido | Dos canales privados únicos | Un canal público genérico | Sí: 14 | Verificado en recepción: mismo socket y sin recarga |
| Seguridad | Canales y pantallas por rol | Autorización original | Sólo audiencia autorizada | Canales ajenos: 403 | Canales privados: 403 | Sí: 12 | Verificado; payload mínimo change_id/resources |

## Evidencia y límites

Pruebas solicitadas 1–3, 5–7 y 10–12 aprobadas en Chrome: nueve completas. Prueba 4 parcial: decisión y actualización visual comprobadas; validación de ingreso real posterior pendiente. Ingreso autorizado y salida real (8 y 9) pendientes por horario real y ausencia de permanencia abierta. Operadores (13) y navegación SPA (14) comprobados adicionalmente. Las pruebas de cambios observados comprobaron operación persistida, mensaje recibido, consulta reactiva y DOM actualizado, con el mismo performance.timeOrigin y sin solicitudes de documento durante la operación observada. El control 12 comprobó rechazos 403 y minimización de mensajes. No se infiere éxito visual de una conexión o contador solamente.

No se ejecutó una prueba de estrés con escritores paralelos; permanecen los bloqueos, transacciones y validaciones de capacidad existentes. No se certifica visualmente cada variante CRUD de la matriz. No se observó un vencimiento real a medianoche. La decisión médica se reflejó en recepción; la validación de ingreso autorizado posterior permanece pendiente junto con la prueba 8.

Suite completa: 424 pruebas Laravel y 1.995 aserciones, incluidas las 406 originales sin modificar; 18 pruebas nuevas de sincronización. Seis pruebas JavaScript aprobadas. Después de corregir el nombre en Blade: 23 pruebas focalizadas y 156 aserciones aprobadas y prueba 13 en Chrome aprobada. Compilación Vite correcta.

Las pruebas nuevas cubren commit/rollback, agrupación por audiencia, privacidad médica y de operadores, solicitantes, rechazo sin asistencia, cambios de grupos, exclusión de remember_token, recuperación de fichas y borradores, autorización actualizada, terminal de sólo lectura, recursos inválidos y auditoría sin secretos. JavaScript cubre deduplicación, áreas complementarias, payload inválido, invalidación para reconexión, dependencias y memoria acotada.

Evidencia de Chrome y capturas guardadas fuera del repositorio en GymControlRealtime_a7cc320 dentro del directorio temporal del usuario. integral-resultados.json conserva las comprobaciones; no debe confundirse con una nueva suite CI.

## Entorno para revisión

Administración: http://127.0.0.1:8127/dashboard — admin@gymcontrol.com. Recepción: http://localhost:8127/dashboard — recep@gymcontrol.com. Terminal: http://127.0.0.1:8127/terminal en contexto Chrome independiente. Los dos hosts mantienen separadas las cookies de las cuentas; la terminal tiene su propio contexto. Contraseña original académica/local; ninguna contraseña privada adicional creada.

Laravel 8127, Vite 5177, Reverb 8097 y worker realtime,default activos. Dos operadores originales. Grupo ficticio con capacidad 15, horarios laborales, dos socios temporales, pagos simulados sin dinero real, cinco intentos denegados y ninguna asistencia. Para repetir: mantener ambos paneles abiertos, enviar desde terminal el PIN local ficticio y observar el nuevo evento con su motivo sin F5; cerrar terminal no borra el historial. Para ingreso autorizado y salida, esperar un horario válido y cumplir pago/certificado y demás reglas.

UserSeeder, DatabaseSeeder y DisciplinaSeeder no modificados; cuatro disciplinas y precios conservados. Ninguna migración nueva. Ningún cambio al repositorio original, MySQL, main, develop remoto o permisos.

## Archivos de implementación y pruebas

Documentación adicional: README.md y este informe.

- app/Events/GymStateChanged.php
- app/Http/Middleware/FlushRealtimeChanges.php
- app/Livewire/Concerns/SyncsGymState.php
- app/Observers/GymStateObserver.php
- app/Services/AccessActivityService.php
- app/Services/RealtimeChanges.php
- resources/js/gym-sync.js
- resources/views/components/gym/realtime-status.blade.php
- resources/views/livewire/realtime/recent-access-events.blade.php
- tests/Feature/RealtimeSyncTest.php
- tests/JavaScript/realtime.test.mjs
- app/Http/Controllers/Api/SocioApiController.php
- app/Livewire/AptosFisicos/CrearAptoFisico.php
- app/Livewire/AptosFisicos/EditarAptoFisico.php
- app/Livewire/AptosFisicos/ListarAptosFisicos.php
- app/Livewire/Asistencias/ListarAsistencias.php
- app/Livewire/Auditoria/PanelAuditoria.php
- app/Livewire/ControlAcceso.php
- app/Livewire/Dashboard.php
- app/Livewire/Disciplinas/CrearDisciplina.php
- app/Livewire/Disciplinas/EditarDisciplina.php
- app/Livewire/Disciplinas/ListarDisciplinas.php
- app/Livewire/Grupos/CrearGrupo.php
- app/Livewire/Grupos/EditarGrupo.php
- app/Livewire/Grupos/ListarGrupos.php
- app/Livewire/Inscripciones/CrearInscripcion.php
- app/Livewire/Inscripciones/ListarInscripciones.php
- app/Livewire/Membresias/CrearMembresia.php
- app/Livewire/Membresias/EditarMembresia.php
- app/Livewire/Membresias/ListarMembresias.php
- app/Livewire/Operadores/CrearOperador.php
- app/Livewire/Operadores/EditarOperador.php
- app/Livewire/Operadores/ListarOperadores.php
- app/Livewire/Pagos/CoberturasSocio.php
- app/Livewire/Pagos/CrearPago.php
- app/Livewire/Pagos/ListarPagos.php
- app/Livewire/Socios/CrearSocio.php
- app/Livewire/Socios/EditarSocio.php
- app/Livewire/Socios/ListarSocios.php
- app/Livewire/Socios/VerSocio.php
- app/Livewire/Solicitudes/GestionarSolicitudes.php
- app/Livewire/TerminalAcceso.php
- app/Providers/AppServiceProvider.php
- app/Services/AsistenciaClaseService.php
- app/Services/CertificadoMedicoService.php
- app/Services/ControlAccesoService.php
- app/Services/InscripcionService.php
- app/Services/PagoService.php
- app/Services/SolicitudService.php
- bootstrap/app.php
- resources/js/echo.js
- resources/views/layouts/app.blade.php
- resources/views/layouts/terminal.blade.php
- resources/views/livewire/aptos-fisicos/crear-apto-fisico.blade.php
- resources/views/livewire/aptos-fisicos/editar-apto-fisico.blade.php
- resources/views/livewire/aptos-fisicos/listar-aptos-fisicos.blade.php
- resources/views/livewire/asistencias/listar-asistencias.blade.php
- resources/views/livewire/auditoria/panel-auditoria.blade.php
- resources/views/livewire/control-acceso.blade.php
- resources/views/livewire/dashboard.blade.php
- resources/views/livewire/disciplinas/crear-disciplina.blade.php
- resources/views/livewire/disciplinas/editar-disciplina.blade.php
- resources/views/livewire/disciplinas/listar-disciplinas.blade.php
- resources/views/livewire/grupos/crear-grupo.blade.php
- resources/views/livewire/grupos/editar-grupo.blade.php
- resources/views/livewire/grupos/listar-grupos.blade.php
- resources/views/livewire/inscripciones/crear-inscripcion.blade.php
- resources/views/livewire/inscripciones/listar-inscripciones.blade.php
- resources/views/livewire/layout/navigation.blade.php
- resources/views/livewire/membresias/crear-membresia.blade.php
- resources/views/livewire/membresias/editar-membresia.blade.php
- resources/views/livewire/membresias/listar-membresias.blade.php
- resources/views/livewire/operadores/crear-operador.blade.php
- resources/views/livewire/operadores/editar-operador.blade.php
- resources/views/livewire/operadores/listar-operadores.blade.php
- resources/views/livewire/pagos/coberturas-socio.blade.php
- resources/views/livewire/pagos/crear-pago.blade.php
- resources/views/livewire/pagos/listar-pagos.blade.php
- resources/views/livewire/socios/crear-socio.blade.php
- resources/views/livewire/socios/editar-socio.blade.php
- resources/views/livewire/socios/listar-socios.blade.php
- resources/views/livewire/socios/ver-socio.blade.php
- resources/views/livewire/solicitudes/gestionar-solicitudes.blade.php
- resources/views/livewire/terminal-acceso.blade.php

## Ampliación integral del 14/15 de noviembre de 2026

Suite completa final: 430 pruebas Laravel, 2.090 aserciones, seis pruebas JavaScript aprobadas y compilación Vite correcta. Se conservan las 406 pruebas originales. Se agregaron tres regresiones de presentación de pagos, una regresión con dos procesos de inscripción y dos recorridos integrales parametrizados de alta, disciplinas, pagos, documento ficticio, credencial, PIN, QR, ingresos, salidas y cambio posterior de requisitos. Los horarios de estos dos recorridos se controlan sólo dentro de PHPUnit y SQLite en memoria; no constituyen una prueba visual de ingreso autorizado con el reloj real.

Defectos reproducidos y corregidos: (1) recepción no mostraba el estado Anulado y la etiqueta de cobertura ignoraba su suspensión, pese a recibir y consultar el cambio; se corrigió sólo la presentación, conservando el permiso exclusivo de anulación de la dueña; (2) dos procesos SQLite que competían por la última plaza podían devolver un bloqueo de base de datos. Se configura SQLite con transacción IMMEDIATE y busy_timeout de 5.000 ms y se acotan a cinco los reintentos de inscripción. En PHP 8.5 dos procesos se solaparon: una inscripción se confirmó y la otra informó grupo completo, sin superar quince plazas. También dos resoluciones simultáneas produjeron una sola aprobación y una sola auditoría de resolución. La configuración MySQL no se modificó.

Verificación visual adicional: formularios reales, PDF válido ficticio y acceso privado, estados pendiente/aprobado/rechazado, archivos no permitidos, PDF falsificado y tamaño excesivo; quince plazas y rechazo de la número dieciséis; dos disciplinas con pagos independientes; credencial y QR móvil; reconexión y pausa/reanudación del worker; validaciones, búsqueda, paginación y permisos. Los intentos de automatización incompletos se conservan y se distinguen de los casos aprobados.

Reglas conservadas: la carga médica pertenece a la dueña y su carga válida queda aprobada directamente; el estado pendiente se prueba por edición administrativa y por datos heredados. No se añade automáticamente una persona a espera al llenarse un grupo. La inscripción actual con vacante se confirma directamente; las solicitudes heredadas de inscripción y las correcciones de permanencias se prueban automáticamente. No se inventaron operaciones nuevas para simularlas.

Siguen pendientes en Chrome el ingreso autorizado y la salida real dentro de horario válido. En pruebas aisladas de tiempo controlado sí se ejecutaron ambos, incluyendo salida después de perder cobertura o requisito médico. No se certifica la concurrencia en MySQL ni en otra versión de PHP.

Nuevos archivos de pruebas: tests/Feature/RealtimePaymentPresentationTest.php, tests/Feature/SqliteConcurrentEnrollmentTest.php, tests/Feature/GymLifecycleIntegrationTest.php y tests/Support/ConcurrentEnrollmentWorker.php. Ajustes adicionales: resources/views/livewire/pagos/listar-pagos.blade.php, app/Services/InscripcionService.php y config/database.php. Informe integral, matriz y capturas fuera del repositorio en la carpeta de continuación integral dentro de GymControlBackups. Sin publicación, commit, PR ni despliegue.

## Cierre integral verificado

La revisión final detectó y corrigió además el reenvío de solicitudes pendientes idénticas y el atributo nombre_completo ausente en Socio. Revisiones médicas, bajas y cambios de grupo reutilizan la solicitud pendiente del mismo solicitante y contenido equivalente; las solicitudes resueltas permiten una nueva revisión, los distintos solicitantes permanecen separados y no se elimina el historial. Se bloquea el socio o inscripción durante la comprobación para serializar escrituras. Cinco regresiones automáticas y las tres comprobaciones de reenvío en Chrome verificaron que no se duplica solicitud, auditoría ni broadcasting.

El atributo calculado de Socio restaura nombres en listados de inscripción/espera, selección y mensajes. Dos regresiones usan nombres literales y evitan que una expectativa vacía apruebe una pantalla sin nombre. Se comprobó también en Chrome la espera heredada: grupo lleno, vacante por baja, incorporación explícita y cancelación. La preparación de esos registros heredados fue un fixture aislado documentado; no se declara que exista un alta automática de espera desde inscripción nueva.

La prueba antigua ControlAccesoTest::test_salida_exitosa dependía de la hora real: usaba fecha de hoy junto a una hora de 45 minutos atrás, creando un ingreso futuro al cruzar medianoche. Se corrige sólo el fixture para derivar fecha y hora del mismo instante, preservando todas sus aserciones y las 406 pruebas originales. No se modifican las reglas de salida ni el reloj.

Resultado final: 437 pruebas Laravel, 2.124 aserciones, seis pruebas JavaScript y Vite correctos. Se agregaron 31 casos Laravel en total respecto de la base de 406. Cuatro defectos de aplicación corregidos en esta ampliación: presentación de pago anulado/suspendido, contención SQLite, solicitudes repetidas y nombre completo ausente. Además se corrigió el fixture temporal legado. Las versiones previas de las pruebas y los intentos fallidos permanecen en las evidencias permanentes; los resultados definitivos no los ocultan.

Nuevos archivos adicionales de regresión: tests/Feature/PendingRequestIdempotencyTest.php y tests/Feature/SocioDisplayNameTest.php. Archivos adicionales corregidos: app/Services/CertificadoMedicoService.php, app/Models/Socio.php y tests/Feature/ControlAccesoTest.php. El inventario definitivo del respaldo es la referencia completa.

También se comprobó en Chrome que una edición administrativa actualiza la ficha/listado de recepción y muestra un aviso genérico en la terminal, sin revelar datos privados ni registrar accesos por recibir una notificación. El intento PIN denegado permanece visible tras cerrar la terminal. Pausa/reanudación del worker y reconexión recuperan información sin F5. Los tres componentes comparten exclusivamente el SQLite del clon.

Pendiente visual: ingreso autorizado y salida real dentro de un horario permitido; sus variantes de PIN/QR, reingreso, cuotas/certificados y permanencias están cubiertas automáticamente con tiempo controlado, sin alterar el reloj real. El vencimiento efectivo a medianoche de una cuota en Chrome no se observó. Concurrencia comprobada en SQLite y PHP 8.5, no en MySQL. Sin publicación ni modificación del proyecto XAMPP original.

## Revisión final de requisitos del 15 de noviembre de 2026

Esta revisión reemplaza la descripción histórica de carga con aprobación automática y espera exclusivamente heredada. La interfaz ahora presenta certificados pendientes de revisión; la dueña aprueba o rechaza en un paso separado. Cambiar si un socio requiere documento sigue siendo otra decisión independiente. Recepción no carga, consulta, aprueba ni rechaza documentación, y sólo recibe el estado operativo. La sustitución de archivo conserva el histórico y crea una nueva presentación pendiente. El método administrativo interno de registro directo se conserva por compatibilidad, exclusivo de la dueña; las dos interfaces de carga utilizan presentarCertificado.

Se corrigió también el indicador de certificados faltantes o vencidos: exige un documento aprobado cuya emisión y vencimiento incluyan la fecha actual. Se agregó el contador de documentos pendientes de revisión, exclusivo de la dueña. No se ocultan los documentos rechazados del historial.

Un socio nuevo puede registrarse de forma preliminar y luego elegir un grupo completo en Inscripciones, Registrar en lista de espera. La acción es explícita y no genera inscripción activa ni cobro. Recepción y dueña pueden registrar la espera; sólo la dueña puede incorporar o cancelar. Se reutiliza un turno repetido; las posiciones se asignan bajo bloqueo del mismo grupo usado por inscripciones y promociones. Liberar una plaza no promueve automáticamente. Dos procesos reales sobre SQLite verificaron posiciones distintas y capacidad conservada. No se afirma concurrencia probada en MySQL.

Validación después del último cambio funcional: 460 pruebas Laravel, 2.257 aserciones, seis pruebas JavaScript y Vite aprobados. Se agregaron 23 casos respecto de las 437 pruebas anteriores. Se conserva la totalidad de la suite previa; AptoFisicoValidationTest ahora comprueba presentación pendiente y aprobación explícita antes de sus aserciones originales. Los ocho escenarios visuales dependientes de horario o permanencia real continúan pendientes y se distinguen de PHPUnit con tiempo controlado. Informes y evidencia permanente en C:/GymControlBackups/Revision_requisitos_20261115.
