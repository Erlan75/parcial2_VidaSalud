-- ============================================================================
-- SCRIPT DE POBLACIÓN MASIVA: poblacion.sql
-- PROYECTO: Sistema Web Clínica Vida Salud
-- MOTOR: PostgreSQL 13+
-- CREDENCIALES ADMINISTRADOR: Usuario: Erlan o Edilson | Clave: Cher_4mi
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. ROLES DEL SISTEMA (RF01)
-- ----------------------------------------------------------------------------
INSERT INTO roles (id_rol, nombre, descripcion, estado) VALUES
(1, 'Administrador', 'Control total de configuración, personal, reportes y auditoría', 'Activo'),
(2, 'Recepcionista', 'Gestión de ventanilla, registro de pacientes, citas y asistencia', 'Activo'),
(3, 'Médico', 'Visualización de agenda médica diaria y registro de consultas', 'Activo'),
(4, 'Paciente', 'Autoservicio de reservas y visualización de historial de citas', 'Activo')
ON CONFLICT (id_rol) DO UPDATE SET nombre = EXCLUDED.nombre;

SELECT setval('roles_id_rol_seq', (SELECT MAX(id_rol) FROM roles));

-- ----------------------------------------------------------------------------
-- 2. USUARIOS DEL SISTEMA (Password para todos: Cher_4mi)
-- Hash BCRYPT verificado en PHP 8+: $2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm
-- ----------------------------------------------------------------------------
INSERT INTO usuarios (id_usuario, id_rol, nombre_completo, nombre_usuario, correo_electronico, telefono, password_hash, estado) VALUES
-- Administradores Principales
(1, 1, 'Garcia Mamani Erlan Gaston', 'Erlan', 'erlan.garcia75@gmail.com', '75909498', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(2, 1, 'Mamani Maturano Edilson', 'Edilson', 'erickmaturano04@gmail.com', '73067890', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),

-- Personal de Recepción
(3, 2, 'Mariana Vaca Suarez', 'mariana.vaca', 'm.vaca@vidasalud.com.bo', '71023456', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(4, 2, 'Carlos Andres Pinto Rojas', 'carlos.pinto', 'c.pinto@vidasalud.com.bo', '72134567', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(5, 2, 'Valeria Justiniano Ortiz', 'valeria.justiniano', 'v.justiniano@vidasalud.com.bo', '73245678', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),

-- Cuentas de Médicos
(6, 3, 'Dr. Juan Pablo Perez Aguilera', 'juan.perez', 'jp.perez@vidasalud.com.bo', '71345678', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(7, 3, 'Dra. Maria Elena Rodriguez Arteaga', 'maria.rodriguez', 'me.rodriguez@vidasalud.com.bo', '72456789', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(8, 3, 'Dr. Roberto Carlos Suarez Cuellar', 'roberto.suarez', 'rc.suarez@vidasalud.com.bo', '73567890', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(9, 3, 'Dra. Andrea Sofia Vargas Mendoza', 'andrea.vargas', 'as.vargas@vidasalud.com.bo', '74678901', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(10, 3, 'Dr. Fernando Rios Mercado', 'fernando.rios', 'f.rios@vidasalud.com.bo', '75789012', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(11, 3, 'Dr. Alejandro Morales Roca', 'alejandro.morales', 'a.morales@vidasalud.com.bo', '76890123', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(12, 3, 'Dra. Claudia Marcela Torrico Leaños', 'claudia.torrico', 'c.torrico@vidasalud.com.bo', '77901234', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(13, 3, 'Dr. Diego Armando Chavez Hurtado', 'diego.chavez', 'd.chavez@vidasalud.com.bo', '78012345', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),

-- Cuentas de Pacientes (Autoservicio Web)
(14, 4, 'Lucas Gabriel Gutierrez Melgar', 'lucas.gutierrez', 'lucas.gutierrez@gmail.com', '70112233', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(15, 4, 'Jimena Paola Flores Saucedo', 'jimena.flores', 'jimena.flores@hotmail.com', '70223344', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(16, 4, 'Mateo Sebastian Ribera Banegas', 'mateo.ribera', 'mateo.ribera@gmail.com', '70334455', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(17, 4, 'Camila Andrea Aguilera Paz', 'camila.aguilera', 'camila.aguilera@yahoo.com', '70445566', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo'),
(18, 4, 'Rodrigo Ignacio Chavez Dorado', 'rodrigo.chavez', 'rodrigo.chavez@gmail.com', '70556677', '$2y$10$JAE5s0xs7Sr71sFcdQTq/OhiRAQRw4CAw5/0oqfYcpuSmOk8/wcYm', 'Activo')
ON CONFLICT (id_usuario) DO NOTHING;

SELECT setval('usuarios_id_usuario_seq', (SELECT MAX(id_usuario) FROM usuarios));

-- ----------------------------------------------------------------------------
-- 3. ESPECIALIDADES MÉDICAS (RF04)
-- ----------------------------------------------------------------------------
INSERT INTO especialidades (id_especialidad, codigo_especialidad, nombre, descripcion, estado) VALUES
(1, 'ESP-GEN', 'Medicina General', 'Atención ambulatoria integral, medicina preventiva y diagnóstico inicial', 'Activo'),
(2, 'ESP-PED', 'Pediatría', 'Atención médica integral, desarrollo y salud de lactantes, niños y adolescentes', 'Activo'),
(3, 'ESP-GIN', 'Ginecología', 'Salud ginecológica integral, control prenatal y salud reproductiva femenina', 'Activo'),
(4, 'ESP-ODO', 'Odontología', 'Salud bucal, limpieza, odontología restauradora y endodoncia básica', 'Activo'),
(5, 'ESP-CAR', 'Cardiología', 'Evaluación cardiovascular preventiva, control de hipertensión y arritmias', 'Activo')
ON CONFLICT (id_especialidad) DO NOTHING;

SELECT setval('especialidades_id_especialidad_seq', (SELECT MAX(id_especialidad) FROM especialidades));

-- ----------------------------------------------------------------------------
-- 4. MÉDICOS Y ESPECIALIDADES ASOCIADAS (RF03, RN02)
-- ----------------------------------------------------------------------------
INSERT INTO medicos (id_medico, id_usuario, codigo_medico, registro_profesional, nombres, apellidos, telefono, correo, estado) VALUES
(1, 6,  'MED-0001', 'MP-SC-10452', 'Juan Pablo', 'Perez Aguilera', '71345678', 'jp.perez@vidasalud.com.bo', 'Activo'),
(2, 7,  'MED-0002', 'MP-SC-11890', 'Maria Elena', 'Rodriguez Arteaga', '72456789', 'me.rodriguez@vidasalud.com.bo', 'Activo'),
(3, 8,  'MED-0003', 'MP-SC-12341', 'Roberto Carlos', 'Suarez Cuellar', '73567890', 'rc.suarez@vidasalud.com.bo', 'Activo'),
(4, 9,  'MED-0004', 'MP-SC-13789', 'Andrea Sofia', 'Vargas Mendoza', '74678901', 'as.vargas@vidasalud.com.bo', 'Activo'),
(5, 10, 'MED-0005', 'MP-SC-14562', 'Fernando', 'Rios Mercado', '75789012', 'f.rios@vidasalud.com.bo', 'Activo'),
(6, 11, 'MED-0006', 'MP-SC-15891', 'Alejandro', 'Morales Roca', '76890123', 'a.morales@vidasalud.com.bo', 'Activo'),
(7, 12, 'MED-0007', 'MP-SC-16430', 'Claudia Marcela', 'Torrico Leaños', '77901234', 'c.torrico@vidasalud.com.bo', 'Activo'),
(8, 13, 'MED-0008', 'MP-SC-17125', 'Diego Armando', 'Chavez Hurtado', '78012345', 'd.chavez@vidasalud.com.bo', 'Activo')
ON CONFLICT (id_medico) DO NOTHING;

SELECT setval('medicos_id_medico_seq', (SELECT MAX(id_medico) FROM medicos));
SELECT setval('seq_cod_medico', (SELECT MAX(id_medico) FROM medicos));

INSERT INTO medico_especialidad (id_medico, id_especialidad, fecha_asignacion) VALUES
(1, 1, '2025-01-10'),
(2, 2, '2025-01-15'),
(3, 3, '2025-02-01'),
(4, 4, '2025-02-10'),
(5, 5, '2025-03-01'),
(5, 1, '2025-03-01'),
(6, 1, '2025-03-15'),
(7, 2, '2025-04-01'),
(8, 4, '2025-04-10')
ON CONFLICT (id_medico, id_especialidad) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 5. HORARIOS DE ATENCIÓN DE MÉDICOS (RF05, RN05, RN08)
-- ----------------------------------------------------------------------------
INSERT INTO horarios_atencion (id_horario, id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, estado) VALUES
(1, 1, 1, '08:00:00', '12:00:00', 30, 'Activo'),
(2, 1, 3, '08:00:00', '12:00:00', 30, 'Activo'),
(3, 1, 5, '08:00:00', '12:00:00', 30, 'Activo'),
(4, 2, 1, '08:00:00', '12:00:00', 30, 'Activo'),
(5, 2, 2, '08:00:00', '12:00:00', 30, 'Activo'),
(6, 2, 4, '08:00:00', '12:00:00', 30, 'Activo'),
(7, 3, 2, '14:00:00', '18:00:00', 30, 'Activo'),
(8, 3, 4, '14:00:00', '18:00:00', 30, 'Activo'),
(9, 3, 6, '14:00:00', '18:00:00', 30, 'Activo'),
(10, 4, 1, '14:00:00', '18:00:00', 30, 'Activo'),
(11, 4, 2, '14:00:00', '18:00:00', 30, 'Activo'),
(12, 4, 3, '14:00:00', '18:00:00', 30, 'Activo'),
(13, 4, 4, '14:00:00', '18:00:00', 30, 'Activo'),
(14, 4, 5, '14:00:00', '18:00:00', 30, 'Activo'),
(15, 5, 1, '14:00:00', '18:00:00', 30, 'Activo'),
(16, 5, 3, '14:00:00', '18:00:00', 30, 'Activo'),
(17, 5, 5, '14:00:00', '18:00:00', 30, 'Activo'),
(18, 6, 2, '08:00:00', '12:00:00', 30, 'Activo'),
(19, 6, 4, '08:00:00', '12:00:00', 30, 'Activo'),
(20, 6, 6, '08:00:00', '12:00:00', 30, 'Activo'),
(21, 7, 3, '14:00:00', '18:00:00', 30, 'Activo'),
(22, 7, 5, '14:00:00', '18:00:00', 30, 'Activo'),
(23, 7, 6, '14:00:00', '18:00:00', 30, 'Activo'),
(24, 8, 1, '08:00:00', '12:00:00', 30, 'Activo'),
(25, 8, 2, '08:00:00', '12:00:00', 30, 'Activo'),
(26, 8, 3, '08:00:00', '12:00:00', 30, 'Activo'),
(27, 8, 4, '08:00:00', '12:00:00', 30, 'Activo')
ON CONFLICT (id_horario) DO NOTHING;

SELECT setval('horarios_atencion_id_horario_seq', (SELECT MAX(id_horario) FROM horarios_atencion));

-- ----------------------------------------------------------------------------
-- 6. BLOQUEOS DE AGENDA Y FERIADOS (CU16, RF05)
-- ----------------------------------------------------------------------------
INSERT INTO bloqueos_agenda (id_bloqueo, id_medico, tipo_bloqueo, fecha_inicio, fecha_fin, motivo) VALUES
(1, NULL, 'Feriado Institucional', '2026-11-02', '2026-11-02', 'Feriado Nacional de Todos Santos y Difuntos'),
(2, 5,    'Congreso',              '2026-11-20', '2026-11-24', 'Participación en Congreso Internacional de Cardiología Clínica')
ON CONFLICT (id_bloqueo) DO NOTHING;

SELECT setval('bloqueos_agenda_id_bloqueo_seq', (SELECT MAX(id_bloqueo) FROM bloqueos_agenda));

-- ----------------------------------------------------------------------------
-- 7. PACIENTES (RF02, RN01) - 26 PACIENTES
-- ----------------------------------------------------------------------------
INSERT INTO pacientes (id_paciente, id_usuario, codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion, estado) VALUES
(1,  14,   'PAC-00001', '5894123', 'Lucas Gabriel',   'Gutierrez Melgar',   '1995-04-12', 'M', '70112233', 'lucas.gutierrez@gmail.com',   'Barrio Sirari, Calle Los Claveles #14', 'Activo'),
(2,  15,   'PAC-00002', '6312450', 'Jimena Paola',    'Flores Saucedo',     '1998-08-25', 'F', '70223344', 'jimena.flores@hotmail.com',   'Av. San Martin, Condominio Sevilla #3B', 'Activo'),
(3,  16,   'PAC-00003', '7456128', 'Mateo Sebastian', 'Ribera Banegas',     '2001-02-18', 'M', '70334455', 'mateo.ribera@gmail.com',      'Plan 3000, Barrio Guapurú Calle 5 #20', 'Activo'),
(4,  17,   'PAC-00004', '8123904', 'Camila Andrea',   'Aguilera Paz',       '1992-11-05', 'F', '70445566', 'camila.aguilera@yahoo.com',   'Av. Beni, 3er Anillo Interno #45', 'Activo'),
(5,  18,   'PAC-00005', '9045127', 'Rodrigo Ignacio', 'Chavez Dorado',      '1989-06-30', 'M', '70556677', 'rodrigo.chavez@gmail.com',    'Equipetrol Norte, Calle 8 Este #120', 'Activo'),
(6,  NULL, 'PAC-00006', '4589120', 'Sofia Belen',     'Justiniano Roca',    '2018-09-14', 'F', '71667788', 'tutor.justiniano@gmail.com',  'Av. Alemana, 4to Anillo #230', 'Activo'),
(7,  NULL, 'PAC-00007', '5120934', 'Diego Alejandro', 'Saucedo Cuellar',    '2015-03-22', 'M', '72778899', 'tutor.saucedo@hotmail.com',   'Av. Santos Dumont, Calle Lemoine #5', 'Activo'),
(8,  NULL, 'PAC-00008', '6789123', 'Valeria Nicole',  'Roca Zambrana',      '1990-12-08', 'F', '73889900', 'valeria.roca@gmail.com',      'Villa 1ro de Mayo, Calle 3 Oeste #18', 'Activo'),
(9,  NULL, 'PAC-00009', '7890124', 'Santiago Jose',   'Melgar Hurtado',     '1985-05-19', 'M', '74990011', 'santiago.melgar@gmail.com',   'Barrio Hamacas, Calle Tulipanes #10', 'Activo'),
(10, NULL, 'PAC-00010', '8901235', 'Natalia Lucia',   'Paz Menacho',        '1997-07-11', 'F', '75001122', 'natalia.paz@outlook.com',     'Av. Busch, Calle Honduras #310', 'Activo'),
(11, NULL, 'PAC-00011', '9012346', 'Joaquin Emilio',  'Torres Villarroel',  '1978-10-03', 'M', '76112233', 'joaquin.torres@gmail.com',    'Barrio Urbari, Calle Guembé #44', 'Activo'),
(12, NULL, 'PAC-00012', '4123457', 'Mariana Isabel',  'Zabala Justiniano',  '1983-01-29', 'F', '77223344', 'mariana.zabala@gmail.com',    'Av. Cristo Redentor, Calle Los Pinos #8', 'Activo'),
(13, NULL, 'PAC-00013', '5234568', 'Fabian Andres',   'Moron Antelo',       '2000-08-16', 'M', '78334455', 'fabian.moron@hotmail.com',    'Barrio Las Palmas, Calle Tajibos #55', 'Activo'),
(14, NULL, 'PAC-00014', '6345679', 'Luciana Maria',   'Cabrera Arteaga',    '2020-04-05', 'F', '79445566', 'tutor.cabrera@gmail.com',     'Av. Piraí, 5to Anillo Calle 2 #10', 'Activo'),
(15, NULL, 'PAC-00015', '7456780', 'Bruno Matias',    'Eguez Cespedes',     '1994-09-27', 'M', '70551122', 'bruno.eguez@gmail.com',       'Av. Tres Pasos al Frente #88', 'Activo'),
(16, NULL, 'PAC-00016', '8567891', 'Adriana Cecilia', 'Villarroel Suarez',  '1996-03-15', 'F', '71662233', 'adriana.v@gmail.com',         'Barrio El Trompillo, Calle 4 #19', 'Activo'),
(17, NULL, 'PAC-00017', '9678902', 'Leonardo David',  'Montaño Rojas',      '1972-06-18', 'M', '72773344', 'leonardo.m@cotas.com.bo',     'Av. Virgen de Cotoca, Calle Junin #5', 'Activo'),
(18, NULL, 'PAC-00018', '4789013', 'Flavia Andrea',   'Justiniano Toledo',  '1988-02-14', 'F', '73884455', 'flavia.j@gmail.com',          'Barrio Petrolero, Calle Petróleo #12', 'Activo'),
(19, NULL, 'PAC-00019', '5890124', 'Alvaro Daniel',   'Soria Galvarro',     '1993-11-23', 'M', '74995566', 'alvaro.soria@hotmail.com',    'Av. Grigota, 2do Anillo #150', 'Activo'),
(20, NULL, 'PAC-00020', '6901235', 'Daniela Paola',   'Guzman Vaca',        '2019-12-01', 'F', '75006677', 'tutor.guzman@gmail.com',      'Plan 4000, Manzana 14 Lote 2', 'Activo'),
(21, NULL, 'PAC-00021', '7012346', 'Carlos Eduardo',  'Arce Melgar',        '1965-08-09', 'M', '76117788', 'carlos.arce@gmail.com',       'Av. Landivar, Calle España #40', 'Activo'),
(22, NULL, 'PAC-00022', '8123457', 'Renata Valentina','Mendez Saucedo',     '2003-05-04', 'F', '77228899', 'renata.mendez@gmail.com',     'Barrio Los Olivos, Calle Los Mangos #7', 'Activo'),
(23, NULL, 'PAC-00023', '9234568', 'Hugo Marcelo',    'Pinto Aguilera',     '1981-01-17', 'M', '78339900', 'hugo.pinto@gmail.com',        'Av. Roca y Coronado, Calle 6 #80', 'Activo'),
(24, NULL, 'PAC-00024', '4345679', 'Carla Patricia',  'Ribera Mercado',     '1991-10-29', 'F', '79440011', 'carla.ribera@yahoo.com',      'Av. Radial 26, Calle Jacarandá #10', 'Activo'),
(25, NULL, 'PAC-00025', '5456780', 'Jorge Luis',      'Suarez Antelo',      '1975-04-03', 'M', '70552233', 'jorge.suarez@gmail.com',      'Barrio Santa Rosita, Calle Lanza #14', 'Activo'),
(26, NULL, 'PAC-00026', '6567891', 'Elena Rocio',     'Banegas Hurtado',    '1999-07-21', 'F', '71663344', 'elena.banegas@gmail.com',     'Av. Centenario, Calle Cochabamba #2', 'Activo')
ON CONFLICT (id_paciente) DO NOTHING;

SELECT setval('pacientes_id_paciente_seq', (SELECT MAX(id_paciente) FROM pacientes));
SELECT setval('seq_cod_paciente', (SELECT MAX(id_paciente) FROM pacientes));

-- ----------------------------------------------------------------------------
-- 8. FICHAS MÉDICAS PREVENTIVAS (Sección 3.4)
-- ----------------------------------------------------------------------------
INSERT INTO fichas_medicas (id_ficha, id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicacion_actual, observaciones) VALUES
(1,  1,  'O+',  'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Paciente deportista regular'),
(2,  2,  'A+',  'Penicilina, Amoxicilina',          'Rinitis alérgica',              'Loratadina 10mg ocasional', 'Precaución con betalactámicos'),
(3,  3,  'B+',  'Aspirina, AINEs',                  'Asma bronquial leve',           'Salbutamol inhalador',      'Asma controlada'),
(4,  4,  'O-',  'Sulfas',                           'Hipotiroidismo',                'Levotiroxina 75mcg',        'Control hormonal semestral'),
(5,  5,  'AB+', 'Ninguna conocida',                 'Hipertensión arterial',         'Losartán 50mg/día',         'Presión controlada'),
(6,  6,  'O+',  'Proteína de leche de vaca (remitida)','Ninguna reportada',           'Ninguna',                   'Control de niño sano'),
(7,  7,  'A+',  'Picadura de abejas',               'Ninguna reportada',             'Ninguna',                   'Porta antihistamínico de auxilio'),
(8,  8,  'O+',  'Polvo, Ácaros',                    'Ninguna reportada',             'Ninguna',                   'Consulta preventiva ginecológica'),
(9,  9,  'B-',  'Ninguna conocida',                 'Diabetes Mellitus Tipo 2',      'Metformina 850mg',          'Monitoreo trimestral de glucemia'),
(10, 10, 'A-',  'Ibuprofeno',                       'Migraña crónica',               'Ergotamina ocasional',      'Sensibilidad a analgésicos fuertes'),
(11, 11, 'O+',  'Ninguna conocida',                 'Dislipidemia, Hipertensión',    'Atorvastatina 20mg, Enalapril','Chequeo cardiológico regular'),
(12, 12, 'O+',  'Yodo contraste',                   'Gastritis crónica',             'Omeprazol 20mg',            'Alérgica a medios de contraste'),
(13, 13, 'A+',  'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Salud bucal preventiva'),
(14, 14, 'O+',  'Ninguna conocida',                 'Dermatitis atópica',            'Crema emoliente',           'Paciente pediátrico'),
(15, 15, 'B+',  'Ciprofloxacino',                   'Ninguna reportada',             'Ninguna',                   'Evitar quinolonas'),
(16, 16, 'O-',  'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Paciente sana'),
(17, 17, 'A+',  'Ninguna conocida',                 'Cardiopatía isquémica previa',  'Aspirina 100mg, Carvedilol','Control riguroso con cardiología'),
(18, 18, 'O+',  'Latex',                            'Ninguna reportada',             'Ninguna',                   'Uso estricto de guantes de nitrilo'),
(19, 19, 'AB-', 'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Control odontológico semestral'),
(20, 20, 'O+',  'Ninguna conocida',                 'Ninguna reportada',             'Multivitamínico jarabe',    'Desarrollo infantil adecuado'),
(21, 21, 'O+',  'Penicilina',                       'Hipertensión, Artrosis severa', 'Amlodipino 5mg, Paracetamol','Adulto mayor, control articular'),
(22, 22, 'A+',  'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Consulta general de rutina'),
(23, 23, 'B+',  'Mariscos',                         'Sobrepeso Grado I',             'Ninguna',                   'Plan de reeducación alimentaria'),
(24, 24, 'O+',  'Ninguna conocida',                 'Ninguna reportada',             'Ninguna',                   'Control prenatal inicial'),
(25, 25, 'O+',  'Dipirona / Metamizol',             'Diabetes Tipo 2',               'Glibenclamida 5mg',         'Revisión dental y glucémica'),
(26, 26, 'Desconocido', 'Ninguna conocida',         'Ninguna reportada',             'Ninguna',                   'Ficha pendiente de tipificación')
ON CONFLICT (id_paciente) DO NOTHING;

SELECT setval('fichas_medicas_id_ficha_seq', (SELECT MAX(id_ficha) FROM fichas_medicas));

-- ----------------------------------------------------------------------------
-- 9. CONTACTOS DE EMERGENCIA
-- ----------------------------------------------------------------------------
INSERT INTO contactos_emergencia (id_contacto, id_paciente, nombre_completo, parentesco, telefono) VALUES
(1,  1,  'Gloria Melgar de Gutierrez', 'Madre',    '71112233'),
(2,  2,  'Fernando Flores Mendez',     'Padre',    '72223344'),
(3,  3,  'Rosa Banegas Cuellar',       'Madre',    '73334455'),
(4,  4,  'Mauricio Aguilera Suarez',   'Cónyuge',  '74445566'),
(5,  5,  'Patricia Dorado Vaca',       'Madre',    '75556677'),
(6,  6,  'Lorena Roca de Justiniano',  'Madre',    '71667788'),
(7,  7,  'Ernesto Saucedo Pinto',      'Padre',    '72778899'),
(8,  8,  'Javier Zambrana Melgar',     'Hermano',  '73889900'),
(9,  9,  'Silvia Hurtado de Melgar',   'Esposa',   '74990011'),
(10, 10, 'Raul Paz Justiniano',        'Padre',    '75001122'),
(11, 11, 'Elena Villarroel de Torres', 'Esposa',   '76112233'),
(12, 12, 'Marcos Justiniano Banegas',  'Hijo',     '77223344'),
(13, 13, 'Claudia Antelo Roca',        'Madre',    '78334455'),
(14, 14, 'Vanessa Arteaga de Cabrera', 'Madre',    '79445566'),
(15, 15, 'Guillermo Eguez Ribera',     'Padre',    '70551122'),
(16, 16, 'Jorge Villarroel Cuellar',   'Hermano',  '71662233'),
(17, 17, 'Carmen Rojas de Montaño',    'Esposa',   '72773344'),
(18, 18, 'Oscar Toledo Mendez',        'Esposo',   '73884455'),
(19, 19, 'Ramiro Soria Melgar',        'Padre',    '74995566'),
(20, 20, 'Maribel Vaca de Guzman',     'Madre',    '75006677'),
(21, 21, 'Teresa Melgar de Arce',      'Esposa',   '76117788'),
(22, 22, 'Sonia Saucedo Dorado',       'Madre',    '77228899'),
(23, 23, 'Daniela Pinto Saucedo',      'Hermana',  '78339900'),
(24, 24, 'Gonzalo Mercado Paz',        'Esposo',   '79440011'),
(25, 25, 'Beatriz Antelo de Suarez',   'Esposa',   '70552233'),
(26, 26, 'Marcos Banegas Ribera',      'Padre',    '71663344')
ON CONFLICT (id_contacto) DO NOTHING;

SELECT setval('contactos_emergencia_id_contacto_seq', (SELECT MAX(id_contacto) FROM contactos_emergencia));

-- ----------------------------------------------------------------------------
-- 10. CITAS MÉDICAS (56 CITAS: 34 HISTÓRICAS + 22 FUTURAS ACTIVAS)
-- ----------------------------------------------------------------------------
INSERT INTO citas (id_cita, codigo_cita, id_paciente, id_medico, id_especialidad, id_usuario_registro, fecha_cita, hora_inicio, hora_fin, motivo_consulta, estado, notas_medicas, fecha_registro) VALUES
(1,  'CM-00001', 1,  1, 1, 3, '2026-09-07', '08:00:00', '08:30:00', 'Cuadro gripal y congestión', 'Atendida', 'Faringitis viral aguda. Reposo e hidratación. Paracetamol 500mg c/8h.', '2026-09-05 09:15:00'),
(2,  'CM-00002', 2,  1, 1, 3, '2026-09-07', '08:30:00', '09:00:00', 'Dolor abdominal leve', 'Atendida', 'Dispepsia funcional por transgresión dietética. Antiespasmódico prescrito.', '2026-09-05 10:20:00'),
(3,  'CM-00003', 3,  1, 1, 3, '2026-09-07', '09:00:00', '09:30:00', 'Cefalea tensional recurrente', 'Atendida', 'Cefalea tensional asociada a estrés. Ejercicios posturales y analgésico.', '2026-09-05 11:00:00'),
(4,  'CM-00004', 6,  2, 2, 3, '2026-09-08', '08:00:00', '08:30:00', 'Control de crecimiento y vacunas', 'Atendida', 'Niña con peso y talla percentil 50. Vacunación pentavalente al día.', '2026-09-06 08:45:00'),
(5,  'CM-00005', 7,  2, 2, 3, '2026-09-08', '08:30:00', '09:00:00', 'Fiebre y tos seca nocturna', 'Atendida', 'Bronquitis catarral. Nebulizaciones fisiológicas y broncodilatador.', '2026-09-06 09:30:00'),
(6,  'CM-00006', 4,  3, 3, 4, '2026-09-08', '14:00:00', '14:30:00', 'Control ginecológico y Papanicolaou', 'Atendida', 'Toma de muestra de PAP realizada sin complicaciones. Todo en orden.', '2026-09-06 14:10:00'),
(7,  'CM-00007', 8,  3, 3, 4, '2026-09-08', '14:30:00', '15:00:00', 'Ecografía de control pélvico', 'Atendida', 'Útero y ovarios ecográficamente normales. Control anual fijado.', '2026-09-06 15:00:00'),
(8,  'CM-00008', 9,  4, 4, 3, '2026-09-09', '14:00:00', '14:30:00', 'Dolor en molar inferior derecho', 'Atendida', 'Caries oclusal profunda en pieza 46. Apertura y obturación con resina.', '2026-09-07 10:00:00'),
(9,  'CM-00009', 10, 4, 4, 3, '2026-09-09', '14:30:00', '15:00:00', 'Limpieza y profilaxis general', 'Atendida', 'Tartrectomía ultrasónica y profilaxis dental completada exitosamente.', '2026-09-07 11:30:00'),
(10, 'CM-00010', 5,  5, 5, 4, '2026-09-09', '14:00:00', '14:30:00', 'Control de hipertensión arterial', 'Atendida', 'PA: 125/80 mmHg. Electrocardiograma normal. Se mantiene Losartán 50mg.', '2026-09-07 16:00:00'),
(11, 'CM-00011', 11, 5, 5, 4, '2026-09-09', '14:30:00', '15:00:00', 'Palpitaciones al realizar esfuerzo', 'Atendida', 'Taquicardia sinusal reactiva. Se solicita Holter de 24 horas.', '2026-09-07 16:30:00'),
(12, 'CM-00012', 12, 6, 1, 3, '2026-09-10', '08:00:00', '08:30:00', 'Dolor lumbar mecánico', 'Atendida', 'Lumbalgia postural aguda. Se indica complejo B, calor local y reposo.', '2026-09-08 09:00:00'),
(13, 'CM-00013', 13, 6, 1, 3, '2026-09-10', '08:30:00', '09:00:00', 'Certificado médico laboral', 'Atendida', 'Examen físico sin alteraciones. Certificado de aptitud médica extendido.', '2026-09-08 10:15:00'),
(14, 'CM-00014', 14, 7, 2, 4, '2026-09-11', '14:00:00', '14:30:00', 'Erupción cutánea en mejillas', 'Atendida', 'Dermatitis atópica reactiva. Crema con hidrocortisona al 1% por 5 días.', '2026-09-09 15:00:00'),
(15, 'CM-00015', 15, 8, 4, 3, '2026-09-14', '08:00:00', '08:30:00', 'Revisión y sensibilidad dental', 'Atendida', 'Sensibilidad dentinaria cervical. Aplicación tópica de flúor barniz.', '2026-09-12 11:00:00'),
(16, 'CM-00016', 16, 1, 1, 3, '2026-10-05', '08:00:00', '08:30:00', 'Malestar general y mareos', 'Atendida', 'Hipotensión ortostática leve. Hidratación con sales orales.', '2026-10-03 08:30:00'),
(17, 'CM-00017', 17, 5, 5, 4, '2026-10-05', '14:00:00', '14:30:00', 'Revisión de resultados de Holter', 'Atendida', 'Holter muestra ritmo sinusal sin arritmias ventriculares. Alta preventiva.', '2026-10-03 14:00:00'),
(18, 'CM-00018', 18, 3, 3, 4, '2026-10-06', '14:00:00', '14:30:00', 'Consulta ginecológica periódica', 'Atendida', 'Ecografía transvaginal normal. Planificación familiar asesorada.', '2026-10-04 10:00:00'),
(19, 'CM-00019', 19, 4, 4, 3, '2026-10-07', '14:00:00', '14:30:00', 'Gingivitis y sangrado al cepillado','Atendida', 'Gingivitis marginal. Curetaje cerrado e indicación de clorhexidina.', '2026-10-05 16:00:00'),
(20, 'CM-00020', 20, 2, 2, 3, '2026-10-08', '08:00:00', '08:30:00', 'Tos y catarro persistente', 'Atendida', 'Rinofaringitis aguda. Descongestionante nasal pediátrico y suero.', '2026-10-06 09:20:00'),
(21, 'CM-00021', 21, 6, 1, 4, '2026-10-13', '08:00:00', '08:30:00', 'Dolor articular en rodillas', 'Atendida', 'Gonartrosis bilateral moderada. Se prescribe Glucosamina y fisioterapia.', '2026-10-11 11:15:00'),
(22, 'CM-00022', 22, 1, 1, 3, '2026-10-14', '08:00:00', '08:30:00', 'Infección urinaria baja sospechada','Atendida', 'Cistitis aguda no complicada. Se inicia Cefalexina 500mg según cultivo.', '2026-10-12 08:40:00'),
(23, 'CM-00023', 23, 5, 5, 4, '2026-10-14', '14:00:00', '14:30:00', 'Control de perfil lipídico', 'Atendida', 'Colesterol total 220 mg/dl. Se ajusta dosis de Rosuvastatina a 10mg.', '2026-10-12 15:30:00'),
(24, 'CM-00024', 24, 3, 3, 3, '2026-10-15', '14:00:00', '14:30:00', 'Confirmación de embarazo y ECO', 'Atendida', 'Gestación única intrauterina de 8 semanas. Vitaminas prenatales dadas.', '2026-10-13 10:50:00'),
(25, 'CM-00025', 25, 1, 1, 3, '2026-10-14', '08:30:00', '09:00:00', 'Control de glucosa en ayunas', 'Inasistencia', NULL, '2026-10-12 09:10:00'),
(26, 'CM-00026', 26, 2, 2, 4, '2026-10-15', '08:00:00', '08:30:00', 'Evaluación pediátrica de peso', 'Inasistencia', NULL, '2026-10-13 08:30:00'),
(27, 'CM-00027', 1,  4, 4, 3, '2026-10-16', '14:00:00', '14:30:00', 'Seguimiento de obturación', 'Inasistencia', NULL, '2026-10-14 11:00:00'),
(28, 'CM-00028', 7,  6, 1, 4, '2026-10-17', '08:00:00', '08:30:00', 'Control de bronquitis', 'Inasistencia', NULL, '2026-10-15 10:15:00'),
(29, 'CM-00029', 11, 7, 2, 3, '2026-10-21', '14:00:00', '14:30:00', 'Control nutricional infantil', 'Inasistencia', NULL, '2026-10-19 14:20:00'),
(30, 'CM-00030', 8,  1, 1, 3, '2026-10-19', '08:00:00', '08:30:00', 'Dolor de garganta', 'Cancelada', NULL, '2026-10-17 08:00:00'),
(31, 'CM-00031', 9,  2, 2, 4, '2026-10-20', '08:00:00', '08:30:00', 'Vacunación infantil', 'Cancelada', NULL, '2026-10-18 09:15:00'),
(32, 'CM-00032', 10, 3, 3, 3, '2026-10-20', '14:00:00', '14:30:00', 'Molestias pélvicas', 'Cancelada', NULL, '2026-10-18 11:30:00'),
(33, 'CM-00033', 12, 5, 5, 4, '2026-10-21', '14:00:00', '14:30:00', 'Chequeo cardiológico', 'Cancelada', NULL, '2026-10-19 15:40:00'),
(34, 'CM-00034', 13, 8, 4, 3, '2026-10-22', '08:00:00', '08:30:00', 'Extracción simple dental', 'Cancelada', NULL, '2026-10-20 10:00:00'),
(35, 'CM-00035', 1,  1, 1, 3, '2026-10-26', '08:00:00', '08:30:00', 'Control de presión arterial', 'Confirmada', NULL, '2026-10-24 08:10:00'),
(36, 'CM-00036', 2,  1, 1, 3, '2026-10-26', '08:30:00', '09:00:00', 'Revisión de análisis de sangre', 'Confirmada', NULL, '2026-10-24 08:20:00'),
(37, 'CM-00037', 3,  1, 1, 14, '2026-10-26', '09:00:00', '09:30:00', 'Chequeo preventivo de rutina', 'Programada', NULL, '2026-10-24 08:30:00'),
(38, 'CM-00038', 4,  1, 1, 4, '2026-10-26', '09:30:00', '10:00:00', 'Dolor lumbar persistente', 'Programada', NULL, '2026-10-24 08:40:00'),
(39, 'CM-00039', 6,  2, 2, 3, '2026-10-26', '08:00:00', '08:30:00', 'Control pediátrico de 8 años', 'Confirmada', NULL, '2026-10-24 08:45:00'),
(40, 'CM-00040', 7,  2, 2, 4, '2026-10-26', '08:30:00', '09:00:00', 'Esquema de vacunación escolar', 'Programada', NULL, '2026-10-24 08:50:00'),
(41, 'CM-00041', 5,  5, 5, 5, '2026-10-26', '14:00:00', '14:30:00', 'Evaluación cardiológica anual', 'Confirmada', NULL, '2026-10-24 08:55:00'),
(42, 'CM-00042', 11, 5, 5, 5, '2026-10-26', '14:30:00', '15:00:00', 'Ecocardiograma de control', 'Programada', NULL, '2026-10-24 09:00:00'),
(43, 'CM-00043', 14, 2, 2, 3, '2026-10-27', '08:00:00', '08:30:00', 'Control de alergia estacional', 'Programada', NULL, '2026-10-24 09:05:00'),
(44, 'CM-00044', 20, 2, 2, 3, '2026-10-27', '08:30:00', '09:00:00', 'Seguimiento de peso infantil', 'Confirmada', NULL, '2026-10-24 09:10:00'),
(45, 'CM-00045', 8,  3, 3, 4, '2026-10-27', '14:00:00', '14:30:00', 'Revisión ginecológica general', 'Programada', NULL, '2026-10-24 09:15:00'),
(46, 'CM-00046', 10, 3, 3, 15, '2026-10-27', '14:30:00', '15:00:00', 'Consulta prenatal de segundo trimestre', 'Confirmada', NULL, '2026-10-24 09:20:00'),
(47, 'CM-00047', 9,  4, 4, 3, '2026-10-27', '14:00:00', '14:30:00', 'Limpieza dental con ultrasonido', 'Programada', NULL, '2026-10-24 09:25:00'),
(48, 'CM-00048', 12, 1, 1, 3, '2026-10-28', '08:00:00', '08:30:00', 'Evaluación de dolor estomacal', 'Programada', NULL, '2026-10-24 09:30:00'),
(49, 'CM-00049', 13, 1, 1, 4, '2026-10-28', '08:30:00', '09:00:00', 'Certificado médico de salud física', 'Programada', NULL, '2026-10-24 09:35:00'),
(50, 'CM-00050', 15, 4, 4, 5, '2026-10-28', '14:00:00', '14:30:00', 'Obturación estética con resina', 'Programada', NULL, '2026-10-24 09:40:00'),
(51, 'CM-00051', 17, 5, 5, 3, '2026-10-28', '14:00:00', '14:30:00', 'Control post-medicación cardiológica', 'Confirmada', NULL, '2026-10-24 09:45:00'),
(52, 'CM-00052', 18, 6, 1, 4, '2026-10-29', '08:00:00', '08:30:00', 'Exámenes médicos de laboratorio', 'Programada', NULL, '2026-10-24 09:50:00'),
(53, 'CM-00053', 21, 6, 1, 4, '2026-10-29', '08:30:00', '09:00:00', 'Control de artrosis y movilidad', 'Programada', NULL, '2026-10-24 09:55:00'),
(54, 'CM-00054', 19, 8, 4, 3, '2026-10-29', '08:00:00', '08:30:00', 'Revisión odontológica semestral', 'Programada', NULL, '2026-10-24 10:00:00'),
(55, 'CM-00055', 22, 1, 1, 3, '2026-10-30', '08:00:00', '08:30:00', 'Evaluación de fatiga y cansancio', 'Programada', NULL, '2026-10-30 10:05:00'),
(56, 'CM-00056', 23, 7, 2, 5, '2026-10-30', '14:00:00', '14:30:00', 'Control de peso y nutrición infantil', 'Programada', NULL, '2026-10-24 10:10:00')
ON CONFLICT (id_cita) DO NOTHING;

SELECT setval('citas_id_cita_seq', (SELECT MAX(id_cita) FROM citas));
SELECT setval('seq_cod_cita', (SELECT MAX(id_cita) FROM citas));

-- ----------------------------------------------------------------------------
-- 11. CANCELACIONES DE CITAS (RF09, RN09, RN10)
-- ----------------------------------------------------------------------------
INSERT INTO cancelaciones_cita (id_cancelacion, id_cita, id_usuario, motivo_cancelacion, fecha_cancelacion) VALUES
(1, 30, 3, 'Paciente solicita cancelación por viaje urgente fuera de la ciudad', '2026-10-18 14:00:00'),
(2, 31, 4, 'Tutor del menor cancela por cruce de horario con actividad escolar', '2026-10-19 10:30:00'),
(3, 32, 3, 'Paciente manifiesta mejoría y solicita postergar cita ginecológica', '2026-10-19 16:15:00'),
(4, 33, 4, 'Paciente tuvo conflicto de horario laboral de última hora', '2026-10-20 11:20:00'),
(5, 34, 3, 'Paciente prefirió reagendar tratamiento dental para el próximo mes', '2026-10-21 09:00:00')
ON CONFLICT (id_cita) DO NOTHING;

SELECT setval('cancelaciones_cita_id_cancelacion_seq', (SELECT MAX(id_cancelacion) FROM cancelaciones_cita));

-- ----------------------------------------------------------------------------
-- 12. AUDITORÍA INICIAL DE EVENTOS (RF17, Smart-DB)
-- ----------------------------------------------------------------------------
INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen, fecha_hora) VALUES
-- Eventos Administrativos y de Seguridad Iniciales
(1, 'Seguridad', 'LOGIN', '1', NULL, '{"evento": "Inicio de sesion exitoso del administrador Erlan Garcia"}'::jsonb, '192.168.1.10', '2026-10-24 07:45:00'),
(2, 'Seguridad', 'LOGIN', '2', NULL, '{"evento": "Inicio de sesion exitoso del administrador Edilson Mamani"}'::jsonb, '192.168.1.15', '2026-10-24 07:50:00'),
(1, 'Cuerpo Médico', 'INSERT', '1', NULL, '{"accion": "Asignacion de turnos semanales para Medicina General"}'::jsonb, '192.168.1.10', '2026-10-24 08:00:00'),
(2, 'Bloqueos Agenda', 'INSERT', '1', NULL, '{"accion": "Registro de feriado de Todos Santos 02/11/2026"}'::jsonb, '192.168.1.15', '2026-10-24 08:05:00'),

-- Cancelaciones Históricas de Citas (Citas 30 a 34 - Eventos CANCEL para Bitácora CU17)
(3, 'Citas Médicas', 'CANCEL', '30',
 '{"id_cita": 30, "codigo_cita": "CM-00030", "id_paciente": 8, "id_medico": 1, "estado": "Programada", "fecha_cita": "2026-10-19", "hora_inicio": "08:00:00"}'::jsonb,
 '{"id_cita": 30, "codigo_cita": "CM-00030", "estado": "Cancelada", "motivo": "Paciente solicita cancelación por viaje urgente fuera de la ciudad", "fecha_cancelacion": "2026-10-18 14:00:00"}'::jsonb,
 '192.168.1.20', '2026-10-18 14:00:00'),

(4, 'Citas Médicas', 'CANCEL', '31',
 '{"id_cita": 31, "codigo_cita": "CM-00031", "id_paciente": 9, "id_medico": 2, "estado": "Programada", "fecha_cita": "2026-10-20", "hora_inicio": "08:00:00"}'::jsonb,
 '{"id_cita": 31, "codigo_cita": "CM-00031", "estado": "Cancelada", "motivo": "Tutor del menor cancela por cruce de horario con actividad escolar", "fecha_cancelacion": "2026-10-19 10:30:00"}'::jsonb,
 '192.168.1.22', '2026-10-19 10:30:00'),

(3, 'Citas Médicas', 'CANCEL', '32',
 '{"id_cita": 32, "codigo_cita": "CM-00032", "id_paciente": 10, "id_medico": 3, "estado": "Programada", "fecha_cita": "2026-10-20", "hora_inicio": "14:00:00"}'::jsonb,
 '{"id_cita": 32, "codigo_cita": "CM-00032", "estado": "Cancelada", "motivo": "Paciente manifiesta mejoría y solicita postergar cita ginecológica", "fecha_cancelacion": "2026-10-19 16:15:00"}'::jsonb,
 '192.168.1.20', '2026-10-19 16:15:00'),

(4, 'Citas Médicas', 'CANCEL', '33',
 '{"id_cita": 33, "codigo_cita": "CM-00033", "id_paciente": 12, "id_medico": 5, "estado": "Programada", "fecha_cita": "2026-10-21", "hora_inicio": "14:00:00"}'::jsonb,
 '{"id_cita": 33, "codigo_cita": "CM-00033", "estado": "Cancelada", "motivo": "Paciente tuvo conflicto de horario laboral de última hora", "fecha_cancelacion": "2026-10-20 11:20:00"}'::jsonb,
 '192.168.1.22', '2026-10-20 11:20:00'),

(3, 'Citas Médicas', 'CANCEL', '34',
 '{"id_cita": 34, "codigo_cita": "CM-00034", "id_paciente": 13, "id_medico": 8, "estado": "Programada", "fecha_cita": "2026-10-22", "hora_inicio": "08:00:00"}'::jsonb,
 '{"id_cita": 34, "codigo_cita": "CM-00034", "estado": "Cancelada", "motivo": "Paciente prefirió reagendar tratamiento dental para el próximo mes", "fecha_cancelacion": "2026-10-21 09:00:00"}'::jsonb,
 '192.168.1.20', '2026-10-21 09:00:00'),

-- Modificaciones Históricas (Eventos UPDATE para Bitácora CU17)
(6, 'Ficha Médica', 'UPDATE', '1',
 '{"id_paciente": 1, "tipo_sangre": "O+", "alergias": "Ninguna conocida", "enfermedades_cronicas": "Ninguna reportada", "medicacion_actual": "Ninguna"}'::jsonb,
 '{"id_paciente": 1, "tipo_sangre": "O+", "alergias": "Penicilina (reacción cutánea leve)", "enfermedades_cronicas": "Hipertensión Grado I", "medicacion_actual": "Losartán 50mg c/24h"}'::jsonb,
 '192.168.1.30', '2026-10-22 10:30:00'),

(3, 'Citas Médicas', 'UPDATE', '36',
 '{"id_cita": 36, "codigo_cita": "CM-00036", "fecha_cita": "2026-10-26", "hora_inicio": "08:00:00", "hora_fin": "08:30:00", "estado": "Programada"}'::jsonb,
 '{"id_cita": 36, "codigo_cita": "CM-00036", "fecha_cita": "2026-10-26", "hora_inicio": "08:30:00", "hora_fin": "09:00:00", "estado": "Confirmada", "motivo": "Reprogramación por solicitud del paciente"}'::jsonb,
 '192.168.1.20', '2026-10-23 11:15:00'),

(10, 'Ficha Médica', 'UPDATE', '5',
 '{"id_paciente": 5, "tipo_sangre": "A+", "alergias": "Ninguna conocida", "enfermedades_cronicas": "Hipertensión", "medicacion_actual": "Enalapril 10mg"}'::jsonb,
 '{"id_paciente": 5, "tipo_sangre": "A+", "alergias": "Ninguna conocida", "enfermedades_cronicas": "Hipertensión Arterial Primaria", "medicacion_actual": "Losartán 50mg + Hidroclorotiazida 12.5mg"}'::jsonb,
 '192.168.1.35', '2026-10-23 15:45:00');

COMMIT;
