/**
 * Motor JavaScript Frontend - Clínica Vida Salud
 * Validaciones en Tiempo Real, Complejidad de Contraseña CU03, Drawer Táctil y Alertas SweetAlert2
 */
window.VidaSalud = (function () {
    'use strict';

    function initSidebarDrawer() {
        const sidebar = document.getElementById('vsSidebar');
        const backdrop = document.getElementById('vsBackdrop');
        const toggleBtns = document.querySelectorAll('[data-vs-toggle="sidebar"]');

        if (!sidebar || !backdrop) return;

        function closeDrawer() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        }

        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                sidebar.classList.toggle('show');
                backdrop.classList.toggle('show');
            });
        });

        backdrop.addEventListener('click', closeDrawer);
    }

    function showAlert(type, title, message) {
        if (typeof Swal !== 'undefined') {
            const iconMap = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            Swal.fire({
                icon: iconMap[type] || 'info',
                title: title || 'Clínica Vida Salud',
                text: message,
                confirmButtonColor: '#002D62',
                confirmButtonText: 'Entendido'
            });
        } else {
            alert((title ? title + ':\n' : '') + message);
        }
    }

    function showToast(type, message, title) {
        if (typeof Swal !== 'undefined') {
            if (type === 'error' || type === 'warning') {
                showAlert(type, title, message);
                return;
            }
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type || 'success',
                title: title ? (title + ': ' + message) : message,
                showConfirmButton: false,
                timer: 4200,
                timerProgressBar: true
            });
        } else {
            console.log('[VidaSalud]', type, title, message);
        }
    }

    function evaluatePassword(pwd) {
        return {
            len: pwd.length >= 8,
            upper: /[A-Z]/.test(pwd),
            lower: /[a-z]/.test(pwd),
            num: /[0-9]/.test(pwd),
            spec: /[^A-Za-z0-9]/.test(pwd)
        };
    }

    function isPasswordValid(pwd) {
        const r = evaluatePassword(pwd);
        return r.len && r.upper && r.lower && r.num && r.spec;
    }

    function attachPasswordMeters() {
        document.querySelectorAll('[data-pwd-meter]').forEach(function (input) {
            const targetId = input.getAttribute('data-pwd-meter');
            const container = document.getElementById(targetId);
            if (!container) return;

            function update() {
                const val = input.value || '';
                const checks = evaluatePassword(val);
                Object.keys(checks).forEach(function (key) {
                    const pill = container.querySelector('[data-rule="' + key + '"]');
                    if (pill) {
                        pill.classList.toggle('valid', checks[key]);
                    }
                });
            }

            input.addEventListener('input', update);
            update();
        });
    }

    function attachClientTableSearch() {
        document.querySelectorAll('[data-table-filter]').forEach(function (input) {
            const tableSelector = input.getAttribute('data-table-filter');
            const table = document.querySelector(tableSelector);
            if (!table) return;

            input.addEventListener('input', function () {
                const term = input.value.trim().toLowerCase();
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    if (row.hasAttribute('data-empty-row')) return;
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        });
    }

    function attachConfirmForms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (form.dataset.confirmed === '1') return;
                ev.preventDefault();
                const msg = form.getAttribute('data-confirm') || '¿Confirma realizar esta operación?';
                const title = form.getAttribute('data-confirm-title') || 'Confirmar operación';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: title,
                        text: msg,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#002D62',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar'
                    }).then(function (res) {
                        if (res.isConfirmed) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
                } else if (confirm(msg)) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    }

    function attachStrictForms() {
        document.querySelectorAll('form.needs-validation').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                // Validar campos de contraseña estricta si están presentes y tienen valor/son requeridos
                const pwdInputs = form.querySelectorAll('[data-strict-password]');
                for (const inp of pwdInputs) {
                    const val = inp.value || '';
                    const optional = inp.hasAttribute('data-optional') && val === '';
                    if (!optional && !isPasswordValid(val)) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        showAlert(
                            'warning',
                            'Política de Contraseña Segura (CU03)',
                            'La contraseña debe tener mínimo 8 caracteres, al menos 1 mayúscula, 1 minúscula, 1 número y 1 carácter especial (ej. Cher_4mi).'
                        );
                        inp.focus();
                        return;
                    }
                }

                if (!form.checkValidity()) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebarDrawer();
        attachPasswordMeters();
        attachClientTableSearch();
        attachConfirmForms();
        attachStrictForms();

        if (Array.isArray(window.VS_FLASHES)) {
            window.VS_FLASHES.forEach(function (f) {
                showToast(f.type || 'info', f.message || '', f.title || '');
            });
        }
    });

    return {
        alert: showAlert,
        toast: showToast,
        evaluatePassword: evaluatePassword,
        isPasswordValid: isPasswordValid
    };
})();
