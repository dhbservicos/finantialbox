/**
 * First Financial Box — bank_import.js
 *
 * Dados injetados via wp_localize_script (Plugin.php::enqueueAssets):
 *   FFB.ajax_url, FFB.nonce           → globais de todo o plugin
 *   FFB_PAGE.categories, FFB_PAGE.accounts → específicos desta página
 *
 * NÃO usa variáveis inline do template para evitar o problema de
 * "script executa antes das vars serem definidas".
 */

/* ---- Aguarda DOM + jQuery prontos ---- */
jQuery(function($) {

/* ---- Referências seguras às variáveis globais ---- */
var AJAX_URL   = (typeof FFB_PAGE !== 'undefined' && FFB_PAGE.ajax_url) ? FFB_PAGE.ajax_url : (FFB ? FFB.ajax_url : '');
var NONCE      = (typeof FFB_PAGE !== 'undefined' && FFB_PAGE.nonce)    ? FFB_PAGE.nonce    : (FFB ? FFB.nonce : '');
var CATEGORIES = (typeof FFB_PAGE !== 'undefined') ? (FFB_PAGE.categories || []) : [];
var ACCOUNTS   = (typeof FFB_PAGE !== 'undefined') ? (FFB_PAGE.accounts   || []) : [];

/* ---- Estado interno ---- */
var _biTxs = [], _biFmt = '', _biFile = '', _biAccountId = '';

/* ============================================================
   UTILITÁRIOS
   ============================================================ */
function escHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
}
function fmtDate(iso) {
    if (!iso) return '—';
    var p = iso.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function fmtMoney(v) {
    return 'R$ ' + parseFloat(v || 0).toFixed(2).replace('.', ',');
}
function catOptions(tipo, sugerida, selectedId) {
    var cats = CATEGORIES.filter(function(c) { return c.type === (tipo === 'income' ? 'income' : 'expense'); });
    var html = '<option value="">— sem categoria —</option>';
    cats.forEach(function(c) {
        var sel = '';
        if (selectedId && String(c.id) === String(selectedId)) {
            sel = ' selected';
        } else if (!selectedId && sugerida && c.name.toLowerCase().indexOf(sugerida.toLowerCase()) >= 0) {
            sel = ' selected';
        }
        html += '<option value="' + c.id + '"' + sel + '>' + escHtml(c.name) + '</option>';
    });
    return html;
}

/* ============================================================
   MAPA DE TIPO POR PALAVRA-CHAVE (reforço client-side)
   ============================================================ */
var TIPO_KEYWORDS = {
    'dinheiro retirado':      'expense',
    'dinheiro reservado':     'expense',
    'saque':                  'expense',
    'pix enviado':            'expense',
    'transferencia enviada':  'expense',
    'transferência enviada':  'expense',
    'ted enviada':            'expense',
    'compra no debito':       'expense',
    'compra no débito':       'expense',
    'pagamento efetuado':     'expense',
    'pagamento cartao':       'expense',
    'pagamento cartão':       'expense',
    'aplicacao':              'expense',
    'aplicação':              'expense',
    'facebook pay':           'expense',
    'pix recebido':           'income',
    'transferencia recebida': 'income',
    'transferência recebida': 'income',
    'ted recebida':           'income',
    'deposito':               'income',
    'depósito':               'income',
    'estorno':                'income',
    'reembolso':              'income',
    'salario':                'income',
    'salário':                'income',
    'rendimento':             'income',
    'rendimentos':            'income',
    'cashback':               'income',
};

function detectarTipoLocal(desc, tipoOriginal) {
    var d = desc.toLowerCase();
    var keys = Object.keys(TIPO_KEYWORDS).sort(function(a, b) { return b.length - a.length; });
    for (var i = 0; i < keys.length; i++) {
        if (d.indexOf(keys[i]) >= 0) return TIPO_KEYWORDS[keys[i]];
    }
    return tipoOriginal;
}

/* ============================================================
   STEP 1 — Upload e análise
   ============================================================ */
window.biAnalisar = function() {
    var fi     = document.getElementById('fileInput');
    var ai     = document.getElementById('accountSelect');
    var erroEl = document.getElementById('uploadErro');
    erroEl.style.display = 'none';

    if (!fi || !fi.files || !fi.files[0]) {
        erroEl.textContent = 'Selecione um arquivo.';
        erroEl.style.display = '';
        return;
    }
    if (!ai || !ai.value) {
        erroEl.textContent = 'Selecione a conta bancária.';
        erroEl.style.display = '';
        return;
    }

    _biAccountId = ai.value;
    var btn = document.getElementById('btnAnalisar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analisando...';

    var fd = new FormData();
    fd.append('arquivo',   fi.files[0]);
    fd.append('action',    'ffb_bank_preview');
    fd.append('_wpnonce',  NONCE);

    $.ajax({
        url:         AJAX_URL,
        method:      'POST',
        data:        fd,
        processData: false,
        contentType: false,
        success: function(resp) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-2"></i>Analisar Arquivo';
            if (!resp.success) {
                var msg = (resp.data && resp.data.message) ? resp.data.message : 'Erro ao processar o arquivo.';
                erroEl.textContent = msg;
                erroEl.style.display = '';
                return;
            }
            _biTxs  = resp.data.transacoes || [];
            _biFmt  = resp.data.formato    || 'csv';
            _biFile = resp.data.filename   || 'extrato';

            // Reforço client-side: corrige tipos pela palavra-chave
            _biTxs.forEach(function(tx) {
                tx.type = detectarTipoLocal(tx.description, tx.type);
            });

            biMostrarRevisao();
        },
        error: function(xhr) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-2"></i>Analisar Arquivo';
            var msg = 'Erro de comunicação (HTTP ' + xhr.status + ').';
            if (xhr.status === 400) msg += ' Verifique se o arquivo é válido e tente novamente.';
            if (xhr.status === 403) msg += ' Sessão expirada — recarregue a página.';
            erroEl.textContent = msg;
            erroEl.style.display = '';
        }
    });
};

/* ============================================================
   STEP 2 — Revisão por linha
   ============================================================ */
window.biMostrarRevisao = function() {
    document.getElementById('stepUpload').style.display  = 'none';
    document.getElementById('stepRevisao').style.display = '';
    document.getElementById('revFilename').textContent   = _biFile;
    document.getElementById('revTotal').textContent      = _biTxs.length;
    document.getElementById('btnImportarCount').textContent = '(' + _biTxs.length + ')';

    var tbody = document.getElementById('corpoRevisao');
    tbody.innerHTML = '';

    _biTxs.forEach(function(tx, idx) {
        var isInc     = tx.type === 'income';
        var tipoBadge = isInc ? 'ffb-badge-paid' : 'ffb-badge-pending';
        var tipoLabel = isInc ? 'Receita' : 'Despesa';

        var tr = document.createElement('tr');
        tr.setAttribute('data-idx',  idx);
        tr.setAttribute('data-type', tx.type);

        tr.innerHTML =
            '<td><input type="checkbox" class="row-check" checked onchange="biAtualizarContador()"></td>' +
            '<td><small>' + escHtml(fmtDate(tx.date)) + '</small></td>' +
            '<td style="max-width:280px" title="' + escHtml(tx.description) + '"><small>' +
                escHtml(tx.description.length > 58 ? tx.description.substring(0, 58) + '…' : tx.description) +
            '</small></td>' +
            '<td class="text-center">' +
                '<button type="button" class="badge border-0 tipo-toggle ' + tipoBadge + '" ' +
                'data-idx="' + idx + '" onclick="biToggleTipo(' + idx + ')" title="Clique para inverter">' +
                tipoLabel + '</button>' +
            '</td>' +
            '<td class="text-end fw-600 ' + (isInc ? 'ffb-income' : 'ffb-expense') + '" id="val-' + idx + '">' +
                '<small>' + fmtMoney(tx.amount) + '</small>' +
            '</td>' +
            '<td>' +
                '<select class="form-select form-select-sm cat-select" data-idx="' + idx + '" style="font-size:11px">' +
                    catOptions(tx.type, tx.categoria_sugerida, null) +
                '</select>' +
            '</td>';

        tbody.appendChild(tr);
    });

    // Popula select do modal "aplicar a todas"
    var selG = document.getElementById('categoriaGlobal');
    if (selG) {
        selG.innerHTML = '<option value="">— manter sugestão —</option>';
        CATEGORIES.forEach(function(c) {
            selG.innerHTML += '<option value="' + c.id + '">' + escHtml(c.name) + ' (' + c.type + ')</option>';
        });
    }

    var kwInput = document.getElementById('kwFiltro');
    if (kwInput) kwInput.value = '';
    var kwMsg = document.getElementById('kwMsg');
    if (kwMsg) kwMsg.style.display = 'none';
};

/* ---- Toggle de tipo por linha ---- */
window.biToggleTipo = function(idx) {
    _biTxs[idx].type = _biTxs[idx].type === 'income' ? 'expense' : 'income';
    var isInc = _biTxs[idx].type === 'income';
    var tr = document.querySelector('tr[data-idx="' + idx + '"]');
    if (!tr) return;
    tr.setAttribute('data-type', _biTxs[idx].type);

    var badge = tr.querySelector('.tipo-toggle');
    if (badge) {
        badge.textContent = isInc ? 'Receita' : 'Despesa';
        badge.className = 'badge border-0 tipo-toggle ' + (isInc ? 'ffb-badge-paid' : 'ffb-badge-pending');
    }
    var valCell = document.getElementById('val-' + idx);
    if (valCell) valCell.className = 'text-end fw-600 ' + (isInc ? 'ffb-income' : 'ffb-expense');

    var sel = tr.querySelector('.cat-select');
    if (sel) sel.innerHTML = catOptions(_biTxs[idx].type, _biTxs[idx].categoria_sugerida, null);
};

/* ---- Filtro por palavra-chave ---- */
window.biAplicarFiltroKW = function() {
    var kw    = (document.getElementById('kwFiltro').value || '').trim().toLowerCase();
    var catId = document.getElementById('kwCategoria').value;
    var tipo  = document.getElementById('kwTipo').value;
    if (!kw) { alert('Digite uma palavra-chave.'); return; }

    var aplicados = 0;
    document.querySelectorAll('tr[data-idx]').forEach(function(tr) {
        var idx  = parseInt(tr.getAttribute('data-idx'), 10);
        var desc = _biTxs[idx].description.toLowerCase();
        if (desc.indexOf(kw) < 0) return;

        if (tipo && _biTxs[idx].type !== tipo) biToggleTipo(idx);
        if (catId) {
            var sel = tr.querySelector('.cat-select');
            if (sel) { sel.innerHTML = catOptions(_biTxs[idx].type, '', catId); sel.value = catId; }
        }
        aplicados++;
    });

    var msg = document.getElementById('kwMsg');
    if (msg) {
        if (aplicados === 0) {
            msg.textContent = 'Nenhuma linha contém "' + kw + '".';
            msg.className = 'small text-danger mt-1 fw-600';
        } else {
            msg.textContent = aplicados + ' linha(s) atualizadas.';
            msg.className = 'small text-success mt-1 fw-600';
        }
        msg.style.display = '';
    }
};

window.biVoltar = function() {
    document.getElementById('stepRevisao').style.display = 'none';
    document.getElementById('stepUpload').style.display  = '';
    document.getElementById('importErro').style.display  = 'none';
    document.getElementById('importSucesso').style.display = 'none';
};
window.biToggleAll = function(chk) {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = chk; });
    biAtualizarContador();
};
window.biAtualizarContador = function() {
    var s = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('btnImportarCount').textContent = '(' + s + ')';
};
window.biAplicarTodas = function() {
    new bootstrap.Modal(document.getElementById('modalCategoriaTodas')).show();
};
window.biAplicarGlobal = function() {
    var catId = document.getElementById('categoriaGlobal').value;
    if (!catId) { bootstrap.Modal.getInstance(document.getElementById('modalCategoriaTodas')).hide(); return; }
    document.querySelectorAll('tr[data-idx]').forEach(function(tr) {
        var cb = tr.querySelector('.row-check');
        if (cb && cb.checked) { var sel = tr.querySelector('.cat-select'); if (sel) sel.value = catId; }
    });
    bootstrap.Modal.getInstance(document.getElementById('modalCategoriaTodas')).hide();
};

