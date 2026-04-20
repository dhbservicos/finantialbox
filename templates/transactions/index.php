<?php
/**
 * Template: transactions/index.php
 * Variáveis: $transactions, $categories, $accounts, $filters,
 *            $summary, $paginator, $total, $ffb_nonce, $ffb_ajax_url
 */
defined('ABSPATH') || exit;
$p = admin_url('admin.php?page=ffb-transactions');
?>

<!-- KPIs -->
<div class="ffb-kpi-row">
    <div class="ffb-kpi ffb-kpi-income">
        <div class="ffb-kpi-icon"><i class="bi bi-arrow-down-circle"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Receitas', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value">R$ <?= number_format(abs($summary['total_income'] ?? 0), 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="ffb-kpi ffb-kpi-expense">
        <div class="ffb-kpi-icon"><i class="bi bi-arrow-up-circle"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Despesas', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value">R$ <?= number_format(abs($summary['total_expense'] ?? 0), 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="ffb-kpi <?= ($summary['balance'] ?? 0) >= 0 ? 'ffb-kpi-positive' : 'ffb-kpi-negative' ?>">
        <div class="ffb-kpi-icon"><i class="bi bi-scale"></i></div>
        <div class="ffb-kpi-body">
            <div class="ffb-kpi-label"><?= esc_html__('Resultado', 'first-financial-box') ?></div>
            <div class="ffb-kpi-value">R$ <?= number_format(abs($summary['balance'] ?? 0), 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ffb-card mb-4">
    <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>">
        <input type="hidden" name="page" value="ffb-transactions">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label"><?= esc_html__('Mês', 'first-financial-box') ?></label>
                <input type="month" name="month" class="form-control" value="<?= esc_attr($filters['month']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= esc_html__('Tipo', 'first-financial-box') ?></label>
                <select name="type" class="form-select">
                    <option value=""><?= esc_html__('Todos', 'first-financial-box') ?></option>
                    <option value="income"  <?= $filters['type'] === 'income'  ? 'selected' : '' ?>><?= esc_html__('Receita', 'first-financial-box') ?></option>
                    <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>><?= esc_html__('Despesa', 'first-financial-box') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= esc_html__('Status', 'first-financial-box') ?></label>
                <select name="status" class="form-select">
                    <option value=""><?= esc_html__('Todos', 'first-financial-box') ?></option>
                    <option value="paid"    <?= $filters['status'] === 'paid'    ? 'selected' : '' ?>><?= esc_html__('Pago', 'first-financial-box') ?></option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>><?= esc_html__('Pendente', 'first-financial-box') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= esc_html__('Categoria', 'first-financial-box') ?></label>
                <select name="category_id" class="form-select">
                    <option value=""><?= esc_html__('Todas', 'first-financial-box') ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int)$filters['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= esc_html($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= esc_html__('Busca', 'first-financial-box') ?></label>
                <input type="text" name="search" class="form-control" placeholder="<?= esc_attr__('Descrição...', 'first-financial-box') ?>" value="<?= esc_attr($filters['search']) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= esc_url(admin_url('admin.php?page=ffb-transactions')) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>
</div>

<!-- Tabela -->
<div class="ffb-card">
    <div class="ffb-card-header">
        <h5><i class="bi bi-list-ul me-2"></i><?= esc_html__('Lançamentos', 'first-financial-box') ?> (<?= (int)$total ?>)</h5>
        <div class="d-flex gap-2">
            <a href="<?= esc_url(admin_url('admin-ajax.php?action=ffb_report_export&month=' . $filters['month'] . '&_wpnonce=' . $ffb_nonce)) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i><?= esc_html__('CSV', 'first-financial-box') ?>
            </a>
            <a href="<?= esc_url(admin_url('admin.php?page=ffb-bank-import')) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-file-earmark-arrow-up me-1"></i><?= esc_html__('Importar OFX/CSV', 'first-financial-box') ?>
            </a>
            <button class="btn btn-sm ffb-btn-income" data-bs-toggle="modal" data-bs-target="#txModal" onclick="setTxType('income')">
                <i class="bi bi-plus-lg me-1"></i><?= esc_html__('Receita', 'first-financial-box') ?>
            </button>
            <button class="btn btn-sm ffb-btn-expense" data-bs-toggle="modal" data-bs-target="#txModal" onclick="setTxType('expense')">
                <i class="bi bi-plus-lg me-1"></i><?= esc_html__('Despesa', 'first-financial-box') ?>
            </button>
        </div>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="ffb-empty"><i class="bi bi-inbox"></i><p><?= esc_html__('Nenhuma transação encontrada.', 'first-financial-box') ?></p></div>
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><small><?= esc_html(date_i18n('d/m/Y', strtotime($tx['date']))) ?></small></td>
                    <td>
                        <?= esc_html($tx['description']) ?>
                        <?php if (!empty($tx['notes'])): ?>
                            <br><small class="text-muted"><?= esc_html(mb_strimwidth($tx['notes'], 0, 60, '…')) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($tx['nfce_chave'])): ?>
                            <br><a href="https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p=<?= esc_attr($tx['nfce_chave']) ?>|3|1"
                               target="_blank" rel="noopener"
                               class="badge bg-info text-dark text-decoration-none"
                               style="font-size:10px">
                                <i class="bi bi-qr-code me-1"></i><?= esc_html(substr($tx['nfce_chave'], 0, 8)) ?>…<?= esc_html(substr($tx['nfce_chave'], -4)) ?>
                            </a>
                        <?php endif; ?>
                    </td>
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
                        <?= ($tx['type'] === 'income' ? '+' : '-') . ' R$ ' . number_format($tx['amount'], 2, ',', '.') ?>
                    </td>
                    <td class="text-end">
                        <button class="btn ffb-btn-xs btn-outline-secondary me-1" onclick="txEdit(<?= (int)$tx['id'] ?>)" title="<?= esc_attr__('Editar', 'first-financial-box') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn ffb-btn-xs btn-outline-danger" onclick="txDelete(<?= (int)$tx['id'] ?>)" title="<?= esc_attr__('Excluir', 'first-financial-box') ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginator->totalPages > 1): ?>
    <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
        <small class="text-muted"><?= sprintf(esc_html__('Exibindo %d–%d de %d', 'first-financial-box'), $paginator->from(), $paginator->to(), $paginator->total) ?></small>
        <?= $paginator->render(admin_url('admin.php?page=ffb-transactions&' . http_build_query(array_filter($filters)))) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal Nova/Editar Transação -->
