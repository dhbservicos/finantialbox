/**
 * First Financial Box — nfce.js
 * Dados via FFB_PAGE (wp_localize_script em Plugin.php)
 */
jQuery(function($) {

var AJAX_URL   = (typeof FFB_PAGE !== 'undefined') ? FFB_PAGE.ajax_url   : FFB.ajax_url;
var NONCE      = (typeof FFB_PAGE !== 'undefined') ? FFB_PAGE.nonce      : FFB.nonce;
var CATEGORIES = (typeof FFB_PAGE !== 'undefined') ? (FFB_PAGE.categories || []) : [];
var ACCOUNTS   = (typeof FFB_PAGE !== 'undefined') ? (FFB_PAGE.accounts   || []) : [];

/* ---- Dados dos gráficos injetados inline pelo template (ainda necessários) ---- */
var TOP_NOMES  = (typeof NFCE_TOP_NOMES  !== 'undefined') ? NFCE_TOP_NOMES  : [];
var TOP_VALS   = (typeof NFCE_TOP_VALS   !== 'undefined') ? NFCE_TOP_VALS   : [];
var TOP_VEZES  = (typeof NFCE_TOP_VEZES  !== 'undefined') ? NFCE_TOP_VEZES  : [];
var TOP_CORES  = (typeof NFCE_TOP_CORES  !== 'undefined') ? NFCE_TOP_CORES  : [];

function escHtml(s) {
    if (s == null) return '';
    var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
}
function fmtBR(v)  { return 'R$ ' + parseFloat(v || 0).toFixed(2).replace('.', ','); }
function fmtQtd(v) { var n = parseFloat(v || 0); return n % 1 === 0 ? String(Math.round(n)) : n.toFixed(3).replace('.', ','); }

/* ---- Gráfico top produtos do mês ---- */
(function() {
    var canvas = document.getElementById('chartTopMes');
    if (!canvas || !TOP_NOMES.length) return;
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: TOP_NOMES,
            datasets: [{
                label: 'Gasto Total (R$)',
                data: TOP_VALS,
                backgroundColor: TOP_CORES.map(function(c) { return c + '55'; }),
                borderColor: TOP_CORES,
                borderWidth: 2, borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function(ctx) {
                    var v  = parseFloat(ctx.raw).toFixed(2).replace('.', ',');
                    var vz = TOP_VEZES[ctx.dataIndex];
                    return ' R$ ' + v + '  (' + vz + 'x comprado' + (vz > 1 ? 's' : '') + ')';
                }}}
            },
            scales: {
                x: { ticks: { callback: function(v) { return 'R$ ' + v.toLocaleString('pt-BR'); } } },
                y: { ticks: { font: { size: 11 } } }
            }
        }
    });
})();

/* ---- Gráfico anual ---- */
var chartAnualInst = null;
window.carregarChartAnual = function(ano) {
    $.get(AJAX_URL, { action: 'ffb_nfce_chart_anual', year: ano, _wpnonce: NONCE }, function(resp) {
        if (!resp.success) return;
        var d = resp.data;
        var canvas = document.getElementById('chartAnual');
        if (!canvas) return;
        if (chartAnualInst) { chartAnualInst.destroy(); chartAnualInst = null; }
        if (!d.labels || !d.labels.length) return;
        chartAnualInst = new Chart(canvas, {
            type: 'line',
            data: { labels: d.labels, datasets: d.datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } },
                    tooltip: { callbacks: { label: function(ctx) {
                        return ' ' + ctx.dataset.label + ': R$ ' + parseFloat(ctx.raw).toFixed(2).replace('.', ',');
                    }}}
                },
                scales: { y: { beginAtZero: true, ticks: { callback: function(v) {
                    return 'R$ ' + v.toLocaleString('pt-BR');
                }}}}
            }
        });
    });
};

var sel = document.getElementById('selectAno');
if (sel) {
    sel.addEventListener('change', function() { carregarChartAnual(this.value); });
    carregarChartAnual(sel.value);
}

/* ---- Reset modal ---- */
var modal = document.getElementById('modalImportar');
if (modal) {
    modal.addEventListener('hidden.bs.modal', function() {
        var s1 = document.getElementById('step1'); if (s1) s1.style.display = '';
        var s2 = document.getElementById('step2'); if (s2) s2.style.display = 'none';
        var bi = document.getElementById('btnImportar'); if (bi) bi.style.display = 'none';
        var ni = document.getElementById('nfceInput'); if (ni) ni.value = '';
        var ci = document.getElementById('corpoItens'); if (ci) ci.innerHTML = '';
        var ic = document.getElementById('itensCard'); if (ic) ic.style.display = 'none';
        var er = document.getElementById('erroConsulta'); if (er) er.style.display = 'none';
    });
}

