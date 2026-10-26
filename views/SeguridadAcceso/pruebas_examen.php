<?php
declare(strict_types=1);
/** @var string $csrfToken */

$pruebas = [
    1 => [
        'titulo' => 'Registrar paciente nuevo con ficha médica',
        'regla'  => 'Registro integral de paciente y expediente clínico',
        'desc'   => 'Verifica el alta atómica de paciente, ficha médica preventiva y contacto de emergencia generando código correlativo PAC-xxxxx.',
        'link'   => url('pacientes'),
        'modulo' => 'Ir a Pacientes',
    ],
    2 => [
        'titulo' => 'Rechazo de Cédula de Identidad (CI) duplicada',
        'regla'  => 'Validación de documento de identidad único',
        'desc'   => 'Verifica que al intentar registrar un paciente con un CI ya existente (ej. 5894123), el sistema bloquea la operación e informa el error.',
        'link'   => url('pacientes'),
        'modulo' => 'Probar en Pacientes',
    ],
    3 => [
        'titulo' => 'Programar cita médica en intervalo disponible',
        'regla'  => 'Consulta de disponibilidad y agendamiento de citas',
        'desc'   => 'Verifica el agendamiento exitoso en un bloque libre de 30 minutos dentro del horario activo del médico, generando código CM-xxxxx.',
        'link'   => url('citas/programar'),
        'modulo' => 'Ir a Programar Cita',
    ],
    4 => [
        'titulo' => 'Rechazo de cita superpuesta (Prevención de Solapamiento)',
        'regla'  => 'Control de horarios sin colisiones',
        'desc'   => 'Verifica que el sistema impide agendar dos citas activas en el mismo bloque horario para el mismo médico o paciente.',
        'link'   => url('citas/programar'),
        'modulo' => 'Ver en Citas',
    ],
    5 => [
        'titulo' => 'Rechazo de cita fuera del horario médico o en feriado',
        'regla'  => 'Validación de turno semanal y días no laborables',
        'desc'   => 'Verifica el rechazo inmediato si se solicita una hora fuera del turno del médico (ej. 19:30) o en un feriado como el 02/11/2026.',
        'link'   => url('horarios'),
        'modulo' => 'Ver Horarios',
    ],
    6 => [
        'titulo' => 'Cancelar cita conservando historial y liberando cupo',
        'regla'  => 'Cancelación documentada y liberación de horario',
        'desc'   => 'Verifica que cancelar una cita exige motivo (>=5 caract.), cambia estado a Cancelada sin borrar el registro y libera el bloque horario.',
        'link'   => url('citas'),
        'modulo' => 'Ir a Control Citas',
    ],
    7 => [
        'titulo' => 'Consultar historial clínico completo de un paciente',
        'regla'  => 'Línea de tiempo clínica y diagnósticos históricos',
        'desc'   => 'Muestra todas las atenciones pasadas con notas médicas, cancelaciones con motivo documentado e inasistencias de un paciente.',
        'link'   => url('historial', ['id_paciente' => 1]),
        'modulo' => 'Abrir Historial PAC-00001',
    ],
    8 => [
        'titulo' => 'Consultar agenda médica diaria filtrada por fecha y médico',
        'regla'  => 'Ordenamiento cronológico diario',
        'desc'   => 'Muestra la agenda cronológica del Dr. Juan Pablo Pérez el lunes 26/10/2026 con estados y acciones de consultorio.',
        'link'   => url('agenda', ['id_medico' => 1, 'fecha' => '2026-10-26']),
        'modulo' => 'Abrir Agenda 26/10/2026',
    ],
    9 => [
        'titulo' => 'Bloqueo de rutas internas sin sesión activa y anti-caché',
        'regla'  => 'Protección de sesión y control de acceso',
        'desc'   => 'Verifica que cualquier acceso directo a rutas internas sin sesión redirige a Login e impide regresar con el botón Atrás tras cerrar sesión.',
        'link'   => url('seguridad/perfil'),
        'modulo' => 'Ver Seguridad Sesión',
    ],
    10 => [
        'titulo' => 'Protección de integridad de citas y consultas atendidas',
        'regla'  => 'Protección contra eliminación física e inmutabilidad de atenciones',
        'desc'   => 'Verifica que el sistema prohíbe eliminar físicamente las citas y prohíbe alterar o cancelar una consulta ya Atendida.',
        'link'   => url('auditorias'),
        'modulo' => 'Ver Auditoría',
    ],
];
?>
<div class="vs-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-1" style="color:var(--vs-navy);">
                <i class="bi bi-patch-check-fill text-success me-1"></i> Centro Interactivo de Verificación: Las 10 Pruebas Mínimas del Examen
            </h5>
            <p class="text-muted small mb-0">
                Ejecute cada prueba directamente contra PostgreSQL (mediante transacciones seguras con <code>SAVEPOINT</code>) o navegue al módulo visual correspondiente para demostrarla en vivo.
            </p>
        </div>
        <button type="button" class="btn btn-vs-primary" onclick="ejecutarTodasLasPruebas()">
            <i class="bi bi-play-btn-fill me-1"></i> Ejecutar las 10 Pruebas Automáticamente
        </button>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($pruebas as $num => $p): ?>
        <div class="col-12 col-xl-6">
            <div class="vs-card h-100 p-3 d-flex flex-column justify-content-between" id="cardTest<?= $num ?>">
                <div>
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span class="badge rounded-pill" style="background:var(--vs-navy);">PRUEBA #<?= $num ?></span>
                        <span id="statusBadge<?= $num ?>" class="badge rounded-pill text-bg-light border text-muted">Lista para probar</span>
                    </div>
                    <h6 class="fw-bold mb-1" style="color:var(--vs-navy);"><?= e($p['titulo']) ?></h6>
                    <div class="small fw-semibold mb-2" style="color:var(--vs-blue);"><i class="bi bi-cpu"></i> <?= e($p['regla']) ?></div>
                    <p class="small text-muted mb-2"><?= e($p['desc']) ?></p>

                    <div id="resultBox<?= $num ?>" class="d-none p-2 rounded-3 small mt-2" style="background:#F8FAFC;border:1px solid #CBD5E1;">
                        <div class="text-muted mb-1" style="font-size:0.73rem;"><code id="sqlBox<?= $num ?>"></code></div>
                        <div class="fw-semibold" id="detailBox<?= $num ?>"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-sm btn-vs-primary" onclick="ejecutarPrueba(<?= $num ?>)">
                        <i class="bi bi-lightning-charge-fill"></i> Probar Motor PostgreSQL
                    </button>
                    <a href="<?= e($p['link']) ?>" class="btn btn-sm btn-vs-secondary">
                        <i class="bi bi-box-arrow-up-right"></i> <?= e($p['modulo']) ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
