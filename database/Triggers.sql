-- ============================================================================
-- TRIGGERS Y FUNCIONES DE BLINDAJE (PL/pgSQL)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- TRIGGER 1: Autogeneración Concurrente de Códigos por Secuencia
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_autogenerar_codigo_paciente()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.codigo_paciente IS NULL OR TRIM(NEW.codigo_paciente) = '' THEN
        NEW.codigo_paciente := 'PAC-' || LPAD(nextval('seq_cod_paciente')::TEXT, 5, '0');
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_autogenerar_codigo_paciente
BEFORE INSERT ON pacientes
FOR EACH ROW EXECUTE FUNCTION fn_autogenerar_codigo_paciente();

CREATE OR REPLACE FUNCTION fn_autogenerar_codigo_medico()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.codigo_medico IS NULL OR TRIM(NEW.codigo_medico) = '' THEN
        NEW.codigo_medico := 'MED-' || LPAD(nextval('seq_cod_medico')::TEXT, 4, '0');
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_autogenerar_codigo_medico
BEFORE INSERT ON medicos
FOR EACH ROW EXECUTE FUNCTION fn_autogenerar_codigo_medico();

CREATE OR REPLACE FUNCTION fn_autogenerar_codigo_cita()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.codigo_cita IS NULL OR TRIM(NEW.codigo_cita) = '' THEN
        NEW.codigo_cita := 'CM-' || LPAD(nextval('seq_cod_cita')::TEXT, 5, '0');
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_autogenerar_codigo_cita
BEFORE INSERT ON citas
FOR EACH ROW EXECUTE FUNCTION fn_autogenerar_codigo_cita();

-- ----------------------------------------------------------------------------
-- TRIGGER 2: Prevención de Solapamiento en Horarios de Atención (RF05)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_validar_horario_medico_solapado()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado = 'Activo' THEN
        IF EXISTS (
            SELECT 1 FROM horarios_atencion
            WHERE id_medico = NEW.id_medico
              AND dia_semana = NEW.dia_semana
              AND estado = 'Activo'
              AND id_horario <> COALESCE(NEW.id_horario, -1)
              AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
        ) THEN
            RAISE EXCEPTION 'El médico ya cuenta con un turno de atención configurado que se solapa con el rango solicitado para ese día.';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_horario_medico_solapado
BEFORE INSERT OR UPDATE ON horarios_atencion
FOR EACH ROW EXECUTE FUNCTION fn_validar_horario_medico_solapado();

-- ----------------------------------------------------------------------------
-- TRIGGER 3: Motor Transaccional de Citas Médicas
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_validar_cita_consistencia_transaccional()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_paciente VARCHAR(10);
    v_estado_medico VARCHAR(10);
    v_dia_semana SMALLINT;
    v_h_inicio TIME;
    v_h_fin TIME;
    v_duracion INTEGER;
    v_cambio_horario BOOLEAN;
