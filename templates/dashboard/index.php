<?php
/**
 * Template: dashboard/index.php
 * Variáveis: $totalBalance, $summary, $accounts, $recentTx,
 *            $expByCategory, $cashflow, $upcoming, $ffb_nonce
 */
defined('ABSPATH') || exit;

function ffb_money(?float $v): string {
    return 'R$ ' . number_format(abs($v ?? 0), 2, ',', '.');
}
function ffb_month_label(string $ym): string {
    [$y, $m] = explode('-', $ym);
    $labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return ($labels[(int)$m - 1] ?? $m) . '/' . substr($y, 2);
}
?>

<!-- KPIs -->
<div class="ffb-kpi-row">
    <div class="ffb-kpi ffb-kpi-balance">
        <div class="ffb-kpi-icon"><i class="bi bi-wallet2"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Saldo Total', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value"><?= ffb_money($totalBalance) ?></div>
        </div>
    </div>
    <div class="ffb-kpi ffb-kpi-income">
        <div class="ffb-kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Receitas do Mês', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value"><?= ffb_money($summary['total_income']) ?></div>
        </div>
    </div>
    <div class="ffb-kpi ffb-kpi-expense">
        <div class="ffb-kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Despesas do Mês', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value"><?= ffb_money($summary['total_expense']) ?></div>
        </div>
    </div>
    <div class="ffb-kpi <?= ($summary['balance'] ?? 0) >= 0 ? 'ffb-kpi-positive' : 'ffb-kpi-negative' ?>">
        <div class="ffb-kpi-icon"><i class="bi bi-scale"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Resultado do Mês', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value"><?= ffb_money($summary['balance']) ?></div>
        </div>
    </div>
</div>

