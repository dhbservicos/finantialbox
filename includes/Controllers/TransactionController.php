<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class TransactionController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid = $this->userId();
        $p   = $this->tablePrefix;

        $filters = [
            'type'        => sanitize_key($this->query('type')),
            'status'      => sanitize_key($this->query('status')),
            'month'       => sanitize_text_field($this->query('month', date('Y-m'))),
            'category_id' => (int)$this->query('category_id'),
            'account_id'  => (int)$this->query('account_id'),
            'search'      => sanitize_text_field($this->query('search')),
        ];

        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 25;

        // Total para paginação
        $where  = $this->buildTxWhere($uid, $filters);
        $total  = (int)$this->db->get_var("SELECT COUNT(*) FROM {$p}transactions t WHERE {$where['sql']}", ...$where['args']);
        $offset = ($page - 1) * $perPage;

        $transactions = $this->db->get_results(
            $this->db->prepare(
                "SELECT t.*, c.name AS category_name, c.color AS category_color,
                        a.name AS account_name, a.color AS account_color
                 FROM {$p}transactions t
                 JOIN {$p}categories c ON t.category_id=c.id
                 JOIN {$p}accounts   a ON t.account_id=a.id
                 WHERE {$where['sql']}
                 ORDER BY t.date DESC, t.id DESC
                 LIMIT %d OFFSET %d",
                ...[...$where['args'], $perPage, $offset]
            ), ARRAY_A
        );

        $summary = $this->db->get_row($this->db->prepare(
            "SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
                    SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS balance
             FROM {$p}transactions
             WHERE user_id=%d AND DATE_FORMAT(date,'%%Y-%%m')=%s AND status='paid' AND deleted_at IS NULL",
            $uid, $filters['month']
        ), ARRAY_A) ?? ['total_income' => 0, 'total_expense' => 0, 'balance' => 0];

        $categories = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE user_id=%d AND active=1 ORDER BY type, name", $uid
        ), ARRAY_A);

        $accounts = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);

        $paginator = new \FFB\Helpers\Paginator($total, $page, $perPage);

        $this->view('transactions/index', compact(
            'transactions', 'categories', 'accounts', 'filters', 'summary', 'paginator', 'total'
        ), __('Transações Bancárias', 'first-financial-box'));
    }

    public function store(): void {
        $this->requireCap();
        $this->verifyNonce();

        $v = new \FFB\Helpers\Validator($_POST);
        $v->required('description', 'Descrição')->string('description', 'Descrição', 255)
          ->required('amount',      'Valor')     ->positiveFloat('amount', 'Valor')
          ->required('date',        'Data')      ->date('date', 'Data')
          ->required('account_id',  'Conta')     ->integer('account_id', 'Conta')
          ->required('category_id', 'Categoria') ->integer('category_id', 'Categoria')
          ->enum('type',   'Tipo',   ['income', 'expense', 'transfer'])
          ->enum('status', 'Status', ['paid', 'pending'])
          ->optional('notes', '');

        if ($v->fails()) { $this->jsonError($v->firstError()); return; }

        $uid  = $this->userId();
        $data = array_merge($v->validated(), ['user_id' => $uid]);
        $id   = $this->insertTransaction($data);

        $this->auditLog('create', 'transaction', $id, null, $data);
        $this->jsonSuccess(['id' => $id], __('Transação criada!', 'first-financial-box'));
    }

    public function update(): void {
        $this->requireCap();
        $this->verifyNonce();

        $id  = (int)$this->post('id');
        $uid = $this->userId();
        $p   = $this->tablePrefix;

        $old = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$p}transactions WHERE id=%d AND user_id=%d", $id, $uid
        ), ARRAY_A);

        if (!$old) { $this->jsonError(__('Não encontrada.', 'first-financial-box'), 404); return; }

        $v = new \FFB\Helpers\Validator($_POST);
        $v->required('description', 'Descrição')->string('description', 'Descrição', 255)
          ->required('amount',      'Valor')     ->positiveFloat('amount', 'Valor')
          ->required('date',        'Data')      ->date('date', 'Data')
          ->required('account_id',  'Conta')     ->integer('account_id', 'Conta')
          ->required('category_id', 'Categoria') ->integer('category_id', 'Categoria')
          ->enum('type',   'Tipo',   ['income', 'expense', 'transfer'])
          ->enum('status', 'Status', ['paid', 'pending'])
          ->optional('notes', '');

        if ($v->fails()) { $this->jsonError($v->firstError()); return; }

        $new = $v->validated();
        $this->updateTransactionWithBalance($id, $old, $new);
        $this->auditLog('update', 'transaction', $id, $old, $new);
        $this->jsonSuccess([], __('Transação atualizada!', 'first-financial-box'));
    }

    public function destroy(): void {
        $this->requireCap();
        $this->verifyNonce();

        $id  = (int)$this->post('id');
        $uid = $this->userId();
        $p   = $this->tablePrefix;

        $tx = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$p}transactions WHERE id=%d AND user_id=%d", $id, $uid
        ), ARRAY_A);

        if (!$tx) { $this->jsonError(__('Não encontrada.', 'first-financial-box'), 404); return; }

        // Reverte saldo
        if ($tx['status'] === 'paid') {
            $op = $tx['type'] === 'income' ? '-' : '+';
            $this->db->query($this->db->prepare(
                "UPDATE {$p}accounts SET balance = balance {$op} %f WHERE id=%d",
                $tx['amount'], $tx['account_id']
            ));
        }
        $this->db->update("{$p}transactions", ['deleted_at' => current_time('mysql')], ['id' => $id]);
        $this->auditLog('delete', 'transaction', $id, $tx);
        $this->jsonSuccess([], __('Transação excluída.', 'first-financial-box'));
    }

    public function fetch(): void {
        $this->requireCap();
        $this->verifyNonce();
        $id  = (int)$this->query('id');
        $uid = $this->userId();
        $p   = $this->tablePrefix;
        $tx  = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$p}transactions WHERE id=%d AND user_id=%d", $id, $uid
        ), ARRAY_A);
        if (!$tx) { $this->jsonError('Não encontrada.', 404); return; }
        $this->jsonSuccess($tx);
    }

    // ---- helpers ----

    private function buildTxWhere(int $uid, array $f): array {
        $p    = $this->tablePrefix;
        $sql  = "t.user_id=%d AND t.deleted_at IS NULL";
        $args = [$uid];
        if (!empty($f['type']))        { $sql .= " AND t.type=%s";                     $args[] = $f['type']; }
        if (!empty($f['status']))      { $sql .= " AND t.status=%s";                   $args[] = $f['status']; }
        if (!empty($f['month']))       { $sql .= " AND DATE_FORMAT(t.date,'%%Y-%%m')=%s"; $args[] = $f['month']; }
        if (!empty($f['category_id'])) { $sql .= " AND t.category_id=%d";              $args[] = $f['category_id']; }
        if (!empty($f['account_id']))  { $sql .= " AND t.account_id=%d";               $args[] = $f['account_id']; }
        if (!empty($f['search']))      { $sql .= " AND t.description LIKE %s";         $args[] = '%' . $this->db->esc_like($f['search']) . '%'; }
        return ['sql' => $sql, 'args' => $args];
    }

    private function insertTransaction(array $data): int {
        $p = $this->tablePrefix;
        $this->db->insert("{$p}transactions", [
            'user_id'     => $data['user_id'],
            'account_id'  => $data['account_id'],
            'category_id' => $data['category_id'],
            'type'        => $data['type'],
            'description' => $data['description'],
            'amount'      => $data['amount'],
            'date'        => $data['date'],
            'status'      => $data['status'] ?? 'paid',
            'notes'       => $data['notes'] ?? '',
            'nfce_chave'  => $data['nfce_chave'] ?? null,
        ]);
        $id = (int)$this->db->insert_id;
        if (($data['status'] ?? 'paid') === 'paid') {
            $op = $data['type'] === 'income' ? '+' : '-';
            $this->db->query($this->db->prepare(
                "UPDATE {$p}accounts SET balance = balance {$op} %f WHERE id=%d",
                $data['amount'], $data['account_id']
            ));
        }
        return $id;
    }

    private function updateTransactionWithBalance(int $id, array $old, array $new): void {
        $p = $this->tablePrefix;
        // Reverte saldo antigo
        if ($old['status'] === 'paid') {
            $op = $old['type'] === 'income' ? '-' : '+';
            $this->db->query($this->db->prepare(
                "UPDATE {$p}accounts SET balance = balance {$op} %f WHERE id=%d",
                $old['amount'], $old['account_id']
            ));
        }
        // Aplica novo saldo
        if (($new['status'] ?? 'paid') === 'paid') {
            $op = $new['type'] === 'income' ? '+' : '-';
            $this->db->query($this->db->prepare(
                "UPDATE {$p}accounts SET balance = balance {$op} %f WHERE id=%d",
                $new['amount'], $new['account_id']
            ));
        }
        $this->db->update("{$p}transactions", [
            'account_id'  => $new['account_id'],
            'category_id' => $new['category_id'],
            'type'        => $new['type'],
            'description' => $new['description'],
            'amount'      => $new['amount'],
            'date'        => $new['date'],
            'status'      => $new['status'] ?? 'paid',
            'notes'       => $new['notes'] ?? '',
        ], ['id' => $id]);
    }
}