async function ejecutarPrueba(num) {
    const badge = document.getElementById('statusBadge' + num);
    const box = document.getElementById('resultBox' + num);
    const sqlBox = document.getElementById('sqlBox' + num);
    const detailBox = document.getElementById('detailBox' + num);

    badge.className = 'badge rounded-pill text-bg-warning';
    badge.textContent = 'Ejecutando...';

    const fd = new FormData();
    fd.append('_csrf', window.CSRF_TOKEN);
    fd.append('test_number', String(num));

    try {
        const resp = await fetch('<?= url('sistema/ejecutar-prueba') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd
        });
        const data = await resp.json();
        box.classList.remove('d-none');
        sqlBox.textContent = data.sql || '';
        detailBox.textContent = data.detail || '';

        if (data.passed) {
            badge.className = 'badge rounded-pill text-bg-success';
            badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> Superada 100%';
            detailBox.className = 'fw-semibold text-success';
        } else {
            badge.className = 'badge rounded-pill text-bg-danger';
            badge.textContent = 'Falló';
            detailBox.className = 'fw-semibold text-danger';
        }
    } catch (err) {
        badge.className = 'badge rounded-pill text-bg-danger';
        badge.textContent = 'Error de red';
    }
}

async function ejecutarTodasLasPruebas() {
    for (let i = 1; i <= 10; i++) {
        await ejecutarPrueba(i);
    }
    VidaSalud.toast('success', 'Las 10 pruebas mínimas del examen fueron verificadas contra PostgreSQL.', 'Verificación Completa');
}
</script>
