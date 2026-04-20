<?php defined('ABSPATH') || exit;
$incomes  = array_filter($categories ?? [], fn($c) => $c['type'] === 'income');
$expenses = array_filter($categories ?? [], fn($c) => $c['type'] === 'expense');
?>
<div class="row g-4">
<?php foreach (['income' => [__('Receitas','first-financial-box'), $incomes, 'ffb-income'], 'expense' => [__('Despesas','first-financial-box'), $expenses, 'ffb-expense']] as $type => [$label, $cats, $cls]): ?>
<div class="col-lg-6">
    <div class="ffb-card">
        <div class="ffb-card-header">
            <h5><i class="bi bi-tags me-2 <?= $cls ?>"></i><?= esc_html($label) ?></h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="catNew('<?= $type ?>')">
                <i class="bi bi-plus-lg me-1"></i><?= esc_html__('Nova', 'first-financial-box') ?>
            </button>
        </div>
        <?php if (empty($cats)): ?>
            <div class="ffb-empty"><i class="bi bi-tags"></i><p><?= esc_html__('Sem categorias.', 'first-financial-box') ?></p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table ffb-table">
                <thead><tr><th><?= esc_html__('Nome', 'first-financial-box') ?></th><th class="text-center"><?= esc_html__('Transações', 'first-financial-box') ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($cats as $cat): ?>
                    <tr>
                        <td><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= esc_attr($cat['color']) ?>;margin-right:6px"></span><?= esc_html($cat['name']) ?></td>
                        <td class="text-center"><small class="text-muted"><?= (int)$cat['tx_count'] ?></small></td>
                        <td class="text-end">
                            <button class="btn ffb-btn-xs btn-outline-secondary me-1" onclick="catEdit(<?= (int)$cat['id'] ?>)"><i class="bi bi-pencil"></i></button>
                            <button class="btn ffb-btn-xs btn-outline-danger" onclick="catDelete(<?= (int)$cat['id'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="catModalTitle"><?= esc_html__('Nova Categoria', 'first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="catId"><input type="hidden" id="catType">
            <div class="row g-3">
                <div class="col-8"><label class="form-label"><?= esc_html__('Nome *', 'first-financial-box') ?></label><input type="text" id="catName" class="form-control" required></div>
                <div class="col-4"><label class="form-label"><?= esc_html__('Cor', 'first-financial-box') ?></label><input type="color" id="catColor" class="form-control form-control-color w-100" value="#6B7280"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar', 'first-financial-box') ?></button><button type="button" class="btn btn-primary" onclick="catSave()"><?= esc_html__('Salvar', 'first-financial-box') ?></button></div>
    </div></div>
</div>
<script>
function catNew(type) {
    document.getElementById('catId').value=''; document.getElementById('catType').value=type;
    document.getElementById('catName').value=''; document.getElementById('catColor').value='#6B7280';
    document.getElementById('catModalTitle').textContent=type==='income'?'<?= esc_js(__('Nova Categoria de Receita','first-financial-box')) ?>':'<?= esc_js(__('Nova Categoria de Despesa','first-financial-box')) ?>';
}
function catSave() {
    var id=document.getElementById('catId').value;
    ffbAjax(id?'ffb_cat_update':'ffb_cat_store',{id:id,name:document.getElementById('catName').value,type:document.getElementById('catType').value,color:document.getElementById('catColor').value},{onSuccess:function(){location.reload();}});
}
function catEdit(id) {
    ffbAjax('ffb_cat_fetch',{id:id},{method:'GET',onSuccess:function(d){
        document.getElementById('catId').value=d.id;document.getElementById('catType').value=d.type;
        document.getElementById('catName').value=d.name;document.getElementById('catColor').value=d.color;
        document.getElementById('catModalTitle').textContent='<?= esc_js(__('Editar Categoria','first-financial-box')) ?>';
        new bootstrap.Modal(document.getElementById('catModal')).show();
    }});
}
function catDelete(id){ffbDelete('ffb_cat_destroy',id,'<?= esc_js(__('Excluir esta categoria?','first-financial-box')) ?>');}
</script>