BEGIN
    v_cambio_horario := (TG_OP = 'INSERT') OR 
                        (OLD.fecha_cita <> NEW.fecha_cita OR 
                         OLD.hora_inicio <> NEW.hora_inicio OR 
                         OLD.hora_fin <> NEW.hora_fin OR 
                         OLD.id_medico <> NEW.id_medico);

    IF v_cambio_horario AND NEW.estado <> 'Cancelada' THEN
        -- RN03: Paciente activo
        SELECT estado INTO v_estado_paciente FROM pacientes WHERE id_paciente = NEW.id_paciente;
        IF v_estado_paciente IS NULL OR v_estado_paciente <> 'Activo' THEN
            RAISE EXCEPTION 'RN03: El paciente seleccionado no existe o se encuentra inactivo.';
        END IF;

        -- RN04: Médico activo
        SELECT estado INTO v_estado_medico FROM medicos WHERE id_medico = NEW.id_medico;
        IF v_estado_medico IS NULL OR v_estado_medico <> 'Activo' THEN
            RAISE EXCEPTION 'RN04: El médico seleccionado no se encuentra activo para atender citas.';
        END IF;

        -- Especialidad asignada al médico
        IF NOT EXISTS (
            SELECT 1 FROM medico_especialidad 
            WHERE id_medico = NEW.id_medico AND id_especialidad = NEW.id_especialidad
        ) THEN
            RAISE EXCEPTION 'El médico seleccionado no tiene asignada la especialidad solicitada.';
        END IF;

        -- RN06: Solo aplica a citas que se van a agendar a futuro (Programada/Confirmada)
        IF NEW.estado IN ('Programada', 'Confirmada') THEN
            IF (NEW.fecha_cita < CURRENT_DATE) OR 
               (NEW.fecha_cita = CURRENT_DATE AND NEW.hora_inicio < CURRENT_TIME) THEN
                RAISE EXCEPTION 'RN06: No se pueden registrar ni reprogramar citas médicas en fechas u horas pasadas.';
            END IF;

            -- Validación de Feriados y Bloqueos de Agenda
            IF EXISTS (
                SELECT 1 FROM bloqueos_agenda
                WHERE (id_medico IS NULL OR id_medico = NEW.id_medico)
                   AND NEW.fecha_cita BETWEEN fecha_inicio AND fecha_fin
            ) THEN
                RAISE EXCEPTION 'La fecha % se encuentra bloqueada por feriado institucional o licencia médica programada.', NEW.fecha_cita;
            END IF;
        END IF;

        -- RN05: Horario de Atención del médico
        v_dia_semana := EXTRACT(ISODOW FROM NEW.fecha_cita);
        SELECT hora_inicio, hora_fin, duracion_minutos 
          INTO v_h_inicio, v_h_fin, v_duracion
          FROM horarios_atencion
         WHERE id_medico = NEW.id_medico 
           AND dia_semana = v_dia_semana 
           AND estado = 'Activo';

        -- En citas activas futuras se exige estricto apego al horario
        IF NEW.estado IN ('Programada', 'Confirmada') THEN
            IF NOT FOUND THEN
                RAISE EXCEPTION 'RN05: El médico no dispone de horarios de atención configurados para el día solicitado.';
            END IF;

            IF NEW.hora_fin IS NULL THEN
                NEW.hora_fin := NEW.hora_inicio + (v_duracion || ' minutes')::interval;
            END IF;

            IF NEW.hora_inicio < v_h_inicio OR NEW.hora_fin > v_h_fin THEN
                RAISE EXCEPTION 'RN05: La cita solicitada (% a %) excede el turno de atención del médico (% a %).', 
                                NEW.hora_inicio, NEW.hora_fin, v_h_inicio, v_h_fin;
            END IF;
        ELSE
            -- En atenciones históricas, calcular hora_fin si viene nula
            IF NEW.hora_fin IS NULL THEN
                NEW.hora_fin := NEW.hora_inicio + (COALESCE(v_duracion, 30) || ' minutes')::interval;
            END IF;
        END IF;

        -- RN07: Prevención de Solapamiento para el médico
        IF EXISTS (
            SELECT 1 FROM citas
            WHERE id_medico = NEW.id_medico
              AND fecha_cita = NEW.fecha_cita
              AND estado <> 'Cancelada'
              AND id_cita <> COALESCE(NEW.id_cita, -1)
              AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
        ) THEN
            RAISE EXCEPTION 'RN07: Conflicto de horario. El médico ya tiene una cita médica registrada en el intervalo solicitado (% a %).', 
                            NEW.hora_inicio, NEW.hora_fin;
        END IF;

        -- Prevención de solapamiento para el mismo paciente
        IF EXISTS (
            SELECT 1 FROM citas
            WHERE id_paciente = NEW.id_paciente
              AND fecha_cita = NEW.fecha_cita
              AND estado <> 'Cancelada'
              AND id_cita <> COALESCE(NEW.id_cita, -1)
              AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
        ) THEN
            RAISE EXCEPTION 'El paciente ya cuenta con otra cita que se solapa en el mismo horario solicitado.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_cita_consistencia_transaccional
BEFORE INSERT OR UPDATE ON citas
FOR EACH ROW EXECUTE FUNCTION fn_validar_cita_consistencia_transaccional();

-- ----------------------------------------------------------------------------
-- TRIGGER 4: Control de Transiciones de Estado (RF08, RF09, RF10, RN10)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_control_transiciones_estado_cita()
RETURNS TRIGGER AS $$
BEGIN
    -- RN10: Una cita Atendida es irreversible
    IF OLD.estado = 'Atendida' THEN
        IF NEW.estado <> 'Atendida' OR 
           OLD.fecha_cita <> NEW.fecha_cita OR 
           OLD.hora_inicio <> NEW.hora_inicio OR 
           OLD.id_medico <> NEW.id_medico THEN
            RAISE EXCEPTION 'RN10: Una cita médica en estado Atendida no puede ser modificada, reprogramada ni cancelada.';
        END IF;
    END IF;

    -- RN09: Cita Cancelada no se reactiva
    IF OLD.estado = 'Cancelada' AND NEW.estado <> 'Cancelada' THEN
        RAISE EXCEPTION 'Una cita Cancelada no puede reactivarse directamente. Programe una nueva cita.';
    END IF;

    IF OLD.estado = 'Inasistencia' AND NEW.estado IN ('Atendida', 'Confirmada') THEN
        RAISE EXCEPTION 'No se puede marcar como Atendida o Confirmada una cita con Inasistencia sellada.';
    END IF;

    IF NEW.estado = 'Atendida' AND OLD.estado NOT IN ('Programada', 'Confirmada') THEN
        RAISE EXCEPTION 'Solo se pueden atender citas en estado Programada o Confirmada.';
    END IF;

    IF NEW.estado = 'Cancelada' AND OLD.estado NOT IN ('Programada', 'Confirmada') THEN
        RAISE EXCEPTION 'Solo se pueden cancelar citas en estado Programada o Confirmada.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_control_transiciones_estado_cita
