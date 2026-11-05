<?php
declare(strict_types=1);

namespace App\Core;

use Database;
use PDO;
use Throwable;

/**
 * Motor Inteligente de Consultas Clínicas por Voz y Lenguaje Natural (Gemini API + Intérprete Semántico SQL en Vivo).
 * Comprende las 13 tablas del esquema PostgreSQL de la Clínica Vida Salud.
 */
final class AiSqlReportService
{
    /**
     * Procesa una pregunta en lenguaje natural desde el Asistente Clínico Flotante
     * y devuelve una respuesta conversacional exacta en texto y voz.
     *
     * @return array{
     *   ok: bool,
     *   question: string,
     *   answer: string,
     *   speech_text: string,
     *   intent: string,
     *   engine: string,
     *   sql_used: string,
     *   data_preview: array<int, array<string, mixed>>
     * }
     */
    public static function answerClinicalQuestion(string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            $msg = 'Por favor, dime o escribe tu consulta sobre la Clínica Vida Salud. Puedo informarte sobre usuarios, roles, médicos, especialidades, horarios, pacientes, fichas médicas o citas.';
            return [
                'ok'           => false,
                'question'     => '',
                'answer'       => $msg,
                'speech_text'  => $msg,
                'intent'       => 'empty',
                'engine'       => 'Motor Analítico Clínico PostgreSQL (Tiempo Real)',
                'sql_used'     => '',
                'data_preview' => [],
            ];
        }

        $pdo = Database::getConnection();

        // 1. Si es un saludo corto ("hola", "buenos días", "ayuda"), responder de inmediato con datos en vivo
        $norm = self::normalize($question);
        if (preg_match('/^(hola|buenos dias|buenas tardes|buenas noches|buen dia|que tal|saludos|hey|ayuda|que puedes hacer|quien eres)[\s!?.]*$/', $norm)) {
            return self::resolveGreeting($question, $pdo);
        }

        // 2. Intentar primero con Gemini API si existe clave configurada
        $geminiResult = self::tryGeminiConversational($question, $pdo);
        if ($geminiResult !== null) {
            return $geminiResult;
        }

