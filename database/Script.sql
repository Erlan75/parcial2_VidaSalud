-- ============================================================================
-- PROYECTO: SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS (CLÍNICA VIDA SALUD)
-- MOTOR: PostgreSQL 13+
-- ARQUITECTURA: Smart-DB (3FN)
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ----------------------------------------------------------------------------
-- LIMPIEZA TOTAL EN CASCADA
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS password_resets CASCADE;
DROP TABLE IF EXISTS cancelaciones_cita CASCADE;
DROP TABLE IF EXISTS citas CASCADE;
DROP TABLE IF EXISTS bloqueos_agenda CASCADE;
DROP TABLE IF EXISTS horarios_atencion CASCADE;
DROP TABLE IF EXISTS medico_especialidad CASCADE;
DROP TABLE IF EXISTS medicos CASCADE;
DROP TABLE IF EXISTS especialidades CASCADE;
DROP TABLE IF EXISTS contactos_emergencia CASCADE;
DROP TABLE IF EXISTS fichas_medicas CASCADE;
DROP TABLE IF EXISTS pacientes CASCADE;
DROP TABLE IF EXISTS auditorias CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

DROP SEQUENCE IF EXISTS seq_cod_paciente;
DROP SEQUENCE IF EXISTS seq_cod_medico;
DROP SEQUENCE IF EXISTS seq_cod_cita;

-- ----------------------------------------------------------------------------
-- SECUENCIAS DEDICADAS PARA CÓDIGOS CORRELATIVOS (CERO CONDICIONES DE CARRERA)
-- ----------------------------------------------------------------------------
CREATE SEQUENCE seq_cod_paciente START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_cod_medico START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_cod_cita START WITH 1 INCREMENT BY 1;

-- ----------------------------------------------------------------------------
-- 1. TABLA: roles (RF01)
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id_rol SERIAL PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion VARCHAR(150),
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    CONSTRAINT chk_roles_nombre CHECK (nombre IN ('Administrador', 'Recepcionista', 'Médico', 'Paciente')),
    CONSTRAINT chk_roles_estado CHECK (estado IN ('Activo', 'Inactivo'))
);

-- ----------------------------------------------------------------------------
-- 2. TABLA: usuarios (RF01, CU03)
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario BIGSERIAL PRIMARY KEY,
    id_rol INTEGER NOT NULL,
    nombre_completo VARCHAR(160) NOT NULL,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    correo_electronico VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    ultimo_acceso TIMESTAMP WITH TIME ZONE,
    fecha_creacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) 
        REFERENCES roles (id_rol) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_usuarios_estado CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT chk_usuarios_correo CHECK (correo_electronico ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$'),
    CONSTRAINT chk_usuarios_pwd_hash CHECK (LENGTH(password_hash) >= 60)
);

-- ----------------------------------------------------------------------------
-- 3. TABLA: pacientes (RF02, RN01)
-- ----------------------------------------------------------------------------
CREATE TABLE pacientes (
    id_paciente BIGSERIAL PRIMARY KEY,
    id_usuario BIGINT UNIQUE,
    codigo_paciente VARCHAR(20) NOT NULL UNIQUE,
    ci VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo CHAR(1) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    correo VARCHAR(100),
    direccion VARCHAR(200),
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    fecha_registro TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pacientes_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_pacientes_sexo CHECK (sexo IN ('M', 'F', 'O')),
    CONSTRAINT chk_pacientes_estado CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT chk_pacientes_fnac CHECK (fecha_nacimiento <= CURRENT_DATE)
);

