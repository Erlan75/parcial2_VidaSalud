<?php
declare(strict_types=1);

namespace App\Modules\SeguridadAcceso\Models;

use Database;
use PDO;

/**
 * Modelo de Bitácora Inmutable de Auditoría del Sistema (CU17 - RF17)
 */
final class AuditoriaModel
{
    public function listLogs(array $filters = [], int $limit = 250): array
    {
        $pdo = Database::getConnection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $where[] = 'a.fecha_hora::date >= :f_desde::date';
            $params[':f_desde'] = (string) $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'a.fecha_hora::date <= :f_hasta::date';
            $params[':f_hasta'] = (string) $filters['fecha_hasta'];
        }
        if (!empty($filters['id_usuario'])) {
            $where[] = 'a.id_usuario = :id_usuario';
            $params[':id_usuario'] = (int) $filters['id_usuario'];
        }
        if (!empty($filters['modulo'])) {
            $where[] = 'a.modulo = :modulo';
            $params[':modulo'] = (string) $filters['modulo'];
        }
        if (!empty($filters['operacion'])) {
            $where[] = 'a.operacion = :operacion';
            $params[':operacion'] = (string) $filters['operacion'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.id_registro_afectado ILIKE :q OR a.modulo ILIKE :q OR COALESCE(u.nombre_usuario, \'\') ILIKE :q OR COALESCE(a.datos_nuevos::text, \'\') ILIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT a.id_auditoria,
                   a.id_usuario,
                   a.modulo,
                   a.operacion,
                   a.id_registro_afectado,
                   a.datos_previos,
                   a.datos_nuevos,
                   a.ip_origen,
                   a.fecha_hora,
                   COALESCE(u.nombre_completo, 'Sistema / Proceso Automático') AS usuario_nombre,
                   COALESCE(u.nombre_usuario, 'system') AS nombre_usuario,
                   COALESCE(r.nombre, 'Motor BD') AS rol_nombre
              FROM auditorias a
              LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
              LEFT JOIN roles r ON r.id_rol = u.id_rol
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.fecha_hora DESC, a.id_auditoria DESC
             LIMIT " . max(10, min(1000, $limit));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getModulosDisponibles(): array
    {
        $pdo = Database::getConnection();
        return $pdo->query('SELECT DISTINCT modulo FROM auditorias ORDER BY modulo ASC')
                   ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getStats(): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT COUNT(*) AS total,
                   COUNT(*) FILTER (WHERE operacion = 'LOGIN') AS logins,
                   COUNT(*) FILTER (WHERE operacion = 'INSERT') AS inserts,
                   COUNT(*) FILTER (WHERE operacion = 'UPDATE') AS updates,
                   COUNT(*) FILTER (WHERE operacion = 'CANCEL') AS cancels
              FROM auditorias
        ";
        return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
