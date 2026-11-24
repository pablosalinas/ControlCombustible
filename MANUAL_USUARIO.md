# Manual Completo de Funcionamiento — Control de Combustible y Flota

> **Aplicación Web:** `https://www.moratalla-murcia.com/combustible/`  
> **Aplicación Android:** App *Control de Combustible* (APK distribuida mediante GitHub Releases)

---

## 1. Introducción y Propósito del Sistema

El sistema **Control de Combustible y Flota** centraliza la gestión integral de los vehículos municipales/corporativos y remolques, permitiendo:
- Registrar repostajes con trazabilidad fotográfica (Ticket/Factura, Surtidor y Odómetro) y lectura automática OCR.
- Controlar consumos medios (`L/100km`), costes por kilómetro y gasto acumulado.
- Gestionar el mantenimiento preventivo (ITV, seguros, cambios de aceite, revisiones) mediante alertas automáticas por fecha y por kilómetros.
- Supervisar parques móviles, talleres, aseguradoras y estaciones de servicio.
- Emitir informes en PDF, enviar alertas por correo electrónico y auditar o recuperar registros eliminados.

---

## 2. Acceso, Autenticación y Seguridad de Sesión

### 2.1. Doble vía de acceso en Web
1. **Acceso integrado desde administración de `moratalla-murcia.com`:** si ya iniciaste sesión como administrador en la web principal, la sesión PHP (`$_SESSION['admin_id']`) se comparte automáticamente en modo solo lectura con la tabla `users`, entrando directo al panel sin volver a introducir credenciales.
2. **Login directo en `/combustible`:** mediante usuario/email y contraseña propios del sistema (también disponible para usuarios registrados desde la App Android tras su aprobación).

### 2.2. Acceso en la App Android y Biometría
- **Inicio de sesión:** con usuario/email y contraseña. Si el dispositivo dispone de huella o biometría, puedes activar el desbloqueo biométrico para próximos accesos.
- **Registro de nuevos usuarios:** desde la pantalla de login de la App puedes solicitar el alta de una cuenta nueva. La cuenta queda en estado **pendiente** hasta que un superusuario (`is_admin`) la apruebe desde el botón de usuarios pendientes.
- **Actualizaciones y Log de Mejoras:** antes de descargar una actualización de la App (tanto en el aviso automático del Login como en *Versión y Actualización* del menú), la App consulta GitHub Releases y muestra en pantalla el **historial acumulado de mejoras** de todas las versiones posteriores a la instalada.

### 2.3. Cierre automático por inactividad
- **Por qué existe:** evita que sesiones abiertas en ordenadores compartidos o móviles desatendidos queden expuestas.
- **Cómo funciona:** si no se detecta actividad (clics, teclado, scroll o toque) durante el tiempo configurado en **Parámetros** (por defecto **30 minutos en Web** y **60 minutos en App**), la sesión se cierra automáticamente y solicita volver a iniciar sesión.

---

## 3. Manual de la Aplicación Web (`index.php`)

### 3.1. Barra Superior (Cabecera) y Selectores Globales
En la parte superior de la pantalla dispones de controles rápidos siempre accesibles:

1. **Selector de Ámbito (`Todos` / `Vehículos` / `Remolques`)**
   - **Por qué existe:** permite separar los costes y registros de vehículos motorizados de los remolques (que no consumen combustible propio pero sí tienen ITV, seguro y mantenimientos).
   - **Cómo proceder:** selecciona el ámbito deseado; todas las tarjetas KPI, gráficas, listados y exportaciones se recalculan al instante.
2. **Filtro por Vehículo específico**
   - **Por qué existe:** analizar el historial individual de un único vehículo o remolque.
   - **Cómo proceder:** elige una matrícula/modelo en el desplegable para centrar toda la vista en esa unidad.
3. **Botón `Estaciones`**
   - **Por qué existe:** mantener un catálogo limpio de gasolineras con sus datos fiscales (CIF, dirección, código postal, teléfono), facilitando que el OCR reconozca automáticamente la estación en futuros tickets.
   - **Cómo proceder:** pulsa `Estaciones` para crear, editar o fusionar estaciones de servicio.
4. **Botón `Talleres / Seguros`**
   - **Por qué existe:** centralizar los datos de contacto de talleres mecánicos, compañías aseguradoras y estaciones ITV para asociarlos a vehículos y tareas.
   - **Cómo proceder:** abre el modal y gestiona el directorio de proveedores (añadir, editar o dar de baja lógica).
5. **Botón `Parques`**
   - **Por qué existe:** organizar la flota por áreas o parques móviles (ej. Obras, Policía, Jardinería).
   - **Cómo proceder:** crea o edita los parques disponibles para poder asignarlos a cada vehículo.
