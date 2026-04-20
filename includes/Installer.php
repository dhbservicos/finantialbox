<?php
namespace FFB;

defined('ABSPATH') || exit;

/**
 * Gerencia ativação, desativação e atualização do plugin.
 * Cria/atualiza as tabelas no banco de dados WordPress.
 */
class Installer {

    public static function activate(): void {
        self::createTables();
        self::insertDefaults();
        self::scheduleCron();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('ffb_daily_cron');
    }

    // =============================================
    // CRIAÇÃO DE TABELAS
    // Usa dbDelta() do WordPress — seguro para upgrades
    // Prefixo: {wp_prefix}ffb_ (ex: wp_ffb_accounts)
    // user_id = ID do usuário WordPress (sem tabela users própria)
    // =============================================
    public static function createTables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix . FFB_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Contas bancárias
        dbDelta("CREATE TABLE {$p}accounts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            name        VARCHAR(100)  NOT NULL,
            bank        VARCHAR(100)  NULL,
            type        ENUM('checking','savings','cash','investment','credit') DEFAULT 'checking',
            balance     DECIMAL(15,2) DEFAULT 0.00,
            color       VARCHAR(7)    DEFAULT '#3B82F6',
            active      TINYINT(1)    DEFAULT 1,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_acc_user (user_id)
        ) $charset;");

        // Plano de contas
        dbDelta("CREATE TABLE {$p}categories (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            name        VARCHAR(100)  NOT NULL,
            type        ENUM('income','expense') NOT NULL,
            color       VARCHAR(7)    DEFAULT '#6B7280',
            icon        VARCHAR(50)   DEFAULT 'tag',
            parent_id   INT UNSIGNED  NULL,
            active      TINYINT(1)    DEFAULT 1,
            created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cat_user (user_id, type, active)
        ) $charset;");

        // Transações
        dbDelta("CREATE TABLE {$p}transactions (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED NOT NULL,
            account_id      INT UNSIGNED NOT NULL,
            category_id     INT UNSIGNED NOT NULL,
            type            ENUM('income','expense','transfer') NOT NULL,
            description     VARCHAR(255) NOT NULL,
            amount          DECIMAL(15,2) NOT NULL,
            date            DATE NOT NULL,
            status          ENUM('paid','pending') DEFAULT 'paid',
            notes           TEXT NULL,
            nfce_chave      CHAR(44) NULL,
            deleted_at      TIMESTAMP NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tx_user_date   (user_id, date),
            INDEX idx_tx_user_status (user_id, status, date)
        ) $charset;");

        // Contas a pagar/receber
        dbDelta("CREATE TABLE {$p}bills (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            account_id  INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            type        ENUM('payable','receivable') NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount      DECIMAL(15,2) NOT NULL,
            due_date    DATE NOT NULL,
            paid_at     DATE NULL,
            status      ENUM('pending','paid','overdue','cancelled') DEFAULT 'pending',
            recurrence  ENUM('none','monthly','quarterly','yearly') DEFAULT 'none',
            notes       TEXT NULL,
            deleted_at  TIMESTAMP NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bill_user_due (user_id, due_date, status)
        ) $charset;");

        // Orçamento
        dbDelta("CREATE TABLE {$p}budgets (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            month       CHAR(7) NOT NULL COMMENT 'YYYY-MM',
            amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_budget (user_id, category_id, month)
        ) $charset;");

        // Log de auditoria
        dbDelta("CREATE TABLE {$p}audit_logs (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            action      VARCHAR(50)  NOT NULL,
            entity      VARCHAR(50)  NOT NULL,
            entity_id   INT UNSIGNED NULL,
            old_value   LONGTEXT     NULL,
            new_value   LONGTEXT     NULL,
            ip          VARCHAR(45)  NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_user (user_id, created_at)
        ) $charset;");

        // Tentativas de login (rate limiting)
        dbDelta("CREATE TABLE {$p}login_attempts (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip         VARCHAR(45) NOT NULL,
            user_login VARCHAR(150) NOT NULL,
            success    TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_ip (ip, created_at)
        ) $charset;");

