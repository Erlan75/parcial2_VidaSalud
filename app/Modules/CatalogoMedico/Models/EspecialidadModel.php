<?php
declare(strict_types=1);

namespace App\Modules\CatalogoMedico\Models;

use Database;
use PDO;

/**
 * CU04: Modelo de Especialidades Médicas (RF04)
 */
final class EspecialidadModel
{
    public function listAll(bool $onlyActive = false): array
    {
        $pdo = Database::getConnection();
        $where = $onlyActive ? "WHERE e.estado = 'Activo'" : '';
        $sql = "
            SELECT e.*,
                   COUNT(DISTINCT me.id_medico) FILTER (WHERE m.estado = 'Activo') AS medicos_activos,
                   COUNT(DISTINCT c.id_cita) AS total_citas
              FROM especialidades e
              LEFT JOIN medico_especialidad me ON me.id_especialidad = e.id_especialidad
              LEFT JOIN medicos m ON m.id_medico = me.id_medico
              LEFT JOIN citas c ON c.id_especialidad = e.id_especialidad
              {$where}
             GROUP BY e.id_especialidad
             ORDER BY e.nombre ASC
        ";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function normalizeName(string $name): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }

    public function existsNormalizedName(string $name, ?int $excludeId = null): bool
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT 1 FROM especialidades
             WHERE LOWER(TRANSLATE(TRIM(nombre), 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU'))
                 = LOWER(TRANSLATE(TRIM(:nombre), 'áéíóúÁÉÍÓÚ', 'aeiouAEIOU'))
        ";
        $params = [':nombre' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_especialidad <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function crear(string $codigo, string $nombre, string $descripcion, int $idAdmin, string $ip): int
    {
        $nombreNorm = $this->normalizeName($nombre);
        if ($this->existsNormalizedName($nombreNorm)) {
            throw new \RuntimeException("Ya existe una especialidad médica registrada con el nombre normalizado «{$nombreNorm}».");
        }

        $codigoNorm = strtoupper(trim($codigo));
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO especialidades (codigo_especialidad, nombre, descripcion, estado)
                VALUES (:cod, :nom, :desc, 'Activo')
                RETURNING id_especialidad
            ");
            $stmt->execute([
                ':cod'  => $codigoNorm,
                ':nom'  => $nombreNorm,
                ':desc' => trim($descripcion),
            ]);
            $id = (int) $stmt->fetchColumn();

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_nuevos, ip_origen)
                VALUES (:uid, 'Especialidades', 'INSERT', :reg, :nuevos::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'    => $idAdmin,
                ':reg'    => (string) $id,
                ':nuevos' => json_encode(['codigo' => $codigoNorm, 'nombre' => $nombreNorm], JSON_UNESCAPED_UNICODE),
                ':ip'     => $ip,
            ]);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $id, string $codigo, string $nombre, string $descripcion, int $idAdmin, string $ip): void
    {
        $nombreNorm = $this->normalizeName($nombre);
        if ($this->existsNormalizedName($nombreNorm, $id)) {
            throw new \RuntimeException("Ya existe otra especialidad médica con el nombre normalizado «{$nombreNorm}».");
        }

        $codigoNorm = strtoupper(trim($codigo));
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $prevStmt = $pdo->prepare('SELECT * FROM especialidades WHERE id_especialidad = :id FOR UPDATE');
            $prevStmt->execute([':id' => $id]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if (!$prev) {
                throw new \RuntimeException('La especialidad indicada no existe.');
            }

            $upd = $pdo->prepare("
                UPDATE especialidades
                   SET codigo_especialidad = :cod,
                       nombre = :nom,
                       descripcion = :desc
                 WHERE id_especialidad = :id
            ");
            $upd->execute([
                ':cod'  => $codigoNorm,
                ':nom'  => $nombreNorm,
                ':desc' => trim($descripcion),
                ':id'   => $id,
            ]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Especialidades', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $id,
                ':prev' => json_encode($prev, JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode(['codigo' => $codigoNorm, 'nombre' => $nombreNorm, 'descripcion' => trim($descripcion)], JSON_UNESCAPED_UNICODE),
                ':ip'   => $ip,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function conmutarEstado(int $id, int $idAdmin, string $ip): string
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT estado, nombre FROM especialidades WHERE id_especialidad = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \RuntimeException('La especialidad no existe.');
            }

            $nuevo = ($row['estado'] === 'Activo') ? 'Inactivo' : 'Activo';
            $pdo->prepare('UPDATE especialidades SET estado = :est WHERE id_especialidad = :id')
                ->execute([':est' => $nuevo, ':id' => $id]);

            $aud = $pdo->prepare("
                INSERT INTO auditorias (id_usuario, modulo, operacion, id_registro_afectado, datos_previos, datos_nuevos, ip_origen)
                VALUES (:uid, 'Especialidades', 'UPDATE', :reg, :prev::jsonb, :new::jsonb, :ip)
            ");
            $aud->execute([
                ':uid'  => $idAdmin,
                ':reg'  => (string) $id,
                ':prev' => json_encode(['estado' => $row['estado']], JSON_UNESCAPED_UNICODE),
                ':new'  => json_encode(['estado' => $nuevo], JSON_UNESCAPED_UNICODE),
                ':ip'   => $ip,
            ]);

            $pdo->commit();
            return $nuevo;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
