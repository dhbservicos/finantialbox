<?php
defined('ABSPATH') || exit;
$cfLabels  = wp_json_encode(array_map(function($r){ $p=explode('-',$r['month']); $l=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; return ($l[(int)$p[1]-1]??$p[1]).'/'.substr($p[0],2); }, $cashflow ?? []));
$cfIncome  = wp_json_encode(array_column($cashflow ?? [], 'income'));
$cfExpense = wp_json_encode(array_column($cashflow ?? [], 'expense'));
$catLabels = wp_json_encode(array_column($byCategory ?? [], 'name'));
$catValues = wp_json_encode(array_column($byCategory ?? [], 'total'));
$catColors = wp_json_encode(array_column($byCategory ?? [], 'color'));
?>

<!-- KPIs -->
<div class="ffb-kpi-row mb-4">
    <div class="ffb-kpi ffb-kpi-income"><div class="ffb-kpi-icon"><i class="bi bi-arrow-down-circle"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Receitas','first-financial-box') ?></div><div class="ffb-kpi-value">R$ <?= number_format(abs($summary['total_income']??0),2,',','.') ?></div></div></div>
    <div class="ffb-kpi ffb-kpi-expense"><div class="ffb-kpi-icon"><i class="bi bi-arrow-up-circle"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Despesas','first-financial-box') ?></div><div class="ffb-kpi-value">R$ <?= number_format(abs($summary['total_expense']??0),2,',','.') ?></div></div></div>
    <div class="ffb-kpi <?= ($summary['balance']??0)>=0?'ffb-kpi-positive':'ffb-kpi-negative' ?>"><div class="ffb-kpi-icon"><i class="bi bi-scale"></i></div><div class="ffb-kpi-body"><div class="ffb-kpi-label"><?= esc_html__('Resultado','first-financial-box') ?></div><div class="ffb-kpi-value">R$ <?= number_format(abs($summary['balance']??0),2,',','.') ?></div></div></div>
</div>

<div class="d-flex gap-3 mb-4 flex-wrap align-items-center">
    <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="page" value="ffb-reports">
        <label><?= esc_html__('Mês:','first-financial-box') ?></label><input type="month" name="month" class="form-control" value="<?= esc_attr($month) ?>" style="width:150px">
        <button class="btn btn-primary btn-sm"><?= esc_html__('Ver','first-financial-box') ?></button>
    </form>
    <a href="<?= esc_url(admin_url('admin-ajax.php?action=ffb_report_export&month=' . $month . '&_wpnonce=' . $ffb_nonce)) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i><?= esc_html__('Exportar CSV','first-financial-box') ?></a>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="ffb-card h-100">
            <div class="ffb-card-header"><h5><i class="bi bi-bar-chart-line me-2"></i><?= esc_html__('Fluxo de Caixa (12 meses)','first-financial-box') ?></h5></div>
            <div style="position:relative;height:280px"><canvas id="reportCashflow"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ffb-card h-100">
            <div class="ffb-card-header"><h5><i class="bi bi-pie-chart me-2"></i><?= esc_html__('Despesas por Categoria','first-financial-box') ?></h5></div>
            <?php if (empty($byCategory)): ?>
                <div class="ffb-empty"><i class="bi bi-inbox"></i><p><?= esc_html__('Sem dados','first-financial-box') ?></p></div>
            <?php else: ?>
            <div style="position:relative;height:200px"><canvas id="reportCategory"></canvas></div>
            <div class="mt-3">
                <?php foreach ($byCategory as $cat): ?>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-2"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= esc_attr($cat['color']) ?>"></span><small><?= esc_html($cat['name']) ?></small></div>
                    <small class="fw-600">R$ <?= number_format($cat['total'],2,',','.') ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DRE -->
<?php if (!empty($dre)): ?>
<div class="ffb-card">
    <div class="ffb-card-header"><h5><i class="bi bi-table me-2"></i><?= esc_html__('DRE — Demonstrativo de Resultado','first-financial-box') ?> (<?= esc_html($year) ?>)</h5></div>
    <div class="table-responsive">
        <table class="table table-sm ffb-table">
            <thead><tr><th><?= esc_html__('Categoria','first-financial-box') ?></th><?php for ($m=1;$m<=12;$m++): ?><th class="text-end small"><?= ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$m-1] ?></th><?php endfor; ?></tr></thead>
            <tbody>
                <?php
                $allCats = [];
                foreach ($dre as $mes => $types) foreach ($types as $type => $cats) foreach ($cats as $name => $val) $allCats[$type][$name] = true;
                foreach (['income' => ['Receitas','ffb-income'], 'expense' => ['Despesas','ffb-expense']] as $type => [$label, $cls]):
                    if (empty($allCats[$type])) continue;
                    echo '<tr class="table-light"><td colspan="13"><strong>' . esc_html($label) . '</strong></td></tr>';
                    foreach (array_keys($allCats[$type] ?? []) as $catName):
                        echo '<tr><td class="ps-4">' . esc_html($catName) . '</td>';
                        for ($m = 1; $m <= 12; $m++):
                            $mesKey = sprintf('%02d', $m);
                            $val = $dre[$mesKey][$type][$catName] ?? 0;
                            echo '<td class="text-end ' . $cls . '">' . ($val > 0 ? 'R$ ' . number_format($val, 2, ',', '.') : '<span class="text-muted">—</span>') . '</td>';
                        endfor;
                        echo '</tr>';
                    endforeach;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var cfCtx = document.getElementById('reportCashflow');
    if (cfCtx) new Chart(cfCtx, {
        type: 'bar',
        data: { labels: <?= $cfLabels ?>, datasets: [
            { label: '<?= esc_js(__('Receitas','first-financial-box')) ?>', data: <?= $cfIncome ?>,  backgroundColor: '#10B98133', borderColor: '#10B981', borderWidth: 2, borderRadius: 4 },
            { label: '<?= esc_js(__('Despesas','first-financial-box')) ?>', data: <?= $cfExpense ?>, backgroundColor: '#EF444433', borderColor: '#EF4444', borderWidth: 2, borderRadius: 4 }
        ]},
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true,ticks:{callback:function(v){return 'R$ '+v.toLocaleString('pt-BR');}}}} }
    });
    var catCtx = document.getElementById('reportCategory');
    if (catCtx && <?= $catLabels ?>.length) new Chart(catCtx, {
        type: 'doughnut',
        data: { labels: <?= $catLabels ?>, datasets: [{ data: <?= $catValues ?>, backgroundColor: <?= $catColors ?>, borderWidth: 2 }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });
});
</script>