<!-- Contas Bancárias -->
<div class="ffb-card mb-4">
    <div class="ffb-card-header">
        <h5><i class="bi bi-bank me-2"></i><?= esc_html__('Contas Bancárias', 'first-financial-box') ?></h5>
        <a href="<?= esc_url(admin_url('admin.php?page=ffb-accounts')) ?>" class="btn btn-sm btn-outline-primary">
            <?= esc_html__('Ver todas', 'first-financial-box') ?>
        </a>
    </div>
    <div class="ffb-accounts-row">
        <?php foreach ($accounts as $acc): ?>
        <div class="ffb-account-card" style="--acc-color:<?= esc_attr($acc['color']) ?>">
            <div class="ffb-acc-bar"></div>
            <div class="ffb-acc-body">
                <div class="ffb-acc-name"><?= esc_html($acc['name']) ?></div>
                <div class="ffb-acc-bank"><?= esc_html($acc['bank'] ?: __('Dinheiro', 'first-financial-box')) ?></div>
                <div class="ffb-acc-balance"><?= ffb_money($acc['balance']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="ffb-card h-100">
            <div class="ffb-card-header">
                <h5><i class="bi bi-bar-chart-line me-2"></i><?= esc_html__('Fluxo de Caixa', 'first-financial-box') ?></h5>
            </div>
            <div style="position:relative;height:240px"><canvas id="ffbCashflowChart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ffb-card h-100">
            <div class="ffb-card-header">
                <h5><i class="bi bi-pie-chart me-2"></i><?= esc_html__('Despesas por Categoria', 'first-financial-box') ?></h5>
            </div>
            <?php if (empty($expByCategory)): ?>
                <div class="ffb-empty"><i class="bi bi-inbox"></i><p><?= esc_html__('Sem despesas este mês', 'first-financial-box') ?></p></div>
            <?php else: ?>
            <div style="position:relative;height:200px"><canvas id="ffbCategoryChart"></canvas></div>
            <div class="ffb-legend mt-2">
                <?php foreach ($expByCategory as $cat): ?>
                <div class="ffb-legend-item">
                    <span class="ffb-legend-dot" style="background:<?= esc_attr($cat['color']) ?>"></span>
                    <span class="ffb-legend-label"><?= esc_html($cat['name']) ?></span>
                    <span class="ffb-legend-value"><?= ffb_money($cat['total']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alertas de Vencimento -->
<?php if (!empty($upcoming)): ?>
<div class="ffb-card mb-4">
    <div class="ffb-card-header">
        <h5><i class="bi bi-bell me-2"></i><?= esc_html__('Vencimentos Próximos', 'first-financial-box') ?></h5>
        <a href="<?= esc_url(admin_url('admin.php?page=ffb-bills')) ?>" class="btn btn-sm btn-outline-warning">
            <?= esc_html__('Ver todos', 'first-financial-box') ?>
        </a>
    </div>
    <div class="ffb-alerts-list">
        <?php foreach ($upcoming as $bill):
            $daysLeft = (int)((strtotime($bill['due_date']) - strtotime('today')) / 86400);
            $urgency  = $daysLeft <= 2 ? 'danger' : ($daysLeft <= 5 ? 'warning' : 'info');
        ?>
        <div class="ffb-alert-item ffb-alert-<?= $urgency ?>">
            <div class="ffb-alert-info">
                <div class="ffb-alert-desc"><?= esc_html($bill['description']) ?></div>
                <div class="ffb-alert-meta"><?= esc_html($bill['account_name']) ?></div>
            </div>
            <div class="ffb-alert-right">
                <div class="ffb-alert-amount"><?= ffb_money($bill['amount']) ?></div>
                <span class="badge bg-<?= $urgency ?>">
                    <?= $daysLeft === 0 ? esc_html__('Hoje', 'first-financial-box')
                      : ($daysLeft === 1 ? esc_html__('Amanhã', 'first-financial-box')
                      : sprintf(esc_html__('Em %d dias', 'first-financial-box'), $daysLeft)) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Transações Recentes -->
<div class="ffb-card">
    <div class="ffb-card-header">
        <h5><i class="bi bi-clock-history me-2"></i><?= esc_html__('Transações Recentes', 'first-financial-box') ?></h5>
        <a href="<?= esc_url(admin_url('admin.php?page=ffb-transactions')) ?>" class="btn btn-sm btn-outline-primary">
            <?= esc_html__('Ver todas', 'first-financial-box') ?>
        </a>
    </div>
    <?php if (empty($recentTx)): ?>
        <div class="ffb-empty"><i class="bi bi-inbox"></i><p><?= esc_html__('Nenhuma transação encontrada', 'first-financial-box') ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover ffb-table">
            <thead>
                <tr>
                    <th><?= esc_html__('Data', 'first-financial-box') ?></th>
                    <th><?= esc_html__('Descrição', 'first-financial-box') ?></th>
                    <th><?= esc_html__('Categoria', 'first-financial-box') ?></th>
                    <th><?= esc_html__('Conta', 'first-financial-box') ?></th>
                    <th><?= esc_html__('Status', 'first-financial-box') ?></th>
                    <th class="text-end"><?= esc_html__('Valor', 'first-financial-box') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                    <td><small><?= esc_html(date_i18n('d/m/Y', strtotime($tx['date']))) ?></small></td>
                    <td><?= esc_html($tx['description']) ?></td>
                    <td>
                        <span class="ffb-cat-badge" style="background:<?= esc_attr($tx['category_color']) ?>20;color:<?= esc_attr($tx['category_color']) ?>">
                            <?= esc_html($tx['category_name']) ?>
                        </span>
                    </td>
                    <td><small><?= esc_html($tx['account_name']) ?></small></td>
                    <td>
                        <span class="badge <?= $tx['status'] === 'paid' ? 'ffb-badge-paid' : 'ffb-badge-pending' ?>">
                            <?= $tx['status'] === 'paid' ? esc_html__('Pago', 'first-financial-box') : esc_html__('Pendente', 'first-financial-box') ?>
                        </span>
                    </td>
                    <td class="text-end fw-600 <?= $tx['type'] === 'income' ? 'ffb-income' : 'ffb-expense' ?>">
                        <?= ($tx['type'] === 'income' ? '+' : '-') . ffb_money($tx['amount']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// Dados para os gráficos — injetados como JSON sem heredoc
$cfLabels  = wp_json_encode(array_map(fn($r) => ffb_month_label($r['month']), $cashflow));
$cfIncome  = wp_json_encode(array_column($cashflow, 'income'));
$cfExpense = wp_json_encode(array_column($cashflow, 'expense'));
$catLabels = wp_json_encode(array_column($expByCategory ?? [], 'name'));
$catValues = wp_json_encode(array_column($expByCategory ?? [], 'total'));
$catColors = wp_json_encode(array_column($expByCategory ?? [], 'color'));
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cfCtx = document.getElementById('ffbCashflowChart');
    if (cfCtx) {
        new Chart(cfCtx, {
            type: 'bar',
            data: {
                labels: <?= $cfLabels ?>,
                datasets: [
                    { label: 'Receitas', data: <?= $cfIncome ?>,  backgroundColor: '#10B98133', borderColor: '#10B981', borderWidth: 2, borderRadius: 6 },
                    { label: 'Despesas', data: <?= $cfExpense ?>, backgroundColor: '#EF444433', borderColor: '#EF4444', borderWidth: 2, borderRadius: 6 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return 'R$ ' + v.toLocaleString('pt-BR'); } } } }
            }
        });
    }
    var catCtx = document.getElementById('ffbCategoryChart');
    if (catCtx && <?= $catLabels ?>.length) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: { labels: <?= $catLabels ?>, datasets: [{ data: <?= $catValues ?>, backgroundColor: <?= $catColors ?>, borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
});
</script>
