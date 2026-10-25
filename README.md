# Sistema de Información Web para la Gestión de Citas Médicas — Clínica Vida Salud

Sistema clínico integral desarrollado en **PHP 8.2+**, **PostgreSQL (`PDO pdo_pgsql`)**, **Arquitectura Smart-DB** y **MVC Modular orientado a 5 Paquetes UML**, cubriendo al **100% el Ciclo #1 y el Ciclo #2 (17 Casos de Uso: CU01 a CU17)** con interfaz **100% Responsive** (Móvil, Tablet y Escritorio).

---

## 1. Arquitectura Modular por Paquetes UML (Trazabilidad 1 a 1)

El código fuente en `app/Modules/` y `views/` replica exactamente los 5 paquetes del Diagrama de Paquetes UML:

| Paquete UML | Directorio Backend | Directorio Vistas | Casos de Uso Implementados |
| :--- | :--- | :--- | :--- |
| **1. `SeguridadAcceso`** | `app/Modules/SeguridadAcceso/` | `views/SeguridadAcceso/` | **CU01** Iniciar sesión · **CU02** Cerrar sesión segura (`no-store`) · **CU03** Usuarios y Roles RBAC · **CU17** Bitácora de Auditoría JSON · Recuperación SMTP Gmail y Cambio de Contraseña · Migrador Web y Laboratorio de 10 Pruebas |
| **2. `CatalogoMedico`** | `app/Modules/CatalogoMedico/` | `views/CatalogoMedico/` | **CU04** Especialidades médicas · **CU05** Médicos y especialidades múltiples · **CU06** Horarios semanales (bloques de 30 min) · **CU16** Feriados y bloqueos de disponibilidad |
| **3. `PacientesClinica`** | `app/Modules/PacientesClinica/` | `views/PacientesClinica/` | **CU07** Gestión integral de pacientes, ficha médica preventiva (`tipo_sangre`, `alergias`, `enfermedades_cronicas`, `medicacion_actual`) y contacto de emergencia |
| **4. `GestionCitas`** | `app/Modules/GestionCitas/` | `views/GestionCitas/` | **CU08** Programar cita con grilla AJAX anti-solapamiento · **CU09** Reprogramar cita · **CU10** Cancelar cita con motivo obligatorio · **CU11** Control de asistencia y atención médica con diagnóstico · **CU12** Agenda médica diaria · **CU13** Historial clínico por paciente · Validador público de QR |
| **5. `ReportesAnalitica`** | `app/Modules/ReportesAnalitica/` | `views/ReportesAnalitica/` | **CU14** Panel gerencial KPI + Chart.js + 6 Reportes ejecutivos filtrables · **CU15** Exportación PDF membretado, Excel/CSV UTF-8 y Ticket PDF con **Código QR vectorial ISO/IEC 18004** nativo |

---

## 2. Regla Arquitectónica Smart-DB (PostgreSQL PL/pgSQL)

Todas las lecturas emplean consultas parametrizadas `PDO::prepare`, mientras que todas las transacciones críticas delegan obligatoriamente en el motor PostgreSQL:

- `SELECT * FROM fn_obtener_intervalos_disponibles(:id_medico, :fecha)`
- `CALL sp_crear_usuario(...)` y `CALL sp_conmutar_estado_usuario(...)`
- `CALL sp_registrar_paciente_completo(...)` y `CALL sp_actualizar_ficha_medica(...)`
- `CALL sp_registrar_medico_completo(...)` y `CALL sp_asignar_horario_medico(...)`
- `CALL sp_programar_cita(...)`, `CALL sp_reprogramar_cita(...)` y `CALL sp_cancelar_cita(...)`
- `CALL sp_controlar_asistencia_cita(...)` y `CALL sp_registrar_atencion_medica(...)`
- `CALL sp_registrar_bloqueo_agenda(...)`

Cualquier excepción de negocio lanzada por Triggers o Procedimientos (`RAISE EXCEPTION` por `RN01`–`RN11`) es interceptada por `DatabaseExceptionParser` y presentada limpiamente con **SweetAlert2** sin exponer rutas ni credenciales del servidor.

---

## 3. Requisitos Previos

