<?php
defined('ABSPATH') || exit;
// ajax_url, nonce, categories e accounts → agora em FFB_PAGE (wp_localize_script)
// Apenas os dados dinâmicos dos gráficos permanecem como vars inline
$jsTopNomes  = wp_json_encode(array_map(fn($p) => mb_strimwidth($p['nome'], 0, 35, '…'), $topProdutos ?? []));
$jsTopValores = wp_json_encode(array_column($topProdutos ?? [], 'valor_total'));
$jsTopVezes   = wp_json_encode(array_column($topProdutos ?? [], 'vezes'));
$jsTopCores   = wp_json_encode(array_map(function($i){
    $p = ['#6366F1','#10B981','#F59E0B','#EF4444','#3B82F6','#EC4899','#14B8A6','#8B5CF6','#F97316','#64748B'];
    return $p[$i % count($p)];
}, range(0, max(0, count($topProdutos ?? []) - 1))));
?>

<!-- KPIs -->
<div class="ffb-kpi-row">
    <div class="ffb-kpi ffb-kpi-balance"><div class="ffb-kpi-icon"><i class="bi bi-receipt"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Cupons','first-financial-box') ?></div><div class="ffb-kpi-value"><?= (int)($resumo['cupons']??0) ?></div></div></div>
    <div class="ffb-kpi ffb-kpi-expense"><div class="ffb-kpi-icon"><i class="bi bi-bag"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Itens Diferentes','first-financial-box') ?></div><div class="ffb-kpi-value"><?= (int)($resumo['total_itens']??0) ?></div></div></div>
    <div class="ffb-kpi ffb-kpi-income"><div class="ffb-kpi-icon"><i class="bi bi-123"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Unidades','first-financial-box') ?></div><div class="ffb-kpi-value"><?= number_format($resumo['total_unidades']??0,0,',','.') ?></div></div></div>
    <div class="ffb-kpi ffb-kpi-negative"><div class="ffb-kpi-icon"><i class="bi bi-currency-dollar"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Total Gasto','first-financial-box') ?></div><div class="ffb-kpi-value">R$ <?= number_format($resumo['valor_total']??0,2,',','.') ?></div></div></div>
</div>

<!-- Filtros -->
<div class="ffb-card mb-4">
    <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>">
        <input type="hidden" name="page" value="ffb-nfce">
        <div class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label"><?= esc_html__('Mês','first-financial-box') ?></label><input type="month" name="month" class="form-control" value="<?= esc_attr($month) ?>"></div>
            <div class="col-md-4"><label class="form-label"><?= esc_html__('Produto','first-financial-box') ?></label><input type="text" name="search" class="form-control" placeholder="<?= esc_attr__('Buscar produto...','first-financial-box') ?>" value="<?= esc_attr($search) ?>"></div>
            <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button><a href="<?= esc_url(admin_url('admin.php?page=ffb-nfce')) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
            <div class="col-md-4 d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportar"><i class="bi bi-plus-lg me-1"></i><?= esc_html__('Importar NFC-e','first-financial-box') ?></button>
            </div>
        </div>
    </form>
</div>

<!-- Gráficos -->
<?php if (!empty($topProdutos)): ?>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="ffb-card h-100">
            <div class="ffb-card-header"><h5><i class="bi bi-bar-chart me-2"></i><?= esc_html__('Top Produtos — ','first-financial-box') . esc_html($month) ?></h5></div>
            <div style="position:relative;height:260px"><canvas id="chartTopMes"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ffb-card h-100">
            <div class="ffb-card-header">
                <h5><i class="bi bi-graph-up me-2"></i><?= esc_html__('Gastos Anuais','first-financial-box') ?></h5>
                <select id="selectAno" class="form-select form-select-sm" style="width:90px">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y')-3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y==(int)date('Y')?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div style="position:relative;height:260px"><canvas id="chartAnual"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Tabela de Itens -->
