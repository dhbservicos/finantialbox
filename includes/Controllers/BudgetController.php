<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class BudgetController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid   = $this->userId();
        $month = sanitize_text_field($this->query('month', date('Y-m')));
        $p     = $this->tablePrefix;

        $budgets = $this->db->get_results($this->db->prepare(
            "SELECT b.*, c.name AS category_name, c.color AS category_color,
                    COALESCE(SUM(t.amount), 0) AS spent
             FROM {$p}budgets b
             JOIN {$p}categories c ON b.category_id=c.id
             LEFT JOIN {$p}transactions t ON t.category_id=b.category_id AND t.user_id=b.user_id
                 AND DATE_FORMAT(t.date,'%%Y-%%m')=b.month AND t.type='expense' AND t.status='paid' AND t.deleted_at IS NULL
             WHERE b.user_id=%d AND b.month=%s
             GROUP BY b.id ORDER BY c.name",
            $uid, $month
        ), ARRAY_A);

        $summary = $this->db->get_row($this->db->prepare(
            "SELECT SUM(b.amount) AS total_budget, COALESCE(SUM(t.spent),0) AS total_spent
             FROM {$p}budgets b
             LEFT JOIN (SELECT category_id, SUM(amount) AS spent FROM {$p}transactions
                        WHERE user_id=%d AND DATE_FORMAT(date,'%%Y-%%m')=%s AND type='expense' AND status='paid' AND deleted_at IS NULL
                        GROUP BY category_id) t ON t.category_id=b.category_id
             WHERE b.user_id=%d AND b.month=%s",
            $uid, $month, $uid, $month
        ), ARRAY_A) ?? ['total_budget' => 0, 'total_spent' => 0];

        $categories = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE user_id=%d AND type='expense' AND active=1 ORDER BY name", $uid
        ), ARRAY_A);

        $this->view('budget/index', compact('budgets', 'summary', 'categories', 'month'),
            __('Orçamento Mensal', 'first-financial-box'));
    }

    public function store(): void {
        $this->requireCap(); $this->verifyNonce();
        $uid = $this->userId(); $p = $this->tablePrefix;
        $categoryId = (int)$this->post('category_id');
        $month      = sanitize_text_field($this->post('month'));
        $amount     = (float)str_replace(',', '.', $this->post('amount'));

        // Upsert
        $existing = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$p}budgets WHERE user_id=%d AND category_id=%d AND month=%s",
            $uid, $categoryId, $month
        ));
        if ($existing) {
            $this->db->update("{$p}budgets", ['amount' => $amount], ['id' => $existing]);
        } else {
            $this->db->insert("{$p}budgets", ['user_id' => $uid, 'category_id' => $categoryId, 'month' => $month, 'amount' => $amount]);
        }
        $this->jsonSuccess([], __('Orçamento salvo!', 'first-financial-box'));
    }

    public function destroy(): void {
        $this->requireCap(); $this->verifyNonce();
        $id = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->delete("{$p}budgets", ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Orçamento removido.', 'first-financial-box'));
    }
}