1. **PHP 8.1 o superior** con extensiones habilitadas en `php.ini`:
   - `extension=pdo_pgsql`
   - `extension=pgsql`
   - `extension=openssl`
   - `extension=mbstring`
   *(No requiere `gd` ni librerías externas de Composer: el generador PDF 1.4 y el codificador QR ISO 18004 están construidos 100% en PHP nativo).*
2. **PostgreSQL 13 o superior** en ejecución (ej. puerto `5432`).

---

## 4. Configuración del Archivo `.env` (PostgreSQL y SMTP Gmail)

Edite el archivo `.env` en la raíz de `VidaSalud/` (puede partir de `.env.example`):

```ini
APP_NAME="Clínica Vida Salud"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=America/La_Paz
APP_URL=http://localhost:8000

# Conexión PostgreSQL 13+ (PDO pdo_pgsql)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=parcial2_VidaSalud
DB_USERNAME=postgres
DB_PASSWORD=

# Configuración SMTP Gmail (Recuperación de Contraseña)
MAIL_ENABLED=false
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=tu.correo@gmail.com
MAIL_PASSWORD=xxxx_xxxx_xxxx_xxxx
MAIL_FROM_ADDRESS=tu.correo@gmail.com
MAIL_FROM_NAME="Clínica Vida Salud - Soporte"
```

> **Nota sobre Gmail SMTP**: Para enviar correos reales a Gmail, active la Verificación en 2 Pasos en su cuenta Google, genere una **Contraseña de Aplicación de 16 caracteres**, colóquela en `MAIL_PASSWORD` y cambie `MAIL_ENABLED=true`. Si `MAIL_ENABLED=false` (modo demostración local/offline), el sistema guarda automáticamente una copia HTML completa del correo con el enlace de restablecimiento en `storage/mail_outbox/` y permite abrirlo desde pantalla.

---

## 5. Ejecución de la Migración Automática de Base de Datos

El migrador automático crea la base de datos `parcial2_VidaSalud` si aún no existe y ejecuta en orden estricto:
1. `Script.sql` (13 tablas en 3FN, secuencias y constraints)
2. `Triggers.sql` (8 Triggers PL/pgSQL: correlativos `PAC/MED/CM`, anti-solapamiento `RN07`, estados `RN10`, bloqueo de borrado físico `RN11` y auditoría `RF17`)
3. `PA.sql` (Procedimientos Almacenados `sp_...` y `fn_obtener_intervalos_disponibles`)
4. `poblacion.sql` (Semilla completa: 4 roles, 18 usuarios, 5 especialidades, 8 médicos, 27 horarios, 26 pacientes con fichas médicas y 56 citas)
5. `extensions.sql` (Tabla auxiliar `password_resets` y `sp_actualizar_ficha_medica`)

### Opción A — Por consola CLI (Recomendada):
```powershell
php database/migrate.php
```
*(Para reconstruir desde cero borrando datos previos: `php database/migrate.php --fresh`)*

### Opción B — Desde la Interfaz Web:
Inicie el servidor y abra en el navegador el botón **"Migración Automática BD"** en la pantalla de Login (`?r=sistema/migracion`).

---

## 6. Cómo Levantar el Servidor Local

### Opción 1 — Servidor embebido de PHP:
```powershell
php -S localhost:8000 -t public
```
Luego ingrese a: **`http://localhost:8000`**

### Opción 2 — Apache / XAMPP:
Copie o apunte el directorio `VidaSalud` dentro de `htdocs` y acceda a:
**`http://localhost/VidaSalud/`**

---

## 7. Credenciales Oficiales de Prueba (Semilla `poblacion.sql`)

Todos los usuarios reales precargados en `poblacion.sql` utilizan la contraseña institucional que cumple la política de complejidad: **`Cher_4mi`**

- **Administrador 1**: `"Erlan"` (`erlan.garcia75@gmail.com`) | Clave: `"Cher_4mi"`
- **Administrador 2**: `"Edilson"` (`erickmaturano04@gmail.com`) | Clave: `"Cher_4mi"`
- **Recepcionista**: `"mariana.vaca"` | Clave: `"Cher_4mi"`
- **Médico**: `"juan.perez"` | Clave: `"Cher_4mi"`
- **Paciente**: `"lucas.gutierrez"` | Clave: `"Cher_4mi"`

