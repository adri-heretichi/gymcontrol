# GymControl Fénix — checklist de inspección manual

Usar en la rama integrada y con datos locales de prueba. Preparar socios, disciplinas, grupos y pagos de prueba desde la interfaz; no usar datos productivos. Cada caso debe registrarse como **Aprobado**, **Fallido** o **Bloqueado**, con evidencia y observaciones.

## Casos

### GF-01 — Inicio de sesión de la dueña
- **Módulo:** Autenticación
- **Actor:** Dueña / administradora
- **Precondiciones:** Existe una cuenta activa con rol `admin`; navegador en `/login`.
- **Pasos:** Ingresar el correo y la contraseña de la dueña; enviar el formulario.
- **Resultado esperado:** La sesión se inicia y abre el dashboard administrativo. No se muestra información de credenciales en la página ni en la URL.

### GF-02 — Inicio de sesión de recepción
- **Módulo:** Autenticación
- **Actor:** Recepcionista
- **Precondiciones:** Existe una cuenta activa con rol `recepcionista`; cerrar la sesión anterior.
- **Pasos:** Ingresar las credenciales de recepción en `/login`; enviar el formulario.
- **Resultado esperado:** Se abre el panel operativo con permisos de recepción. No se muestran accesos de administración exclusivos de la dueña.

### GF-03 — Alta de socio sin membresía general
- **Módulo:** Socios
- **Actor:** Recepcionista
- **Precondiciones:** Sesión iniciada; DNI y correo de prueba aún no registrados.
- **Pasos:** Abrir Socios → Registrar socio; completar datos personales y de contacto; guardar sin elegir plan general.
- **Resultado esperado:** El socio se crea sin `membresia_id` ni fecha de vencimiento general. La ficha permite continuar con la inscripción a disciplina y grupo.

### GF-04 — Decisión médica no requerida
- **Módulo:** Decisión médica y control de acceso
- **Actor:** Dueña y socio
- **Precondiciones:** Socio activo con inscripción aprobada, horario vigente y cobertura pagada vigente; la dueña marcó que no requiere certificado; credencial activa y sin permanencia abierta.
- **Pasos:** Presentar PIN en la terminal durante el horario del grupo.
- **Resultado esperado:** Ingreso autorizado. El mensaje de autorización no menciona certificado médico.

### GF-05 — Certificado requerido pero faltante
- **Módulo:** Decisión médica y control de acceso
- **Actor:** Dueña y socio
- **Precondiciones:** Inscripción aprobada, horario y cobertura vigentes; decisión médica de certificado requerido; no hay certificado vigente; credencial activa.
- **Pasos:** Presentar PIN o QR en la terminal.
- **Resultado esperado:** Acceso denegado sin exponer el motivo médico en la terminal, QR móvil o panel de recepción; la dueña puede consultar el detalle en la ficha administrativa. No se crea una asistencia de ingreso.

### GF-06 — Excepción médica aprobada por la dueña
- **Módulo:** Decisión médica y solicitudes
- **Actor:** Dueña y socio
- **Precondiciones:** Socio con solicitud/excepción médica pendiente y actividad con cobertura vigente.
- **Pasos:** Como dueña, resolver la solicitud aprobándola; volver a presentar la credencial dentro del horario.
- **Resultado esperado:** La recepción no puede aprobar por la dueña. Con excepción aprobada y los demás requisitos cumplidos, el acceso se autoriza y queda auditado sin exponer datos médicos sensibles.

### GF-07 — Sin pago o cobertura vigente
- **Módulo:** Pagos, coberturas y control de acceso
- **Actor:** Socio
- **Precondiciones:** Inscripción aprobada y horario actual; no existe pago activo con cobertura vigente para esa inscripción; credencial activa.
- **Pasos:** Presentar la credencial en la terminal.
- **Resultado esperado:** Acceso denegado indicando falta o vencimiento de cobertura de esa actividad; ninguna membresía general habilita el ingreso.

