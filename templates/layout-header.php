<?php
/**
 * Layout Header — First Financial Box
 * Sem menu lateral próprio — usa o menu nativo do WordPress.
 * O conteúdo é renderizado diretamente na área de conteúdo do WP admin.
 */
defined('ABSPATH') || exit;

// Flash message via transient
$uid   = get_current_user_id();
$flash = get_transient("ffb_flash_{$uid}");
if ($flash) delete_transient("ffb_flash_{$uid}");
?>
<div class="wrap ffb-wrap" id="ffb-app">

    <!-- Título da página (padrão WP) -->
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-line" style="font-size:26px;line-height:1;color:#6366F1;vertical-align:middle;margin-right:6px"></span>
        <?= esc_html($page_title ?? 'First Financial Box') ?>
    </h1>
    <hr class="wp-header-end">

    <?php if ($flash): ?>
    <div class="notice notice-<?= $flash['type'] === 'success' ? 'success' : 'error' ?> is-dismissible" style="margin:12px 0">
        <p><?= esc_html($flash['message']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Container de conteúdo -->
    <div class="ffb-content">
