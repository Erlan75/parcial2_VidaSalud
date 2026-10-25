-- ============================================================================
-- PROCEDIMIENTOS Y FUNCIONES SMART-DB
-- ============================================================================

-- ----------------------------------------------------------------------------
-- fn_obtener_intervalos_disponibles (RF05, RF06)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_obtener_intervalos_disponibles(
    p_id_medico BIGINT,
    p_fecha DATE
)
RETURNS TABLE (
    hora_inicio TIME,
    hora_fin TIME,
    disponible BOOLEAN,
    motivo_no_disponible VARCHAR(50)
) AS $$
DECLARE
    v_dia_semana SMALLINT;
    v_h_inicio TIME;
    v_h_fin TIME;
    v_duracion INTEGER;
    v_cursor_inicio TIME;
    v_cursor_fin TIME;
    v_bloqueo_motivo VARCHAR(200);
BEGIN
    IF NOT EXISTS (SELECT 1 FROM medicos WHERE id_medico = p_id_medico AND estado = 'Activo') THEN
        RAISE EXCEPTION 'El médico no existe o se encuentra inactivo.';
    END IF;

    v_dia_semana := EXTRACT(ISODOW FROM p_fecha);

    SELECT motivo INTO v_bloqueo_motivo
      FROM bloqueos_agenda
     WHERE (id_medico IS NULL OR id_medico = p_id_medico)
       AND p_fecha BETWEEN fecha_inicio AND fecha_fin
     LIMIT 1;

    IF v_bloqueo_motivo IS NOT NULL THEN
        RETURN;
    END IF;

    SELECT h.hora_inicio, h.hora_fin, h.duracion_minutos
      INTO v_h_inicio, v_h_fin, v_duracion
      FROM horarios_atencion h
     WHERE h.id_medico = p_id_medico
       AND h.dia_semana = v_dia_semana
       AND h.estado = 'Activo';

    IF NOT FOUND THEN
        RETURN;
    END IF;

    v_cursor_inicio := v_h_inicio;
    WHILE v_cursor_inicio + (v_duracion || ' minutes')::interval <= v_h_fin LOOP
        v_cursor_fin := v_cursor_inicio + (v_duracion || ' minutes')::interval;

        hora_inicio := v_cursor_inicio;
        hora_fin := v_cursor_fin;

        IF (p_fecha < CURRENT_DATE) OR (p_fecha = CURRENT_DATE AND v_cursor_inicio <= CURRENT_TIME) THEN
            disponible := FALSE;
            motivo_no_disponible := 'Horario Pasado';
        ELSIF EXISTS (
            SELECT 1 FROM citas c
             WHERE c.id_medico = p_id_medico
               AND c.fecha_cita = p_fecha
               AND c.estado <> 'Cancelada'
               AND (v_cursor_inicio < c.hora_fin AND v_cursor_fin > c.hora_inicio)
        ) THEN
            disponible := FALSE;
            motivo_no_disponible := 'Ocupado';
        ELSE
            disponible := TRUE;
            motivo_no_disponible := 'Disponible';
        END IF;

        RETURN NEXT;
        v_cursor_inicio := v_cursor_fin;
    END LOOP;
END;
$$ LANGUAGE plpgsql;

