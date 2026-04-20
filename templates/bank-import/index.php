<?php
defined('ABSPATH') || exit;
// Variáveis de dados (categories, accounts, nonce, ajax_url) agora são injetadas
// via wp_localize_script em Plugin::enqueueAssets() como FFB_PAGE.
// O banco_import.js as lê diretamente de FFB_PAGE, sem depender de vars inline.
?>

<!-- STEP 1: Upload -->
<div id="stepUpload">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="ffb-card">
                <div class="ffb-card-header"><h5><i class="bi bi-file-earmark-arrow-up me-2"></i><?= esc_html__('Enviar Extrato','first-financial-box') ?></h5></div>
                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-stars me-1"></i>
                    <?= esc_html__('Detecção automática: Mercado Pago, Nubank, Banco Inter, OFX e CSV genérico. Você revisará cada transação antes de importar.','first-financial-box') ?>
                </div>
                <div id="uploadErro" class="alert alert-danger py-2 small" style="display:none"></div>
                <div class="mb-3">
                    <label class="form-label"><?= esc_html__('Arquivo OFX ou CSV *','first-financial-box') ?></label>
                    <input type="file" id="fileInput" class="form-control" accept=".ofx,.csv,.txt">
                    <div class="form-text"><?= esc_html__('Máx. 5 MB — .ofx, .csv, .txt','first-financial-box') ?></div>
                </div>
                <div class="mb-4">
                    <label class="form-label"><?= esc_html__('Conta Bancária *','first-financial-box') ?></label>
                    <select id="accountSelect" class="form-select">
                        <option value=""><?= esc_html__('Selecione a conta...','first-financial-box') ?></option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= esc_html($acc['name']) ?><?= $acc['bank'] ? ' — '.esc_html($acc['bank']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary w-100" id="btnAnalisar" onclick="biAnalisar()">
                    <i class="bi bi-search me-2"></i><?= esc_html__('Analisar Arquivo','first-financial-box') ?>
                </button>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="ffb-card">
                <div class="ffb-card-header"><h5><i class="bi bi-clock-history me-2"></i><?= esc_html__('Importações Recentes','first-financial-box') ?></h5></div>
                <?php if (empty($imports)): ?>
                    <div class="ffb-empty"><i class="bi bi-file-earmark-x"></i><p><?= esc_html__('Nenhum extrato importado ainda.','first-financial-box') ?></p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table ffb-table">
                        <thead><tr>
                            <th><?= esc_html__('Data','first-financial-box') ?></th>
                            <th><?= esc_html__('Arquivo','first-financial-box') ?></th>
                            <th><?= esc_html__('Conta','first-financial-box') ?></th>
                            <th>Formato</th>
                            <th class="text-center ffb-income"><?= esc_html__('Import.','first-financial-box') ?></th>
                            <th class="text-center text-muted"><?= esc_html__('Ignoradas','first-financial-box') ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($imports as $imp): ?>
                            <tr>
                                <td><small><?= esc_html(date_i18n('d/m/Y H:i',strtotime($imp['created_at']))) ?></small></td>
                                <td><small class="font-monospace" title="<?= esc_attr($imp['filename']) ?>"><?= esc_html(mb_strimwidth($imp['filename'],0,22,'…')) ?></small></td>
                                <td><small><?= esc_html($imp['account_name']??'—') ?></small></td>
                                <td><span class="badge bg-secondary"><?= strtoupper(esc_html($imp['format'])) ?></span></td>
                                <td class="text-center ffb-income fw-600"><small><?= (int)$imp['imported'] ?></small></td>
                                <td class="text-center text-muted"><small><?= (int)$imp['skipped'] ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- STEP 2: Revisão por linha -->
<div id="stepRevisao" style="display:none">

    <!-- Header da revisão -->
    <div class="ffb-card mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-0"><i class="bi bi-table me-2"></i><?= esc_html__('Revisar Transações','first-financial-box') ?></h5>
                <small class="text-muted"><?= esc_html__('Arquivo:','first-financial-box') ?> <strong id="revFilename"></strong> | <span id="revTotal"></span> <?= esc_html__('transações encontradas','first-financial-box') ?></small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-outline-secondary" onclick="biVoltar()">
                    <i class="bi bi-arrow-left me-1"></i><?= esc_html__('Voltar','first-financial-box') ?>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="biAplicarTodas()">
                    <i class="bi bi-tags me-1"></i><?= esc_html__('Categoria a todas','first-financial-box') ?>
                </button>
                <button class="btn btn-sm btn-primary" id="btnImportar" onclick="biConfirmar()">
                    <i class="bi bi-download me-2"></i><?= esc_html__('Importar','first-financial-box') ?> <span id="btnImportarCount"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Painel de filtro por palavra-chave -->
    <div class="ffb-card mb-3" style="background:#F8FAFC">
        <div class="ffb-card-header" style="margin-bottom:10px">
            <h5 style="font-size:13px"><i class="bi bi-funnel me-2 text-primary"></i><?= esc_html__('Aplicar regra por palavra-chave','first-financial-box') ?></h5>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small"><?= esc_html__('Palavra-chave na descrição','first-financial-box') ?></label>
                <input type="text" id="kwFiltro" class="form-control form-control-sm" placeholder="<?= esc_attr__('Ex: Pix Enviado, Mercado...','first-financial-box') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small"><?= esc_html__('Forçar tipo','first-financial-box') ?></label>
                <select id="kwTipo" class="form-select form-select-sm">
                    <option value=""><?= esc_html__('— manter detectado —','first-financial-box') ?></option>
                    <option value="income"><?= esc_html__('Receita','first-financial-box') ?></option>
                    <option value="expense"><?= esc_html__('Despesa','first-financial-box') ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small"><?= esc_html__('Aplicar categoria','first-financial-box') ?></label>
                <select id="kwCategoria" class="form-select form-select-sm">
                    <option value=""><?= esc_html__('— sem alteração de categoria —','first-financial-box') ?></option>
                    <?php
                    $incCats = array_filter($categories, fn($c) => $c['type'] === 'income');
                    $expCats = array_filter($categories, fn($c) => $c['type'] === 'expense');
                    ?>
                    <optgroup label="<?= esc_attr__('Receitas','first-financial-box') ?>">
                        <?php foreach ($incCats as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= esc_html($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="<?= esc_attr__('Despesas','first-financial-box') ?>">
                        <?php foreach ($expCats as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= esc_html($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" onclick="biAplicarFiltroKW()">
                    <i class="bi bi-check2-all me-1"></i><?= esc_html__('Aplicar','first-financial-box') ?>
                </button>
            </div>
        </div>
        <div id="kwMsg" class="small text-success mt-1 fw-600" style="display:none"></div>
        <div class="mt-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                <?= esc_html__('Filtra as linhas abaixo pela palavra-chave e aplica o tipo/categoria escolhidos em todas que coincidirem. O badge de tipo em cada linha também pode ser clicado para inverter individualmente.','first-financial-box') ?>
            </small>
        </div>
    </div>

    <div id="importErro" class="alert alert-danger py-2 small mb-3" style="display:none"></div>
    <div id="importSucesso" class="alert alert-success py-2 small mb-3" style="display:none"></div>

    <!-- Tabela de revisão -->
    <div class="ffb-card p-0">
        <div class="table-responsive">
            <table class="table ffb-table mb-0" id="tabelaRevisao">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="checkAll" checked onchange="biToggleAll(this.checked)" title="Selecionar todas"></th>
                        <th style="width:95px"><?= esc_html__('Data','first-financial-box') ?></th>
                        <th><?= esc_html__('Descrição','first-financial-box') ?></th>
                        <th style="width:90px" class="text-center"><?= esc_html__('Tipo','first-financial-box') ?><br><small class="text-muted" style="font-size:9px;font-weight:400"><?= esc_html__('(clique p/ inverter)','first-financial-box') ?></small></th>
                        <th style="width:110px" class="text-end"><?= esc_html__('Valor','first-financial-box') ?></th>
                        <th style="width:200px"><?= esc_html__('Categoria','first-financial-box') ?></th>
                    </tr>
                </thead>
                <tbody id="corpoRevisao"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: categoria para todas -->
<div class="modal fade" id="modalCategoriaTodas" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= esc_html__('Aplicar a selecionadas','first-financial-box') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small text-muted"><?= esc_html__('Categoria para aplicar em todas as linhas marcadas:','first-financial-box') ?></p>
            <select id="categoriaGlobal" class="form-select"><option value=""><?= esc_html__('— manter sugestão —','first-financial-box') ?></option></select>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= esc_html__('Cancelar','first-financial-box') ?></button>
            <button type="button" class="btn btn-primary btn-sm" onclick="biAplicarGlobal()"><?= esc_html__('Aplicar','first-financial-box') ?></button>
        </div>
    </div></div>
</div>

<?php
// JS (bank_import.js) e dados (FFB_PAGE) já foram enfileirados
// por Plugin::enqueueAssets() via admin_enqueue_scripts.
// Nenhuma tag <script> adicional necessária aqui.
?>
