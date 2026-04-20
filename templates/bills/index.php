<?php defined('ABSPATH') || exit; ?>
<div class="ffb-card mb-4">
    <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>">
        <input type="hidden" name="page" value="ffb-bills">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label"><?= esc_html__('Mês de Vencimento', 'first-financial-box') ?></label><input type="month" name="month" class="form-control" value="<?= esc_attr($filters['month']) ?>"></div>
            <div class="col-md-2"><label class="form-label"><?= esc_html__('Tipo', 'first-financial-box') ?></label><select name="type" class="form-select"><option value=""><?= esc_html__('Todos','first-financial-box') ?></option><option value="payable" <?= $filters['type']==='payable'?'selected':'' ?>><?= esc_html__('A Pagar','first-financial-box') ?></option><option value="receivable" <?= $filters['type']==='receivable'?'selected':'' ?>><?= esc_html__('A Receber','first-financial-box') ?></option></select></div>
            <div class="col-md-2"><label class="form-label"><?= esc_html__('Status', 'first-financial-box') ?></label><select name="status" class="form-select"><option value=""><?= esc_html__('Todos','first-financial-box') ?></option><option value="pending" <?= $filters['status']==='pending'?'selected':'' ?>><?= esc_html__('Pendente','first-financial-box') ?></option><option value="paid" <?= $filters['status']==='paid'?'selected':'' ?>><?= esc_html__('Pago','first-financial-box') ?></option><option value="overdue" <?= $filters['status']==='overdue'?'selected':'' ?>><?= esc_html__('Vencido','first-financial-box') ?></option></select></div>
            <div class="col-md-3 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i><?= esc_html__('Filtrar','first-financial-box') ?></button><a href="<?= esc_url(admin_url('admin.php?page=ffb-bills')) ?>" class="btn btn-outline-secondary"><?= esc_html__('Limpar','first-financial-box') ?></a></div>
        </div>
    </form>
</div>

<div class="ffb-card">
    <div class="ffb-card-header">
        <h5><i class="bi bi-calendar-check me-2"></i><?= esc_html__('Contas', 'first-financial-box') ?> (<?= count($bills ?? []) ?>)</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#boletoModal"><?= esc_html__('Ler Código de Barras','first-financial-box') ?></button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#billModal" onclick="billNew()"><i class="bi bi-plus-lg me-1"></i><?= esc_html__('Nova Conta','first-financial-box') ?></button>
        </div>
    </div>
    <?php if (empty($bills)): ?>
        <div class="ffb-empty"><i class="bi bi-calendar-x"></i><p><?= esc_html__('Nenhuma conta encontrada.', 'first-financial-box') ?></p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover ffb-table">
            <thead><tr><th><?= esc_html__('Vencimento','first-financial-box') ?></th><th><?= esc_html__('Descrição','first-financial-box') ?></th><th><?= esc_html__('Categoria','first-financial-box') ?></th><th><?= esc_html__('Tipo','first-financial-box') ?></th><th><?= esc_html__('Status','first-financial-box') ?></th><th class="text-end"><?= esc_html__('Valor','first-financial-box') ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($bills as $bill): ?>
                <tr class="<?= $bill['status']==='overdue'?'table-danger':'' ?>">
                    <td><small><?= esc_html(date_i18n('d/m/Y', strtotime($bill['due_date']))) ?></small></td>
                    <td><?= esc_html($bill['description']) ?></td>
                    <td><span class="ffb-cat-badge" style="background:<?= esc_attr($bill['category_color']) ?>20;color:<?= esc_attr($bill['category_color']) ?>"><?= esc_html($bill['category_name']) ?></span></td>
                    <td><span class="badge <?= $bill['type']==='payable'?'bg-danger':'bg-success' ?>"><?= $bill['type']==='payable'?esc_html__('Pagar','first-financial-box'):esc_html__('Receber','first-financial-box') ?></span></td>
                    <td><span class="badge <?= match($bill['status']){'paid'=>'ffb-badge-paid','overdue'=>'bg-danger',default=>'ffb-badge-pending'} ?>"><?= match($bill['status']){'paid'=>esc_html__('Pago','first-financial-box'),'overdue'=>esc_html__('Vencido','first-financial-box'),default=>esc_html__('Pendente','first-financial-box')} ?></span></td>
                    <td class="text-end fw-600 <?= $bill['type']==='receivable'?'ffb-income':'ffb-expense' ?>">R$ <?= number_format($bill['amount'],2,',','.') ?></td>
                    <td class="text-end">
                        <?php if ($bill['status']!=='paid'): ?><button class="btn ffb-btn-xs btn-success me-1" onclick="billPaid(<?= (int)$bill['id'] ?>)"><i class="bi bi-check-lg"></i></button><?php endif; ?>
                        <button class="btn ffb-btn-xs btn-outline-danger" onclick="billDelete(<?= (int)$bill['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Conta -->
