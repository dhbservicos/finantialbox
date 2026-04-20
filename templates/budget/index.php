<?php defined('ABSPATH') || exit; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>">
        <input type="hidden" name="page" value="ffb-budget">
        <div class="d-flex gap-2 align-items-center">
            <input type="month" name="month" class="form-control" value="<?= esc_attr($month) ?>" style="width:160px">
            <button type="submit" class="btn btn-primary"><?= esc_html__('Ver','first-financial-box') ?></button>
        </div>
    </form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#budgetModal"><i class="bi bi-plus-lg me-1"></i><?= esc_html__('Novo Orçamento','first-financial-box') ?></button>
</div>

<?php
$totalBudget = (float)($summary['total_budget'] ?? 0);
$totalSpent  = (float)($summary['total_spent'] ?? 0);
$pctGeral    = $totalBudget > 0 ? min(100, $totalSpent / $totalBudget * 100) : 0;
?>
<div class="ffb-card mb-4">
    <div class="ffb-card-header"><h5><?= esc_html__('Resumo do Orçamento','first-financial-box') ?></h5></div>
    <div class="row g-4 text-center">
        <div class="col"><div class="fs-5 fw-700">R$ <?= number_format($totalBudget,2,',','.') ?></div><small class="text-muted"><?= esc_html__('Orçado','first-financial-box') ?></small></div>
        <div class="col"><div class="fs-5 fw-700 ffb-expense">R$ <?= number_format($totalSpent,2,',','.') ?></div><small class="text-muted"><?= esc_html__('Gasto','first-financial-box') ?></small></div>
        <div class="col"><div class="fs-5 fw-700 <?= ($totalBudget-$totalSpent)>=0?'ffb-income':'ffb-expense' ?>">R$ <?= number_format(abs($totalBudget-$totalSpent),2,',','.') ?></div><small class="text-muted"><?= esc_html__('Disponível','first-financial-box') ?></small></div>
        <div class="col"><div class="fs-5 fw-700"><?= number_format($pctGeral,1) ?>%</div><small class="text-muted"><?= esc_html__('Utilização','first-financial-box') ?></small></div>
    </div>
    <div class="progress mt-3" style="height:8px">
        <div class="progress-bar <?= $pctGeral>=100?'bg-danger':($pctGeral>=80?'bg-warning':'bg-success') ?>" style="width:<?= $pctGeral ?>%"></div>
    </div>
</div>

<div class="ffb-card">
    <div class="ffb-card-header"><h5><i class="bi bi-bullseye me-2"></i><?= esc_html__('Orçamento por Categoria','first-financial-box') ?></h5></div>
    <?php if (empty($budgets)): ?>
        <div class="ffb-empty"><i class="bi bi-bullseye"></i><p><?= esc_html__('Nenhum orçamento definido.','first-financial-box') ?></p></div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($budgets as $b):
            $spent = (float)$b['spent'];
            $plan  = (float)$b['amount'];
            $pct   = $plan > 0 ? min(100, $spent / $plan * 100) : 0;
            $cls   = $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success');
        ?>
        <div class="col-md-6">
            <div class="p-3 border rounded" style="border-color:#E2E8F0!important">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= esc_attr($b['category_color']) ?>;margin-right:6px"></span>
                        <strong style="font-size:13px"><?= esc_html($b['category_name']) ?></strong>
                    </div>
                    <button class="btn ffb-btn-xs btn-outline-danger" onclick="budgetDelete(<?= (int)$b['id'] ?>)"><i class="bi bi-trash"></i></button>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <small class="ffb-expense">R$ <?= number_format($spent,2,',','.') ?> <?= esc_html__('gasto','first-financial-box') ?></small>
                    <small class="text-muted">R$ <?= number_format($plan,2,',','.') ?> <?= esc_html__('orçado','first-financial-box') ?></small>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <small class="text-muted"><?= number_format($pct,1) ?>% <?= esc_html__('utilizado','first-financial-box') ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= esc_html__('Novo Orçamento','first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label"><?= esc_html__('Categoria *','first-financial-box') ?></label><select id="budgetCat" class="form-select"><option value=""><?= esc_html__('Selecione...','first-financial-box') ?></option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= esc_html($cat['name']) ?></option><?php endforeach; ?></select></div>
            <div class="mb-3"><label class="form-label"><?= esc_html__('Mês','first-financial-box') ?></label><input type="month" id="budgetMonth" class="form-control" value="<?= esc_attr($month) ?>"></div>
            <div class="mb-3"><label class="form-label"><?= esc_html__('Valor Orçado *','first-financial-box') ?></label><div class="input-group"><span class="input-group-text">R$</span><input type="number" id="budgetAmount" class="form-control" step="0.01" required></div></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar','first-financial-box') ?></button><button type="button" class="btn btn-primary" onclick="budgetSave()"><?= esc_html__('Salvar','first-financial-box') ?></button></div>
    </div></div>
</div>
<script>
function budgetSave(){ffbAjax('ffb_budget_store',{category_id:document.getElementById('budgetCat').value,month:document.getElementById('budgetMonth').value,amount:document.getElementById('budgetAmount').value},{onSuccess:function(){location.reload();}});}
function budgetDelete(id){ffbDelete('ffb_budget_destroy',id,'<?= esc_js(__('Remover este orçamento?','first-financial-box')) ?>');}
</script>