-- ----------------------------------------------------------------------------
-- 4. TABLA: fichas_medicas (Sección 3.4 Perfil Clínico Preventivo)
-- ----------------------------------------------------------------------------
CREATE TABLE fichas_medicas (
    id_ficha BIGSERIAL PRIMARY KEY,
    id_paciente BIGINT NOT NULL UNIQUE,
    tipo_sangre VARCHAR(15) NOT NULL DEFAULT 'Desconocido',
    alergias TEXT DEFAULT 'Ninguna conocida',
    enfermedades_cronicas TEXT DEFAULT 'Ninguna reportada',
    medicacion_actual TEXT DEFAULT 'Ninguna',
    observaciones TEXT,
    fecha_actualizacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fichas_paciente FOREIGN KEY (id_paciente) 
        REFERENCES pacientes (id_paciente) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_fichas_sangre CHECK (tipo_sangre IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Desconocido'))
);

-- ----------------------------------------------------------------------------
-- 5. TABLA: contactos_emergencia (Sección 3.4 Contacto de Auxilio)
-- ----------------------------------------------------------------------------
CREATE TABLE contactos_emergencia (
    id_contacto BIGSERIAL PRIMARY KEY,
    id_paciente BIGINT NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    parentesco VARCHAR(40) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    CONSTRAINT fk_contactos_paciente FOREIGN KEY (id_paciente) 
        REFERENCES pacientes (id_paciente) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ----------------------------------------------------------------------------
-- 6. TABLA: especialidades (RF04)
-- ----------------------------------------------------------------------------
CREATE TABLE especialidades (
    id_especialidad SERIAL PRIMARY KEY,
    codigo_especialidad VARCHAR(15) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion TEXT,
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    CONSTRAINT chk_especialidades_estado CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT chk_especialidades_nombre CHECK (LENGTH(TRIM(nombre)) >= 3)
);

-- ----------------------------------------------------------------------------
-- 7. TABLA: medicos (RF03, RN02)
-- ----------------------------------------------------------------------------
CREATE TABLE medicos (
    id_medico BIGSERIAL PRIMARY KEY,
    id_usuario BIGINT NOT NULL UNIQUE,
    codigo_medico VARCHAR(20) NOT NULL UNIQUE,
    registro_profesional VARCHAR(30) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    fecha_registro TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_medicos_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_medicos_estado CHECK (estado IN ('Activo', 'Inactivo'))
);

-- ----------------------------------------------------------------------------
-- 8. TABLA: medico_especialidad (Relación N:M Médicos <-> Especialidades)
-- ----------------------------------------------------------------------------
CREATE TABLE medico_especialidad (
    id_medico BIGINT NOT NULL,
    id_especialidad INTEGER NOT NULL,
    fecha_asignacion DATE NOT NULL DEFAULT CURRENT_DATE,
    PRIMARY KEY (id_medico, id_especialidad),
    CONSTRAINT fk_me_medico FOREIGN KEY (id_medico) 
        REFERENCES medicos (id_medico) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_me_especialidad FOREIGN KEY (id_especialidad) 
        REFERENCES especialidades (id_especialidad) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ----------------------------------------------------------------------------
-- 9. TABLA: horarios_atencion (RF05, RN05, RN08)
-- ----------------------------------------------------------------------------
CREATE TABLE horarios_atencion (
    id_horario BIGSERIAL PRIMARY KEY,
    id_medico BIGINT NOT NULL,
    dia_semana SMALLINT NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_minutos INTEGER NOT NULL DEFAULT 30,
    estado VARCHAR(10) NOT NULL DEFAULT 'Activo',
    CONSTRAINT fk_horarios_medico FOREIGN KEY (id_medico) 
        REFERENCES medicos (id_medico) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_horarios_dia CHECK (dia_semana BETWEEN 1 AND 6),
    CONSTRAINT chk_horarios_rango CHECK (hora_fin > hora_inicio),
    CONSTRAINT chk_horarios_duracion CHECK (duracion_minutos IN (15, 20, 30, 45, 60)),
    CONSTRAINT chk_horarios_estado CHECK (estado IN ('Activo', 'Inactivo'))
);

-- ----------------------------------------------------------------------------
-- 10. TABLA: citas (RF06, RF07, RF10, RN03, RN06, RN07, RN08, RN10)
-- ----------------------------------------------------------------------------
CREATE TABLE citas (
    id_cita BIGSERIAL PRIMARY KEY,
    codigo_cita VARCHAR(20) NOT NULL UNIQUE,
    id_paciente BIGINT NOT NULL,
    id_medico BIGINT NOT NULL,
    id_especialidad INTEGER NOT NULL,
    id_usuario_registro BIGINT,
    fecha_cita DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo_consulta TEXT,
    estado VARCHAR(15) NOT NULL DEFAULT 'Programada',
    notas_medicas TEXT,
    fecha_registro TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_citas_paciente FOREIGN KEY (id_paciente) 
        REFERENCES pacientes (id_paciente) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_citas_medico FOREIGN KEY (id_medico) 
        REFERENCES medicos (id_medico) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_citas_especialidad FOREIGN KEY (id_especialidad) 
        REFERENCES especialidades (id_especialidad) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_citas_usuario_registro FOREIGN KEY (id_usuario_registro) 
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_citas_rango_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT chk_citas_estado CHECK (estado IN ('Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia'))
);

-- Índice parcial único contra colisiones de turnos activos (RN07, RN09)
CREATE UNIQUE INDEX uq_medico_cita_horario_activo 
ON citas (id_medico, fecha_cita, hora_inicio) 
WHERE estado <> 'Cancelada';

-- ----------------------------------------------------------------------------
-- 11. TABLA: cancelaciones_cita (RF09, RN09, RN10)
-- ----------------------------------------------------------------------------
CREATE TABLE cancelaciones_cita (
    id_cancelacion BIGSERIAL PRIMARY KEY,
    id_cita BIGINT NOT NULL UNIQUE,
    id_usuario BIGINT NOT NULL,
    motivo_cancelacion TEXT NOT NULL,
    fecha_cancelacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cancelaciones_cita FOREIGN KEY (id_cita) 
        REFERENCES citas (id_cita) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cancelaciones_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_cancelacion_motivo CHECK (LENGTH(TRIM(motivo_cancelacion)) >= 5)
);

-- ----------------------------------------------------------------------------
-- 12. TABLA: bloqueos_agenda (RF05 ampliado: Feriados y Ausencias Médicas)
-- ----------------------------------------------------------------------------
CREATE TABLE bloqueos_agenda (
    id_bloqueo BIGSERIAL PRIMARY KEY,
    id_medico BIGINT,
    tipo_bloqueo VARCHAR(25) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    motivo VARCHAR(200) NOT NULL,
    fecha_registro TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bloqueos_medico FOREIGN KEY (id_medico) 
        REFERENCES medicos (id_medico) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_bloqueos_fechas CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT chk_bloqueos_tipo CHECK (tipo_bloqueo IN ('Feriado Institucional', 'Vacaciones', 'Licencia Médica', 'Congreso'))
);

-- ----------------------------------------------------------------------------
-- 13. TABLA: auditorias (RF17, Regla Smart-DB)
-- ----------------------------------------------------------------------------
CREATE TABLE auditorias (
    id_auditoria BIGSERIAL PRIMARY KEY,
    id_usuario BIGINT,
    modulo VARCHAR(50) NOT NULL,
    operacion VARCHAR(20) NOT NULL,
    id_registro_afectado VARCHAR(50) NOT NULL,
    datos_previos JSONB,
    datos_nuevos JSONB,
    ip_origen VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
    fecha_hora TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) 
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_auditoria_operacion CHECK (operacion IN ('LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'CANCEL'))
);

-- ÍNDICES DE RENDIMIENTO (RF11, RF12, RF13)
CREATE INDEX idx_citas_medico_fecha ON citas (id_medico, fecha_cita);
CREATE INDEX idx_citas_paciente ON citas (id_paciente);
CREATE INDEX idx_citas_estado ON citas (estado);
CREATE INDEX idx_pacientes_ci ON pacientes (ci);
CREATE INDEX idx_medicos_registro ON medicos (registro_profesional);
CREATE INDEX idx_horarios_medico_dia ON horarios_atencion (id_medico, dia_semana);
