<?php defined('ABSPATH') || exit; ?>

<div class="ffb-card">
    <div class="ffb-card-header">
        <h5><i class="bi bi-bank me-2"></i><?= esc_html__('Contas Bancárias', 'first-financial-box') ?></h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#accModal" onclick="accNew()">
            <i class="bi bi-plus-lg me-1"></i><?= esc_html__('Nova Conta', 'first-financial-box') ?>
        </button>
    </div>
    <?php if (empty($accounts)): ?>
        <div class="ffb-empty"><i class="bi bi-bank"></i><p><?= esc_html__('Nenhuma conta cadastrada.', 'first-financial-box') ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table ffb-table">
            <thead><tr><th><?= esc_html__('Nome', 'first-financial-box') ?></th><th><?= esc_html__('Banco', 'first-financial-box') ?></th><th><?= esc_html__('Tipo', 'first-financial-box') ?></th><th class="text-end"><?= esc_html__('Saldo', 'first-financial-box') ?></th><th></th></tr></thead>
            <tbody>
                <?php
                $typeLabels = ['checking'=>'Conta Corrente','savings'=>'Poupança','cash'=>'Dinheiro','investment'=>'Investimento','credit'=>'Crédito'];
                foreach ($accounts as $acc):
                ?>
                <tr>
                    <td>
                        <span class="ffb-dot" style="background:<?= esc_attr($acc['color']) ?>"></span>
                        <strong><?= esc_html($acc['name']) ?></strong>
                    </td>
                    <td><?= esc_html($acc['bank'] ?: '—') ?></td>
                    <td><small class="text-muted"><?= esc_html($typeLabels[$acc['type']] ?? $acc['type']) ?></small></td>
                    <td class="text-end fw-600 <?= (float)$acc['balance'] >= 0 ? 'ffb-income' : 'ffb-expense' ?>">R$ <?= number_format(abs($acc['balance']), 2, ',', '.') ?></td>
                    <td class="text-end">
                        <button class="btn ffb-btn-xs btn-outline-secondary me-1" onclick="accEdit(<?= (int)$acc['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        <button class="btn ffb-btn-xs btn-outline-danger" onclick="accDelete(<?= (int)$acc['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="accModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="accModalTitle"><?= esc_html__('Nova Conta', 'first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="accId">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label"><?= esc_html__('Nome *', 'first-financial-box') ?></label><input type="text" id="accName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label"><?= esc_html__('Banco', 'first-financial-box') ?></label><input type="text" id="accBank" class="form-control" placeholder="Nubank, Itaú..."></div>
                    <div class="col-md-6"><label class="form-label"><?= esc_html__('Tipo', 'first-financial-box') ?></label>
                        <select id="accType" class="form-select">
                            <option value="checking"><?= esc_html__('Conta Corrente', 'first-financial-box') ?></option>
                            <option value="savings"><?= esc_html__('Poupança', 'first-financial-box') ?></option>
                            <option value="cash"><?= esc_html__('Dinheiro', 'first-financial-box') ?></option>
                            <option value="investment"><?= esc_html__('Investimento', 'first-financial-box') ?></option>
                            <option value="credit"><?= esc_html__('Cartão de Crédito', 'first-financial-box') ?></option>
                        </select>
                    </div>
                    <div class="col-md-8"><label class="form-label"><?= esc_html__('Saldo Inicial', 'first-financial-box') ?></label><div class="input-group"><span class="input-group-text">R$</span><input type="number" id="accBalance" class="form-control" step="0.01" value="0"></div></div>
                    <div class="col-md-4"><label class="form-label"><?= esc_html__('Cor', 'first-financial-box') ?></label><input type="color" id="accColor" class="form-control form-control-color w-100" value="#3B82F6"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar', 'first-financial-box') ?></button><button type="button" class="btn btn-primary" onclick="accSave()"><?= esc_html__('Salvar', 'first-financial-box') ?></button></div>
        </div>
    </div>
</div>
<style>.ffb-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px}</style>
<script>
function accNew() {
    document.getElementById('accId').value=''; document.getElementById('accName').value='';
    document.getElementById('accBank').value=''; document.getElementById('accType').value='checking';
    document.getElementById('accBalance').value='0'; document.getElementById('accColor').value='#3B82F6';
    document.getElementById('accModalTitle').textContent='<?= esc_js(__('Nova Conta', 'first-financial-box')) ?>';
}
function accSave() {
    var id = document.getElementById('accId').value;
    var data = { id:id, name:document.getElementById('accName').value, bank:document.getElementById('accBank').value,
                 type:document.getElementById('accType').value, balance:document.getElementById('accBalance').value,
                 color:document.getElementById('accColor').value };
    ffbAjax(id ? 'ffb_acc_update' : 'ffb_acc_store', data, { onSuccess: function() { location.reload(); } });
}
function accEdit(id) {
    ffbAjax('ffb_acc_fetch', {id:id}, { method:'GET', onSuccess: function(d) {
        document.getElementById('accId').value=d.id; document.getElementById('accName').value=d.name;
        document.getElementById('accBank').value=d.bank||''; document.getElementById('accType').value=d.type;
        document.getElementById('accBalance').value=d.balance; document.getElementById('accColor').value=d.color;
        document.getElementById('accModalTitle').textContent='<?= esc_js(__('Editar Conta', 'first-financial-box')) ?>';
        new bootstrap.Modal(document.getElementById('accModal')).show();
    }});
}
function accDelete(id) { ffbDelete('ffb_acc_destroy', id, '<?= esc_js(__('Desativar esta conta?', 'first-financial-box')) ?>'); }
</script>