        // 3. Motor Analítico Local Comprensivo en Vivo sobre las 13 tablas PostgreSQL
        return self::resolveQuestionLocally($question, $pdo);
    }

    /**
     * Compatibilidad con llamadas previas.
     */
    public static function processNaturalLanguageQuery(string $prompt): array
    {
        $res = self::answerClinicalQuestion($prompt);
        return [
            'titulo_reporte'          => 'Consulta Clínica: ' . $res['intent'],
            'nombre_archivo_sugerido' => 'Consulta_Clinica',
            'sql_generado'            => $res['sql_used'],
            'explicacion_corta'       => $res['answer'],
            'resumen_voz'             => $res['speech_text'],
            'motor_usado'             => $res['engine'],
            'columnas'                => !empty($res['data_preview']) ? array_keys($res['data_preview'][0]) : [],
            'filas'                   => $res['data_preview'],
            'total_filas'             => count($res['data_preview']),
            'prompt_original'         => $prompt,
        ];
    }

    /**
     * Respuesta cálida e informativa cuando el usuario saluda ("hola", "buenos días", etc.).
     */
    private static function resolveGreeting(string $question, PDO $pdo): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM citas) AS total_citas,
                    (SELECT COUNT(*) FROM pacientes WHERE estado = 'Activo') AS pacientes_activos,
                    (SELECT COUNT(*) FROM medicos WHERE estado = 'Activo') AS medicos_activos,
                    (SELECT COUNT(*) FROM especialidades WHERE estado = 'Activo') AS especialidades_activas";
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        $answer = "¡Hola! Soy el **Asistente Clínico Inteligente de la Clínica Vida Salud**.\n"
                . "Estoy conectado en tiempo real a la base de datos (**{$row['pacientes_activos']} pacientes**, **{$row['medicos_activos']} médicos**, **{$row['especialidades_activas']} especialidades** y **{$row['total_citas']} citas**).\n\n"
                . "Puedes preguntarme por voz o texto, por ejemplo:\n"
                . "• *«¿Cuántas citas canceladas hay?»*\n"
                . "• *«¿Cuántos roles tenemos?»* o *«¿Quiénes son los administradores?»*\n"
                . "• *«¿Qué médicos atienden en pediatría?»*\n"
                . "• *«¿Qué pacientes tienen alergias?»*";

        $speech = "¡Hola! Soy el Asistente Clínico Inteligente de la Clínica Vida Salud. Estoy conectado en tiempo real con los datos de nuestros {$row['pacientes_activos']} pacientes, {$row['medicos_activos']} médicos y {$row['total_citas']} citas médicas. ¿Qué deseas consultar hoy?";

        return self::buildResult($question, $answer, $speech, 'saludo_asistente', $sql, [$row]);
    }

    /**
     * Intenta resolver la pregunta usando Google Gemini API (soporta API Key AIza... y tokens AQ... / Bearer).
     */
    private static function tryGeminiConversational(string $question, PDO $pdo): ?array
    {
        $apiKey = trim((string) env('GEMINI_API_KEY', ''));
        $model  = trim((string) env('GEMINI_MODEL', 'gemini-2.0-flash'));

        if ($apiKey === '' || !function_exists('curl_init')) {
            return null;
        }

        $today = date('Y-m-d');
        $systemInstruction = <<<PROMPT
Eres el Asistente Clínico Inteligente de la "Clínica Vida Salud" (Santa Cruz, Bolivia).
Fecha actual del servidor: {$today}.
Genera ÚNICAMENTE un objeto JSON válido (sin bloques markdown ```json) para responder la pregunta del usuario consultando la base de datos PostgreSQL.

ESQUEMA EXACTO DE LAS 13 TABLAS POSTGRESQL:
1. roles (id_rol, nombre, descripcion, estado, fecha_creacion)
2. usuarios (id_usuario, id_rol, nombres, apellidos, nombre_usuario, correo, telefono, contrasena_hash, estado, ultimo_acceso, fecha_creacion)
3. pacientes (id_paciente, id_usuario, codigo_paciente, ci, nombres, apellidos, fecha_nacimiento, sexo, direccion, telefono, correo, estado, fecha_registro)
4. fichas_medicas (id_ficha, id_paciente, tipo_sangre, alergias, enfermedades_base, medicamentos_actuales, antecedentes_quirurgicos, observaciones_medicas, ultima_actualizacion)
5. contactos_emergencia (id_contacto, id_paciente, nombre_completo, parentesco, telefono, direccion, es_principal)
6. especialidades (id_especialidad, codigo_especialidad, nombre, descripcion, estado, fecha_creacion)
7. medicos (id_medico, id_usuario, codigo_medico, registro_profesional, consultorio, biografia, estado, fecha_alta)
8. medico_especialidad (id_medico_especialidad, id_medico, id_especialidad, es_principal, fecha_asignacion)
9. horarios_atencion (id_horario, id_medico, id_especialidad, dia_semana [1=Lunes..6=Sábado], hora_inicio, hora_fin, duracion_minutos, cupo_maximo, estado)
10. citas (id_cita, codigo_cita, id_paciente, id_medico, id_especialidad, fecha_cita, hora_inicio, hora_fin, motivo_consulta, estado ['Programada','Confirmada','Atendida','Cancelada','Inasistencia'], observaciones_clinias, creado_por, fecha_registro)
11. cancelaciones_cita (id_cancelacion, id_cita, motivo_cancelacion, cancelado_por, fecha_cancelacion, observaciones)
12. bloqueos_agenda (id_bloqueo, id_medico, fecha_inicio, fecha_fin, motivo, creado_por, fecha_creacion)
13. auditorias (id_auditoria, id_usuario, modulo, accion, descripcion, ip_origen, fecha_evento)

REGLAS:
1. "sql_select" debe ser UNA sola sentencia SELECT válida en PostgreSQL que obtenga exactamente los datos que pide el usuario. Usa ILIKE para búsquedas de texto.
2. Responde en JSON con estas llaves:
{
  "intent": "etiqueta corta de la intención",
  "sql_select": "SELECT ...",
  "fallback_answer": "Respuesta en español en caso de que quieras explicar el contexto"
}
PROMPT;

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => "Pregunta del usuario: \"{$question}\""]],
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 900,
                'responseMimeType' => 'application/json',
            ],
        ];

        $rawResponse = self::callGeminiCurl($model, $apiKey, $payload);
        if ($rawResponse === null) {
            return null;
        }

        $decoded = json_decode($rawResponse, true);
        $textContent = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($textContent) || trim($textContent) === '') {
            return null;
        }

        $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($textContent));
        $aiData = json_decode((string) $cleanJson, true);
        if (!is_array($aiData) || empty($aiData['sql_select'])) {
            return null;
        }

        $sql = self::sanitizeReadOnlySql((string) $aiData['sql_select']);
        if ($sql === null) {
            return null;
        }

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $formatted = self::formatRowsAsNaturalSpanish($question, $rows, (string) ($aiData['intent'] ?? 'consulta_ia'));

            return [
                'ok'           => true,
                'question'     => $question,
                'answer'       => $formatted['markdown'],
                'speech_text'  => self::cleanForSpeech($formatted['speech']),
                'intent'       => (string) ($aiData['intent'] ?? 'gemini_sql'),
                'engine'       => 'Google Gemini AI (' . $model . ')',
                'sql_used'     => $sql,
                'data_preview' => array_slice($rows, 0, 15),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ejecuta petición HTTP a Gemini probando query param key y Authorization Bearer si el token empieza con AQ.
     */
    private static function callGeminiCurl(string $model, string $apiKey, array $payload): ?string
    {
        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $baseUrl  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        // Intento 1: Con ?key=
        $ch = curl_init($baseUrl . '?key=' . urlencode($apiKey));
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (is_string($res) && $code >= 200 && $code < 300) {
            return $res;
        }

        // Intento 2: Si el token empieza por AQ. o falló con 400/401/403, intentar con Bearer Header
        if (str_starts_with($apiKey, 'AQ.')) {
            $ch2 = curl_init($baseUrl);
            if ($ch2 !== false) {
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey,
                        'x-goog-api-key: ' . $apiKey,
                    ],
                    CURLOPT_POSTFIELDS     => $jsonBody,
                    CURLOPT_TIMEOUT        => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $res2  = curl_exec($ch2);
                $code2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                if (is_string($res2) && $code2 >= 200 && $code2 < 300) {
                    return $res2;
                }
            }
        }

        return null;
    }

    /**
     * Motor Analítico Local Comprensivo en PHP:
     * Analiza intenciones y entidades en español y consulta PostgreSQL en vivo sobre las 13 tablas.
     */
    private static function resolveQuestionLocally(string $question, PDO $pdo): array
    {
        $norm = self::normalize($question);

        // =====================================================================
        // 1. PREGUNTAS SOBRE ROLES Y USUARIOS DEL SISTEMA (Tablas: roles, usuarios)
        // =====================================================================

        // 1.A: ¿Cuántos roles tenemos? / ¿Qué roles existen en el sistema?
        if (
            preg_match('/\b(rol|roles|perfiles)\b/', $norm)
            && !preg_match('/\b(administrador|administradores|recepcionista|recepcionistas)\b/', $norm)
        ) {
            $sql = "SELECT r.id_rol, r.nombre, r.descripcion, r.estado,
                           COUNT(u.id_usuario) AS total_usuarios,
                           COUNT(u.id_usuario) FILTER (WHERE u.estado = 'Activo') AS usuarios_activos
                    FROM roles r
                    LEFT JOIN usuarios u ON u.id_rol = r.id_rol
                    GROUP BY r.id_rol, r.nombre, r.descripcion, r.estado
                    ORDER BY r.id_rol ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $totalRoles = count($rows);
            $totalUsuarios = array_sum(array_map(static fn($r) => (int) $r['total_usuarios'], $rows));

            $detallesMd = [];
            $detallesVoz = [];
            foreach ($rows as $r) {
                $cant = (int) $r['total_usuarios'];
                $detallesMd[] = "• **{$r['nombre']}**: {$cant} usuario(s) ({$r['descripcion']})";
                $detallesVoz[] = "{$r['nombre']} con {$cant} " . ($cant === 1 ? 'usuario' : 'usuarios');
            }

            $answer = "En el sistema tenemos **{$totalRoles} roles** registrados (con un total de **{$totalUsuarios} usuarios** asignados):\n" . implode("\n", $detallesMd);
            $speech = "Actualmente tenemos {$totalRoles} roles en el sistema: " . implode(', ', $detallesVoz) . ", sumando {$totalUsuarios} usuarios en total.";

            return self::buildResult($question, $answer, $speech, 'roles_sistema', $sql, $rows);
        }

        // 1.B: ¿Cuántos administradores tenemos? / ¿Quiénes son los administradores?
        if (preg_match('/\b(administrador|administradores|admin|admins)\b/', $norm)) {
            $sql = "SELECT u.id_usuario, u.nombres, u.apellidos, u.nombre_usuario, u.correo, u.telefono, u.estado
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol = u.id_rol
                    WHERE r.nombre ILIKE '%Administrador%'
                    ORDER BY u.id_usuario ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            $nombresMd = [];
            $nombresVoz = [];
            foreach ($rows as $u) {
                $fullName = trim($u['nombres'] . ' ' . $u['apellidos']);
                $nombresMd[] = "• **{$fullName}** (usuario: `{$u['nombre_usuario']}`, correo: {$u['correo']}, estado: {$u['estado']})";
                $nombresVoz[] = "{$fullName} con usuario {$u['nombre_usuario']}";
            }

            $answer = "Contamos con **{$count} " . ($count === 1 ? 'administrador' : 'administradores') . "** en el sistema:\n" . implode("\n", $nombresMd);
            $speech = "Tenemos {$count} " . ($count === 1 ? 'administrador registrado' : 'administradores registrados') . " en el sistema: " . implode(' y ', $nombresVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'usuarios_administradores', $sql, $rows);
        }

        // 1.C: ¿Quién es la recepcionista? / ¿Cuántos recepcionistas hay?
        if (preg_match('/\b(recepcionista|recepcionistas|recepcion)\b/', $norm)) {
            $sql = "SELECT u.id_usuario, u.nombres, u.apellidos, u.nombre_usuario, u.correo, u.telefono, u.estado
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol = u.id_rol
                    WHERE r.nombre ILIKE '%Recepcionista%'
                    ORDER BY u.id_usuario ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            $nombresMd = [];
            $nombresVoz = [];
            foreach ($rows as $u) {
                $fullName = trim($u['nombres'] . ' ' . $u['apellidos']);
                $nombresMd[] = "• **{$fullName}** (usuario: `{$u['nombre_usuario']}`, tel: {$u['telefono']}, estado: {$u['estado']})";
                $nombresVoz[] = $fullName;
            }

            $answer = "En la clínica trabajan **{$count} " . ($count === 1 ? 'recepcionista' : 'recepcionistas') . "**:\n" . implode("\n", $nombresMd);
            $speech = "Contamos con {$count} " . ($count === 1 ? 'recepcionista' : 'recepcionistas') . " en la clínica: " . implode(', ', $nombresVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'usuarios_recepcionistas', $sql, $rows);
        }

        // 1.D: ¿Cuántos usuarios hay en el sistema? / Lista de usuarios
        if (preg_match('/\b(usuario|usuarios|cuentas)\b/', $norm)) {
            $sql = "SELECT u.id_usuario, u.nombres || ' ' || u.apellidos AS nombre_completo,
                           u.nombre_usuario, r.nombre AS rol, u.correo, u.estado
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol = u.id_rol
                    ORDER BY r.id_rol ASC, u.apellidos ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            $activos = count(array_filter($rows, static fn($r) => ($r['estado'] ?? '') === 'Activo'));

            $porRol = [];
            foreach ($rows as $r) {
                $rol = (string) $r['rol'];
                $porRol[$rol] = ($porRol[$rol] ?? 0) + 1;
            }
            $desgloseMd = [];
            $desgloseVoz = [];
            foreach ($porRol as $rol => $cant) {
                $desgloseMd[] = "• **{$rol}**: {$cant}";
                $desgloseVoz[] = "{$cant} con rol {$rol}";
            }

            $answer = "Hay un total de **{$total} usuarios** registrados en el sistema (**{$activos} activos**), distribuidos así:\n" . implode("\n", $desgloseMd);
            $speech = "El sistema tiene {$total} usuarios registrados, de los cuales {$activos} están activos: " . implode(', ', $desgloseVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'usuarios_sistema', $sql, $rows);
        }

        // =====================================================================
        // 2. MÉDICOS, ESPECIALIDADES, HORARIOS Y BLOQUEOS
        //    (Tablas: medicos, especialidades, medico_especialidad, horarios_atencion, bloqueos_agenda)
        // =====================================================================

        // 2.A: Bloqueos de agenda / horario
        if (preg_match('/\b(bloqueo|bloqueos|ausencia|vacacion|permiso|licencia)\b/', $norm)) {
            $sql = "SELECT b.id_bloqueo, u.nombres || ' ' || u.apellidos AS medico,
                           b.fecha_inicio, b.fecha_fin, b.motivo
                    FROM bloqueos_agenda b
                    INNER JOIN medicos m ON m.id_medico = b.id_medico
                    INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                    ORDER BY b.fecha_inicio DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            if ($count === 0) {
                $answer = "No existen bloqueos de agenda registrados actualmente.";
                $speech = $answer;
            } else {
                $itemsMd = [];
                $itemsVoz = [];
                foreach ($rows as $b) {
                    $fi = date('d/m/Y H:i', strtotime((string) $b['fecha_inicio']));
                    $ff = date('d/m/Y H:i', strtotime((string) $b['fecha_fin']));
                    $itemsMd[] = "• **Dr(a). {$b['medico']}**: del {$fi} al {$ff} — Motivo: *{$b['motivo']}*";
                    $itemsVoz[] = "Doctor {$b['medico']} por motivo de {$b['motivo']}";
                }
                $answer = "Existen **{$count} bloqueos de agenda** registrados:\n" . implode("\n", $itemsMd);
                $speech = "Hay {$count} bloqueos de agenda registrados: " . implode('; ', $itemsVoz) . ".";
            }

            return self::buildResult($question, $answer, $speech, 'bloqueos_agenda', $sql, $rows);
        }

        // 2.B: Horarios de atención (de un médico específico, de un día o generales)
        if (preg_match('/\b(horario|horarios|turno|turnos|atiende|atienden|dias de atencion)\b/', $norm)) {
            $medicoMatch = self::findMatchingDoctor($norm, $pdo);
            $diaMatch    = self::detectDayOfWeek($norm);
            $espMatch    = self::findMatchingSpecialty($norm, $pdo);

            // Si pregunta "¿Qué médicos atienden en [Especialidad]?" sin pedir horario específico
            if ($espMatch !== null && $medicoMatch === null && $diaMatch === null && !preg_match('/\b(horario|horarios|turno|turnos|hora)\b/', $norm)) {
                return self::queryDoctorsBySpecialty($question, $espMatch, $pdo);
            }

            $where = ["h.estado = 'Activo'"];
            $params = [];

            if ($medicoMatch !== null) {
                $where[] = "h.id_medico = :id_medico";
                $params[':id_medico'] = (int) $medicoMatch['id_medico'];
            }
            if ($diaMatch !== null) {
                $where[] = "h.dia_semana = :dia_semana";
                $params[':dia_semana'] = $diaMatch['num'];
            }
            if ($espMatch !== null) {
                $where[] = "h.id_especialidad = :id_esp";
                $params[':id_esp'] = (int) $espMatch['id_especialidad'];
            }

            $whereSql = implode(' AND ', $where);
            $sql = "SELECT h.id_horario, m.codigo_medico, u.nombres || ' ' || u.apellidos AS medico,
                           e.nombre AS especialidad, m.consultorio, h.dia_semana,
                           CASE h.dia_semana
                               WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles'
                               WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado'
                               ELSE 'Domingo'
                           END AS dia_nombre,
                           TO_CHAR(h.hora_inicio, 'HH24:MI') AS hora_inicio,
                           TO_CHAR(h.hora_fin, 'HH24:MI') AS hora_fin,
                           h.duracion_minutos, h.cupo_maximo
                    FROM horarios_atencion h
                    INNER JOIN medicos m ON m.id_medico = h.id_medico
                    INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                    INNER JOIN especialidades e ON e.id_especialidad = h.id_especialidad
                    WHERE {$whereSql}
                    ORDER BY u.apellidos ASC, h.dia_semana ASC, h.hora_inicio ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            if ($medicoMatch !== null) {
                $docName = $medicoMatch['nombres'] . ' ' . $medicoMatch['apellidos'];
                if ($count === 0) {
                    $answer = "El/la **Dr(a). {$docName}** no tiene turnos activos registrados con ese criterio.";
                    $speech = "El doctor {$docName} no tiene horarios activos registrados con ese criterio.";
                } else {
                    $turnosMd = [];
                    $turnosVoz = [];
                    foreach ($rows as $r) {
                        $turnosMd[] = "• **{$r['dia_nombre']}**: {$r['hora_inicio']} a {$r['hora_fin']} ({$r['especialidad']} - {$r['consultorio']})";
                        $turnosVoz[] = "{$r['dia_nombre']} de {$r['hora_inicio']} a {$r['hora_fin']}";
                    }
                    $answer = "El horario de atención de **Dr(a). {$docName}** ({$rows[0]['especialidad']}, {$rows[0]['consultorio']}) es:\n" . implode("\n", $turnosMd);
                    $speech = "El doctor {$docName} atiende en {$rows[0]['especialidad']} los días: " . implode(', ', $turnosVoz) . ".";
                }
                return self::buildResult($question, $answer, $speech, 'horario_medico', $sql, $rows);
            }

            if ($diaMatch !== null) {
                $diaNom = $diaMatch['nombre'];
                $turnosMd = [];
                $turnosVoz = [];
                foreach ($rows as $r) {
                    $turnosMd[] = "• **Dr(a). {$r['medico']}** ({$r['especialidad']}): {$r['hora_inicio']} a {$r['hora_fin']} — {$r['consultorio']}";
                    $turnosVoz[] = "Doctor {$r['medico']} en {$r['especialidad']} de {$r['hora_inicio']} a {$r['hora_fin']}";
                }
                $answer = "Los días **{$diaNom}** tenemos **{$count} turnos médicos** activos:\n" . implode("\n", $turnosMd);
                $speech = "Los días {$diaNom} atienden {$count} turnos médicos: " . implode('; ', array_slice($turnosVoz, 0, 5)) . ".";
                return self::buildResult($question, $answer, $speech, 'horario_dia', $sql, $rows);
            }

            // Resumen general de horarios
            $medicosConHorario = count(array_unique(array_column($rows, 'codigo_medico')));
            $ejemplosMd = [];
            foreach (array_slice($rows, 0, 8) as $r) {
                $ejemplosMd[] = "• **Dr(a). {$r['medico']}** ({$r['especialidad']}): {$r['dia_nombre']} {$r['hora_inicio']}-{$r['hora_fin']}";
            }
            $answer = "La clínica cuenta con **{$count} turnos de atención vigentes** distribuidos entre **{$medicosConHorario} médicos** de lunes a sábado:\n" . implode("\n", $ejemplosMd);
            $speech = "La clínica tiene {$count} turnos de atención vigentes de lunes a sábado cubiertos por {$medicosConHorario} médicos especialistas. Puedes preguntarme por el horario de un médico o día específico.";

            return self::buildResult($question, $answer, $speech, 'horarios_vigentes', $sql, $rows);
        }

        // 2.C: ¿Qué médicos atienden en [Especialidad]? o búsqueda de un médico específico
        $espMatch = self::findMatchingSpecialty($norm, $pdo);
        if ($espMatch !== null && preg_match('/\b(medico|medicos|doctor|doctores|doctora|especialista|especialistas|quien|quienes|cuales)\b/', $norm) && !preg_match('/\b(cita|citas)\b/', $norm)) {
            return self::queryDoctorsBySpecialty($question, $espMatch, $pdo);
        }

        // 2.D: ¿Qué especialidades tiene la clínica? / ¿Cuántas especialidades hay?
        if (
            preg_match('/\b(especialidad|especialidades)\b/', $norm)
            && !preg_match('/\b(cita|citas)\b/', $norm)
        ) {
            $sql = "SELECT e.id_especialidad, e.codigo_especialidad, e.nombre, e.descripcion, e.estado,
                           COUNT(DISTINCT me.id_medico) AS total_medicos,
                           COUNT(DISTINCT c.id_cita) AS total_citas
                    FROM especialidades e
                    LEFT JOIN medico_especialidad me ON me.id_especialidad = e.id_especialidad
                    LEFT JOIN citas c ON c.id_especialidad = e.id_especialidad
                    GROUP BY e.id_especialidad, e.codigo_especialidad, e.nombre, e.descripcion, e.estado
                    ORDER BY e.nombre ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            $listaMd = [];
            $listaVoz = [];
            foreach ($rows as $e) {
                $listaMd[] = "• **{$e['nombre']}** (`{$e['codigo_especialidad']}`): {$e['total_medicos']} médico(s), {$e['total_citas']} citas registradas";
                $listaVoz[] = "{$e['nombre']} con {$e['total_medicos']} médicos";
            }

            $answer = "La Clínica Vida Salud cuenta con **{$count} especialidades médicas** activas:\n" . implode("\n", $listaMd);
            $speech = "La clínica cuenta con {$count} especialidades médicas: " . implode(', ', $listaVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'especialidades_clinica', $sql, $rows);
        }

        // 2.E: ¿Cuántos médicos hay? / Directorio de médicos / Información de un médico específico
        if (
            preg_match('/\b(medico|medicos|doctor|doctores|doctora|plantel|consultorio|consultorios)\b/', $norm)
            && !preg_match('/\b(cita|citas)\b/', $norm)
        ) {
            $medicoMatch = self::findMatchingDoctor($norm, $pdo);
            if ($medicoMatch !== null) {
                $idMed = (int) $medicoMatch['id_medico'];
                $sql = "SELECT m.codigo_medico, u.nombres, u.apellidos, m.registro_profesional,
                               m.consultorio, m.biografia, m.estado, u.telefono, u.correo,
                               COALESCE(STRING_AGG(DISTINCT e.nombre, ', '), 'General') AS especialidades,
                               (SELECT COUNT(*) FROM citas c WHERE c.id_medico = m.id_medico) AS total_citas
                        FROM medicos m
                        INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                        LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
                        LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
                        WHERE m.id_medico = {$idMed}
                        GROUP BY m.id_medico, u.id_usuario";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $doc = $rows[0];
                $fullName = $doc['nombres'] . ' ' . $doc['apellidos'];
                $answer = "**Dr(a). {$fullName}** (`{$doc['codigo_medico']}`)\n"
                        . "• **Especialidad:** {$doc['especialidades']}\n"
                        . "• **Matrícula:** {$doc['registro_profesional']} | **Consultorio:** {$doc['consultorio']}\n"
                        . "• **Contacto:** Tel. {$doc['telefono']} | {$doc['correo']}\n"
                        . "• **Citas históricas:** {$doc['total_citas']} | **Estado:** {$doc['estado']}";
                $speech = "El doctor {$fullName}, con código {$doc['codigo_medico']}, es especialista en {$doc['especialidades']}, atiende en el {$doc['consultorio']}, su teléfono es {$doc['telefono']} y registra {$doc['total_citas']} citas.";
                return self::buildResult($question, $answer, $speech, 'detalle_medico', $sql, $rows);
            }

            $sql = "SELECT m.codigo_medico, u.nombres || ' ' || u.apellidos AS medico,
                           m.registro_profesional, m.consultorio, m.estado,
                           COALESCE(STRING_AGG(DISTINCT e.nombre, ', '), 'Sin asignar') AS especialidades
                    FROM medicos m
                    INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                    LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
                    LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
                    GROUP BY m.id_medico, u.nombres, u.apellidos
                    ORDER BY u.apellidos ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            $listaMd = [];
            $listaVoz = [];
            foreach ($rows as $m) {
                $listaMd[] = "• **Dr(a). {$m['medico']}** (`{$m['codigo_medico']}`) — {$m['especialidades']} ({$m['consultorio']})";
                $listaVoz[] = "Doctor {$m['medico']} en {$m['especialidades']}";
            }

            $answer = "Contamos con **{$count} médicos especialistas** activos en la clínica:\n" . implode("\n", $listaMd);
            $speech = "En la clínica trabajan {$count} médicos especialistas: " . implode(', ', $listaVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'directorio_medicos', $sql, $rows);
        }

        // =====================================================================
        // 3. PACIENTES, FICHAS MÉDICAS Y CONTACTOS DE EMERGENCIA
        //    (Tablas: pacientes, fichas_medicas, contactos_emergencia)
        // =====================================================================

        // 3.A: Búsqueda o consulta de un paciente específico por nombre, apellido, CI o código
        $pacienteMatch = self::findMatchingPatient($norm, $pdo);
        if (
            $pacienteMatch !== null
            && !preg_match('/\b(cita|citas)\b/', $norm)
        ) {
            $idPac = (int) $pacienteMatch['id_paciente'];
            $sql = "SELECT p.id_paciente, p.codigo_paciente, p.ci, p.nombres, p.apellidos,
                           DATE_PART('year', AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS edad,
                           p.sexo, p.telefono, p.correo, p.direccion, p.estado,
                           COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                           COALESCE(fm.alergias, 'Ninguna registrada') AS alergias,
                           COALESCE(fm.enfermedades_base, 'Ninguna') AS enfermedades_base,
                           COALESCE(fm.medicamentos_actuales, 'Ninguno') AS medicamentos_actuales,
                           COALESCE(fm.antecedentes_quirurgicos, 'Ninguno') AS antecedentes_quirurgicos,
                           ce.nombre_completo AS contacto_emergencia,
                           ce.parentesco AS contacto_parentesco,
                           ce.telefono AS contacto_telefono,
                           (SELECT COUNT(*) FROM citas c WHERE c.id_paciente = p.id_paciente) AS total_citas
                    FROM pacientes p
                    LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
                    LEFT JOIN contactos_emergencia ce ON ce.id_paciente = p.id_paciente AND ce.es_principal = TRUE
                    WHERE p.id_paciente = {$idPac}";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $pac = $rows[0];
            $fullName = $pac['nombres'] . ' ' . $pac['apellidos'];

            $answer = "Expediente de **{$fullName}** (`{$pac['codigo_paciente']}` — CI: **{$pac['ci']}**):\n"
                    . "• **Edad / Sexo:** {$pac['edad']} años ({$pac['sexo']}) | **Tel:** {$pac['telefono']}\n"
                    . "• **Tipo de Sangre:** `{$pac['tipo_sangre']}`\n"
                    . "• **Alergias:** {$pac['alergias']}\n"
                    . "• **Enfermedades de Base:** {$pac['enfermedades_base']}\n"
                    . "• **Medicamentos Actuales:** {$pac['medicamentos_actuales']}\n"
                    . "• **Contacto de Emergencia:** {$pac['contacto_emergencia']} ({$pac['contacto_parentesco']}) — Tel. {$pac['contacto_telefono']}\n"
                    . "• **Citas registradas:** {$pac['total_citas']}";

            $speech = "El paciente {$fullName}, con carnet {$pac['ci']} y código {$pac['codigo_paciente']}, tiene {$pac['edad']} años, grupo sanguíneo {$pac['tipo_sangre']}, alergias: {$pac['alergias']}, enfermedades de base: {$pac['enfermedades_base']}, y su contacto de emergencia es {$pac['contacto_emergencia']} al teléfono {$pac['contacto_telefono']}.";

            return self::buildResult($question, $answer, $speech, 'ficha_paciente', $sql, $rows);
        }

        // 3.B: Preguntas sobre alergias, tipos de sangre, enfermedades crónicas o fichas médicas en general
        if (preg_match('/\b(alergia|alergias|alergico|alergicos|tipo de sangre|grupo sanguineo|sangre|ficha medica|fichas medicas|hipertension|diabetes|asma|enfermedades base)\b/', $norm)) {
            if (preg_match('/\b(alergia|alergias|alergico|alergicos)\b/', $norm)) {
                $sql = "SELECT p.codigo_paciente, p.nombres || ' ' || p.apellidos AS paciente,
                               p.ci, fm.tipo_sangre, fm.alergias, fm.enfermedades_base
                        FROM pacientes p
                        INNER JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
                        WHERE fm.alergias IS NOT NULL
                          AND fm.alergias NOT ILIKE '%ninguna%'
                          AND TRIM(fm.alergias) <> ''
                        ORDER BY p.apellidos ASC";
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                $count = count($rows);

                $listaMd = [];
                $listaVoz = [];
                foreach ($rows as $r) {
                    $listaMd[] = "• **{$r['paciente']}** (`{$r['tipo_sangre']}`): *{$r['alergias']}*";
                    $listaVoz[] = "{$r['paciente']} con alergia a {$r['alergias']}";
                }

                $answer = "Tenemos **{$count} pacientes con alergias medicamentosas o clínicas** registradas en su ficha médica:\n" . implode("\n", array_slice($listaMd, 0, 12));
                $speech = "Hay {$count} pacientes con alergias registradas en sus fichas médicas. Entre ellos: " . implode('; ', array_slice($listaVoz, 0, 5)) . ".";

                return self::buildResult($question, $answer, $speech, 'pacientes_con_alergias', $sql, $rows);
            }

            // Distribución por tipo de sangre
            $sql = "SELECT COALESCE(fm.tipo_sangre, 'Desconocido') AS tipo_sangre,
                           COUNT(*) AS cantidad
                    FROM pacientes p
                    LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
                    GROUP BY COALESCE(fm.tipo_sangre, 'Desconocido')
                    ORDER BY cantidad DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $itemsMd = [];
            $itemsVoz = [];
            foreach ($rows as $r) {
                $itemsMd[] = "• Grupo **{$r['tipo_sangre']}**: {$r['cantidad']} paciente(s)";
                $itemsVoz[] = "{$r['cantidad']} con grupo {$r['tipo_sangre']}";
            }
            $answer = "Distribución de pacientes por **grupo sanguíneo** en fichas médicas:\n" . implode("\n", $itemsMd);
            $speech = "La distribución por tipo de sangre de nuestros pacientes es: " . implode(', ', $itemsVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'distribucion_tipo_sangre', $sql, $rows);
        }

        // 3.C: Contactos de emergencia
        if (preg_match('/\b(contacto de emergencia|contactos de emergencia|emergencia)\b/', $norm)) {
            $sql = "SELECT p.codigo_paciente, p.nombres || ' ' || p.apellidos AS paciente,
                           ce.nombre_completo AS contacto, ce.parentesco, ce.telefono
                    FROM contactos_emergencia ce
                    INNER JOIN pacientes p ON p.id_paciente = ce.id_paciente
                    ORDER BY p.apellidos ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);
            $previewMd = [];
            foreach (array_slice($rows, 0, 8) as $r) {
                $previewMd[] = "• **{$r['paciente']}** -> Contacto: {$r['contacto']} ({$r['parentesco']}, Tel: {$r['telefono']})";
            }
            $answer = "Existen **{$count} contactos de emergencia** registrados para los pacientes:\n" . implode("\n", $previewMd);
            $speech = "Tenemos {$count} contactos de emergencia vinculados al padrón de pacientes. Puedes preguntarme por el contacto de emergencia de cualquier paciente por su nombre.";
            return self::buildResult($question, $answer, $speech, 'contactos_emergencia', $sql, $rows);
        }

        // 3.D: ¿Cuántos pacientes están registrados? / Padrón de pacientes
        if (
            preg_match('/\b(paciente|pacientes|padron|expediente|expedientes)\b/', $norm)
            && !preg_match('/\b(cita|citas)\b/', $norm)
        ) {
            $sql = "SELECT p.codigo_paciente, p.ci, p.nombres || ' ' || p.apellidos AS paciente,
                           DATE_PART('year', AGE(CURRENT_DATE, p.fecha_nacimiento))::int AS edad,
                           p.sexo, p.telefono, p.estado, COALESCE(fm.tipo_sangre, 'O+') AS tipo_sangre
                    FROM pacientes p
                    LEFT JOIN fichas_medicas fm ON fm.id_paciente = p.id_paciente
                    ORDER BY p.id_paciente ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);
            $activos = count(array_filter($rows, static fn($r) => ($r['estado'] ?? '') === 'Activo'));
            $mujeres = count(array_filter($rows, static fn($r) => ($r['sexo'] ?? '') === 'F'));
            $hombres = count(array_filter($rows, static fn($r) => ($r['sexo'] ?? '') === 'M'));

            $muestraMd = [];
            foreach (array_slice($rows, 0, 6) as $r) {
                $muestraMd[] = "• **{$r['paciente']}** (`{$r['codigo_paciente']}`, CI {$r['ci']}, {$r['edad']} años, sangre {$r['tipo_sangre']})";
            }

            $answer = "En la clínica están registrados **{$total} pacientes** (**{$activos} activos**, {$mujeres} mujeres y {$hombres} hombres), cada uno con su ficha médica y contacto de emergencia. Algunos registros:\n" . implode("\n", $muestraMd);
            $speech = "Actualmente tenemos {$total} pacientes registrados en el padrón clínico, de los cuales {$activos} están activos: {$mujeres} mujeres y {$hombres} hombres. Puedes preguntarme los datos o alergias de cualquier paciente por su nombre o carnet.";

            return self::buildResult($question, $answer, $speech, 'padron_pacientes', $sql, $rows);
        }

        // =====================================================================
        // 4. AUDITORÍA DEL SISTEMA Y CANCELACIONES DE CITAS
        //    (Tablas: auditorias, cancelaciones_cita)
        // =====================================================================

        // 4.A: Auditoría del sistema
        if (preg_match('/\b(auditoria|auditorias|bitacora|logs|eventos del sistema)\b/', $norm)) {
            $sql = "SELECT a.id_auditoria, COALESCE(u.nombre_usuario, 'sistema') AS usuario,
                           a.modulo, a.accion, a.descripcion, a.ip_origen,
                           TO_CHAR(a.fecha_evento, 'DD/MM/YYYY HH24:MI') AS fecha
                    FROM auditorias a
                    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
                    ORDER BY a.fecha_evento DESC
                    LIMIT 20";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $totalAudit = (int) $pdo->query("SELECT COUNT(*) FROM auditorias")->fetchColumn();

            $itemsMd = [];
            foreach (array_slice($rows, 0, 6) as $a) {
                $itemsMd[] = "• `[{$a['fecha']}]` **{$a['modulo']}** ({$a['accion']}) por `{$a['usuario']}`: {$a['descripcion']}";
            }

            $answer = "La bitácora de auditoría registra un total de **{$totalAudit} eventos de seguridad**. Últimos movimientos:\n" . implode("\n", $itemsMd);
            $speech = "La tabla de auditorías tiene {$totalAudit} eventos registrados. El último evento corresponde al módulo {$rows[0]['modulo']} con la acción {$rows[0]['accion']}.";

            return self::buildResult($question, $answer, $speech, 'auditoria_sistema', $sql, $rows);
        }

        // 4.B: Cancelaciones documentadas (motivos de cancelación)
        if (preg_match('/\b(motivo de cancelacion|motivos de cancelacion|cancelaciones documentadas|por que se cancelaron|quien cancelo)\b/', $norm)) {
            $sql = "SELECT cc.id_cancelacion, c.codigo_cita, c.fecha_cita,
                           p.nombres || ' ' || p.apellidos AS paciente,
                           cc.motivo_cancelacion, cc.observaciones,
                           TO_CHAR(cc.fecha_cancelacion, 'DD/MM/YYYY HH24:MI') AS fecha_cancelacion
                    FROM cancelaciones_cita cc
                    INNER JOIN citas c ON c.id_cita = cc.id_cita
                    INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
                    ORDER BY cc.fecha_cancelacion DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $count = count($rows);

            $itemsMd = [];
            $itemsVoz = [];
            foreach ($rows as $r) {
                $itemsMd[] = "• Cita **{$r['codigo_cita']}** ({$r['paciente']}): *{$r['motivo_cancelacion']}*";
                $itemsVoz[] = "cita {$r['codigo_cita']} de {$r['paciente']} por {$r['motivo_cancelacion']}";
            }

            $answer = "Se registran **{$count} cancelaciones documentadas** en `cancelaciones_cita`:\n" . implode("\n", $itemsMd);
            $speech = "Existen {$count} cancelaciones documentadas: " . implode('; ', array_slice($itemsVoz, 0, 4)) . ".";

            return self::buildResult($question, $answer, $speech, 'cancelaciones_cita', $sql, $rows);
        }

        // =====================================================================
        // 5. CITAS MÉDICAS Y ESTADÍSTICAS (Tabla: citas)
        // =====================================================================
        if (preg_match('/\b(cita|citas|consulta|consultas|agenda|atendida|atendidas|programada|programadas|confirmada|confirmadas|cancelada|canceladas|inasistencia|inasistencias|pendiente|pendientes|hoy|manana|ayer)\b/', $norm)) {
            return self::resolveCitasQuestion($question, $norm, $pdo);
        }

        // =====================================================================
        // 6. RESUMEN GENERAL DE LA CLÍNICA O CONSULTA ABIERTA
        // =====================================================================
        return self::resolveGeneralSummary($question, $pdo);
    }

    /**
     * Resuelve preguntas específicas sobre Citas Médicas (por fecha, estado, médico, paciente o especialidad).
     */
    private static function resolveCitasQuestion(string $question, string $norm, PDO $pdo): array
    {
        $medicoMatch   = self::findMatchingDoctor($norm, $pdo);
        $pacienteMatch = self::findMatchingPatient($norm, $pdo);
        $espMatch      = self::findMatchingSpecialty($norm, $pdo);

        // Detectar estados pedidos
        $estados = [];
        if (preg_match('/\b(programada|programadas)\b/', $norm)) {
            $estados[] = 'Programada';
        }
        if (preg_match('/\b(confirmada|confirmadas)\b/', $norm)) {
            $estados[] = 'Confirmada';
        }
        if (preg_match('/\b(pendiente|pendientes|activa|activas|proxima|proximas)\b/', $norm)) {
            $estados[] = 'Programada';
            $estados[] = 'Confirmada';
        }
        if (preg_match('/\b(atendida|atendidas|completada|completadas|realizada|realizadas)\b/', $norm)) {
            $estados[] = 'Atendida';
        }
        if (preg_match('/\b(cancelada|canceladas|anuladas)\b/', $norm)) {
            $estados[] = 'Cancelada';
        }
        if (preg_match('/\b(inasistencia|inasistencias|ausente|ausentes|no asistio)\b/', $norm)) {
            $estados[] = 'Inasistencia';
        }
        $estados = array_values(array_unique($estados));

        // Detectar filtro de fecha
        $fechaLabel = '';
        $where = ['1=1'];
        $params = [];

        if (preg_match('/\bhoy\b/', $norm)) {
            $where[] = 'c.fecha_cita = CURRENT_DATE';
            $fechaLabel = 'para hoy (' . date('d/m/Y') . ')';
        } elseif (preg_match('/\bmanana\b/', $norm)) {
            $where[] = "c.fecha_cita = (CURRENT_DATE + INTERVAL '1 day')::date";
            $fechaLabel = 'para mañana';
        } elseif (preg_match('/\bayer\b/', $norm)) {
            $where[] = "c.fecha_cita = (CURRENT_DATE - INTERVAL '1 day')::date";
            $fechaLabel = 'de ayer';
        }

        if ($medicoMatch !== null) {
            $where[] = 'c.id_medico = :id_medico';
            $params[':id_medico'] = (int) $medicoMatch['id_medico'];
        }
        if ($pacienteMatch !== null) {
            $where[] = 'c.id_paciente = :id_paciente';
            $params[':id_paciente'] = (int) $pacienteMatch['id_paciente'];
        }
        if ($espMatch !== null) {
            $where[] = 'c.id_especialidad = :id_esp';
            $params[':id_esp'] = (int) $espMatch['id_especialidad'];
        }
        if (!empty($estados)) {
            $quoted = array_map(static fn(string $st) => "'" . $st . "'", $estados);
            $where[] = 'c.estado IN (' . implode(', ', $quoted) . ')';
        }

        // Si menciona varios estados (ej. "atendidas, programadas o canceladas") o no filtró por ninguno en específico, dar desglose global por estado
        if ($medicoMatch === null && $pacienteMatch === null && $espMatch === null && $fechaLabel === '' && (empty($estados) || count($estados) >= 2)) {
            $sql = "SELECT estado, COUNT(*) AS total
                    FROM citas
                    GROUP BY estado
                    ORDER BY total DESC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $totalCitas = array_sum(array_map(static fn($r) => (int) $r['total'], $rows));

            $desgloseMd = [];
            $desgloseVoz = [];
            foreach ($rows as $r) {
                $desgloseMd[] = "• **{$r['estado']}**: {$r['total']} citas";
                $desgloseVoz[] = "{$r['total']} en estado {$r['estado']}";
            }

            $answer = "En el sistema existen **{$totalCitas} citas médicas** registradas en total, distribuidas por estado así:\n" . implode("\n", $desgloseMd);
            $speech = "Actualmente hay {$totalCitas} citas médicas registradas en total: " . implode(', ', $desgloseVoz) . ".";

            return self::buildResult($question, $answer, $speech, 'resumen_citas_estados', $sql, $rows);
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT c.codigo_cita, TO_CHAR(c.fecha_cita, 'DD/MM/YYYY') AS fecha,
                       TO_CHAR(c.hora_inicio, 'HH24:MI') AS hora,
                       p.nombres || ' ' || p.apellidos AS paciente,
                       um.nombres || ' ' || um.apellidos AS medico,
                       e.nombre AS especialidad,
                       c.estado, c.motivo_consulta
                FROM citas c
                INNER JOIN pacientes p ON p.id_paciente = c.id_paciente
                INNER JOIN medicos m ON m.id_medico = c.id_medico
                INNER JOIN usuarios um ON um.id_usuario = m.id_usuario
                INNER JOIN especialidades e ON e.id_especialidad = c.id_especialidad
                WHERE {$whereSql}
                ORDER BY c.fecha_cita DESC, c.hora_inicio ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);

        $contextParts = [];
        if (!empty($estados)) {
            $contextParts[] = 'en estado **' . implode(' / ', $estados) . '**';
        }
        if ($fechaLabel !== '') {
            $contextParts[] = $fechaLabel;
        }
        if ($medicoMatch !== null) {
            $contextParts[] = 'con **Dr(a). ' . $medicoMatch['nombres'] . ' ' . $medicoMatch['apellidos'] . '**';
        }
        if ($pacienteMatch !== null) {
            $contextParts[] = 'del paciente **' . $pacienteMatch['nombres'] . ' ' . $pacienteMatch['apellidos'] . '**';
        }
        if ($espMatch !== null) {
            $contextParts[] = 'en **' . $espMatch['nombre'] . '**';
        }
        $ctxStr = implode(' ', $contextParts);

        if ($count === 0) {
            $answer = "No se encontraron citas médicas registradas {$ctxStr}.";
            $speech = strip_tags(str_replace('**', '', $answer));
            return self::buildResult($question, $answer, $speech, 'citas_filtradas_vacio', $sql, []);
        }

        $itemsMd = [];
        $itemsVoz = [];
        foreach (array_slice($rows, 0, 8) as $r) {
            $itemsMd[] = "• **{$r['codigo_cita']}** ({$r['fecha']} {$r['hora']}) — Pac: **{$r['paciente']}** | Dr(a). {$r['medico']} ({$r['especialidad']}) — `[{$r['estado']}]`";
            $itemsVoz[] = "cita {$r['codigo_cita']} el {$r['fecha']} a las {$r['hora']} del paciente {$r['paciente']} con el doctor {$r['medico']} en estado {$r['estado']}";
        }

        $answer = "Hay **{$count} citas médicas** {$ctxStr}:\n" . implode("\n", $itemsMd);
        $speech = "Tenemos {$count} citas médicas " . str_replace('**', '', $ctxStr) . ". "
                . ($count <= 3 ? "Detalle: " . implode('; ', $itemsVoz) . "." : "Las más recientes son: " . implode('; ', array_slice($itemsVoz, 0, 3)) . ".");

        return self::buildResult($question, $answer, $speech, 'citas_filtradas', $sql, $rows);
    }

    /**
     * Consulta los médicos que atienden una especialidad específica.
     */
    private static function queryDoctorsBySpecialty(string $question, array $espMatch, PDO $pdo): array
    {
        $idEsp = (int) $espMatch['id_especialidad'];
        $espNombre = (string) $espMatch['nombre'];

        $sql = "SELECT m.codigo_medico, u.nombres || ' ' || u.apellidos AS medico,
                       m.registro_profesional, m.consultorio, u.telefono, m.estado
                FROM medicos m
                INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
                INNER JOIN medico_especialidad me ON me.id_medico = m.id_medico
                WHERE me.id_especialidad = {$idEsp}
                ORDER BY u.apellidos ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);

        $listaMd = [];
        $listaVoz = [];
        foreach ($rows as $m) {
            $listaMd[] = "• **Dr(a). {$m['medico']}** (`{$m['codigo_medico']}`) — {$m['consultorio']}, Matrícula: {$m['registro_profesional']}, Tel: {$m['telefono']}";
            $listaVoz[] = "Doctor {$m['medico']} en el {$m['consultorio']}";
        }

        $answer = "En la especialidad de **{$espNombre}** atienden **{$count} " . ($count === 1 ? 'médico especialista' : 'médicos especialistas') . "**:\n" . implode("\n", $listaMd);
        $speech = "En {$espNombre} atienden {$count} " . ($count === 1 ? 'médico' : 'médicos') . ": " . implode(' y ', $listaVoz) . ".";

        return self::buildResult($question, $answer, $speech, 'medicos_por_especialidad', $sql, $rows);
    }

    /**
     * Resumen general de las 13 tablas de la clínica.
     */
    private static function resolveGeneralSummary(string $question, PDO $pdo): array
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM roles) AS total_roles,
                    (SELECT COUNT(*) FROM usuarios WHERE estado = 'Activo') AS usuarios_activos,
                    (SELECT COUNT(*) FROM pacientes WHERE estado = 'Activo') AS pacientes_activos,
                    (SELECT COUNT(*) FROM fichas_medicas) AS total_fichas,
                    (SELECT COUNT(*) FROM medicos WHERE estado = 'Activo') AS medicos_activos,
                    (SELECT COUNT(*) FROM especialidades WHERE estado = 'Activo') AS especialidades_activas,
                    (SELECT COUNT(*) FROM horarios_atencion WHERE estado = 'Activo') AS horarios_activos,
                    (SELECT COUNT(*) FROM citas) AS total_citas,
                    (SELECT COUNT(*) FROM citas WHERE estado = 'Atendida') AS citas_atendidas,
                    (SELECT COUNT(*) FROM citas WHERE estado IN ('Programada', 'Confirmada')) AS citas_pendientes,
                    (SELECT COUNT(*) FROM citas WHERE estado = 'Cancelada') AS citas_canceladas,
                    (SELECT COUNT(*) FROM citas WHERE estado = 'Inasistencia') AS citas_inasistencia";

        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        $answer = "**Resumen Ejecutivo en Tiempo Real — Clínica Vida Salud:**\n"
                . "• **Usuarios y Roles:** {$row['usuarios_activos']} usuarios activos en {$row['total_roles']} roles (`Administrador`, `Recepcionista`, `Médico`, `Paciente`).\n"
                . "• **Plantel Médico:** {$row['medicos_activos']} médicos activos en {$row['especialidades_activas']} especialidades y {$row['horarios_activos']} turnos semanales.\n"
                . "• **Padrón Clínico:** {$row['pacientes_activos']} pacientes activos con {$row['total_fichas']} fichas médicas y contactos de emergencia.\n"
                . "• **Citas Médicas ({$row['total_citas']} totales):** {$row['citas_atendidas']} atendidas, {$row['citas_pendientes']} pendientes (programadas/confirmadas), {$row['citas_canceladas']} canceladas y {$row['citas_inasistencia']} inasistencias.";

        $speech = "Resumen actual de la Clínica Vida Salud: contamos con {$row['usuarios_activos']} usuarios en {$row['total_roles']} roles, {$row['medicos_activos']} médicos especialistas en {$row['especialidades_activas']} especialidades, {$row['pacientes_activos']} pacientes registrados con ficha médica, y {$row['total_citas']} citas médicas de las cuales {$row['citas_atendidas']} fueron atendidas, {$row['citas_pendientes']} están pendientes y {$row['citas_canceladas']} fueron canceladas.";

        return self::buildResult($question, $answer, $speech, 'resumen_ejecutivo_clinica', $sql, [$row]);
    }

    /**
     * Busca si la pregunta menciona el nombre, apellido o código de algún médico en la base de datos.
     */
    private static function findMatchingDoctor(string $normQuestion, PDO $pdo): ?array
    {
        $sql = "SELECT m.id_medico, m.codigo_medico, u.nombres, u.apellidos
                FROM medicos m
                INNER JOIN usuarios u ON u.id_usuario = m.id_usuario";
        $medicos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($medicos as $m) {
            $cod = strtolower((string) $m['codigo_medico']);
            if ($cod !== '' && str_contains($normQuestion, $cod)) {
                return $m;
            }
            $tokens = array_filter(
                explode(' ', self::normalize($m['nombres'] . ' ' . $m['apellidos'])),
                static fn(string $t) => strlen($t) >= 4
            );
            foreach ($tokens as $tk) {
                if (preg_match('/\b' . preg_quote($tk, '/') . '\b/', $normQuestion)) {
                    return $m;
                }
            }
        }
        return null;
    }

    /**
     * Busca si la pregunta menciona el nombre, apellido, CI o código de algún paciente en la base de datos.
     */
    private static function findMatchingPatient(string $normQuestion, PDO $pdo): ?array
    {
        $sql = "SELECT id_paciente, codigo_paciente, ci, nombres, apellidos FROM pacientes";
        $pacientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Primero intentar match por código PAC-xxxxx o CI
        foreach ($pacientes as $p) {
            $cod = strtolower((string) $p['codigo_paciente']);
            $ci  = strtolower((string) $p['ci']);
            if (($cod !== '' && str_contains($normQuestion, $cod)) || ($ci !== '' && preg_match('/\b' . preg_quote($ci, '/') . '\b/', $normQuestion))) {
                return $p;
            }
        }

        // Luego intentar match por nombre + apellido o tokens de nombre (evitando palabras comunes)
        $stopWords = ['maria', 'juan', 'jose', 'carlos', 'luis', 'ana'];
        $bestMatch = null;
        $bestScore = 0;

        foreach ($pacientes as $p) {
            $tokens = array_filter(
                explode(' ', self::normalize($p['nombres'] . ' ' . $p['apellidos'])),
                static fn(string $t) => strlen($t) >= 4
            );
            $score = 0;
            foreach ($tokens as $tk) {
                if (preg_match('/\b' . preg_quote($tk, '/') . '\b/', $normQuestion)) {
                    $score += in_array($tk, $stopWords, true) ? 1 : 2;
                }
            }
            if ($score > $bestScore && $score >= 2) {
                $bestScore = $score;
                $bestMatch = $p;
            }
        }

        return $bestMatch;
    }

    /**
     * Busca si la pregunta menciona una especialidad médica de la clínica.
     */
    private static function findMatchingSpecialty(string $normQuestion, PDO $pdo): ?array
    {
        $sql = "SELECT id_especialidad, codigo_especialidad, nombre FROM especialidades";
        $especialidades = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($especialidades as $e) {
            $nomNorm = self::normalize((string) $e['nombre']);
            if ($nomNorm !== '' && str_contains($normQuestion, $nomNorm)) {
                return $e;
            }
        }

        // Sinónimos comunes
        $synonyms = [
            'pediatra'     => 'pediatria',
            'ninos'        => 'pediatria',
            'ginecologo'   => 'ginecologia',
            'ginecologa'   => 'ginecologia',
            'odontologo'   => 'odontologia',
            'dentista'     => 'odontologia',
            'dental'       => 'odontologia',
            'cardiologo'   => 'cardiologia',
            'corazon'      => 'cardiologia',
            'general'      => 'medicina general',
        ];
        foreach ($synonyms as $word => $target) {
            if (preg_match('/\b' . $word . '\b/', $normQuestion)) {
                foreach ($especialidades as $e) {
                    if (self::normalize((string) $e['nombre']) === $target) {
                        return $e;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detecta mención a día de la semana (1=Lunes..6=Sábado).
     */
    private static function detectDayOfWeek(string $norm): ?array
    {
        $map = [
            'lunes'     => ['num' => 1, 'nombre' => 'Lunes'],
            'martes'    => ['num' => 2, 'nombre' => 'Martes'],
            'miercoles' => ['num' => 3, 'nombre' => 'Miércoles'],
            'jueves'    => ['num' => 4, 'nombre' => 'Jueves'],
            'viernes'   => ['num' => 5, 'nombre' => 'Viernes'],
            'sabado'    => ['num' => 6, 'nombre' => 'Sábado'],
            'sabados'   => ['num' => 6, 'nombre' => 'Sábado'],
        ];
        foreach ($map as $key => $info) {
            if (preg_match('/\b' . $key . '\b/', $norm)) {
                return $info;
            }
        }
        return null;
    }

    /**
     * Formatea resultados genéricos de Gemini en español conversacional.
     */
    private static function formatRowsAsNaturalSpanish(string $question, array $rows, string $intent): array
    {
        $count = count($rows);
        if ($count === 0) {
            $msg = "Consulté la base de datos de la Clínica Vida Salud y no se encontraron registros para tu consulta.";
            return ['markdown' => $msg, 'speech' => $msg];
        }

        if ($count === 1 && count($rows[0]) <= 4) {
            $parts = [];
            foreach ($rows[0] as $k => $v) {
                $label = ucwords(str_replace('_', ' ', (string) $k));
                $parts[] = "**{$label}**: {$v}";
            }
            $md = "Resultado de tu consulta:\n• " . implode(' | ', $parts);
            $sp = "El resultado de tu consulta es: " . strip_tags(str_replace('**', '', implode(', ', $parts))) . ".";
            return ['markdown' => $md, 'speech' => $sp];
        }

        $linesMd = [];
        $linesSp = [];
        foreach (array_slice($rows, 0, 8) as $row) {
            $vals = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $row), static fn($v) => $v !== ''));
            $linesMd[] = "• " . implode(' — ', array_slice($vals, 0, 5));
            $linesSp[] = implode(', ', array_slice($vals, 0, 3));
        }

        $md = "Encontré **{$count} registro(s)** en la base de datos:\n" . implode("\n", $linesMd);
        $sp = "Encontré {$count} registros. " . implode('; ', array_slice($linesSp, 0, 4)) . ".";

        return ['markdown' => $md, 'speech' => $sp];
    }

    /**
     * Construye la estructura uniforme de respuesta.
     */
    private static function buildResult(string $question, string $answer, string $speech, string $intent, string $sql, array $rows): array
    {
        return [
            'ok'           => true,
            'question'     => $question,
            'answer'       => $answer,
            'speech_text'  => self::cleanForSpeech($speech),
            'intent'       => $intent,
            'engine'       => 'Motor Analítico Clínico PostgreSQL (Tiempo Real)',
            'sql_used'     => preg_replace('/\s+/', ' ', trim($sql)) ?: '',
            'data_preview' => array_slice($rows, 0, 15),
        ];
    }

    /**
     * Limpia símbolos markdown (*, #, _, `) para que la síntesis de voz suene natural en español.
     */
    public static function cleanForSpeech(string $text): string
    {
        $clean = str_replace(['*', '#', '_', '`', '~', '>', '[', ']'], '', $text);
        $clean = str_replace(['Dr(a).', 'Dr.', 'Dra.'], 'Doctor ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        return trim($clean);
    }

    /**
     * Normaliza texto a minúsculas sin tildes para detección semántica.
     */
    private static function normalize(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        return strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);
    }

    /**
     * Valida que el SQL sea únicamente SELECT de solo lectura.
     */
    public static function sanitizeReadOnlySql(string $sql): ?string
    {
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r\0\x0B");

        if ($sql === '' || str_contains($sql, ';')) {
            return null;
        }
        if (!preg_match('/^(SELECT|WITH)\b/i', $sql)) {
            return null;
        }
        $forbidden = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|COPY|CALL|EXECUTE|PG_SLEEP|SET|VACUUM)\b/i';
        if (preg_match($forbidden, $sql)) {
            return null;
        }
        return $sql;
    }
}