6. **Botón `Parámetros` *(Solo Superusuario)***
   - **Por qué existe:** adaptar los tiempos de seguridad y márgenes de alerta sin tocar código.
   - **Cómo proceder:** visible únicamente para usuarios con rol `is_admin`. Permite configurar:
     - **Inactividad Web (min):** tiempo de inactividad antes del cierre automático en Web (ej. `30`).
     - **Inactividad App (min):** tiempo de inactividad antes del cierre automático en la App Android (ej. `60`).
     - **Días antes aviso tareas:** días de antelación con los que una tarea por fecha entra en estado de alerta (ej. `15`).
     - **Km margen aviso tareas:** kilómetros de antelación con los que una tarea por kilometraje entra en alerta (ej. `1000`).
     - *Nota sobre tareas mixtas (`ambos`):* cuando una tarea tiene vencimiento por fecha y por kilómetros, la alerta se dispara **cuando se cumpla cualquiera de las dos condiciones primero**.
7. **Botón `Usuarios Pendientes` *(Solo Superusuario)***
   - **Por qué existe:** controlar quién obtiene acceso al sistema desde la App.
   - **Cómo proceder:** revisa las solicitudes pendientes y pulsa **Aprobar** (asignando si corresponde permisos) o **Rechazar**.
8. **Botón `Papelera`**
   - **Por qué existe:** protección ante borrados accidentales (el sistema aplica borrado lógico `activo = 0`).
   - **Cómo proceder:** abre la papelera para consultar repostajes o vehículos eliminados y pulsar **Restaurar** si deseas recuperarlos.
9. **Botón `Importar CSV / Excel`**
   - **Por qué existe:** cargar de golpe históricos de repostajes o flotas existentes.
   - **Cómo proceder:** descarga o prepara el CSV con las columnas esperadas, súbelo desde el modal, revisa la previsualización y confirma la importación.
10. **Botón `Informe PDF` / `Enviar Email`**
    - **Por qué existe:** generar partes imprimibles para intervención/alcaldía o enviar resúmenes de alertas y consumos por correo.
    - **Cómo proceder:** aplica los filtros deseados (fechas, vehículo, ámbito) y pulsa generar PDF o enviar informe por email.
11. **Botón `+ Nuevo Repostaje` y `+ Nuevo Vehículo`**
    - Accesos directos principales para el trabajo diario.
12. **Botón `Cerrar Sesión`**
    - Cierra manualmente la sesión actual de forma segura.

### 3.2. Tarjetas de Resumen (KPIs) y Gráficas
- **KPIs superiores:** muestran en tiempo real total de litros repostados, importe total (`€`), kilómetros recorridos, consumo medio (`L/100km`) y número de alertas de mantenimiento activas.
- **Gráficas interactivas:** permiten visualizar la evolución mensual del gasto y comparativas de consumo entre vehículos.

### 3.3. Registro de Repostajes y Sistema OCR Inteligente (Web)
- **Por qué existe:** reducir errores manuales al transcribir tickets y conservar prueba visual (3 fotos) de cada repostaje.
- **Cómo proceder al pulsar `+ Nuevo Repostaje`:**
  1. **Sube las fotos (opcional pero recomendado):**
     - **Foto Factura / Ticket:** extrae estación de servicio, fecha, litros, precio/litro e importe total.
     - **Foto Surtidor:** extrae litros, precio/litro e importe total directamente de la pantalla del surtidor.
     - **Foto Odómetro:** extrae los kilómetros actuales del cuadro del vehículo.
  2. **Indicador de fiabilidad OCR:** si el sistema no supera el umbral de confianza (**95%**), muestra un aviso indicando que la lectura no es fiable para que el usuario la revise obligatoriamente.
  3. **Entrenador visual de formato (Plantillas OCR):** si un ticket o marcador tiene un formato nuevo o algún campo no se detectó bien, pulsa en el botón de mapeo/entrenamiento visual sobre la imagen, selecciona con el ratón la zona exacta de cada campo (*Estación, Fecha, Litros, Precio, Total o Kilómetros*) y guarda la plantilla. En futuros tickets del mismo formato/estación, el sistema aplicará esas zonas automáticamente.
  4. **Validación y guardado:** selecciona el vehículo, comprueba que los kilómetros son coherentes con la lectura anterior (el sistema te muestra el último kilometraje registrado), ajusta la estación si es necesario y pulsa **Guardar**.

