<?php defined('ABSPATH') || exit; ?>
<div class="ffb-card">
    <div class="ffb-card-header"><h5><i class="bi bi-gear me-2"></i><?= esc_html__('Configurações','first-financial-box') ?></h5></div>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label"><?= esc_html__('Moeda','first-financial-box') ?></label><select id="sCurrency" class="form-select"><option value="BRL" <?= $settings['currency']==='BRL'?'selected':'' ?>>BRL — Real Brasileiro</option><option value="USD" <?= $settings['currency']==='USD'?'selected':'' ?>>USD — Dollar</option><option value="EUR" <?= $settings['currency']==='EUR'?'selected':'' ?>>EUR — Euro</option></select></div>
        <div class="col-md-6"><label class="form-label"><?= esc_html__('Formato de Data','first-financial-box') ?></label><select id="sDateFormat" class="form-select"><option value="d/m/Y" <?= $settings['date_format']==='d/m/Y'?'selected':'' ?>>DD/MM/YYYY</option><option value="Y-m-d" <?= $settings['date_format']==='Y-m-d'?'selected':'' ?>>YYYY-MM-DD</option></select></div>
        <div class="col-12"><label class="d-flex align-items-center gap-2"><input type="checkbox" id="sNfce" <?= $settings['nfce_enabled']==='1'?'checked':'' ?>><?= esc_html__('Habilitar módulo NFC-e','first-financial-box') ?></label></div>
        <div class="col-12"><hr><h6><?= esc_html__('Permissões por Perfil','first-financial-box') ?></h6><div class="row g-2"><?php foreach ($roles as $rk => $rn): ?><div class="col-md-4"><label class="d-flex align-items-center gap-2"><input type="checkbox" name="roles" value="<?= esc_attr($rk) ?>" <?= in_array($rk,(array)$settings['allow_roles'])?'checked':'' ?>><?= esc_html($rn) ?></label></div><?php endforeach; ?></div></div>
        <div class="col-12 pt-2"><button class="btn btn-primary" onclick="settingsSave()"><i class="bi bi-check-lg me-1"></i><?= esc_html__('Salvar','first-financial-box') ?></button></div>
    </div>
</div>
<script>
function settingsSave(){var roles=Array.from(document.querySelectorAll('[name="roles"]:checked')).map(function(el){return el.value;});ffbAjax('ffb_settings_save',{currency:document.getElementById('sCurrency').value,date_format:document.getElementById('sDateFormat').value,nfce_enabled:document.getElementById('sNfce').checked?'1':'0','allow_roles[]':roles},{onSuccess:function(){ffbAlert('<?= esc_js(__('Configurações salvas!','first-financial-box')) ?>','success');}});}
</script>