### GF-08 — Fuera de horario o de disciplina
- **Módulo:** Grupos, horarios y control de acceso
- **Actor:** Socio
- **Precondiciones:** Inscripción aprobada y cobertura vigente; credencial activa; seleccionar una hora fuera del horario del grupo o un grupo distinto al inscrito.
- **Pasos:** Intentar el ingreso indicando el grupo, si se solicita.
- **Resultado esperado:** Acceso denegado con mensaje que identifica que no corresponde la actividad, disciplina o día/horario; no se registra el ingreso.

### GF-09 — Inscripción directa por recepción
- **Módulo:** Inscripciones y control de acceso
- **Actor:** Recepcionista y socio
- **Precondiciones:** Socio registrado; grupo activo con vacante y horario vigente.
- **Pasos:** Desde recepción, inscribir al socio directamente en el grupo; completar el pago/cobertura; presentar su credencial en el horario correspondiente. Repetir con un grupo sin vacantes.
- **Resultado esperado:** La inscripción con vacante queda confirmada por recepción sin aprobación posterior de la dueña y habilita el acceso cuando se cumplen los demás requisitos. Sin vacante no se confirma ni habilita el ingreso; ninguna inscripción pendiente autoriza el acceso.

### GF-10 — Grupo lleno y lista de espera
- **Módulo:** Grupos e inscripciones
- **Actor:** Recepcionista y dueña
- **Precondiciones:** Grupo activo con cupo máximo alcanzado por inscripciones aprobadas.
- **Pasos:** Intentar inscribir otro socio; consultar lista de espera; liberar un cupo y revisar la gestión posterior.
- **Resultado esperado:** No se supera el cupo. El socio queda en lista de espera según el flujo definido; ninguna inscripción pendiente habilita el acceso.

### GF-11 — Emisión de PIN y QR
- **Módulo:** Socios y credenciales
- **Actor:** Operador autorizado
- **Precondiciones:** Socio con inscripción aprobada, cobertura vigente y requisito médico cumplido; no hay una credencial activa.
- **Pasos:** Abrir la ficha del socio; emitir las credenciales; consultar la tarjeta o QR generado.
- **Resultado esperado:** Se emiten PIN y QR válidos y activos. Los secretos no aparecen en auditoría, logs ni vistas de operadores no autorizados.

### GF-12 — Ingreso por PIN
- **Módulo:** Terminal y control de acceso
- **Actor:** Socio
- **Precondiciones:** Credencial activa y todos los requisitos de actividad, horario, cobertura y decisión médica cumplidos.
- **Pasos:** Abrir `/terminal`; ingresar el PIN con el teclado físico y con el teclado numérico en pantalla, incluyendo un PIN que comience en cero. Después generar un intento denegado y revisar los últimos eventos en Control de Acceso como recepción.
- **Resultado esperado:** La terminal conserva los seis dígitos, muestra el resultado y la actividad correspondiente y crea una única permanencia abierta. El panel de recepción muestra intentos recientes con motivos operativos, sin PIN, token QR ni detalles médicos.

### GF-13 — Acceso por QR móvil
- **Módulo:** Credenciales QR
- **Actor:** Socio
- **Precondiciones:** Socio con QR activo y requisitos de acceso cumplidos; teléfono con cámara y red local accesible.
- **Pasos:** Abrir el enlace QR móvil desde la ficha autorizada. Verificar que la página GET solo evalúa y no crea asistencia ni auditoría; después confirmar explícitamente el ingreso mediante el formulario POST. Confirmar la salida desde su acción POST.
- **Resultado esperado:** El enlace QR abre la pantalla móvil existente, sin depender de un lector o una aplicación que aún no estén implementados. Solo el POST explícito registra entrada/salida; se aplican las mismas reglas actuales que al PIN y el token no se filtra en auditoría, eventos ni mensajes operativos.

