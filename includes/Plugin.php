<?php
namespace FFB;

defined('ABSPATH') || exit;

/**
 * Classe principal do plugin.
 * Registra hooks, menus, assets e roteamento de requisições AJAX e de página.
 */
class Plugin {

    private static ?Plugin $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        // Permissões nas roles padrão WP
        add_action('init', [$this, 'setupCapabilities']);

        // Menu no admin WP
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Shortcode para usar em páginas do site (modo frontend)
        add_shortcode('first_financial_box', [$this, 'shortcode']);

        // AJAX handlers (wp_ajax_ = logado, wp_ajax_nopriv_ = não logado)
        $this->registerAjaxHandlers();

        // Cron diário (vencimentos, recorrências)
        add_action('ffb_daily_cron', [Cron::class, 'run']);
    }

    public function setupCapabilities(): void {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(FFB_CAP_VIEW);
            $admin->add_cap(FFB_CAP_MANAGE);
        }
    }

    // =============================================
    // MENU ADMIN WORDPRESS
    // =============================================
    public function registerAdminMenu(): void {
        // Menu principal
        add_menu_page(
            __('First Financial Box', 'first-financial-box'),
            __('Financial Box', 'first-financial-box'),
            FFB_CAP_VIEW,
            'ffb-dashboard',
            [$this, 'renderAdminPage'],
            'dashicons-chart-line',
            30
        );

        // Submenus
        $submenus = [
            ['ffb-dashboard',    __('Dashboard', 'first-financial-box'),              'ffb-dashboard'],
            ['ffb-transactions', __('Transações Bancárias', 'first-financial-box'),   'ffb-transactions'],
            ['ffb-bank-import',  __('Importar OFX / CSV', 'first-financial-box'),     'ffb-bank-import'],
            ['ffb-bills',        __('Contas a Pagar/Receber', 'first-financial-box'), 'ffb-bills'],
            ['ffb-nfce',         __('NFC-e Cupons Fiscais', 'first-financial-box'),   'ffb-nfce'],
            ['ffb-budget',       __('Orçamento Mensal', 'first-financial-box'),       'ffb-budget'],
            ['ffb-accounts',     __('Contas Bancárias', 'first-financial-box'),       'ffb-accounts'],
            ['ffb-categories',   __('Categorias', 'first-financial-box'),             'ffb-categories'],
            ['ffb-reports',      __('Relatórios', 'first-financial-box'),             'ffb-reports'],
            ['ffb-settings',     __('Configurações', 'first-financial-box'),          'ffb-settings'],
        ];

        foreach ($submenus as [$slug, $label, $pageSlug]) {
            add_submenu_page(
                'ffb-dashboard',
                $label . ' — First Financial Box',
                $label,
                FFB_CAP_VIEW,
                $pageSlug,
                [$this, 'renderAdminPage']
            );
        }
    }

    // =============================================
    // ROTEADOR DE PÁGINAS
    // =============================================
    public function renderAdminPage(): void {
        if (!current_user_can(FFB_CAP_VIEW)) {
            wp_die(__('Sem permissão.', 'first-financial-box'));
        }

        $page = sanitize_key($_GET['page'] ?? 'ffb-dashboard');
        $map  = [
            'ffb-dashboard'    => [Controllers\DashboardController::class, 'index'],
            'ffb-transactions' => [Controllers\TransactionController::class, 'index'],
            'ffb-bank-import'  => [Controllers\BankImportController::class, 'index'],
            'ffb-bills'        => [Controllers\BillController::class, 'index'],
            'ffb-nfce'         => [Controllers\NfceController::class, 'index'],
            'ffb-budget'       => [Controllers\BudgetController::class, 'index'],
            'ffb-accounts'     => [Controllers\AccountController::class, 'index'],
            'ffb-categories'   => [Controllers\CategoryController::class, 'index'],
            'ffb-reports'      => [Controllers\ReportController::class, 'index'],
            'ffb-settings'     => [Controllers\SettingsController::class, 'index'],
        ];

        if (isset($map[$page])) {
            [$class, $method] = $map[$page];
            (new $class())->$method();
        } else {
            wp_die(__('Página não encontrada.', 'first-financial-box'));
        }
    }

    // =============================================
    // AJAX HANDLERS
    // =============================================
    private function registerAjaxHandlers(): void {
        $actions = [
            // Transactions
            'ffb_tx_store'           => [Controllers\TransactionController::class, 'store'],
            'ffb_tx_update'          => [Controllers\TransactionController::class, 'update'],
            'ffb_tx_destroy'         => [Controllers\TransactionController::class, 'destroy'],
            'ffb_tx_fetch'           => [Controllers\TransactionController::class, 'fetch'],
            // Accounts
            'ffb_acc_store'          => [Controllers\AccountController::class, 'store'],
            'ffb_acc_update'         => [Controllers\AccountController::class, 'update'],
            'ffb_acc_destroy'        => [Controllers\AccountController::class, 'destroy'],
            // Categories
            'ffb_cat_store'          => [Controllers\CategoryController::class, 'store'],
            'ffb_cat_update'         => [Controllers\CategoryController::class, 'update'],
            'ffb_cat_destroy'        => [Controllers\CategoryController::class, 'destroy'],
            // Bills
            'ffb_bill_store'         => [Controllers\BillController::class, 'store'],
            'ffb_bill_mark_paid'     => [Controllers\BillController::class, 'markPaid'],
            'ffb_bill_destroy'       => [Controllers\BillController::class, 'destroy'],
            // Budget
            'ffb_budget_store'       => [Controllers\BudgetController::class, 'store'],
            'ffb_budget_destroy'     => [Controllers\BudgetController::class, 'destroy'],
            // NFC-e
            'ffb_nfce_consultar'     => [Controllers\NfceController::class, 'consultar'],
            'ffb_nfce_importar'      => [Controllers\NfceController::class, 'importar'],
            'ffb_nfce_chart_anual'   => [Controllers\NfceController::class, 'chartAnual'],
            'ffb_nfce_download'      => [Controllers\NfceController::class, 'downloadJson'],
            // Bank import
            'ffb_bank_preview'       => [Controllers\BankImportController::class, 'preview'],
            'ffb_bank_upload'        => [Controllers\BankImportController::class, 'upload'],
            // Reports
            'ffb_report_cashflow'    => [Controllers\ReportController::class, 'cashflow'],
            'ffb_report_dre'         => [Controllers\ReportController::class, 'dre'],
            'ffb_report_export'      => [Controllers\ReportController::class, 'export'],
            // Settings
            'ffb_settings_save'      => [Controllers\SettingsController::class, 'save'],
        ];

        foreach ($actions as $action => [$class, $method]) {
            add_action('wp_ajax_' . $action, function () use ($class, $method) {
                (new $class())->$method();
            });
        }
    }

    // =============================================
    // ASSETS
    // =============================================
    public function enqueueAssets(string $hook): void {
        // O hook tem formato: toplevel_page_ffb-dashboard
        // ou {parent}_page_ffb-bank-import — ambos contêm "ffb-"
        if (!str_contains($hook, 'ffb-')) return;

        // Bootstrap 5
        wp_enqueue_style('bootstrap5',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
        wp_enqueue_style('bootstrap-icons',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', [], '1.11.3');

        // CSS principal
        wp_enqueue_style('ffb-main', FFB_URL . 'assets/css/ffb.css', ['bootstrap5'], FFB_VERSION);

        // Chart.js + Bootstrap JS
        wp_enqueue_script('chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        wp_enqueue_script('bootstrap5-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true);

        // JS principal — carregado no footer (true) para que jQuery esteja disponível
        wp_enqueue_script('ffb-main',
            FFB_URL . 'assets/js/ffb.js',
            ['jquery', 'bootstrap5-js', 'chartjs'],
            FFB_VERSION, true);

        // Dados globais — disponíveis para TODOS os scripts FFB antes de qualquer execução
        wp_localize_script('ffb-main', 'FFB', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ffb_nonce'),
            'user_id'  => get_current_user_id(),
            'currency' => get_option('ffb_currency', 'BRL'),
            'strings'  => [
                'confirm_delete' => __('Confirmar exclusão?', 'first-financial-box'),
                'saving'         => __('Salvando...', 'first-financial-box'),
                'saved'          => __('Salvo!', 'first-financial-box'),
                'error'          => __('Erro:', 'first-financial-box'),
            ],
        ]);

        // Dados específicos por página — também via wp_localize_script
        // IMPORTANTE: wp_localize_script deve ser chamado ANTES de wp_enqueue_script
        // do script que usa os dados, e ambos no mesmo hook admin_enqueue_scripts.
        // Aqui lemos $_GET['page'] para identificar a página atual.
        $page = sanitize_key($_GET['page'] ?? '');
        $uid  = get_current_user_id();

        $jsMap = [
            'ffb-bills'       => 'bills.js',
            'ffb-nfce'        => 'nfce.js',
            'ffb-bank-import' => 'bank_import.js',
        ];

        if (isset($jsMap[$page])) {
            global $wpdb;
            $p = $wpdb->prefix . FFB_PREFIX;

            $handle = 'ffb-page-' . sanitize_key($page);

            wp_enqueue_script(
                $handle,
                FFB_URL . 'assets/js/' . $jsMap[$page],
                ['ffb-main'],
                FFB_VERSION,
                true   // footer → após o conteúdo da página
            );

            // Dados específicos da página: passados ANTES da execução do script
            if ($page === 'ffb-bank-import' || $page === 'ffb-nfce') {
                $categories = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, type FROM {$p}categories
                     WHERE user_id=%d AND active=1 ORDER BY type, name", $uid
                ), ARRAY_A);

                $accounts = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name, bank FROM {$p}accounts
                     WHERE user_id=%d AND active=1 ORDER BY name", $uid
                ), ARRAY_A);

                wp_localize_script($handle, 'FFB_PAGE', [
                    'ajax_url'   => admin_url('admin-ajax.php'),
                    'nonce'      => wp_create_nonce('ffb_nonce'),
                    'categories' => array_map(fn($c) => [
                        'id'   => (int)$c['id'],
                        'name' => $c['name'],
                        'type' => $c['type'],
                    ], $categories),
                    'accounts' => array_map(fn($a) => [
                        'id'   => (int)$a['id'],
                        'name' => $a['name'] . ($a['bank'] ? ' — ' . $a['bank'] : ''),
                    ], $accounts),
                ]);
            }

            if ($page === 'ffb-bills') {
                wp_localize_script($handle, 'FFB_PAGE', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('ffb_nonce'),
                ]);
            }
        }
    }

    // =============================================
    // SHORTCODE — uso em páginas/posts
    // [first_financial_box page="dashboard"]
    // =============================================
    public function shortcode(array $atts): string {
        if (!is_user_logged_in()) {
            return '<p>' . __('Faça login para acessar o Financial Box.', 'first-financial-box') . '</p>';
        }

        $atts = shortcode_atts(['page' => 'dashboard'], $atts, 'first_financial_box');
        $page = 'ffb-' . sanitize_key($atts['page']);

        ob_start();
        $this->renderAdminPage();
        return ob_get_clean();
    }
}