-- ----------------------------------------------------------------------------
-- sp_crear_usuario (Actualizado con nombre_completo y telefono)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_crear_usuario(
    IN p_id_rol INTEGER,
    IN p_nombre_completo VARCHAR(160),
    IN p_nombre_usuario VARCHAR(50),
    IN p_correo VARCHAR(100),
    IN p_telefono VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_id_admin_ejecutor BIGINT,
    IN p_ip_origen VARCHAR(45),
    OUT p_id_usuario BIGINT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM usuarios WHERE nombre_usuario = TRIM(p_nombre_usuario)) THEN
        RAISE EXCEPTION 'El nombre de usuario % ya se encuentra en uso.', p_nombre_usuario;
    END IF;

    IF EXISTS (SELECT 1 FROM usuarios WHERE correo_electronico = TRIM(p_correo)) THEN
        RAISE EXCEPTION 'El correo electrónico % ya se encuentra registrado.', p_correo;
    END IF;

    INSERT INTO usuarios (
        id_rol, nombre_completo, nombre_usuario, correo_electronico, telefono, password_hash, estado
    ) VALUES (
        p_id_rol, TRIM(p_nombre_completo), TRIM(p_nombre_usuario), TRIM(p_correo), TRIM(p_telefono), p_password_hash, 'Activo'
    ) RETURNING id_usuario INTO p_id_usuario;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
    VALUES (p_id_admin_ejecutor, 'Seguridad', 'INSERT', p_id_usuario::TEXT,
            json_build_object('nombre', p_nombre_completo, 'username', p_nombre_usuario, 'correo', p_correo, 'id_rol', p_id_rol)::jsonb,
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_registrar_paciente_completo
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_registrar_paciente_completo(
    IN p_id_usuario BIGINT,
    IN p_ci VARCHAR(20),
    IN p_nombres VARCHAR(80),
    IN p_apellidos VARCHAR(80),
    IN p_fecha_nacimiento DATE,
    IN p_sexo CHAR(1),
    IN p_telefono VARCHAR(20),
    IN p_correo VARCHAR(100),
    IN p_direccion VARCHAR(200),
    IN p_tipo_sangre VARCHAR(15),
    IN p_alergias TEXT,
    IN p_enfermedades_cronicas TEXT,
    IN p_medicacion_actual TEXT,
    IN p_contacto_nombre VARCHAR(120),
    IN p_contacto_parentesco VARCHAR(40),
    IN p_contacto_telefono VARCHAR(20),
    IN p_ip_origen VARCHAR(45),
    OUT p_id_paciente BIGINT,
    OUT p_codigo_paciente VARCHAR(20)
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM pacientes WHERE ci = TRIM(p_ci)) THEN
        RAISE EXCEPTION 'RN01: Ya existe un paciente registrado con el CI %.', p_ci;
    END IF;

    INSERT INTO pacientes (
        id_usuario, codigo_paciente, ci, nombres, apellidos,
        fecha_nacimiento, sexo, telefono, correo, direccion, estado
    ) VALUES (
        p_id_usuario, '', TRIM(p_ci), TRIM(p_nombres), TRIM(p_apellidos),
        p_fecha_nacimiento, UPPER(p_sexo), TRIM(p_telefono), TRIM(p_correo), TRIM(p_direccion), 'Activo'
    ) RETURNING id_paciente, codigo_paciente INTO p_id_paciente, p_codigo_paciente;

    INSERT INTO fichas_medicas (
        id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicacion_actual
    ) VALUES (
        p_id_paciente, COALESCE(NULLIF(TRIM(p_tipo_sangre), ''), 'Desconocido'), 
        COALESCE(NULLIF(TRIM(p_alergias), ''), 'Ninguna conocida'),
        COALESCE(NULLIF(TRIM(p_enfermedades_cronicas), ''), 'Ninguna reportada'),
        COALESCE(NULLIF(TRIM(p_medicacion_actual), ''), 'Ninguna')
    );

    IF p_contacto_nombre IS NOT NULL AND TRIM(p_contacto_nombre) <> '' THEN
        INSERT INTO contactos_emergencia (
            id_paciente, nombre_completo, parentesco, telefono
        ) VALUES (
            p_id_paciente, TRIM(p_contacto_nombre), TRIM(p_contacto_parentesco), TRIM(p_contacto_telefono)
        );
    END IF;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
    VALUES (p_id_usuario, 'Pacientes', 'INSERT', p_id_paciente::TEXT, 
            json_build_object('ci', p_ci, 'codigo', p_codigo_paciente, 'nombres', p_nombres, 'apellidos', p_apellidos)::jsonb, 
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_registrar_medico_completo
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_registrar_medico_completo(
    IN p_id_usuario BIGINT,
    IN p_registro_profesional VARCHAR(30),
    IN p_nombres VARCHAR(80),
    IN p_apellidos VARCHAR(80),
    IN p_telefono VARCHAR(20),
    IN p_correo VARCHAR(100),
    IN p_especialidades_ids INTEGER[],
    IN p_ip_origen VARCHAR(45),
    OUT p_id_medico BIGINT,
    OUT p_codigo_medico VARCHAR(20)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_esp_id INTEGER;
BEGIN
    IF EXISTS (SELECT 1 FROM medicos WHERE registro_profesional = TRIM(p_registro_profesional)) THEN
        RAISE EXCEPTION 'RN02: Ya existe un médico registrado con el número profesional %.', p_registro_profesional;
    END IF;

    IF array_length(p_especialidades_ids, 1) IS NULL THEN
        RAISE EXCEPTION 'Debe asignar al menos una especialidad válida al médico.';
    END IF;

    INSERT INTO medicos (
        id_usuario, codigo_medico, registro_profesional, nombres, apellidos, telefono, correo, estado
    ) VALUES (
        p_id_usuario, '', TRIM(p_registro_profesional), TRIM(p_nombres), TRIM(p_apellidos), 
        TRIM(p_telefono), TRIM(p_correo), 'Activo'
    ) RETURNING id_medico, codigo_medico INTO p_id_medico, p_codigo_medico;

    FOREACH v_esp_id IN ARRAY p_especialidades_ids LOOP
        IF EXISTS (SELECT 1 FROM especialidades WHERE id_especialidad = v_esp_id AND estado = 'Activo') THEN
            INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion)
            VALUES (p_id_medico, v_esp_id, CURRENT_DATE);
        END IF;
    END LOOP;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
    VALUES (p_id_usuario, 'Médicos', 'INSERT', p_id_medico::TEXT, 
            json_build_object('codigo', p_codigo_medico, 'registro', p_registro_profesional, 'especialidades', p_especialidades_ids)::jsonb, 
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_asignar_horario_medico
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_asignar_horario_medico(
    IN p_id_medico BIGINT,
    IN p_id_usuario_admin BIGINT,
    IN p_dia_semana SMALLINT,
    IN p_hora_inicio TIME,
    IN p_hora_fin TIME,
    IN p_duracion_minutos INTEGER,
    IN p_ip_origen VARCHAR(45),
    OUT p_id_horario BIGINT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_hora_fin <= p_hora_inicio THEN
        RAISE EXCEPTION 'La hora de finalización (%) debe ser posterior a la de inicio (%).', p_hora_fin, p_hora_inicio;
    END IF;

    INSERT INTO horarios_atencion (
        id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, estado
    ) VALUES (
        p_id_medico, p_dia_semana, p_hora_inicio, p_hora_fin, COALESCE(p_duracion_minutos, 30), 'Activo'
    ) RETURNING id_horario INTO p_id_horario;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
    VALUES (p_id_usuario_admin, 'Horarios Médicos', 'INSERT', p_id_horario::TEXT,
            json_build_object('id_medico', p_id_medico, 'dia', p_dia_semana, 'inicio', p_hora_inicio, 'fin', p_hora_fin)::jsonb,
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_programar_cita
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_programar_cita(
    IN p_id_paciente BIGINT,
    IN p_id_medico BIGINT,
    IN p_id_especialidad INTEGER,
    IN p_id_usuario_registro BIGINT,
    IN p_fecha_cita DATE,
    IN p_hora_inicio TIME,
    IN p_motivo_consulta TEXT,
    IN p_ip_origen VARCHAR(45),
    OUT p_id_cita BIGINT,
    OUT p_codigo_cita VARCHAR(20)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_duracion INTEGER;
    v_hora_fin TIME;
    v_dia_semana SMALLINT;
BEGIN
    v_dia_semana := EXTRACT(ISODOW FROM p_fecha_cita);
    SELECT duracion_minutos INTO v_duracion
      FROM horarios_atencion
     WHERE id_medico = p_id_medico
       AND dia_semana = v_dia_semana
       AND estado = 'Activo';

    v_duracion := COALESCE(v_duracion, 30);
    v_hora_fin := p_hora_inicio + (v_duracion || ' minutes')::interval;

    INSERT INTO citas (
        codigo_cita, id_paciente, id_medico, id_especialidad, id_usuario_registro,
        fecha_cita, hora_inicio, hora_fin, motivo_consulta, estado
    ) VALUES (
        '', p_id_paciente, p_id_medico, p_id_especialidad, p_id_usuario_registro,
        p_fecha_cita, p_hora_inicio, v_hora_fin, TRIM(p_motivo_consulta), 'Programada'
    ) RETURNING id_cita, codigo_cita INTO p_id_cita, p_codigo_cita;
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_reprogramar_cita
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_reprogramar_cita(
    IN p_id_cita BIGINT,
    IN p_id_usuario BIGINT,
    IN p_nueva_fecha DATE,
    IN p_nueva_hora_inicio TIME,
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_id_medico BIGINT;
    v_duracion INTEGER;
    v_nueva_hora_fin TIME;
    v_dia_semana SMALLINT;
    v_estado_actual VARCHAR(15);
BEGIN
    SELECT estado, id_medico INTO v_estado_actual, v_id_medico 
      FROM citas 
     WHERE id_cita = p_id_cita;

    IF v_estado_actual IS NULL THEN
        RAISE EXCEPTION 'La cita médica especificada no existe.';
    END IF;

    IF v_estado_actual IN ('Atendida', 'Cancelada') THEN
        RAISE EXCEPTION 'RF08: Una cita en estado % no puede ser reprogramada.', v_estado_actual;
    END IF;

    v_dia_semana := EXTRACT(ISODOW FROM p_nueva_fecha);
    SELECT duracion_minutos INTO v_duracion
      FROM horarios_atencion
     WHERE id_medico = v_id_medico AND dia_semana = v_dia_semana AND estado = 'Activo';

    v_duracion := COALESCE(v_duracion, 30);
    v_nueva_hora_fin := p_nueva_hora_inicio + (v_duracion || ' minutes')::interval;

    UPDATE citas
       SET fecha_cita = p_nueva_fecha,
           hora_inicio = p_nueva_hora_inicio,
           hora_fin = v_nueva_hora_fin,
           id_usuario_registro = p_id_usuario
     WHERE id_cita = p_id_cita;
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_cancelar_cita
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_cancelar_cita(
    IN p_id_cita BIGINT,
    IN p_id_usuario BIGINT,
    IN p_motivo_cancelacion TEXT,
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estado_actual VARCHAR(15);
BEGIN
    IF p_motivo_cancelacion IS NULL OR LENGTH(TRIM(p_motivo_cancelacion)) < 5 THEN
        RAISE EXCEPTION 'Debe proporcionar un motivo de cancelación descriptivo (mínimo 5 caracteres).';
    END IF;

    SELECT estado INTO v_estado_actual FROM citas WHERE id_cita = p_id_cita;

    IF v_estado_actual IS NULL THEN
        RAISE EXCEPTION 'La cita médica no existe.';
    END IF;

    IF v_estado_actual = 'Atendida' THEN
        RAISE EXCEPTION 'RF09: Una cita médica con estado Atendida no puede ser cancelada.';
    END IF;

    IF v_estado_actual = 'Cancelada' THEN
        RAISE EXCEPTION 'La cita ya se encuentra en estado Cancelada.';
    END IF;

    INSERT INTO cancelaciones_cita (
        id_cita, id_usuario, motivo_cancelacion, fecha_cancelacion
    ) VALUES (
        p_id_cita, p_id_usuario, TRIM(p_motivo_cancelacion), CURRENT_TIMESTAMP
    );
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_controlar_asistencia_cita
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_controlar_asistencia_cita(
    IN p_id_cita BIGINT,
    IN p_id_usuario BIGINT,
    IN p_nuevo_estado VARCHAR(15),
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_nuevo_estado NOT IN ('Confirmada', 'Inasistencia') THEN
        RAISE EXCEPTION 'El estado indicado (%) no es válido para control de asistencia.', p_nuevo_estado;
    END IF;

    UPDATE citas
       SET estado = p_nuevo_estado,
           id_usuario_registro = p_id_usuario
     WHERE id_cita = p_id_cita;
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_registrar_atencion_medica
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_registrar_atencion_medica(
    IN p_id_cita BIGINT,
    IN p_id_medico BIGINT,
    IN p_id_usuario_medico BIGINT,
    IN p_notas_medicas TEXT,
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_cita_medico BIGINT;
    v_estado_actual VARCHAR(15);
BEGIN
    SELECT id_medico, estado INTO v_cita_medico, v_estado_actual
      FROM citas
     WHERE id_cita = p_id_cita;

    IF v_cita_medico IS NULL THEN
        RAISE EXCEPTION 'La cita médica especificada no existe.';
    END IF;

    IF v_cita_medico <> p_id_medico THEN
        RAISE EXCEPTION 'Seguridad: El médico que intenta cerrar la consulta no coincide con el asignado a la cita.';
    END IF;

    IF v_estado_actual NOT IN ('Programada', 'Confirmada') THEN
        RAISE EXCEPTION 'Solo se pueden marcar como atendidas aquellas citas en estado Programada o Confirmada.';
    END IF;

    UPDATE citas
       SET estado = 'Atendida',
           notas_medicas = TRIM(p_notas_medicas),
           id_usuario_registro = p_id_usuario_medico
     WHERE id_cita = p_id_cita;
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_conmutar_estado_usuario
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_conmutar_estado_usuario(
    IN p_id_usuario_objetivo BIGINT,
    IN p_id_admin_ejecutor BIGINT,
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estado_actual VARCHAR(10);
    v_nuevo_estado VARCHAR(10);
BEGIN
    IF p_id_usuario_objetivo = p_id_admin_ejecutor THEN
        RAISE EXCEPTION 'Un administrador no puede suspender o desactivar su propia cuenta activa.';
    END IF;

    SELECT estado INTO v_estado_actual FROM usuarios WHERE id_usuario = p_id_usuario_objetivo;

    IF v_estado_actual IS NULL THEN
        RAISE EXCEPTION 'El usuario indicado no existe.';
    END IF;

    v_nuevo_estado := CASE WHEN v_estado_actual = 'Activo' THEN 'Inactivo' ELSE 'Activo' END;

    UPDATE usuarios SET estado = v_nuevo_estado WHERE id_usuario = p_id_usuario_objetivo;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
    VALUES (p_id_admin_ejecutor, 'Seguridad', 'UPDATE', p_id_usuario_objetivo::TEXT,
            json_build_object('estado', v_estado_actual)::jsonb,
            json_build_object('estado', v_nuevo_estado)::jsonb,
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;

-- ----------------------------------------------------------------------------
-- sp_registrar_bloqueo_agenda
-- ----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_registrar_bloqueo_agenda(
    IN p_id_medico BIGINT,
    IN p_id_usuario_admin BIGINT,
    IN p_tipo_bloqueo VARCHAR(25),
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE,
    IN p_motivo VARCHAR(200),
    IN p_ip_origen VARCHAR(45),
    OUT p_id_bloqueo BIGINT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_fecha_fin < p_fecha_inicio THEN
        RAISE EXCEPTION 'La fecha de finalización no puede ser anterior a la de inicio.';
    END IF;

    IF p_fecha_inicio < CURRENT_DATE THEN
        RAISE EXCEPTION 'No se pueden registrar bloqueos en fechas pasadas.';
    END IF;

    INSERT INTO bloqueos_agenda (
        id_medico, tipo_bloqueo, fecha_inicio, fecha_fin, motivo
    ) VALUES (
        p_id_medico, p_tipo_bloqueo, p_fecha_inicio, p_fecha_fin, TRIM(p_motivo)
    ) RETURNING id_bloqueo INTO p_id_bloqueo;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
    VALUES (p_id_usuario_admin, 'Bloqueos Agenda', 'INSERT', p_id_bloqueo::TEXT,
            json_build_object('medico', p_id_medico, 'tipo', p_tipo_bloqueo, 'inicio', p_fecha_inicio, 'fin', p_fecha_fin)::jsonb,
            COALESCE(p_ip_origen, '127.0.0.1'));
END;
$$;