### GF-14 — Salida y cierre de permanencia
- **Módulo:** Control de acceso y asistencias
- **Actor:** Socio
- **Precondiciones:** Socio con ingreso autorizado y permanencia abierta.
- **Pasos:** Presentar nuevamente la credencial para registrar la salida; revisar el historial. Repetir con pago vencido y, en un caso separado, con credencial revocada explícitamente.
- **Resultado esperado:** Se cierra la permanencia con fecha, hora y método de salida correctos, incluso si venció el pago o se revocó la credencial después del ingreso; no queda otra permanencia abierta duplicada ni se reactiva la credencial revocada.

### GF-15 — Asistencia a clase
- **Módulo:** Asistencias de clase
- **Actor:** Recepcionista
- **Precondiciones:** Grupo con horario vigente e inscripción aprobada.
- **Pasos:** Registrar la asistencia del socio en la clase correspondiente; consultar el detalle del grupo y el historial.
- **Resultado esperado:** Asistencia asociada al socio, grupo y fecha correctos; el resultado puntual/tardío sigue la regla horaria configurada.

### GF-16 — Recepción propone y dueña resuelve
- **Módulo:** Solicitudes y permisos
- **Actor:** Recepcionista y dueña
- **Precondiciones:** Recepcionista con sesión iniciada y socio existente.
- **Pasos:** Recepción crea una solicitud que requiere decisión de la dueña; intentar resolverla con recepción; iniciar sesión como dueña y resolverla.
- **Resultado esperado:** Recepción puede proponer/gestionar sin aprobar una decisión reservada a la dueña. La dueña puede resolver y queda registrada la autoría.

### GF-17 — Auditoría sin secretos ni datos médicos
- **Módulo:** Auditoría
- **Actor:** Dueña
- **Precondiciones:** Se realizaron eventos de credenciales, acceso y decisión médica en datos locales.
- **Pasos:** Abrir Auditoría; revisar eventos y detalles; buscar PIN, token QR, diagnóstico o documentos médicos.
- **Resultado esperado:** Los eventos relevantes están disponibles para control, pero no exponen PIN, token QR, información médica sensible ni contenido de documentos.

### GF-18 — Dashboard de la dueña
- **Módulo:** Dashboard
- **Actor:** Dueña
- **Precondiciones:** Sesión de administradora; existen registros de prueba para los indicadores que se quieran validar.
- **Pasos:** Abrir el dashboard; contrastar totales, solicitudes, actividad, pagos y accesos con las pantallas fuente.
- **Resultado esperado:** Indicadores consistentes con los datos actuales, enlaces operativos disponibles y sin depender de cuotas generales de membresía.

### GF-19 — Operación de recepción
- **Módulo:** Panel operativo
- **Actor:** Recepcionista
- **Precondiciones:** Sesión de recepcionista.
- **Pasos:** Recorrer Socios, Disciplinas, Grupos, Inscripciones, Pagos/Coberturas, Control de acceso, Historial y Solicitudes; intentar abrir/listar/cargar/editar/descargar Aptos Físicos por URL directa y API.
- **Resultado esperado:** Las tareas asignadas a recepción funcionan; se muestra únicamente un estado operativo mínimo y las decisiones/documentos médicos exclusivos de la dueña se deniegan correctamente. No aparece el módulo principal Membresías.

### GF-20 — Reportes PDF
- **Módulo:** Reportes
- **Actor:** Dueña y recepción según permisos
- **Precondiciones:** Existen datos locales para socios, pagos y asistencias; iniciar como cada rol.
- **Pasos:** Descargar reporte de ficha de socio, asistencias, pagos, comprobante y tarjeta donde estén disponibles; revisar filtros, contenido y apertura del PDF.
- **Resultado esperado:** Los reportes cargan relaciones y datos vigentes de inscripción/cobertura; reportes financieros respetan permisos y no presentan membresía general como autorización de acceso.