BEFORE UPDATE OF estado, fecha_cita, hora_inicio, id_medico ON citas
FOR EACH ROW EXECUTE FUNCTION fn_control_transiciones_estado_cita();

-- ----------------------------------------------------------------------------
-- TRIGGER 5: Sincronización Automática al Cancelar Cita (RF09)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_sincronizar_cancelacion_cita()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE citas 
       SET estado = 'Cancelada',
           id_usuario_registro = NEW.id_usuario
     WHERE id_cita = NEW.id_cita 
       AND estado <> 'Cancelada';
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sincronizar_cancelacion_cita
AFTER INSERT ON cancelaciones_cita
FOR EACH ROW EXECUTE FUNCTION fn_sincronizar_cancelacion_cita();

-- ----------------------------------------------------------------------------
-- TRIGGER 6: Prohibición Estricta de Borrado Físico (DELETE) (RN10, RN11)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_proteger_borrado_fisico_citas()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'RN10/RN11: Queda estrictamente prohibido el borrado físico de citas médicas. Utilice el procedimiento de cancelación lógica.';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_proteger_borrado_fisico_citas
BEFORE DELETE ON citas
FOR EACH ROW EXECUTE FUNCTION fn_proteger_borrado_fisico_citas();

CREATE OR REPLACE FUNCTION fn_proteger_borrado_pacientes_medicos()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_TABLE_NAME = 'pacientes' THEN
        IF EXISTS (SELECT 1 FROM citas WHERE id_paciente = OLD.id_paciente) THEN
            RAISE EXCEPTION 'RN11: No se puede eliminar el paciente porque registra atenciones o citas históricas. Proceda a desactivarlo (estado = Inactivo).';
        END IF;
    ELSIF TG_TABLE_NAME = 'medicos' THEN
        IF EXISTS (SELECT 1 FROM citas WHERE id_medico = OLD.id_medico) THEN
            RAISE EXCEPTION 'RN11: No se puede eliminar el médico porque registra citas históricas. Proceda a desactivarlo (estado = Inactivo).';
        END IF;
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_proteger_borrado_pacientes
BEFORE DELETE ON pacientes
FOR EACH ROW EXECUTE FUNCTION fn_proteger_borrado_pacientes_medicos();

CREATE TRIGGER trg_proteger_borrado_medicos
BEFORE DELETE ON medicos
FOR EACH ROW EXECUTE FUNCTION fn_proteger_borrado_pacientes_medicos();

-- ----------------------------------------------------------------------------
-- TRIGGER 7: Auditoría Transaccional Automática en Citas (RF17)
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_auditoria_automatica_citas()
RETURNS TRIGGER AS $$
DECLARE
    v_operacion VARCHAR(20);
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_operacion := 'INSERT';
        INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos)
        VALUES (NEW.id_usuario_registro, 'Citas Médicas', v_operacion, NEW.id_cita::TEXT, to_jsonb(NEW));
    ELSIF TG_OP = 'UPDATE' THEN
        IF NEW.estado = 'Cancelada' AND OLD.estado <> 'Cancelada' THEN
            v_operacion := 'CANCEL';
        ELSE
            v_operacion := 'UPDATE';
        END IF;
        INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos)
        VALUES (NEW.id_usuario_registro, 'Citas Médicas', v_operacion, NEW.id_cita::TEXT, to_jsonb(OLD), to_jsonb(NEW));
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_auditoria_automatica_citas
AFTER INSERT OR UPDATE ON citas
FOR EACH ROW EXECUTE FUNCTION fn_auditoria_automatica_citas();

-- ----------------------------------------------------------------------------
-- TRIGGER 8: Timestamp de Actualización en Fichas Médicas
-- ----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_actualizar_fecha_ficha_medica()
RETURNS TRIGGER AS $$
BEGIN
    NEW.fecha_actualizacion := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_actualizar_fecha_ficha_medica
BEFORE UPDATE ON fichas_medicas
FOR EACH ROW EXECUTE FUNCTION fn_actualizar_fecha_ficha_medica();
