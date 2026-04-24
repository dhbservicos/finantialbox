<?php
/**
 * Plugin Name:       First Financial Box
 * Plugin URI:        https://github.com/firstfinancialbox
 * Description:       Sistema completo de gestão financeira pessoal e empresarial integrado ao WordPress. Dashboard, transações, contas, categorias, orçamento, contas a pagar, importação OFX/CSV, NFC-e modelo 65 (SEFAZ-SP) e relatórios DRE.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.3
 * Author:            First Financial Box
 * Author URI:        https://firstfinancialbox.com.br
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       first-financial-box
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// =============================================
// CONSTANTES DO PLUGIN
// =============================================
define('FFB_VERSION',    '1.1.0');
define('FFB_FILE',       __FILE__);
define('FFB_PATH',       plugin_dir_path(__FILE__));
define('FFB_URL',        plugin_dir_url(__FILE__));
define('FFB_SLUG',       'first-financial-box');
define('FFB_PREFIX',     'ffb_');       // prefixo das tabelas: {wp_prefix}ffb_
define('FFB_CAP_VIEW',   'ffb_view');   // capability: acessar o plugin
define('FFB_CAP_MANAGE', 'ffb_manage'); // capability: configurar o plugin
define('FFB_MIN_PHP',    '8.3.0');

// Garante PHP 8.3+ — aviso no admin se versão insuficiente
if (version_compare(PHP_VERSION, FFB_MIN_PHP, '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p><strong>First Financial Box</strong> requer PHP %s ou superior. Versão atual: %s.</p></div>',
            esc_html(FFB_MIN_PHP),
            esc_html(PHP_VERSION)
        );
    });
    return;
}

// =============================================
// CARREGAMENTO EXPLÍCITO DE DEPENDÊNCIAS
// Ordem importa: Helpers antes dos Controllers
// =============================================

// Helpers — carrega Validator.php e Paginator.php (via Helpers.php que os inclui)
require_once FFB_PATH . 'includes/Helpers/Validator.php';
require_once FFB_PATH . 'includes/Helpers/Paginator.php';

// Services — classes sem namespace FFB (usadas diretamente pelo nome simples)
require_once FFB_PATH . 'includes/Services/BankImportService.php';
require_once FFB_PATH . 'includes/Services/NfceService.php';

// =============================================
// AUTOLOADER PSR-4 PARA NAMESPACE FFB\
// Mapeia FFB\Foo\Bar → includes/Foo/Bar.php
// Carrega Controllers, Installer, Plugin, Cron, etc.
// =============================================
spl_autoload_register(function (string $class): void {
    // Só processa classes do namespace FFB\
    if (!str_starts_with($class, 'FFB\\')) return;

    // FFB\Plugin         → includes/Plugin.php
    // FFB\Installer      → includes/Installer.php
    // FFB\Controllers\X  → includes/Controllers/X.php
    // FFB\Helpers\X      → includes/Helpers/X.php   (Validator, Paginator)
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen('FFB\\')));
    $file     = FFB_PATH . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// =============================================
// INICIALIZAÇÃO
// =============================================
add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('first-financial-box', false, FFB_PATH . 'languages');
    \FFB\Plugin::getInstance()->init();
});

// Hooks de ciclo de vida
register_activation_hook(__FILE__,   [\FFB\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\FFB\Installer::class, 'deactivate']);