### GF-21 — Notificaciones en tiempo real
- **Módulo:** Reverb y dashboard
- **Actor:** Dueña y recepción
- **Precondiciones:** Laravel, Reverb y el worker de cola de broadcasting activos; abrir dashboard en dos sesiones/navegadores autenticados según rol.
- **Pasos:** Generar un evento operativo de prueba; observar ambos paneles y la conexión WebSocket en las herramientas de desarrollo del navegador.
- **Resultado esperado:** La conexión WebSocket se establece y el worker procesa el evento; las sesiones autorizadas reciben/actualizan los datos permitidos. No se filtran datos sensibles entre roles. Si Reverb o el worker no están activos, marcar el caso bloqueado; las pruebas automatizadas no sustituyen esta verificación E2E.

### GF-22 — Navbar, marca y responsive
- **Módulo:** Interfaz
- **Actor:** Dueña y recepción
- **Precondiciones:** Sesión iniciada; Chrome con viewport de escritorio, tablet y móvil.
- **Pasos:** Revisar el navbar a 1440 px, 1024 px, 768 px y 390 px; abrir/cerrar menú responsive; recorrer todos los enlaces; revisar login, backoffice, terminal, ficha y credencial QR.
- **Resultado esperado:** Todos los ítems, incluida Auditoría, quedan dentro del contenedor blanco; no hay desbordamiento horizontal; el menú móvil es utilizable. Backoffice muestra identidad dual GymControl + Fénix, terminal/credenciales priorizan el logo Fénix, y naranja/negro/blanco son la paleta principal con dorado como acento legible.

## Preparación manual de casos de prueba

Registrar manualmente datos ficticios desde Socios en un entorno aislado, con las inscripciones, cobros explícitos y decisiones médicas requeridas por cada caso. La instalación no precarga socios ni grupos. No usar personas ni documentos reales ni ejecutar cargas de prueba sobre MySQL existente. Los grupos y horarios se administran en Membresías → Grupos; usar únicamente horarios confirmados por la dueña.

### A. Pruebas de la dueña

#### GF-23 — Certificado aprobado y vigente
- **Módulo:** Aptos Físicos y credenciales
- **Actor:** Dueña y socio
- **Precondiciones:** Inscripción confirmada en Krav Maga Kids, cobertura vigente, decisión `requerido` y documento explícitamente ficticio guardado en almacenamiento privado.
- **Pasos:** Abrir la ficha, consultar el apto físico y verificar estado y vencimiento; luego comprobar el estado de la credencial.
- **Resultado esperado:** El documento se consulta únicamente desde el panel protegido de la dueña; el apto está aprobado/vigente y el PIN/QR se emitieron mediante el servicio existente.

#### GF-24 — Cambio de decisión después de emitir credenciales
- **Módulo:** Decisión médica individual
- **Actor:** Dueña
- **Precondiciones:** PIN y QR emitidos cuando la decisión era `no_requerido`; inscripción y pago vigentes.
- **Pasos:** Cambiar la decisión a `requerido` sin cargar un certificado; consultar la ficha y probar el acceso.
- **Resultado esperado:** El acceso se deniega por requisito médico pendiente; la decisión queda auditada y las credenciales emitidas se conservan sin regenerarse.

#### GF-25 — Revocación explícita de credenciales
- **Módulo:** PIN y QR
- **Actor:** Dueña
- **Precondiciones:** El socio recibió credenciales legítimas y luego la dueña las revocó; la cobertura de prueba está vencida.
- **Pasos:** Consultar la ficha y estado revocado; registrar una renovación desde recepción y volver a revisar el estado.
- **Resultado esperado:** La revocación permanece; el pago no reactiva ni vuelve a emitir PIN/QR. La revocación queda auditada sin guardar los valores de las credenciales.

### B. Pruebas de recepción

#### GF-26 — Cobro y reintento de acceso
- **Módulo:** Pagos por inscripción
- **Actor:** Recepcionista
- **Precondiciones:** Credencial activa, certificado no requerido y cobertura vencida.
- **Pasos:** Registrar en recepción una cuota para la inscripción de Funcional; presentar el mismo PIN durante el horario del grupo.
- **Resultado esperado:** El pago genera cobertura de esa inscripción; el socio puede ingresar inmediatamente si los demás requisitos se mantienen. El PIN y el QR son los mismos.

