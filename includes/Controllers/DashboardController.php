<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class DashboardController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid   = $this->userId();
        $month = date('Y-m');
        $p     = $this->tablePrefix;

        // Saldo total das contas
        $totalBalance = (float)$this->db->get_var($this->db->prepare(
            "SELECT SUM(balance) FROM {$p}accounts WHERE user_id = %d AND active = 1", $uid
        ));

        // KPIs do mês
        $summary = $this->db->get_row($this->db->prepare(
            "SELECT
                SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
                SUM(CASE WHEN type='income'  THEN amount ELSE -amount END) AS balance
             FROM {$p}transactions
             WHERE user_id=%d AND DATE_FORMAT(date,'%%Y-%%m')=%s AND status='paid' AND deleted_at IS NULL",
            $uid, $month
        ), ARRAY_A) ?? ['total_income' => 0, 'total_expense' => 0, 'balance' => 0];

        // Contas bancárias
        $accounts = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);

        // Transações recentes
        $recentTx = $this->db->get_results($this->db->prepare(
            "SELECT t.*, c.name AS category_name, c.color AS category_color,
                    a.name AS account_name, a.color AS account_color
             FROM {$p}transactions t
             JOIN {$p}categories c ON t.category_id=c.id
             JOIN {$p}accounts   a ON t.account_id=a.id
             WHERE t.user_id=%d AND t.deleted_at IS NULL
             ORDER BY t.date DESC, t.id DESC LIMIT 8",
            $uid
        ), ARRAY_A);

        // Despesas por categoria no mês
        $expByCategory = $this->db->get_results($this->db->prepare(
            "SELECT c.name, c.color, SUM(t.amount) AS total
             FROM {$p}transactions t
             JOIN {$p}categories c ON t.category_id=c.id
             WHERE t.user_id=%d AND t.type='expense'
               AND DATE_FORMAT(t.date,'%%Y-%%m')=%s AND t.status='paid' AND t.deleted_at IS NULL
             GROUP BY c.id ORDER BY total DESC",
            $uid, $month
        ), ARRAY_A);

        // Fluxo de caixa 6 meses
        $cashflow = $this->db->get_results($this->db->prepare(
            "SELECT DATE_FORMAT(date,'%%Y-%%m') AS month,
                    SUM(CASE WHEN type='income'  THEN amount ELSE 0 END) AS income,
                    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
             FROM {$p}transactions
             WHERE user_id=%d AND date>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH)
               AND status='paid' AND deleted_at IS NULL
             GROUP BY DATE_FORMAT(date,'%%Y-%%m') ORDER BY month ASC",
            $uid
        ), ARRAY_A);

        // Alertas de vencimento próximo (10 dias)
        $upcoming = $this->db->get_results($this->db->prepare(
            "SELECT b.*, c.name AS category_name, a.name AS account_name
             FROM {$p}bills b
             JOIN {$p}categories c ON b.category_id=c.id
             JOIN {$p}accounts   a ON b.account_id=a.id
             WHERE b.user_id=%d AND b.status='pending'
               AND b.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 10 DAY)
             ORDER BY b.due_date ASC",
            $uid
        ), ARRAY_A);

        $this->view('dashboard/index', compact(
            'totalBalance', 'summary', 'accounts', 'recentTx',
            'expByCategory', 'cashflow', 'upcoming'
        ), __('Dashboard', 'first-financial-box'));
    }
}
