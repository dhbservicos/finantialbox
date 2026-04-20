<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

/**
 * Controller base para o plugin First Financial Box.
 * Substitui o Controller standalone pelo equivalente WordPress:
 *  - Autenticação via wp_get_current_user() / current_user_can()
 *  - CSRF via wp_verify_nonce() / wp_create_nonce()
 *  - Respostas JSON via wp_send_json_*()
 *  - Renderização de templates via include + extract()
 *  - Queries via $wpdb com prefixo correto
 */
abstract class Controller {

    protected \wpdb $db;
    protected string $tablePrefix;

    public function __construct() {
        global $wpdb;
        $this->db          = $wpdb;
        $this->tablePrefix = $wpdb->prefix . FFB_PREFIX;
    }

    // =============================================
    // AUTENTICAÇÃO
    // =============================================

    protected function requireAuth(): void {
        if (!is_user_logged_in()) {
            if ($this->isAjax()) {
                $this->jsonError(__('Não autenticado.', 'first-financial-box'), 401);
            }
            wp_die(__('Faça login para continuar.', 'first-financial-box'));
        }
    }

    protected function requireCap(string $cap = FFB_CAP_VIEW): void {
        $this->requireAuth();
        if (!current_user_can($cap)) {
            if ($this->isAjax()) {
                $this->jsonError(__('Sem permissão.', 'first-financial-box'), 403);
            }
            wp_die(__('Sem permissão para esta ação.', 'first-financial-box'));
        }
    }

    protected function userId(): int {
        return get_current_user_id();
    }

    protected function isAdmin(): bool {
        return current_user_can(FFB_CAP_MANAGE) || current_user_can('administrator');
    }

    // =============================================
    // CSRF / NONCE (WordPress nativo)
    // =============================================

    protected function verifyNonce(string $action = 'ffb_nonce'): void {
        // Busca o nonce em todas as fontes possíveis (POST body, GET params, header WP)
        $nonce = $_POST['_wpnonce']
              ?? $_GET['_wpnonce']
              ?? $_REQUEST['_wpnonce']
              ?? $_SERVER['HTTP_X_WP_NONCE']
              ?? '';
        if (!wp_verify_nonce($nonce, $action)) {
            if ($this->isAjax()) {
                $this->jsonError(__('Nonce inválido. Recarregue a página.', 'first-financial-box'), 403);
                // wp_send_json_error já chama exit, mas para segurança:
                exit;
            }
            wp_die(__('Nonce inválido.', 'first-financial-box'));
        }
    }

    protected function nonce(string $action = 'ffb_nonce'): string {
        return wp_create_nonce($action);
    }

    // =============================================
    // RESPOSTAS
    // =============================================

    protected function jsonSuccess(mixed $data = [], string $message = ''): void {
        wp_send_json_success(array_merge(
            is_array($data) ? $data : ['data' => $data],
            $message ? ['message' => $message] : []
        ));
    }

    protected function jsonError(string $message, int $code = 400): void {
        wp_send_json_error(['message' => $message], $code);
    }

    protected function json(mixed $data): void {
        wp_send_json($data);
    }

    // =============================================
    // TEMPLATES
    // =============================================

    /**
     * Renderiza um template do plugin.
     * Os templates ficam em /templates/{module}/{view}.php
     * e recebem as variáveis via extract().
     */
    protected function render(string $template, array $data = []): void {
        $file = FFB_PATH . 'templates/' . $template . '.php';
        if (!file_exists($file)) {
            wp_die("Template não encontrado: {$template}");
        }
        // Dados disponíveis globalmente nos templates
        $data['ffb_nonce']    = $this->nonce();
        $data['ffb_ajax_url'] = admin_url('admin-ajax.php');
        $data['ffb_user_id']  = $this->userId();

        extract($data, EXTR_SKIP);
        include $file;
    }

    /**
     * Envolve o template no layout administrativo do plugin.
     */
    protected function view(string $template, array $data = [], string $title = ''): void {
        $data['page_title']  = $title ?: __('First Financial Box', 'first-financial-box');
        $data['page_module'] = $template;
        $data['ffb_nonce']   = $this->nonce();

        // Abre o layout
        include FFB_PATH . 'templates/layout-header.php';
        $this->render($template, $data);
        include FFB_PATH . 'templates/layout-footer.php';
    }

    // =============================================
    // INPUT
    // =============================================

    protected function post(string $key, mixed $default = ''): mixed {
        return $_POST[$key] ?? $default;
    }

    protected function query(string $key, mixed $default = ''): mixed {
        return $_GET[$key] ?? $default;
    }

    protected function input(string $key, mixed $default = ''): mixed {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function sanitize(string $value): string {
        return sanitize_text_field($value);
    }

    protected function sanitizeTextarea(string $value): string {
        return sanitize_textarea_field($value);
    }

    protected function isAjax(): bool {
        return defined('DOING_AJAX') && DOING_AJAX;
    }

    protected function isPost(): bool {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    // =============================================
    // FLASH MESSAGES (via transients por usuário)
    // =============================================

    protected function flash(string $type, string $message): void {
        $uid = $this->userId();
        set_transient("ffb_flash_{$uid}", ['type' => $type, 'message' => $message], 60);
    }

    protected function getFlash(): ?array {
        $uid   = $this->userId();
        $flash = get_transient("ffb_flash_{$uid}");
        if ($flash) {
            delete_transient("ffb_flash_{$uid}");
            return $flash;
        }
        return null;
    }

    // =============================================
    // FORMATAÇÃO
    // =============================================

    protected function formatMoney(?float $value): string {
        $v = abs($value ?? 0.0);
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    protected function formatDate(string $date): string {
        if (!$date) return '—';
        return date_i18n('d/m/Y', strtotime($date));
    }

    // =============================================
    // AUDIT LOG
    // =============================================

    protected function auditLog(
        string $action,
        string $entity,
        ?int   $entityId = null,
        ?array $old      = null,
        ?array $new      = null
    ): void {
        $this->db->insert($this->tablePrefix . 'audit_logs', [
            'user_id'   => $this->userId(),
            'action'    => $action,
            'entity'    => $entity,
            'entity_id' => $entityId,
            'old_value' => $old ? wp_json_encode($old) : null,
            'new_value' => $new ? wp_json_encode($new) : null,
            'ip'        => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }
}
