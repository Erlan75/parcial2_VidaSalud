<?php
declare(strict_types=1);
/** @var array|null $currentUser */
/** @var string $csrfToken */
/** @var array $flashes */
/** @var string $content */

$rolActual = $currentUser['rol'] ?? '';
$activeMenu = $activeMenu ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Clínica Vida Salud') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/clinical.css') ?>" rel="stylesheet">
</head>
<body>
<div class="vs-backdrop" id="vsBackdrop"></div>

<div class="vs-wrapper">
    <!-- SIDEBAR CLÍNICO -->
    <aside class="vs-sidebar" id="vsSidebar">
        <div class="vs-sidebar-brand">
            <div class="vs-brand-cross"><i class="bi bi-hospital"></i></div>
            <div>
                <div class="fw-bold lh-sm" style="font-size:1.03rem;">CLÍNICA VIDA SALUD</div>
                <div style="font-size:0.74rem;color:var(--vs-sky);opacity:0.9;">Gestión de Citas Médicas</div>
            </div>
        </div>

        <nav class="vs-sidebar-nav">
            <div class="vs-nav-pkg">PANEL Y ANALÍTICA</div>
            <a href="<?= url('dashboard') ?>" class="vs-nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Panel Principal</span>
            </a>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista'], true)): ?>
                <a href="<?= url('reportes') ?>" class="vs-nav-link <?= $activeMenu === 'reportes' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Reportes y Estadísticas</span>
                </a>
            <?php endif; ?>

            <div class="vs-nav-pkg">GESTIÓN DE CITAS</div>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Paciente'], true)): ?>
                <a href="<?= url('citas/programar') ?>" class="vs-nav-link <?= $activeMenu === 'citas_programar' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-plus-fill"></i>
                    <span>Programar Cita</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('citas') ?>" class="vs-nav-link <?= $activeMenu === 'citas_listado' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i>
                <span><?= $rolActual === 'Paciente' ? 'Mis Citas' : 'Control de Citas' ?></span>
            </a>
            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <a href="<?= url('agenda') ?>" class="vs-nav-link <?= $activeMenu === 'agenda' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Agenda Médica Diaria</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('historial') ?>" class="vs-nav-link <?= $activeMenu === 'historial' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse-fill"></i>
                <span><?= $rolActual === 'Paciente' ? 'Mi Historial Clínico' : 'Historial por Paciente' ?></span>
            </a>

            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <div class="vs-nav-pkg">PACIENTES</div>
                <a href="<?= url('pacientes') ?>" class="vs-nav-link <?= $activeMenu === 'pacientes' ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Pacientes y Ficha Médica</span>
                </a>
            <?php endif; ?>

            <?php if (in_array($rolActual, ['Administrador', 'Recepcionista', 'Médico'], true)): ?>
                <div class="vs-nav-pkg">CATÁLOGO MÉDICO</div>
                <?php if (in_array($rolActual, ['Administrador', 'Recepcionista'], true)): ?>
                    <a href="<?= url('especialidades') ?>" class="vs-nav-link <?= $activeMenu === 'especialidades' ? 'active' : '' ?>">
                        <i class="bi bi-award-fill"></i>
                        <span>Especialidades</span>
                    </a>
                    <a href="<?= url('medicos') ?>" class="vs-nav-link <?= $activeMenu === 'medicos' ? 'active' : '' ?>">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Médicos</span>
                    </a>
                <?php endif; ?>
                <a href="<?= url('horarios') ?>" class="vs-nav-link <?= $activeMenu === 'horarios' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-week-fill"></i>
                    <span><?= $rolActual === 'Médico' ? 'Mis Horarios' : 'Horarios de Atención' ?></span>
                </a>
                <a href="<?= url('bloqueos') ?>" class="vs-nav-link <?= $activeMenu === 'bloqueos' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-x-fill"></i>
                    <span>Feriados y Bloqueos</span>
                </a>
            <?php endif; ?>

            <div class="vs-nav-pkg">SEGURIDAD Y ACCESO</div>
            <?php if ($rolActual === 'Administrador'): ?>
                <a href="<?= url('usuarios') ?>" class="vs-nav-link <?= $activeMenu === 'usuarios' ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Usuarios y Roles</span>
                </a>
                <a href="<?= url('auditorias') ?>" class="vs-nav-link <?= $activeMenu === 'auditorias' ? 'active' : '' ?>">
                    <i class="bi bi-eye-fill"></i>
                    <span>Bitácora de Auditoría</span>
                </a>
            <?php endif; ?>
            <a href="<?= url('seguridad/perfil') ?>" class="vs-nav-link <?= $activeMenu === 'perfil' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Mi Perfil / Contraseña</span>
            </a>
        </nav>

        <div class="vs-sidebar-footer">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="text-truncate">
                    <div class="fw-semibold small text-white text-truncate"><?= e($currentUser['nombre_completo'] ?? '') ?></div>
                    <span class="badge bg-light text-dark" style="font-size:0.7rem;"><?= e($rolActual) ?></span>
                </div>
                <button type="button" class="btn btn-sm btn-danger flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalConfirmLogout" title="Cerrar Sesión">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="vs-main">
        <header class="vs-topbar">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary d-lg-none" data-vs-toggle="sidebar" aria-label="Abrir menú">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h1 class="h5 mb-0 fw-bold" style="color:var(--vs-navy);"><?= e($pageTitle ?? 'Clínica Vida Salud') ?></h1>
                    <div class="text-muted d-none d-sm-block" style="font-size:0.77rem;">
                        <i class="bi bi-geo-alt-fill text-primary"></i> Santa Cruz, Bolivia · <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($currentUser['codigo_medico'])): ?>
                    <span class="badge rounded-pill text-bg-primary d-none d-md-inline-block"><?= e($currentUser['codigo_medico']) ?></span>
                <?php elseif (!empty($currentUser['codigo_paciente'])): ?>
                    <span class="badge rounded-pill text-bg-info d-none d-md-inline-block"><?= e($currentUser['codigo_paciente']) ?></span>
                <?php endif; ?>
                <a href="<?= url('seguridad/perfil') ?>" class="btn btn-sm btn-vs-secondary d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-person-circle"></i> <?= e($currentUser['nombre_usuario'] ?? '') ?>
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalConfirmLogout">
                    <i class="bi bi-power"></i> <span class="d-none d-md-inline">Salir</span>
                </button>
            </div>
        </header>

        <main class="vs-content">
            <?php if (!empty($flashes)): ?>
                <?php foreach ($flashes as $f): ?>
                    <?php
                    $bsClass = match ($f['type'] ?? 'info') {
                        'success' => 'alert-success',
                        'error'   => 'alert-danger',
                        'warning' => 'alert-warning',
                        default   => 'alert-info',
                    };
                    ?>
                    <div class="alert <?= $bsClass ?> alert-dismissible fade show shadow-sm border-0 mb-3" role="alert">
                        <?php if (!empty($f['title'])): ?>
                            <strong><?= e($f['title']) ?>:</strong>
                        <?php endif; ?>
                        <?= e($f['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN PARA CERRAR SESIÓN -->
<div class="modal fade" id="modalConfirmLogout" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background:var(--vs-navy);">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-shield-exclamation me-1"></i> Cerrar Sesión</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-box-arrow-right text-danger display-5 d-block mb-2"></i>
                <p class="mb-1 fw-semibold">¿Desea finalizar su sesión actual?</p>
                <p class="text-muted small mb-0">Su sesión se cerrará de forma segura en este dispositivo.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal">Cancelar</button>
                <a href="<?= url('seguridad/logout') ?>" class="btn btn-danger px-4 fw-semibold">Sí, Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>

<!-- BOTÓN CIRCULAR FLOTANTE GLOBAL Y VENTANA DEL ASISTENTE CLÍNICO DE VOZ CON IA -->
<style>
    .vs-ai-fab {
        position: fixed;
        right: 24px;
        bottom: 24px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0b2545 0%, #1d4ed8 100%);
        color: #ffffff;
        border: 3px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 10px 28px rgba(11, 37, 69, 0.38);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.55rem;
        cursor: pointer;
        z-index: 1060;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .vs-ai-fab:hover {
        transform: translateY(-3px) scale(1.04);
        box-shadow: 0 14px 32px rgba(29, 78, 216, 0.48);
    }
    .vs-ai-fab.listening {
        background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
        animation: vsAiFabPulse 1.3s infinite;
    }
    @keyframes vsAiFabPulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.65); }
        70% { box-shadow: 0 0 0 16px rgba(220, 38, 38, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
    }
    .vs-ai-panel {
        position: fixed;
        right: 24px;
        bottom: 96px;
        width: 390px;
        max-width: calc(100vw - 32px);
        height: 540px;
        max-height: calc(100vh - 125px);
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 18px 48px rgba(11, 37, 69, 0.28);
        border: 1px solid rgba(11, 37, 69, 0.12);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 1060;
    }
    .vs-ai-panel.open {
        display: flex;
    }
    .vs-ai-header {
        background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
        color: #ffffff;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .vs-ai-messages {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        background: #f8fafc;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .vs-ai-bubble {
        max-width: 88%;
        padding: 10px 13px;
        border-radius: 14px;
        font-size: 0.84rem;
        line-height: 1.45;
        word-wrap: break-word;
    }
    .vs-ai-bubble.bot {
        align-self: flex-start;
        background: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
    }
    .vs-ai-bubble.user {
        align-self: flex-end;
        background: #1d4ed8;
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .vs-ai-chips {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 8px 12px;
        background: #f1f5f9;
        border-top: 1px solid #e2e8f0;
        scrollbar-width: thin;
    }
    .vs-ai-chip {
        white-space: nowrap;
        font-size: 0.73rem;
        padding: 4px 10px;
        border-radius: 999px;
        background: #ffffff;
        color: #0b2545;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        transition: all 0.15s;
    }
    .vs-ai-chip:hover {
        background: #0b2545;
        color: #ffffff;
        border-color: #0b2545;
    }
    .vs-ai-footer {
        padding: 10px 12px;
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
    }
    .vs-ai-mic-btn {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35);
        transition: transform 0.15s ease;
    }
    .vs-ai-mic-btn:hover {
        transform: scale(1.06);
    }
    .vs-ai-mic-btn.recording {
        animation: vsAiFabPulse 1.1s infinite;
    }
</style>

<button type="button" class="vs-ai-fab" id="vsAiFloatingBtn" title="Asistente Clínico Inteligente por Voz (IA)">
    <i class="bi bi-mic-fill" id="vsAiFabIcon"></i>
</button>

<div class="vs-ai-panel" id="vsAiAssistantPanel" aria-live="polite">
    <div class="vs-ai-header">
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-info text-dark"><i class="bi bi-stars"></i> IA</span>
            <div>
                <div class="fw-bold" style="font-size:0.88rem;line-height:1.15;">Asistente Clínico Vida Salud</div>
                <small class="text-white-50" style="font-size:0.7rem;" id="vsAiVoiceStatus">Consultas en vivo por voz en español</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-sm btn-link text-white p-1" id="vsAiToggleTtsBtn" title="Activar/Silenciar respuesta por voz">
                <i class="bi bi-volume-up-fill fs-6" id="vsAiTtsIcon"></i>
            </button>
            <button type="button" class="btn btn-sm btn-link text-white p-1" id="vsAiCloseBtn" title="Cerrar asistente">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <div class="vs-ai-messages" id="vsAiChatHistory">
        <div class="vs-ai-bubble bot">
            👋 ¡Hola <strong><?= e($currentUser['nombres'] ?? $currentUser['nombre_usuario'] ?? '') ?></strong>! Soy el <strong>Asistente Clínico Inteligente</strong> de la Clínica Vida Salud.<br>
            Presiona el micrófono <i class="bi bi-mic-fill text-danger"></i> o escribe para preguntarme en tiempo real sobre <strong>roles, usuarios, médicos, especialidades, horarios, pacientes, fichas médicas o citas</strong>.
        </div>
    </div>

    <div class="vs-ai-chips">
        <button type="button" class="vs-ai-chip" data-ask="¿Cuántos roles tenemos en el sistema?">¿Cuántos roles tenemos?</button>
        <button type="button" class="vs-ai-chip" data-ask="¿Quiénes son los administradores?">¿Quiénes son los administradores?</button>
        <button type="button" class="vs-ai-chip" data-ask="¿Qué médicos atienden en pediatría?">Médicos en Pediatría</button>
        <button type="button" class="vs-ai-chip" data-ask="¿Cuántos pacientes están registrados?">Total de pacientes</button>
        <button type="button" class="vs-ai-chip" data-ask="¿Qué pacientes tienen alergias?">Pacientes con alergias</button>
        <button type="button" class="vs-ai-chip" data-ask="¿Cuántas citas están atendidas, programadas o canceladas?">Estado de las citas</button>
    </div>

    <div class="vs-ai-footer">
        <form id="vsAiForm" class="d-flex align-items-center gap-2 mb-0">
            <button type="button" class="vs-ai-mic-btn" id="vsAiMicBtn" title="Hablar por micrófono (Español)">
                <i class="bi bi-mic-fill" id="vsAiMicIcon"></i>
            </button>
            <input type="text" id="vsAiInput" class="form-control form-control-sm"
                   placeholder="Habla con el micrófono o escribe tu pregunta..." autocomplete="off">
            <button type="submit" class="btn btn-sm btn-vs-primary px-3" id="vsAiSendBtn" title="Enviar consulta">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
    </div>
</div>

<script>
    window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    window.VS_FLASHES = <?= json_encode($flashes, JSON_UNESCAPED_UNICODE) ?>;
    window.VS_AI_ENDPOINT = <?= json_encode(BASE_URL . '/api_asistente.php') ?>;
    window.VS_AI_FALLBACK_ENDPOINT = <?= json_encode(url('asistente-ia/consultar')) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>
(function () {
    const fabBtn = document.getElementById('vsAiFloatingBtn');
    const panel = document.getElementById('vsAiAssistantPanel');
    const closeBtn = document.getElementById('vsAiCloseBtn');
    const chatHistory = document.getElementById('vsAiChatHistory');
    const form = document.getElementById('vsAiForm');
    const input = document.getElementById('vsAiInput');
    const micBtn = document.getElementById('vsAiMicBtn');
    const micIcon = document.getElementById('vsAiMicIcon');
    const voiceStatus = document.getElementById('vsAiVoiceStatus');
    const toggleTtsBtn = document.getElementById('vsAiToggleTtsBtn');
    const ttsIcon = document.getElementById('vsAiTtsIcon');

    if (!fabBtn || !panel) return;

    let ttsEnabled = true;
    let spanishVoice = null;

    // 1. Selección explícita de voz en Español Natural (window.speechSynthesis)
    function selectBestSpanishVoice() {
        if (!('speechSynthesis' in window)) return null;
        const voices = window.speechSynthesis.getVoices() || [];
        if (!voices.length) return null;

        const esVoices = voices.filter(v => /^es([-_]|$)/i.test(v.lang || ''));
        if (!esVoices.length) return null;

        const preferredNames = [
            'google español',
            'microsoft sabina',
            'microsoft elena',
            'microsoft dalia',
            'microsoft laura',
            'microsoft helena',
            'microsoft paloma',
            'paulina',
            'monica',
            'jorge',
            'natural'
        ];

        for (const pref of preferredNames) {
            const match = esVoices.find(v => (v.name || '').toLowerCase().includes(pref));
            if (match) return match;
        }

        const preferredLocales = ['es-mx', 'es-bo', 'es-es', 'es-ar', 'es-co', 'es-pe', 'es-cl', 'es-us', 'es-419'];
        for (const loc of preferredLocales) {
            const match = esVoices.find(v => (v.lang || '').toLowerCase().replace('_', '-') === loc);
            if (match) return match;
        }

        return esVoices[0];
    }

    if ('speechSynthesis' in window) {
        spanishVoice = selectBestSpanishVoice();
        window.speechSynthesis.onvoiceschanged = function () {
            spanishVoice = selectBestSpanishVoice();
        };
    }

    function cleanSpeechText(rawText) {
        return String(rawText || '')
            .replace(/[*#_`~>\[\]]+/g, '')
            .replace(/Dr\(a\)\./gi, 'Doctor ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function speakInSpanish(text) {
        if (!ttsEnabled || !('speechSynthesis' in window)) return;
        const cleaned = cleanSpeechText(text);
        if (!cleaned) return;

        window.speechSynthesis.cancel();
        if (!spanishVoice) {
            spanishVoice = selectBestSpanishVoice();
        }

        const utterance = new SpeechSynthesisUtterance(cleaned);
        if (spanishVoice) {
            utterance.voice = spanishVoice;
            utterance.lang = spanishVoice.lang || 'es-MX';
        } else {
            utterance.lang = 'es-MX';
        }
        utterance.rate = 1.02;
        utterance.pitch = 1.0;
        window.speechSynthesis.speak(utterance);
    }

    toggleTtsBtn.addEventListener('click', function () {
        ttsEnabled = !ttsEnabled;
        if (!ttsEnabled && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        ttsIcon.className = ttsEnabled ? 'bi bi-volume-up-fill fs-6' : 'bi bi-volume-mute-fill fs-6 text-warning';
    });

    // Abrir / cerrar ventana del asistente
    fabBtn.addEventListener('click', function () {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            input.focus();
        }
    });

    closeBtn.addEventListener('click', function () {
        panel.classList.remove('open');
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    });

    function formatMarkdownToHtml(md) {
        const escaped = String(md || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        return escaped
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    function appendBubble(content, role) {
        const div = document.createElement('div');
        div.className = 'vs-ai-bubble ' + role;
        div.innerHTML = role === 'bot' ? formatMarkdownToHtml(content) : formatMarkdownToHtml(content);
        chatHistory.appendChild(div);
        chatHistory.scrollTop = chatHistory.scrollHeight;
        return div;
    }

    async function sendQuestionToAssistant(questionText) {
        const q = String(questionText || '').trim();
        if (!q) return;

        if (!panel.classList.contains('open')) {
            panel.classList.add('open');
        }

        appendBubble(q, 'user');
        input.value = '';
        const loadingBubble = appendBubble('Consultando base de datos en tiempo real...', 'bot');
        voiceStatus.textContent = 'Analizando consulta clínica...';

        try {
            const fd = new FormData();
            fd.append('pregunta', q);
            fd.append('_csrf', window.CSRF_TOKEN || '');

            let data = null;
            try {
                const resp = await fetch(window.VS_AI_ENDPOINT, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const rawText = await resp.text();
                data = JSON.parse(rawText);
            } catch (primaryErr) {
                const respFallback = await fetch(window.VS_AI_FALLBACK_ENDPOINT, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const fallbackText = await respFallback.text();
                data = JSON.parse(fallbackText);
            }

            const answerText = (data && (data.answer || data.message)) ? (data.answer || data.message) : 'No pude procesar la respuesta.';
            loadingBubble.innerHTML = formatMarkdownToHtml(answerText);
            chatHistory.scrollTop = chatHistory.scrollHeight;
            voiceStatus.textContent = (data && data.engine) ? data.engine : 'Consultas en vivo por voz en español';

            speakInSpanish((data && data.speech_text) ? data.speech_text : answerText);
        } catch (err) {
            loadingBubble.innerHTML = 'Ocurrió un error de conexión al consultar el asistente.';
            voiceStatus.textContent = 'Error de conexión';
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendQuestionToAssistant(input.value);
    });

    document.querySelectorAll('.vs-ai-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const q = chip.getAttribute('data-ask') || '';
            sendQuestionToAssistant(q);
        });
    });

    // 2. Reconocimiento de voz en español (SpeechRecognition es-BO / es-ES)
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let isRecording = false;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'es-BO';
        recognition.continuous = false;
        recognition.interimResults = true;

        let finalTranscript = '';

        recognition.onstart = function () {
            isRecording = true;
            finalTranscript = '';
            micBtn.classList.add('recording');
            fabBtn.classList.add('listening');
            micIcon.className = 'bi bi-stop-fill';
            voiceStatus.textContent = '🎙️ Escuchando en español... habla ahora';
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        };

        recognition.onresult = function (event) {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) {
                    finalTranscript += transcript;
                } else {
                    interim += transcript;
                }
            }
            input.value = (finalTranscript || interim).trim();
        };

        recognition.onerror = function () {
            isRecording = false;
            micBtn.classList.remove('recording');
            fabBtn.classList.remove('listening');
            micIcon.className = 'bi bi-mic-fill';
            voiceStatus.textContent = 'No se detectó audio. Intenta de nuevo.';
        };

        recognition.onend = function () {
            isRecording = false;
            micBtn.classList.remove('recording');
            fabBtn.classList.remove('listening');
            micIcon.className = 'bi bi-mic-fill';

            const spokenText = input.value.trim();
            if (spokenText !== '') {
                sendQuestionToAssistant(spokenText);
            } else {
                voiceStatus.textContent = 'Consultas en vivo por voz en español';
            }
        };

        micBtn.addEventListener('click', function () {
            if (isRecording) {
                recognition.stop();
            } else {
                try {
                    recognition.start();
                } catch (e) {
                    recognition.lang = 'es-ES';
                    recognition.start();
                }
            }
        });
    } else {
        micBtn.addEventListener('click', function () {
            voiceStatus.textContent = 'Tu navegador no soporta dictado por voz; escribe tu consulta abajo.';
            input.focus();
        });
    }
})();
</script>
</body>
</html>