/* ============================================================
   STEP 3 — Confirmar importação
   ============================================================ */
window.biConfirmar = function() {
    var erroEl = document.getElementById('importErro');
    var sucEl  = document.getElementById('importSucesso');
    erroEl.style.display = 'none';
    sucEl.style.display  = 'none';

    var selecionadas = [], categorias = {};
    document.querySelectorAll('tr[data-idx]').forEach(function(tr) {
        var idx = parseInt(tr.getAttribute('data-idx'), 10);
        var cb  = tr.querySelector('.row-check');
        var sel = tr.querySelector('.cat-select');
        if (cb && cb.checked) {
            var txCopia  = $.extend({}, _biTxs[idx]);
            txCopia.type = tr.getAttribute('data-type') || txCopia.type;
            selecionadas.push(txCopia);
            categorias[selecionadas.length - 1] = sel ? sel.value : '';
        }
    });

    if (!selecionadas.length) {
        erroEl.textContent = 'Selecione ao menos uma transação.';
        erroEl.style.display = '';
        return;
    }

    var btn = document.getElementById('btnImportar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importando...';

    var data = {
        action:           'ffb_bank_upload',
        _wpnonce:         NONCE,
        account_id:       _biAccountId,
        formato:          _biFmt,
        filename:         _biFile,
        transacoes_json:  JSON.stringify(selecionadas),
    };
    Object.keys(categorias).forEach(function(k) { data['categories[' + k + ']'] = categorias[k]; });

    $.post(AJAX_URL, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download me-2"></i>Importar';
        if (!resp.success) {
            erroEl.textContent = (resp.data && resp.data.message) ? resp.data.message : 'Erro desconhecido.';
            erroEl.style.display = '';
            return;
        }
        var d   = resp.data;
        var msg = d.importado + ' transações importadas';
        if (d.pulado > 0) msg += ', ' + d.pulado + ' ignoradas (duplicatas)';
        if (d.erros && d.erros.length) msg += '. Erros: ' + d.erros.join('; ');
        sucEl.textContent = '✓ ' + msg;
        sucEl.style.display = '';
        setTimeout(function() { location.reload(); }, 2000);
    }).fail(function(xhr) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download me-2"></i>Importar';
        erroEl.textContent = 'Erro de comunicação (HTTP ' + xhr.status + '). Tente novamente.';
        erroEl.style.display = '';
    });
};

}); // fim jQuery(function($) {...})
