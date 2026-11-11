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

### GF-09 — Inscripción pendiente
- **Módulo:** Inscripciones y control de acceso
- **Actor:** Socio y dueña
- **Precondiciones:** Socio con solicitud de inscripción pendiente y horario actual.
- **Pasos:** Intentar ingresar antes de la aprobación; luego aprobar la inscripción como dueña y repetir con cobertura vigente.
- **Resultado esperado:** Antes de aprobar, el acceso se deniega por inscripción pendiente. Solo tras aprobación y cumplimiento del resto de requisitos puede autorizarse.

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
- **Pasos:** Abrir `/terminal`; ingresar el PIN.
- **Resultado esperado:** Terminal Fénix muestra autorización y actividad correspondiente; se crea una única permanencia abierta.

### GF-13 — Acceso por QR móvil
- **Módulo:** Credenciales QR
- **Actor:** Socio
- **Precondiciones:** Socio con QR activo y requisitos de acceso cumplidos; teléfono con cámara y red local accesible.
- **Pasos:** Abrir el QR móvil desde la ficha autorizada; escanearlo con el dispositivo o lector previsto.
- **Resultado esperado:** El QR abre el flujo de escaneo registrado y permite autorizar solo bajo las mismas reglas que el PIN; token no se filtra en auditoría ni en mensajes públicos.

### GF-14 — Salida y cierre de permanencia
- **Módulo:** Control de acceso y asistencias
- **Actor:** Socio
- **Precondiciones:** Socio con ingreso autorizado y permanencia abierta.
- **Pasos:** Presentar nuevamente la credencial para registrar la salida; revisar el historial.
- **Resultado esperado:** Se cierra la permanencia con fecha, hora y método de salida correctos; no queda otra permanencia abierta duplicada.

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
- **Precondiciones:** Laravel y Reverb activos; abrir dashboard en dos sesiones/navegadores autenticados según rol.
- **Pasos:** Generar un evento operativo de prueba; observar ambos paneles y la conexión WebSocket en las herramientas de desarrollo del navegador.
- **Resultado esperado:** La conexión se establece y las sesiones autorizadas reciben/actualizan los datos permitidos. No se filtran datos sensibles entre roles.

### GF-22 — Navbar, marca y responsive
- **Módulo:** Interfaz
- **Actor:** Dueña y recepción
- **Precondiciones:** Sesión iniciada; Chrome con viewport de escritorio, tablet y móvil.
- **Pasos:** Revisar el navbar a 1440 px, 1024 px, 768 px y 390 px; abrir/cerrar menú responsive; recorrer todos los enlaces; revisar login, backoffice, terminal, ficha y credencial QR.
- **Resultado esperado:** Todos los ítems, incluida Auditoría, quedan dentro del contenedor blanco; no hay desbordamiento horizontal; el menú móvil es utilizable. Backoffice muestra identidad dual GymControl + Fénix, terminal/credenciales priorizan el logo Fénix, y naranja/negro/blanco son la paleta principal con dorado como acento legible.
