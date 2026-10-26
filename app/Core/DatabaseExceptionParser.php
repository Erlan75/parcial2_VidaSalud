<?php
declare(strict_types=1);

namespace App\Core;

use PDOException;
use Throwable;

/**
 * Extractor limpio de excepciones Smart-DB de PostgreSQL (Triggers y Procedimientos PL/pgSQL).
 * Garantiza que mensajes RAISE EXCEPTION (RN01..RN11, RF05..RF17) lleguen claros al usuario
 * sin exponer rutas del servidor, trazas internas ni credenciales.
 */
final class DatabaseExceptionParser
{
    public static function parse(Throwable $e): string
    {
        $raw = $e->getMessage();

        // 1. Extraer mensaje explícito de RAISE EXCEPTION en PostgreSQL (SQLSTATE P0001 o ERROR:)
        if (preg_match('/ERROR:\s*(.+?)(?:\n|CONTEXT:|DETAIL:|HINT:|$)/su', $raw, $matches)) {
            $clean = trim($matches[1]);
            if ($clean !== '') {
                return self::humanizeConstraintIfNeeded($clean);
            }
        }

        // 2. Manejo específico por código SQLSTATE si es PDOException
        if ($e instanceof PDOException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            return match ($sqlState) {
                '23505' => self::translateUniqueViolation($raw),
                '23514' => self::translateCheckViolation($raw),
                '23503' => 'No se puede completar la operación porque el registro está referenciado por otros datos clínicos históricos.',
                '23502' => 'Falta completar uno o más campos obligatorios requeridos por la base de datos.',
                '22007', '22008' => 'El formato de fecha u hora ingresado no es válido.',
                '08001', '08006' => 'No se pudo establecer conexión con el servidor PostgreSQL. Verifique que el servicio esté activo y revise la configuración .env.',
                '28P01' => 'Credenciales de PostgreSQL rechazadas (usuario o contraseña incorrectos en .env).',
                '3D000' => 'La base de datos especificada aún no existe en PostgreSQL. Ejecute el asistente de migración.',
                default => self::sanitizeGeneric($raw),
            };
        }

        return self::sanitizeGeneric($raw);
    }

    private static function translateUniqueViolation(string $raw): string
    {
        if (str_contains($raw, 'pacientes_ci_key') || str_contains($raw, '(ci)')) {
            return 'Ya existe un paciente registrado con esa Cédula de Identidad (CI).';
        }
        if (str_contains($raw, 'usuarios_nombre_usuario_key')) {
            return 'El nombre de usuario ingresado ya se encuentra en uso.';
        }
        if (str_contains($raw, 'usuarios_correo_electronico_key') || str_contains($raw, 'medicos_correo_key')) {
            return 'El correo electrónico ingresado ya se encuentra registrado en el sistema.';
        }
        if (str_contains($raw, 'medicos_registro_profesional_key')) {
            return 'Ya existe un médico registrado con ese número de matrícula profesional.';
        }
        if (str_contains($raw, 'especialidades_nombre_key')) {
            return 'Ya existe una especialidad médica registrada con ese nombre.';
        }
        if (str_contains($raw, 'especialidades_codigo_especialidad_key')) {
            return 'El código de especialidad indicado ya está en uso.';
        }
        if (str_contains($raw, 'uq_medico_cita_horario_activo')) {
            return 'Conflicto de horario. El médico ya tiene una cita médica activa en ese bloque horario.';
        }
        return 'El registro infringe una regla de unicidad: uno de los códigos, CI o correos ya existe.';
    }

    private static function translateCheckViolation(string $raw): string
    {
        if (str_contains($raw, 'chk_pacientes_fnac')) {
            return 'La fecha de nacimiento no puede ser posterior a la fecha actual.';
        }
        if (str_contains($raw, 'chk_horarios_rango') || str_contains($raw, 'chk_citas_rango_horas')) {
            return 'La hora de finalización debe ser estrictamente mayor a la hora de inicio.';
        }
        if (str_contains($raw, 'chk_cancelacion_motivo')) {
            return 'El motivo de cancelación debe contener al menos 5 caracteres descriptivos.';
        }
        if (str_contains($raw, 'chk_usuarios_correo')) {
            return 'El formato del correo electrónico ingresado no es válido.';
        }
        if (str_contains($raw, 'chk_bloqueos_fechas')) {
            return 'La fecha final del bloqueo no puede ser anterior a la fecha inicial.';
        }
        return 'Uno de los valores enviados no cumple las restricciones de validación clínica del sistema.';
    }

    private static function humanizeConstraintIfNeeded(string $msg): string
    {
        if (str_contains($msg, 'duplicate key value') || str_contains($msg, 'llave duplicada')) {
            return self::translateUniqueViolation($msg);
        }
        if (str_contains($msg, 'check constraint') || str_contains($msg, 'restricción «chk_')) {
            return self::translateCheckViolation($msg);
        }
        $msg = preg_replace('/^(?:(?:RN|RF|CU)\d+(?:\s*[\/,]\s*(?:RN|RF|CU)\d+)*)\s*:\s*/iu', '', $msg) ?? $msg;
        return trim($msg);
    }

    private static function sanitizeGeneric(string $raw): string
    {
        // Ocultar rutas de archivos locales, credenciales o SQLSTATE crudos
        $clean = preg_replace('/SQLSTATE\[[^\]]+\]:[^:]*:\s*\d*\s*/i', '', $raw) ?? $raw;
        $clean = preg_replace('/CONTEXT:.*$/is', '', $clean) ?? $clean;
        $clean = preg_replace('/[A-Za-z]:\\\\[^\s]+/', '[ruta-protegida]', $clean) ?? $clean;
        $clean = trim($clean);

        return $clean !== '' ? $clean : 'Ocurrió un error al procesar la solicitud en la base de datos.';
    }
}