<div class="ffb-card">
    <div class="ffb-card-header"><h5><i class="bi bi-list-ul me-2"></i><?= esc_html__('Itens de Cupons','first-financial-box') ?> (<?= (int)$total ?>)</h5></div>
    <?php if (!$temItens): ?>
        <div class="ffb-empty"><i class="bi bi-receipt"></i><p><?= esc_html__('Execute a ativação do plugin para criar as tabelas necessárias.','first-financial-box') ?></p></div>
    <?php elseif (empty($itens)): ?>
        <div class="ffb-empty"><i class="bi bi-inbox"></i><p><?= esc_html__('Nenhum item encontrado para o período selecionado.','first-financial-box') ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover ffb-table">
            <thead><tr><th><?= esc_html__('Data','first-financial-box') ?></th><th><?= esc_html__('Emitente','first-financial-box') ?></th><th><?= esc_html__('Produto','first-financial-box') ?></th><th class="text-center"><?= esc_html__('Qtd','first-financial-box') ?></th><th class="text-center">UN</th><th class="text-end"><?= esc_html__('Unit.','first-financial-box') ?></th><th class="text-end"><?= esc_html__('Total','first-financial-box') ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($itens as $item):
                    $q = (float)$item['quantidade'];
                    $qFmt = $q == (int)$q ? (int)$q : number_format($q, 3, ',', '.');
                ?>
                <tr>
                    <td><small><?= $item['data_emissao'] ? esc_html(date_i18n('d/m/Y', strtotime($item['data_emissao']))) : '—' ?></small></td>
                    <td><small><?= esc_html(mb_strimwidth($item['emitente']??'—',0,22,'…')) ?></small></td>
                    <td><?= esc_html($item['nome']) ?></td>
                    <td class="text-center"><?= esc_html($qFmt) ?></td>
                    <td class="text-center"><small class="text-muted"><?= esc_html($item['unidade']) ?></small></td>
                    <td class="text-end">R$ <?= number_format($item['valor_unitario'],2,',','.') ?></td>
                    <td class="text-end ffb-expense fw-600">R$ <?= number_format($item['valor_total'],2,',','.') ?></td>
                    <td><a href="https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p=<?= esc_attr($item['chave']) ?>|3|1" target="_blank" rel="noopener" class="btn ffb-btn-xs btn-outline-info" title="SEFAZ-SP"><i class="bi bi-box-arrow-up-right"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginator->totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
        <small class="text-muted"><?= sprintf(esc_html__('Exibindo %d–%d de %d','first-financial-box'), $paginator->from(), $paginator->to(), $paginator->total) ?></small>
        <?= $paginator->render(admin_url('admin.php?page=ffb-nfce&' . http_build_query(array_filter(['month'=>$month,'search'=>$search])))) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Cupons Importados -->
<?php if (!empty($imports)): ?>
<div class="ffb-card mt-4">
    <div class="ffb-card-header">
        <h5><i class="bi bi-receipt me-2"></i><?= esc_html__('Cupons Importados','first-financial-box') ?> (<?= count($imports) ?>)</h5>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#colImports"><i class="bi bi-chevron-down"></i></button>
    </div>
    <div class="collapse" id="colImports">
        <div class="table-responsive">
            <table class="table ffb-table">
                <thead><tr><th><?= esc_html__('Emissão','first-financial-box') ?></th><th><?= esc_html__('Emitente','first-financial-box') ?></th><th class="text-end"><?= esc_html__('Total','first-financial-box') ?></th><th><?= esc_html__('Chave','first-financial-box') ?></th></tr></thead>
                <tbody>
                    <?php foreach ($imports as $imp): ?>
                    <tr>
                        <td><small><?= $imp['data_emissao'] ? esc_html(date_i18n('d/m/Y',strtotime($imp['data_emissao']))) : '—' ?></small></td>
                        <td><small><?= esc_html(mb_strimwidth($imp['emitente']??'—',0,30,'…')) ?></small></td>
                        <td class="text-end ffb-expense fw-600">R$ <?= number_format((float)($imp['total']??0),2,',','.') ?></td>
                        <td><a href="https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p=<?= esc_attr($imp['chave']) ?>|3|1" target="_blank" class="small font-monospace text-muted"><?= esc_html(substr($imp['chave'],0,8)) ?>…<?= esc_html(substr($imp['chave'],-4)) ?> <i class="bi bi-box-arrow-up-right" style="font-size:10px"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Importar NFC-e -->