| Rol RBAC | Usuario | Correo Electrónico | Contraseña | Alcance Principal |
| :--- | :--- | :--- | :--- | :--- |
| **Administrador 1** | `Erlan` | `erlan.garcia75@gmail.com` | `Cher_4mi` | Acceso total (CU01–CU17, Usuarios, Auditoría JSON, Migración, 10 Pruebas) |
| **Administrador 2** | `Edilson` | `erickmaturano04@gmail.com` | `Cher_4mi` | Acceso total (CU01–CU17, Usuarios, Auditoría JSON, Migración, 10 Pruebas) |
| **Recepcionista** | `mariana.vaca` | `m.vaca@vidasalud.com.bo` | `Cher_4mi` | Admisión de pacientes, programación/reprogramación/cancelación, asistencia y reportes |
| **Médico** | `juan.perez` | `jp.perez@vidasalud.com.bo` | `Cher_4mi` | Agenda médica diaria, ficha clínica preventiva y cierre de atención con diagnóstico (CU11) |
| **Paciente** | `lucas.gutierrez` | `lucas.gutierrez@gmail.com` | `Cher_4mi` | Autogestión de reservas (CU08), descarga de Ticket PDF + QR y consulta de historial |

---

## 8. Guía Rápida para Evaluar las 10 Pruebas Mínimas del Examen

Además de probar cada flujo manualmente en sus pantallas, puede ingresar como `Erlan`, `Edilson` o `mariana.vaca` al menú lateral **"10 Pruebas del Examen"** (`?r=sistema/pruebas`) para ejecutar diagnósticos en vivo sobre PostgreSQL:

1. **Login válido e inválido**: Ingrese con `Erlan` (o `erlan.garcia75@gmail.com`) / `Cher_4mi` (redirige al Dashboard y audita `LOGIN`); pruebe clave errónea y verifique rechazo seguro.
2. **Complejidad de contraseña**: En `Usuarios y Roles (CU03)` o `Mi Perfil`, intente ingresar `12345678` o `cher4mi` (bloqueado en JS y PHP); ingrese `Cher_4mi` (aceptado).
3. **Paciente con CI duplicado (`RN01`)**: En `Pacientes y Fichas (CU07)`, registre un paciente con CI existente `5894123` (`PAC-00001`) y verifique la alerta limpia del motor PostgreSQL.
4. **Asignación de horario médico (`CU06`)**: En `Horarios Atención (CU06)`, asigne un turno de bloques de 30 min e intente ingresar `hora_fin <= hora_inicio`.
5. **Cita en bloque disponible (`CU08`)**: En `Programar Cita (CU08)`, elija paciente, especialidad, médico y fecha futura (ej. `2026-10-27`), seleccione un botón verde libre y confirme.
6. **Bloqueo de doble reserva / solapamiento (`RN07`)**: En la misma fecha y médico, observe que el horario recién tomado aparece gris ocupado; si se fuerza vía petición concurrente, el trigger `trg_validar_cita_consistencia_transaccional` aborta con `RN07`.
7. **Reprogramación (`CU09`) y Cancelación (`CU10`)**: En `Control de Citas`, reprograme una cita activa liberando su bloque anterior, o cancélela con motivo obligatorio (>= 5 caracteres).
8. **Consulta de Agenda Diaria (`CU12`) e Historial (`CU13`)**: Revise la agenda cronológica por fecha/médico (ej. `juan.perez`) y abra el expediente histórico completo de un paciente (ej. `lucas.gutierrez` / `PAC-00001`).
9. **Seguridad de sesión al cerrar (`CU02`)**: Cierre sesión desde el modal de confirmación y presione el botón **Atrás** del navegador: las cabeceras `Cache-Control: no-store, no-cache, must-revalidate` obligan a reautenticar.
10. **Diseño Responsive (Móvil / Tablet / Escritorio)**: Abra DevTools (`F12 -> Toggle Device Toolbar`) en `375px` (iPhone), `768px` (iPad) y `1366px` (Desktop) verificando el menú lateral retráctil (Offcanvas) y las tarjetas apiladas sin desbordamiento.
