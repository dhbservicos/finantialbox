<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class BillController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid = $this->userId(); $p = $this->tablePrefix;

        // Atualiza vencidos automaticamente
        $this->db->query($this->db->prepare(
            "UPDATE {$p}bills SET status='overdue'
             WHERE user_id=%d AND status='pending' AND due_date < CURDATE()", $uid
        ));

        $filters = [
            'type'   => sanitize_key($this->query('type')),
            'status' => sanitize_key($this->query('status')),
            'month'  => sanitize_text_field($this->query('month', date('Y-m'))),
        ];

        $where = "b.user_id={$uid} AND b.deleted_at IS NULL";
        $args  = [];
        if ($filters['type'])   { $where .= " AND b.type='"  . esc_sql($filters['type'])   . "'"; }
        if ($filters['status']) { $where .= " AND b.status='"  . esc_sql($filters['status']) . "'"; }
        if ($filters['month'])  { $where .= " AND DATE_FORMAT(b.due_date,'%%Y-%%m')='" . esc_sql($filters['month']) . "'"; }

        $bills = $this->db->get_results(
            "SELECT b.*, c.name AS category_name, c.color AS category_color, a.name AS account_name
             FROM {$p}bills b
             JOIN {$p}categories c ON b.category_id=c.id
             JOIN {$p}accounts   a ON b.account_id=a.id
             WHERE {$where} ORDER BY b.due_date ASC",
            ARRAY_A
        );

        $categories = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE user_id=%d AND active=1 ORDER BY type, name", $uid
        ), ARRAY_A);
        $accounts = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);

        $this->view('bills/index', compact('bills', 'categories', 'accounts', 'filters'),
            __('Contas a Pagar/Receber', 'first-financial-box'));
    }

    public function store(): void {
        $this->requireCap(); $this->verifyNonce();
        $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->insert("{$p}bills", [
            'user_id'     => $uid,
            'account_id'  => (int)$this->post('account_id'),
            'category_id' => (int)$this->post('category_id'),
            'type'        => sanitize_key($this->post('type')),
            'description' => $this->sanitize($this->post('description')),
            'amount'      => (float)str_replace(',', '.', $this->post('amount')),
            'due_date'    => sanitize_text_field($this->post('due_date')),
            'recurrence'  => sanitize_key($this->post('recurrence', 'none')),
            'notes'       => $this->sanitizeTextarea($this->post('notes')),
        ]);
        $this->jsonSuccess(['id' => $this->db->insert_id], __('Conta criada!', 'first-financial-box'));
    }

    public function markPaid(): void {
        $this->requireCap(); $this->verifyNonce();
        $id  = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->update("{$p}bills", [
            'status'  => 'paid',
            'paid_at' => current_time('Y-m-d'),
        ], ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Marcado como pago!', 'first-financial-box'));
    }

    public function destroy(): void {
        $this->requireCap(); $this->verifyNonce();
        $id = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->update("{$p}bills", ['deleted_at' => current_time('mysql')], ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Conta excluída.', 'first-financial-box'));
    }
}
