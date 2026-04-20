/**
 * First Financial Box — ffb.js
 * JavaScript principal do plugin WordPress.
 * Usa FFB.ajax_url e FFB.nonce injetados via wp_localize_script().
 */

(function($) {
    'use strict';

    /* ============================================================
       INICIALIZAÇÃO
       ============================================================ */
    $(document).ready(function() {
        initSidebar();
        initAlerts();
        initForms();
        initModals();
    });

    /* ---- Sidebar toggle (mobile) ---- */
    function initSidebar() {
        $('#ffbToggle').on('click', function() {
            $('#ffbSidebar').toggleClass('open');
            $('#ffbOverlay').toggleClass('open');
        });
        $('#ffbOverlay').on('click', function() {
            $('#ffbSidebar').removeClass('open');
            $(this).removeClass('open');
        });
    }

    /* ---- Auto-dismiss de alertas bootstrap ---- */
    function initAlerts() {
        setTimeout(function() {
            $('.ffb-flash .alert').each(function() {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(this);
                bsAlert.close();
            });
        }, 4000);
    }

    /* ---- Loading state em formulários ---- */
    function initForms() {
        $(document).on('submit', '.ffb-wrap form', function() {
            var $btn = $(this).find('[type="submit"]');
            if ($btn.length) {
                $btn.prop('disabled', true);
                $btn.data('orig', $btn.html());
                $btn.html('<span class="spinner-border spinner-border-sm me-1"></span>' + (FFB.strings.saving || 'Salvando...'));
                setTimeout(function() {
                    $btn.prop('disabled', false).html($btn.data('orig'));
                }, 8000);
            }
        });
    }

    /* ---- Inicializa tooltips Bootstrap ---- */
    function initModals() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    }

    /* ============================================================
       AJAX HELPER — wrapper sobre admin-ajax.php do WP
       ============================================================ */

    /**
     * Faz uma requisição AJAX ao WordPress.
     * @param {string} action  - Nome da action (wp_ajax_{action})
     * @param {object} data    - Dados adicionais
     * @param {object} options - { method, onSuccess, onError, fileUpload }
     */
    window.ffbAjax = function(action, data, options) {
        options = options || {};
        var formData;

        if (options.fileUpload) {
            formData = data instanceof FormData ? data : new FormData();
            formData.append('action', action);
            formData.append('_wpnonce', FFB.nonce);
        } else {
            // Garante que action e nonce estejam presentes em qualquer método (GET ou POST)
            formData = $.extend({ action: action, _wpnonce: FFB.nonce }, data || {});
        }

        var method = (options.method || 'POST').toUpperCase();
        var ajaxOpts = {
            url:         FFB.ajax_url,
            method:      method,
            processData: options.fileUpload ? false : true,
            contentType: options.fileUpload ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            success: function(response) {
                if (response.success) {
                    if (typeof options.onSuccess === 'function') options.onSuccess(response.data);
                } else {
                    var msg = (response.data && response.data.message) || (FFB.strings.error || 'Erro desconhecido');
                    if (typeof options.onError === 'function') options.onError(msg);
                    else ffbAlert(msg, 'danger');
                }
            },
            error: function(xhr) {
                var msg = 'Erro de comunicação (' + xhr.status + ')';
                if (typeof options.onError === 'function') options.onError(msg);
                else ffbAlert(msg, 'danger');
            }
        };

        // Para GET: envia dados como query string na URL para garantir que _wpnonce chegue ao servidor
        // Para POST: envia no body normalmente
        if (method === 'GET') {
            ajaxOpts.url  = FFB.ajax_url + '?' + $.param(formData);
            ajaxOpts.data = null;
        } else {
            ajaxOpts.data = formData;
        }

        $.ajax(ajaxOpts);
    };

    /* ============================================================
       UTILITÁRIOS GLOBAIS
       ============================================================ */

    /** Exibe um alerta temporário no topo da página */
    window.ffbAlert = function(message, type) {
        type = type || 'success';
        var icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
        var html = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                   '<i class="bi bi-' + icon + ' me-2"></i>' + escHtml(message) +
                   '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                   '</div>';
        var $container = $('.ffb-flash');
        if (!$container.length) {
            $container = $('<div class="ffb-flash"></div>').insertBefore('.ffb-content');
        }
        var $alert = $(html).appendTo($container);
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance($alert[0]);
            bsAlert.close();
        }, 5000);
    };

    /** Formata valor em BRL */
    window.ffbMoney = function(v) {
        return 'R$ ' + parseFloat(v || 0).toFixed(2).replace('.', ',');
    };

    /** Formata data ISO → DD/MM/YYYY */
    window.ffbDate = function(iso) {
        if (!iso) return '—';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    };

    /** Escape HTML */
    window.escHtml = function(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    };

    /** Formata mês YYYY-MM → Mmm/AA */
    window.ffbMonthLabel = function(ym) {
        var parts  = ym.split('-');
        var labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        return (labels[parseInt(parts[1]) - 1] || parts[1]) + '/' + parts[0].slice(2);
    };

    /* ============================================================
       HANDLERS COMUNS (reutilizados em múltiplas páginas)
       ============================================================ */

    /** Confirma e apaga uma entidade via AJAX */
    window.ffbDelete = function(action, id, confirmMsg, callback) {
        if (!confirm(confirmMsg || (FFB.strings.confirm_delete || 'Confirmar exclusão?'))) return;
        ffbAjax(action, { id: id }, {
            onSuccess: function() {
                if (typeof callback === 'function') callback();
                else location.reload();
            }
        });
    };

    /** Carrega dados de uma entidade via AJAX e preenche um modal */
    window.ffbFetch = function(action, id, callback) {
        ffbAjax(action, { id: id }, {
            method: 'GET',
            onSuccess: function(data) {
                if (typeof callback === 'function') callback(data);
            }
        });
    };

})(jQuery);