<div class="modal fade" id="txModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="txModalTitle"><?= esc_html__('Nova Transação', 'first-financial-box') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="txId" value="">
                <input type="hidden" id="txType" value="expense">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><?= esc_html__('Descrição *', 'first-financial-box') ?></label>
                        <input type="text" id="txDesc" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= esc_html__('Valor *', 'first-financial-box') ?></label>
                        <div class="input-group"><span class="input-group-text">R$</span><input type="number" id="txAmount" class="form-control" step="0.01" min="0.01" required></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= esc_html__('Data *', 'first-financial-box') ?></label>
                        <input type="date" id="txDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?= esc_html__('Status', 'first-financial-box') ?></label>
                        <select id="txStatus" class="form-select">
                            <option value="paid"><?= esc_html__('Pago', 'first-financial-box') ?></option>
                            <option value="pending"><?= esc_html__('Pendente', 'first-financial-box') ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= esc_html__('Conta *', 'first-financial-box') ?></label>
                        <select id="txAccount" class="form-select">
                            <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= esc_html($acc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= esc_html__('Categoria *', 'first-financial-box') ?></label>
                        <select id="txCategory" class="form-select">
                            <option value=""><?= esc_html__('Selecione...', 'first-financial-box') ?></option>
                            <?php
                            $incomes  = array_filter($categories, fn($c) => $c['type'] === 'income');
                            $expenses = array_filter($categories, fn($c) => $c['type'] === 'expense');
                            ?>
                            <optgroup label="<?= esc_attr__('Receitas', 'first-financial-box') ?>">
                                <?php foreach ($incomes as $cat): ?>
                                <option value="<?= $cat['id'] ?>" data-type="income"><?= esc_html($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="<?= esc_attr__('Despesas', 'first-financial-box') ?>">
                                <?php foreach ($expenses as $cat): ?>
                                <option value="<?= $cat['id'] ?>" data-type="expense"><?= esc_html($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= esc_html__('Observações', 'first-financial-box') ?></label>
                        <input type="text" id="txNotes" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= esc_html__('Cancelar', 'first-financial-box') ?></button>
                <button type="button" class="btn btn-primary" id="txSaveBtn" onclick="txSave()">
                    <i class="bi bi-check-lg me-1"></i><?= esc_html__('Salvar', 'first-financial-box') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function setTxType(type) {
    document.getElementById('txType').value = type;
    document.getElementById('txModalTitle').textContent = type === 'income' ? '<?= esc_js(__('Nova Receita', 'first-financial-box')) ?>' : '<?= esc_js(__('Nova Despesa', 'first-financial-box')) ?>';
    document.getElementById('txId').value = '';
    document.getElementById('txDesc').value = '';
    document.getElementById('txAmount').value = '';
    document.getElementById('txDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('txStatus').value = 'paid';
    document.getElementById('txNotes').value = '';
}

function txSave() {
    var id   = document.getElementById('txId').value;
    var data = {
        id:          id,
        type:        document.getElementById('txType').value,
        description: document.getElementById('txDesc').value,
        amount:      document.getElementById('txAmount').value,
        date:        document.getElementById('txDate').value,
        status:      document.getElementById('txStatus').value,
        account_id:  document.getElementById('txAccount').value,
        category_id: document.getElementById('txCategory').value,
        notes:       document.getElementById('txNotes').value,
    };
    var action = id ? 'ffb_tx_update' : 'ffb_tx_store';
    var btn = document.getElementById('txSaveBtn');
    btn.disabled = true;
    btn.textContent = '<?= esc_js(__('Salvando...', 'first-financial-box')) ?>';
    ffbAjax(action, data, {
        onSuccess: function() { location.reload(); },
        onError: function(msg) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i><?= esc_js(__('Salvar', 'first-financial-box')) ?>';
            ffbAlert(msg, 'danger');
        }
    });
}

function txEdit(id) {
    ffbAjax('ffb_tx_fetch', { id: id }, {
        method: 'GET',
        onSuccess: function(tx) {
            document.getElementById('txId').value          = tx.id;
            document.getElementById('txType').value        = tx.type;
            document.getElementById('txDesc').value        = tx.description;
            document.getElementById('txAmount').value      = tx.amount;
            document.getElementById('txDate').value        = tx.date;
            document.getElementById('txStatus').value      = tx.status;
            document.getElementById('txAccount').value     = tx.account_id;
            document.getElementById('txCategory').value    = tx.category_id;
            document.getElementById('txNotes').value       = tx.notes || '';
            document.getElementById('txModalTitle').textContent = '<?= esc_js(__('Editar Transação', 'first-financial-box')) ?>';
            new bootstrap.Modal(document.getElementById('txModal')).show();
        }
    });
}

function txDelete(id) {
    ffbDelete('ffb_tx_destroy', id, '<?= esc_js(__('Excluir esta transação?', 'first-financial-box')) ?>');
}
</script>