/* ---- Consulta NFC-e ---- */
window.nfceConsultar = function() {
    var inputEl = document.getElementById('nfceInput');
    var input   = inputEl ? inputEl.value.trim() : '';
    var erroEl  = document.getElementById('erroConsulta');
    if (!input) { erroEl.textContent = '⚠ Informe a chave ou URL do QR Code.'; erroEl.style.display = ''; return; }

    var btn = document.getElementById('btnConsultar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Consultando SEFAZ-SP...';
    erroEl.style.display = 'none';

    $.post(AJAX_URL, { action: 'ffb_nfce_consultar', chave_ou_qrcode: input, _wpnonce: NONCE }, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search me-2"></i>Consultar SEFAZ-SP';
        if (!resp.success) {
            erroEl.textContent = '⚠ ' + ((resp.data && resp.data.message) ? resp.data.message : 'Erro desconhecido');
            erroEl.style.display = '';
            return;
        }
        nfcePreencherStep2(resp.data.nfce);
    }).fail(function(xhr) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-search me-2"></i>Consultar SEFAZ-SP';
        erroEl.textContent = '⚠ Erro de comunicação (HTTP ' + xhr.status + ')';
        erroEl.style.display = '';
    });
};

window.nfcePreencherStep2 = function(n) {
    document.getElementById('impChave').value    = n.chave || '';
    document.getElementById('impEmitente').value = (n.emitente && n.emitente.nome) ? n.emitente.nome : '';
    document.getElementById('impProdutosJson').value = JSON.stringify(n.produtos || []);
    var total = (n.pagamentos && n.pagamentos[0] && n.pagamentos[0].valor) ? n.pagamentos[0].valor : 0;
    var tipo  = (n.pagamentos && n.pagamentos[0] && n.pagamentos[0].forma) ? n.pagamentos[0].forma : ''; 
    if (total > 0) document.getElementById('impAmount').value = total.toFixed(2);
    document.getElementById('impDate').value = n.data_emissao || new Date().toISOString().slice(0, 10);
    document.getElementById('impDesc').value = 'NFC-e: ' + ((n.emitente && n.emitente.nome) ? n.emitente.nome : 'Emitente');
    // alert(JSON.stringify(n.pagamentos[0].forma) + tipo);
    var info = '';
    if (n.emitente && n.emitente.nome) info += '<div class="mb-1"><strong>' + escHtml(n.emitente.nome) + '</strong></div>';
    if (n.emitente && n.emitente.cnpj) info += '<div class="small text-muted">CNPJ: ' + escHtml(n.emitente.cnpj) + '</div>';
    if (n.data_emissao) info += '<div class="small text-muted">Emiss\u00e3o: ' + escHtml(n.data_emissao) + '</div>';
    if (tipo) info += '<div class="small text-muted">Tipo de Pagto.: ' + escHtml(tipo) + '</div>';
    if (total > 0) info += '<div class="mt-2 fw-600 ffb-expense">Total: R$ ' + total.toFixed(2).replace('.', ',') + '</div>';
    
    document.getElementById('infoNotaContent').innerHTML = info || '<small class="text-muted">Dados parciais</small>';

    var tbody = document.getElementById('corpoItens');
    var produtos = n.produtos || [];
    tbody.innerHTML = '';
    if (produtos.length) {
        var tg = 0;
        produtos.forEach(function(p) {
            tg += parseFloat(p.valor_total) || 0;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="max-width:160px;word-break:break-word"><small>' + escHtml(p.nome) + '</small></td>' +
                '<td class="text-center"><small>' + fmtQtd(p.quantidade) + '</small></td>' +
                '<td class="text-end"><small>' + fmtBR(p.valor_unitario) + '</small></td>' +
                '<td class="text-end ffb-expense fw-600"><small>' + fmtBR(p.valor_total) + '</small></td>';
            tbody.appendChild(tr);
        });
        document.getElementById('totalCupom').textContent = 'R$ ' + tg.toFixed(2).replace('.', ',');
        document.getElementById('itensCard').style.display = '';
    }

    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = '';
    document.getElementById('btnImportar').style.display = '';
};

window.nfceImportar = function() {
    var btn = document.getElementById('btnImportar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';

    var data = {
        action:        'ffb_nfce_importar',
        _wpnonce:      NONCE,
        chave:         document.getElementById('impChave').value,
        emitente:      document.getElementById('impEmitente').value,
        description:   document.getElementById('impDesc').value,
        amount:        document.getElementById('impAmount').value,
        date:          document.getElementById('impDate').value,
        account_id:    document.getElementById('impAccount').value,
        category_id:   document.getElementById('impCategory').value,
        produtos_json: document.getElementById('impProdutosJson').value,
    };
        console.log(JSON.stringify(data));

    $.post(AJAX_URL, data, function(resp) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download me-1"></i>Criar Despesa';
        if (resp.success) {
            var m = bootstrap.Modal.getInstance(document.getElementById('modalImportar'));
            if (m) m.hide();
            location.reload();
        } else {
            ffbAlert((resp.data && resp.data.message ? resp.data.message : 'Erro desconhecido'), 'danger');
        }
    }).fail(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download me-1"></i>Criar Despesa';
        ffbAlert('Erro de comunicação.', 'danger');
    });
};

}); // fim jQuery
