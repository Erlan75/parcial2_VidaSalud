-- ============================================================================
-- EXTENSIONES AUXILIARES NO INTRUSIVAS (PASSWORD RESETS + SP FICHA MÉDICA)
-- Preserva intactas las 13 tablas oficiales en 3FN de Script.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id_reset BIGSERIAL PRIMARY KEY,
    id_usuario BIGINT NOT NULL,
    correo_electronico VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expira_en TIMESTAMP WITH TIME ZONE NOT NULL,
    usado BOOLEAN NOT NULL DEFAULT FALSE,
    ip_solicitud VARCHAR(45) DEFAULT '127.0.0.1',
    fecha_creacion TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwd_reset_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_pwd_resets_correo ON password_resets (correo_electronico);

-- ----------------------------------------------------------------------------
-- sp_actualizar_ficha_medica (CU07 - Regla Smart-DB)
-- ----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_actualizar_ficha_medica(bigint, bigint, character varying, text, text, text, text, character varying);
DROP PROCEDURE IF EXISTS sp_actualizar_ficha_medica;
CREATE OR REPLACE PROCEDURE sp_actualizar_ficha_medica(
    IN p_id_paciente BIGINT,
    IN p_id_usuario_ejecutor BIGINT,
    IN p_tipo_sangre VARCHAR(15),
    IN p_alergias TEXT,
    IN p_enfermedades_cronicas TEXT,
    IN p_medicacion_actual TEXT,
    IN p_observaciones TEXT,
    IN p_ip_origen VARCHAR(45)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_prev JSONB;
    v_new JSONB;
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pacientes WHERE id_paciente = p_id_paciente) THEN
        RAISE EXCEPTION 'El paciente especificado no existe.';
    END IF;

    SELECT to_jsonb(fm) INTO v_prev
      FROM fichas_medicas fm
     WHERE fm.id_paciente = p_id_paciente;

    IF v_prev IS NULL THEN
        INSERT INTO fichas_medicas (
            id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicacion_actual, observaciones
        ) VALUES (
            p_id_paciente,
            COALESCE(NULLIF(TRIM(p_tipo_sangre), ''), 'Desconocido'),
            COALESCE(NULLIF(TRIM(p_alergias), ''), 'Ninguna conocida'),
            COALESCE(NULLIF(TRIM(p_enfermedades_cronicas), ''), 'Ninguna reportada'),
            COALESCE(NULLIF(TRIM(p_medicacion_actual), ''), 'Ninguna'),
            NULLIF(TRIM(p_observaciones), '')
        );
    ELSE
        UPDATE fichas_medicas
           SET tipo_sangre = COALESCE(NULLIF(TRIM(p_tipo_sangre), ''), 'Desconocido'),
               alergias = COALESCE(NULLIF(TRIM(p_alergias), ''), 'Ninguna conocida'),
               enfermedades_cronicas = COALESCE(NULLIF(TRIM(p_enfermedades_cronicas), ''), 'Ninguna reportada'),
               medicacion_actual = COALESCE(NULLIF(TRIM(p_medicacion_actual), ''), 'Ninguna'),
               observaciones = NULLIF(TRIM(p_observaciones), '')
         WHERE id_paciente = p_id_paciente;
    END IF;

    SELECT to_jsonb(fm) INTO v_new
      FROM fichas_medicas fm
     WHERE fm.id_paciente = p_id_paciente;

    INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
    VALUES (
        p_id_usuario_ejecutor,
        'Ficha Médica',
        'UPDATE',
        p_id_paciente::TEXT,
        v_prev,
        v_new,
        COALESCE(p_ip_origen, '127.0.0.1')
    );
END;
$$;