#### GF-27 — Rechazo de aspirante número 16
- **Módulo:** Inscripciones y cupos
- **Actor:** Recepcionista
- **Precondiciones:** Krav Maga Kids Grupo A tiene 15 inscripciones confirmadas; el socio ya ocupa una plaza en Grupo B.
- **Pasos:** Intentar inscribirlo también en Grupo A desde recepción.
- **Resultado esperado:** La operación informa que el grupo está completo; no crea una inscripción confirmada adicional y el total de inscripciones del grupo no aumenta.

### C. Pruebas de terminal PIN

#### GF-28 — Acceso sin certificado requerido
- **Módulo:** Terminal PIN
- **Actor:** Socio
- **Precondiciones:** Inscripción confirmada, cobertura vigente, decisión `no_requerido` y credenciales activas.
- **Pasos:** Abrir `/terminal`; ingresar el PIN usando el teclado físico y el teclado numérico en pantalla.
- **Resultado esperado:** Acceso permitido en el horario; se registra una sola permanencia y el mensaje no hace referencia a una condición médica.

#### GF-29 — Integrante de grupo completo puede ingresar
- **Módulo:** Terminal PIN y cupos
- **Actor:** Socio
- **Precondiciones:** Krav Maga Kids Grupo A está completo con sus 15 integrantes; este socio tiene cobertura y credencial vigentes.
- **Pasos:** Presentar el PIN durante el horario del grupo.
- **Resultado esperado:** El ingreso se autoriza; el cupo completo no bloquea a integrantes ya inscritos.

#### GF-30 — Disciplina ajena y horario incorrecto
- **Módulo:** Terminal PIN y horarios
- **Actor:** Socio
- **Precondiciones:** Inscripción vigente en Kapap Krav Maga Grupo A.
- **Pasos:** Intentar acceso a Kickboxing Muay Thai Grupo A y, por separado, a Kapap fuera del horario asignado.
- **Resultado esperado:** El primer intento se deniega por falta de inscripción en la disciplina elegida; el segundo, por horario. No se registra asistencia.

### D. Pruebas de QR móvil

#### GF-31 — Evaluación y confirmación explícita por QR
- **Módulo:** QR móvil
- **Actor:** Socio
- **Precondiciones:** QR activo, cobertura vigente e inscripción confirmada.
- **Pasos:** Abrir el QR desde la ficha autorizada; comprobar que el GET no registra movimientos; confirmar entrada y luego salida mediante sus acciones POST.
- **Resultado esperado:** Solo los POST explícitos registran entrada/salida. GET no escribe asistencia ni auditoría; el historial no expone el token QR. No se presupone una app lectora adicional.

### E. Pruebas de pagos, certificados y vencimientos

#### GF-32 — Certificado requerido pero faltante
- **Módulo:** Aptos Físicos y emisión
- **Actor:** Dueña
- **Precondiciones:** Inscripción y cobertura vigentes; decisión `requerido`; no existe apto físico.
- **Pasos:** Revisar el estado médico e intentar emitir credenciales.
- **Resultado esperado:** La evaluación médica no se cumple; no se emite PIN ni QR y recepción no ve información clínica.

#### GF-33 — Certificado vencido
- **Módulo:** Aptos Físicos y emisión
- **Actor:** Dueña
- **Precondiciones:** Inscripción y cobertura vigentes; decisión `requerido`; existe únicamente un certificado ficticio vencido.
- **Pasos:** Revisar el vencimiento e intentar emitir credenciales.
- **Resultado esperado:** El requisito médico bloquea la emisión; el certificado y su documento siguen accesibles solo mediante las rutas privadas autorizadas.

