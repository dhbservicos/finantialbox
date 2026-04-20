<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class ReportController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid   = $this->userId();
        $month = sanitize_text_field($this->query('month', date('Y-m')));
        $year  = (int)$this->query('year', date('Y'));
        $p     = $this->tablePrefix;

        $summary = $this->db->get_row($this->db->prepare(
            "SELECT SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_expense,
                    SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS balance
             FROM {$p}transactions
             WHERE user_id=%d AND DATE_FORMAT(date,'%%Y-%%m')=%s AND status='paid' AND deleted_at IS NULL",
            $uid, $month
        ), ARRAY_A) ?? ['total_income' => 0, 'total_expense' => 0, 'balance' => 0];

        $cashflow = $this->db->get_results($this->db->prepare(
            "SELECT DATE_FORMAT(date,'%%Y-%%m') AS month,
                    SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income,
                    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
             FROM {$p}transactions
             WHERE user_id=%d AND date>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
               AND status='paid' AND deleted_at IS NULL
             GROUP BY DATE_FORMAT(date,'%%Y-%%m') ORDER BY month ASC",
            $uid
        ), ARRAY_A);

        $byCategory = $this->db->get_results($this->db->prepare(
            "SELECT c.name, c.color, SUM(t.amount) AS total
             FROM {$p}transactions t JOIN {$p}categories c ON t.category_id=c.id
             WHERE t.user_id=%d AND t.type='expense'
               AND DATE_FORMAT(t.date,'%%Y-%%m')=%s AND t.status='paid' AND t.deleted_at IS NULL
             GROUP BY c.id ORDER BY total DESC",
            $uid, $month
        ), ARRAY_A);

        // DRE
        $dreRows = $this->db->get_results($this->db->prepare(
            "SELECT DATE_FORMAT(date, '%%m') AS month, c.name AS category, c.type AS cat_type, SUM(t.amount) AS total
             FROM {$p}transactions t JOIN {$p}categories c ON t.category_id=c.id
             WHERE t.user_id=%d AND YEAR(t.date)=%d AND t.status='paid' AND t.deleted_at IS NULL
             GROUP BY month, c.id ORDER BY month, c.type, c.name",
            $uid, $year
        ), ARRAY_A);

        $dre = [];
        foreach ($dreRows as $row) {
            $dre[$row['month']][$row['cat_type']][$row['category']] = $row['total'];
        }

        $this->view('reports/index', compact('summary', 'cashflow', 'byCategory', 'dre', 'month', 'year'),
            __('Relatórios', 'first-financial-box'));
    }

    public function cashflow(): void {
        $this->requireCap(); $this->verifyNonce();
        $uid   = $this->userId(); $p = $this->tablePrefix;
        $month = sanitize_text_field($this->post('month', date('Y-m')));
        $rows  = $this->db->get_results($this->db->prepare(
            "SELECT DATE_FORMAT(date,'%%Y-%%m') AS m,
                    SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income,
                    SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
             FROM {$p}transactions
             WHERE user_id=%d AND date>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
               AND status='paid' AND deleted_at IS NULL
             GROUP BY m ORDER BY m ASC",
            $uid
        ), ARRAY_A);
        $this->jsonSuccess(['rows' => $rows]);
    }

    public function dre(): void {
        $this->requireCap(); $this->verifyNonce();
        // retorna dados DRE via AJAX se necessário
        $this->jsonSuccess([]);
    }

    public function export(): void {
        $this->requireCap();
        $uid   = $this->userId(); $p = $this->tablePrefix;
        $month = sanitize_text_field($this->query('month', date('Y-m')));

        $rows = $this->db->get_results($this->db->prepare(
            "SELECT t.date, t.description, t.type, c.name AS category_name,
                    a.name AS account_name, t.amount, t.status
             FROM {$p}transactions t
             JOIN {$p}categories c ON t.category_id=c.id
             JOIN {$p}accounts   a ON t.account_id=a.id
             WHERE t.user_id=%d AND DATE_FORMAT(t.date,'%%Y-%%m')=%s AND t.deleted_at IS NULL
             ORDER BY t.date DESC",
            $uid, $month
        ), ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transacoes_' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Data', 'Descrição', 'Tipo', 'Categoria', 'Conta', 'Valor', 'Status'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                date('d/m/Y', strtotime($r['date'])),
                $r['description'],
                $r['type'] === 'income' ? 'Receita' : 'Despesa',
                $r['category_name'],
                $r['account_name'],
                number_format($r['amount'], 2, ',', '.'),
                $r['status'] === 'paid' ? 'Pago' : 'Pendente',
            ], ';');
        }
        fclose($out);
        exit;
    }
}
