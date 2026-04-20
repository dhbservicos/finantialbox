<?php
namespace FFB;

defined('ABSPATH') || exit;

/**
 * Tarefas agendadas do plugin (substitui o cron/process_bills.php standalone).
 * Registrado via wp_schedule_event() na ativação.
 * Hook: ffb_daily_cron (dispara diariamente às 06h)
 */
class Cron {

    public static function run(): void {
        global $wpdb;
        $p   = $wpdb->prefix . FFB_PREFIX;
        $now = current_time('Y-m-d');

        // 1. Marca bills vencidas como "overdue"
        $overdue = $wpdb->query($wpdb->prepare(
            "UPDATE {$p}bills SET status='overdue'
             WHERE status='pending' AND due_date < %s AND deleted_at IS NULL", $now
        ));
        self::log("Bills marcadas como vencidas: {$overdue}");

        // 2. Gera recorrências para bills pagas recentemente
        $bills = $wpdb->get_results(
            "SELECT * FROM {$p}bills
             WHERE status='paid' AND recurrence != 'none'
               AND paid_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
               AND deleted_at IS NULL",
            ARRAY_A
        );

        $geradas = 0;
        foreach ($bills as $bill) {
            $nextDate = match($bill['recurrence']) {
                'monthly'   => date('Y-m-d', strtotime($bill['due_date'] . ' +1 month')),
                'quarterly' => date('Y-m-d', strtotime($bill['due_date'] . ' +3 months')),
                'yearly'    => date('Y-m-d', strtotime($bill['due_date'] . ' +1 year')),
                default     => null,
            };
            if (!$nextDate) continue;

            // Evita duplicata
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}bills WHERE user_id=%d AND description=%s AND due_date=%s",
                $bill['user_id'], $bill['description'], $nextDate
            ));
            if ($exists) continue;

            $wpdb->insert("{$p}bills", [
                'user_id'     => $bill['user_id'],
                'account_id'  => $bill['account_id'],
                'category_id' => $bill['category_id'],
                'type'        => $bill['type'],
                'description' => $bill['description'],
                'amount'      => $bill['amount'],
                'due_date'    => $nextDate,
                'recurrence'  => $bill['recurrence'],
            ]);
            $geradas++;
        }
        self::log("Recorrências geradas: {$geradas}");

        // 3. Limpa tentativas de login antigas (mais de 2 horas)
        $wpdb->query("DELETE FROM {$p}login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");

        // 4. Limpa audit_logs antigos (mais de 90 dias)
        $wpdb->query("DELETE FROM {$p}audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }

    private static function log(string $msg): void {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[First Financial Box] ' . $msg);
        }
    }
}