### 3.4. Gestión de Vehículos y Tareas de Mantenimiento
- **Listado de Vehículos:** muestra matrícula, marca/modelo, tipo (vehículo o remolque), parque asignado, kilómetros actuales y badges de alertas pendientes.
- **Ficha / Tareas de un Vehículo:**
  - **Por qué existe:** evitar sanciones por ITV caducada o averías por retraso en mantenimientos.
  - **Cómo proceder:** entra en las tareas del vehículo, pulsa **+ Nueva Tarea**, elige el tipo (ITV, Seguro, Revisión, Neumáticos, etc.), la periodicidad (`fecha`, `km` o `ambos`), los umbrales de próximo vencimiento y el taller/aseguradora.
  - **Registro de cumplimiento:** cuando realices la revisión o pases la ITV, marca la tarea como completada; el sistema registra el histórico y calcula automáticamente el próximo vencimiento.

---

## 4. Manual de la Aplicación Android (`Control de Combustible`)

La App está diseñada para el trabajo en movilidad (conductores y encargados en gasolinera o taller) con interfaz optimizada para pantalla táctil.

### 4.1. Pantalla Principal (`DashboardScreen`) y Barra Superior
- **Selector de Ámbito / Filtros:** permite alternar entre toda la flota, solo vehículos o solo remolques, y buscar por matrícula o conductor.
- **Resumen superior:** tarjetas rápidas con el estado general y acceso directo a alertas activas.
- **Menú principal (icono `⋮` arriba a la derecha):**
  1. **Registrar Repostaje:** abre el formulario completo con cámara directa para Ticket, Surtidor y Odómetro.
  2. **Vehículos y Tareas:** acceso al listado de vehículos, sus fichas, kilometraje y alertas de mantenimiento.
  3. **Estaciones de Servicio:** consultar, añadir o editar gasolineras.
  4. **Talleres / Seguros / ITV:** directorio de proveedores vinculados a mantenimientos.
  5. **Parques Móviles:** consultar o gestionar los parques de la flota.
  6. **Importar / Exportar / Informes:** generar resúmenes o exportar datos según permisos.
  7. **Papelera:** recuperar registros eliminados lógicamente (si tienes permisos).
  8. **Usuarios Pendientes *(Solo Superusuario)*:** aprobar o denegar nuevas cuentas solicitadas desde la App.
  9. **Parámetros del Sistema *(Solo Superusuario)*:** editar en remoto los minutos de inactividad (Web y App), días de aviso de tareas y km de margen de aviso. Los cambios se aplican inmediatamente tras guardar.
  10. **Versión y Actualización:** muestra la versión instalada, comprueba en GitHub si hay una nueva versión disponible, despliega el **Log de Mejoras acumulado** (con todas las novedades desde tu versión actual hasta la última) y ofrece el botón de descarga del APK.
  11. **Cerrar Sesión:** cierra la sesión en el dispositivo.

### 4.2. Botón Flotante (`+`) y Flujo Rápido de Repostaje en App
1. Pulsa el botón **`+`** en la parte inferior derecha.
2. Selecciona el vehículo.
3. Toma las fotos con la cámara (o elige de la galería):
   - **Ticket / Factura**, **Surtidor** y **Odómetro**.
4. La App envía las imágenes al motor OCR, rellena los campos automáticamente y advierte si la lectura tiene baja fiabilidad (<95%).
5. Si algún dato no coincide o es un formato nuevo de ticket/odómetro/surtidor, usa el **selector táctil de zonas** sobre la foto para marcar manualmente dónde está cada valor y guardar el formato para próximas ocasiones.
6. Revisa los datos y pulsa **Guardar Repostaje**.

### 4.3. Tareas y Alertas desde la App
- Desde la pestaña o sección de **Vehículos**, selecciona cualquier unidad para ver sus **Tareas de Mantenimiento**.
- Los avisos aparecen destacados en color cuando faltan menos días de los configurados en `tarea_dias_aviso` o menos kilómetros de los configurados en `tarea_km_aviso` (en tareas mixtas `ambos`, salta en cuanto se cumple la primera de las dos condiciones).
- Puedes crear nuevas tareas o registrar una tarea como realizada directamente desde el móvil adjuntando observaciones o costes.

---

## 5. Buenas Prácticas Recomendadas
1. **Fotografía nítida y recta:** evita reflejos en la pantalla del surtidor y procura que el ticket esté plano para maximizar la precisión del OCR (>95%).
2. **Entrena formatos nuevos una sola vez:** si repostas habitualmente en una gasolinera nueva cuyo ticket no se lee bien a la primera, dedica 15 segundos a marcar sus zonas en el editor visual; a partir de ahí la lectura será automática.
3. **Actualiza la App cuando veas novedades:** revisa el *Log de Mejoras* antes de pulsar *Descargar* para estar al tanto de las nuevas funciones y correcciones.
4. **No reutilices contraseñas:** utiliza contraseñas robustas y respeta el tiempo de inactividad configurado para proteger los datos de la flota.