<div class="modal fade" id="billModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= esc_html__('Nova Conta','first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12"><label class="form-label"><?= esc_html__('Descrição *','first-financial-box') ?></label><input type="text" id="billDesc" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Tipo','first-financial-box') ?></label><select id="billType" class="form-select"><option value="payable"><?= esc_html__('A Pagar','first-financial-box') ?></option><option value="receivable"><?= esc_html__('A Receber','first-financial-box') ?></option></select></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Valor *','first-financial-box') ?></label><div class="input-group"><span class="input-group-text">R$</span><input type="number" id="billAmount" class="form-control" step="0.01" required></div></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Vencimento *','first-financial-box') ?></label><input type="date" id="billDue" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Recorrência','first-financial-box') ?></label><select id="billRecurrence" class="form-select"><option value="none"><?= esc_html__('Não recorrente','first-financial-box') ?></option><option value="monthly"><?= esc_html__('Mensal','first-financial-box') ?></option><option value="quarterly"><?= esc_html__('Trimestral','first-financial-box') ?></option><option value="yearly"><?= esc_html__('Anual','first-financial-box') ?></option></select></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Categoria *','first-financial-box') ?></label><select id="billCategory" class="form-select"><option value=""><?= esc_html__('Selecione...','first-financial-box') ?></option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= esc_html($cat['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label"><?= esc_html__('Conta *','first-financial-box') ?></label><select id="billAccount" class="form-select"><?php foreach ($accounts as $acc): ?><option value="<?= $acc['id'] ?>"><?= esc_html($acc['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label"><?= esc_html__('Observações','first-financial-box') ?></label><input type="text" id="billNotes" class="form-control"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar','first-financial-box') ?></button><button type="button" class="btn btn-primary" onclick="billSave()"><?= esc_html__('Salvar','first-financial-box') ?></button></div>
    </div></div>
</div>

<!-- Modal Boleto -->
<div class="modal fade" id="boletoModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-upc me-2"></i><?= esc_html__('Ler Código de Barras','first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="text-muted small"><?= esc_html__('Cole o código de barras ou linha digitável. O sistema extrai valor e vencimento automaticamente.','first-financial-box') ?></p>
            <textarea id="boletoInput" class="form-control font-monospace mb-2" rows="3" placeholder="34191.09008 14523.000097 01522.800011 1 97440000015000"></textarea>
            <div id="boletoErro" class="alert alert-danger py-2 small" style="display:none"></div>
            <div id="boletoResultado" style="display:none">
                <div class="alert alert-success py-2 mb-3"><i class="bi bi-check-circle me-1"></i><strong><?= esc_html__('Dados extraídos!','first-financial-box') ?></strong></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label"><?= esc_html__('Valor','first-financial-box') ?></label><div class="input-group"><span class="input-group-text">R$</span><input type="number" id="boletoValor" class="form-control" readonly></div></div>
                    <div class="col-md-6"><label class="form-label"><?= esc_html__('Vencimento','first-financial-box') ?></label><input type="date" id="boletoVencimento" class="form-control" readonly></div>
                    <div class="col-12"><label class="form-label"><?= esc_html__('Banco/Emissor','first-financial-box') ?></label><input type="text" id="boletoBanco" class="form-control" readonly></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar','first-financial-box') ?></button>
            <button type="button" class="btn btn-outline-primary" onclick="analisarBoleto()"><?= esc_html__('Analisar','first-financial-box') ?></button>
            <button type="button" class="btn btn-primary" id="btnUsarDados" onclick="usarDadosBoleto()" style="display:none"><?= esc_html__('Preencher Conta','first-financial-box') ?></button>
        </div>
    </div></div>
</div>

<script>
function billNew(){document.getElementById('billDesc').value='';document.getElementById('billAmount').value='';document.getElementById('billDue').value='<?= date('Y-m-d') ?>';document.getElementById('billNotes').value='';}
function billSave(){ffbAjax('ffb_bill_store',{description:document.getElementById('billDesc').value,type:document.getElementById('billType').value,amount:document.getElementById('billAmount').value,due_date:document.getElementById('billDue').value,recurrence:document.getElementById('billRecurrence').value,category_id:document.getElementById('billCategory').value,account_id:document.getElementById('billAccount').value,notes:document.getElementById('billNotes').value},{onSuccess:function(){location.reload();}});}
function billPaid(id){if(!confirm('<?= esc_js(__('Marcar como pago?','first-financial-box')) ?>'))return;ffbAjax('ffb_bill_mark_paid',{id:id},{onSuccess:function(){location.reload();}});}
function billDelete(id){ffbDelete('ffb_bill_destroy',id,'<?= esc_js(__('Excluir esta conta?','first-financial-box')) ?>');}
</script>