        // Importações bancárias OFX/CSV
        dbDelta("CREATE TABLE {$p}bank_imports (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     BIGINT UNSIGNED NOT NULL,
            account_id  INT UNSIGNED NOT NULL,
            filename    VARCHAR(255) NOT NULL,
            format      ENUM('ofx','csv') NOT NULL,
            date_from   DATE NULL,
            date_to     DATE NULL,
            total_rows  INT UNSIGNED DEFAULT 0,
            imported    INT UNSIGNED DEFAULT 0,
            skipped     INT UNSIGNED DEFAULT 0,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bank_user (user_id)
        ) $charset;");

        // NFC-e importadas
        dbDelta("CREATE TABLE {$p}nfce_imports (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED NOT NULL,
            chave           CHAR(44) NOT NULL,
            emitente        VARCHAR(255) NULL,
            total           DECIMAL(15,2) DEFAULT 0.00,
            data_emissao    DATE NULL,
            transaction_id  INT UNSIGNED NULL,
            status          ENUM('ok','error','duplicate') DEFAULT 'ok',
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_nfce (user_id, chave),
            INDEX idx_nfce_user (user_id)
        ) $charset;");

        // Itens das NFC-e
        dbDelta("CREATE TABLE {$p}nfce_itens (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nfce_import_id  INT UNSIGNED NOT NULL,
            user_id         BIGINT UNSIGNED NOT NULL,
            chave           CHAR(44) NOT NULL,
            nome            VARCHAR(255) NOT NULL,
            quantidade      DECIMAL(10,3) NOT NULL DEFAULT 1.000,
            unidade         VARCHAR(10) NOT NULL DEFAULT 'UN',
            valor_unitario  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            valor_total     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_nfce_itens_chave (chave),
            INDEX idx_nfce_itens_user  (user_id)
        ) $charset;");

        // Versão do schema
        update_option('ffb_db_version', FFB_VERSION);
    }

    // =============================================
    // DADOS PADRÃO POR USUÁRIO
    // Chamado na ativação e quando um novo usuário ativa o sistema
    // =============================================
    public static function insertDefaults(int $userId = 0): void {
        global $wpdb;
        $p = $wpdb->prefix . FFB_PREFIX;

        if (!$userId) $userId = get_current_user_id();
        if (!$userId) return;

        // Verifica se usuário já tem categorias
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}categories WHERE user_id = %d", $userId
        ));
        if ($exists > 0) return;

        // Categorias padrão
        $categories = [
            ['Salário',           'income',  '#10B981', 'briefcase'],
            ['Freelance',         'income',  '#3B82F6', 'laptop'],
            ['Investimentos',     'income',  '#8B5CF6', 'graph-up'],
            ['Outros Rendimentos','income',  '#F59E0B', 'plus-circle'],
            ['Moradia',           'expense', '#EF4444', 'house'],
            ['Alimentação',       'expense', '#F97316', 'cart'],
            ['Transporte',        'expense', '#EAB308', 'car-front'],
            ['Saúde',             'expense', '#EC4899', 'heart-pulse'],
            ['Educação',          'expense', '#6366F1', 'book'],
            ['Lazer',             'expense', '#14B8A6', 'music-note'],
            ['Contas e Serviços', 'expense', '#64748B', 'lightning'],
            ['Outros',            'expense', '#9CA3AF', 'three-dots'],
        ];

        foreach ($categories as [$name, $type, $color, $icon]) {
            $wpdb->insert("{$p}categories", [
                'user_id' => $userId,
                'name'    => $name,
                'type'    => $type,
                'color'   => $color,
                'icon'    => $icon,
            ]);
        }

        // Conta padrão
        $wpdb->insert("{$p}accounts", [
            'user_id' => $userId,
            'name'    => 'Conta Corrente',
            'bank'    => '',
            'type'    => 'checking',
            'balance' => 0.00,
            'color'   => '#3B82F6',
        ]);
    }

    private static function scheduleCron(): void {
        if (!wp_next_scheduled('ffb_daily_cron')) {
            wp_schedule_event(strtotime('06:00:00'), 'daily', 'ffb_daily_cron');
        }
    }
}