<div class="modal fade" id="modalImportar" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-qr-code-scan me-2"></i><?= esc_html__('Importar NFC-e','first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div id="step1">
                <p class="text-muted small"><?= esc_html__('Cole a chave de acesso (44 dígitos, modelo 65) ou URL do QR Code.','first-financial-box') ?></p>
                <textarea id="nfceInput" class="form-control font-monospace mb-2" rows="3" placeholder="35260404222166000103650020000006271554252357"></textarea>
                <div id="erroConsulta" class="alert alert-danger py-2 small" style="display:none"></div>
                <button class="btn btn-primary w-100" id="btnConsultar" onclick="nfceConsultar()"><?= esc_html__('Consultar SEFAZ-SP','first-financial-box') ?></button>
            </div>
            <div id="step2" style="display:none">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 mb-2" style="background:#F8FAFC;border-radius:8px"><small class="text-muted fw-600 text-uppercase d-block mb-1" style="letter-spacing:.5px"><?= esc_html__('Nota','first-financial-box') ?></small><div id="infoNotaContent"></div></div>
                        <div id="itensCard" style="display:none">
                            <small class="text-muted fw-600 text-uppercase d-block mb-1" style="letter-spacing:.5px"><?= esc_html__('Itens','first-financial-box') ?></small>
                            <table class="table table-sm ffb-table"><thead><tr><th><?= esc_html__('Produto','first-financial-box') ?></th><th class="text-center"><?= esc_html__('Qtd','first-financial-box') ?></th><th class="text-end"><?= esc_html__('Unit.','first-financial-box') ?></th><th class="text-end"><?= esc_html__('Total','first-financial-box') ?></th></tr></thead>
                            <tbody id="corpoItens"></tbody>
                            <tfoot><tr class="fw-700"><td colspan="3" class="text-end text-muted small"><?= esc_html__('Total:','first-financial-box') ?></td><td class="text-end ffb-expense" id="totalCupom">—</td></tr></tfoot></table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2"><label class="form-label"><?= esc_html__('Descrição','first-financial-box') ?></label><input type="text" id="impDesc" class="form-control"></div>
                        <div class="row g-2 mb-2"><div class="col-7"><label class="form-label"><?= esc_html__('Valor (R$)','first-financial-box') ?></label><div class="input-group"><span class="input-group-text">R$</span><input type="number" id="impAmount" class="form-control" step="0.01"></div></div><div class="col-5"><label class="form-label"><?= esc_html__('Data','first-financial-box') ?></label><input type="date" id="impDate" class="form-control"></div></div>
                        <div class="mb-2"><label class="form-label"><?= esc_html__('Conta','first-financial-box') ?></label><select id="impAccount" class="form-select"><?php foreach ($accounts as $acc): ?><option value="<?= $acc['id'] ?>"><?= esc_html($acc['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-2"><label class="form-label"><?= esc_html__('Categoria','first-financial-box') ?></label><select id="impCategory" class="form-select"><option value=""><?= esc_html__('Selecione...','first-financial-box') ?></option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= esc_html($cat['name']) ?></option><?php endforeach; ?></select></div>
                        <input type="hidden" id="impChave"><input type="hidden" id="impEmitente"><input type="hidden" id="impProdutosJson" value="[]">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar','first-financial-box') ?></button>
            <button type="button" class="btn btn-primary" id="btnImportar" onclick="nfceImportar()" style="display:none"><i class="bi bi-download me-1"></i><?= esc_html__('Criar Despesa','first-financial-box') ?></button>
        </div>
    </div></div>
</div>

<script>
// Dados dos gráficos (dinâmicos, dependem do PHP) — inline é necessário
// ajax_url, nonce, categories e accounts estão em FFB_PAGE (via wp_localize_script)
var NFCE_TOP_NOMES = <?= $jsTopNomes ?>;
var NFCE_TOP_VALS  = <?= $jsTopValores ?>;
var NFCE_TOP_VEZES = <?= $jsTopVezes ?>;
var NFCE_TOP_CORES = <?= $jsTopCores ?>;
</script>
<?php
// nfce.js já foi enfileirado por Plugin::enqueueAssets() com FFB_PAGE via wp_localize_script.
// Não chamamos wp_enqueue_script aqui para evitar duplo carregamento.
?>
