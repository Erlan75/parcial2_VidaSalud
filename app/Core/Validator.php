<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validador Estricto de Reglas de Negocio y Formato en Servidor (Validación Dual)
 */
final class Validator
{
    /**
     * CU03: Complejidad estricta de contraseña:
     * Mínimo 8 caracteres, >= 1 mayúscula, >= 1 minúscula, >= 1 número y >= 1 carácter especial.
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Debe incluir al menos una letra mayúscula (A-Z).';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Debe incluir al menos una letra minúscula (a-z).';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Debe incluir al menos un número (0-9).';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Debe incluir al menos un carácter especial (ej. _, @, #, $, !, -).';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    public static function isValidEmail(string $email): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', trim($email));
    }

    public static function isValidCI(string $ci): bool
    {
        $clean = trim($ci);
        return (bool) preg_match('/^[0-9]{5,12}(-[A-Za-z0-9]{1,3})?$/', $clean);
    }

    public static function isValidPhone(string $phone): bool
    {
        $clean = trim($phone);
        return (bool) preg_match('/^[0-9+\-\s]{7,20}$/', $clean);
    }

    public static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y);
    }

    public static function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', trim($time));
    }

    public static function normalizeTime(string $time): string
    {
        $clean = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $clean)) {
            return $clean . ':00';
        }
        return $clean;
    }
}