#### GF-34 — Certificado vigente, cobertura vencida
- **Módulo:** Pagos, Aptos Físicos y acceso
- **Actor:** Dueña y socio
- **Precondiciones:** Certificado aprobado/vigente y credenciales emitidas legítimamente; luego venció la cobertura.
- **Pasos:** Presentar el PIN durante el horario de Kapap Krav Maga Grupo B.
- **Resultado esperado:** Acceso denegado por cobertura vencida. Las credenciales se conservan y no se borran ni regeneran.

#### GF-35 — Decisión médica pendiente
- **Módulo:** Decisión médica y emisión
- **Actor:** Dueña
- **Precondiciones:** Inscripción y cobertura vigentes; decisión `pendiente`.
- **Pasos:** Consultar la ficha e intentar emitir credenciales.
- **Resultado esperado:** Pendiente no equivale a exención; no se emiten credenciales hasta que la dueña determine la condición.

#### GF-36 — Vencimiento posterior a emisión legítima
- **Módulo:** Coberturas y terminal
- **Actor:** Socio
- **Precondiciones:** PIN/QR emitidos con requisitos satisfechos; cobertura luego vencida.
- **Pasos:** Intentar acceso y revisar la ficha.
- **Resultado esperado:** Acceso denegado por cobertura; PIN y QR permanecen asociados al socio.

#### GF-37 — Renovación tras cambio médico
- **Módulo:** Decisión médica, pagos y credenciales
- **Actor:** Dueña y recepción
- **Precondiciones:** Credenciales emitidas con decisión no requerida; la dueña la cambió a requerida y no se presentó certificado.
- **Pasos:** Registrar una renovación en recepción y volver a presentar la credencial.
- **Resultado esperado:** La cobertura queda vigente, pero el ingreso continúa denegado por el requisito médico; el PIN/QR no se modifican.

### F. Pruebas de cupos y grupos

#### GF-38 — Grupos configurables sin alumnos precargados
- **Módulo:** Membresías → Grupos e inscripciones dentro de Socios
- **Actor:** Dueña y recepción, según sus permisos
- **Precondiciones:** Instalación aislada con las cuatro disciplinas y cero socios y grupos.
- **Pasos:** Crear grupos y horarios autorizados desde la interfaz; registrar socios manualmente e inscribirlos en horarios compatibles.
- **Resultado esperado:** Los grupos pueden administrarse vacíos, nunca superan 15 inscripciones confirmadas y no se generan alumnos ni pagos automáticamente. Los horarios incompatibles y las inscripciones duplicadas se rechazan.

### G. Pruebas de auditoría y notificaciones en tiempo real

#### GF-39 — Idempotencia, privacidad y Reverb
- **Módulo:** Auditoría, catálogo y notificaciones
- **Actor:** Dueña
- **Precondiciones:** Casos registrados manualmente en un entorno aislado; Reverb y el worker de cola disponibles para la parte en tiempo real.
- **Pasos:** Revisar auditorías de decisiones, certificados, pagos, emisión/revocación y accesos; comprobar que los operadores originales no cambiaron; procesar un evento operativo en dos sesiones autenticadas. La idempotencia del catálogo y la instalación con cero socios se verifican con SQLite aislado.
- **Resultado esperado:** No aparecen PIN, tokens QR, documentos ni datos clínicos en auditorías/eventos. Dueña y recepción reciben solo los eventos autorizados. Si WebSocket o worker no están activos, marcar Reverb bloqueado; las pruebas automatizadas no sustituyen la comprobación E2E.

### Instalación sin precarga de socios

El catálogo inicial contiene Krav Maga Kids a $30.000 y Kapap Krav Maga, Kickboxing Muay Thai y Funcional a $35.000 mensuales cada una. Las disciplinas existentes se conservan sin cambios, incluso si no tienen precio. Los grupos y horarios de los escenarios anteriores no se precargan; configurarlos desde Membresías según el calendario autorizado. Registrar cada socio desde Socios y confirmar cada cobro real de forma independiente. Los operadores originales conservan su aprovisionamiento existente.
