<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class BankImportController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid = $this->userId(); $p = $this->tablePrefix;

        $imports = [];
        $tableExists = $this->db->get_var("SHOW TABLES LIKE '{$p}bank_imports'");
        if ($tableExists) {
            $imports = $this->db->get_results($this->db->prepare(
                "SELECT b.*, a.name AS account_name FROM {$p}bank_imports b
                 JOIN {$p}accounts a ON b.account_id=a.id
                 WHERE b.user_id=%d ORDER BY b.created_at DESC LIMIT 20", $uid
            ), ARRAY_A);
        }

        $accounts   = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);
        $categories = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE user_id=%d AND active=1 ORDER BY type, name", $uid
        ), ARRAY_A);

        $this->view('bank-import/index', compact('imports', 'accounts', 'categories'),
            __('Importar Extrato Bancário', 'first-financial-box'));
    }

    public function preview(): void {
        $this->requireCap();
        $this->verifyNonce();

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $this->jsonError('Nenhum arquivo enviado ou erro no upload.'); return;
        }
        $file = $_FILES['arquivo'];
        if ($file['size'] > 5 * 1024 * 1024) { $this->jsonError('Arquivo muito grande. Máximo 5 MB.'); return; }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['ofx', 'csv', 'txt'], true)) {
            $this->jsonError('Formato inválido. Envie .ofx, .csv ou .txt'); return;
        }
        $content = file_get_contents($file['tmp_name']);
        if (!$content) { $this->jsonError('Não foi possível ler o arquivo.'); return; }

        $service    = new \BankImportService();
        $formato    = ($ext === 'ofx' || $ext === 'txt') ? 'ofx' : $service->detectFormat($content);
        $transacoes = $formato === 'ofx' ? $service->parseOfx($content) : $service->parseCsv($content);

        if (empty($transacoes)) {
            $this->jsonError('Nenhuma transação encontrada. Verifique o formato do arquivo.'); return;
        }

        $this->jsonSuccess([
            'formato'    => $formato,
            'filename'   => sanitize_file_name($file['name']),
            'total'      => count($transacoes),
            'transacoes' => $transacoes,
        ]);
    }

    public function upload(): void {
        $this->requireCap();
        $this->verifyNonce();
        $uid       = $this->userId(); $p = $this->tablePrefix;
        $accountId = (int)$this->post('account_id');
        $formato   = sanitize_key($this->post('formato', 'csv'));
        $filename  = sanitize_file_name($this->post('filename', 'extrato'));
        $txJson    = $this->post('transacoes_json', '[]');
        $transacoes = json_decode($txJson, true);

        if (empty($transacoes) || !$accountId) {
            $this->jsonError('Dados inválidos.'); return;
        }

        $categoriasPorLinha = $_POST['categories'] ?? [];
        $txCtrl  = new TransactionController();
        $importado = 0; $pulado = 0; $erros = [];

        foreach ($transacoes as $idx => $tx) {
            $categoryId = (int)($categoriasPorLinha[$idx] ?? 0);

            // Deduplicação
            $dup = $this->db->get_var($this->db->prepare(
                "SELECT id FROM {$p}transactions
                 WHERE user_id=%d AND account_id=%d AND date=%s AND amount=%f
                   AND description=%s AND type=%s LIMIT 1",
                $uid, $accountId, $tx['date'], $tx['amount'], $tx['description'], $tx['type']
            ));
            if ($dup) { $pulado++; continue; }

            // Resolve categoria por nome sugerido
            if (!$categoryId && !empty($tx['categoria_sugerida'])) {
                $catType = $tx['type'] === 'income' ? 'income' : 'expense';
                $row = $this->db->get_row($this->db->prepare(
                    "SELECT id FROM {$p}categories WHERE user_id=%d AND type=%s AND active=1
                     AND name LIKE %s LIMIT 1",
                    $uid, $catType, '%' . $this->db->esc_like($tx['categoria_sugerida']) . '%'
                ));
                if ($row) $categoryId = (int)$row->id;
            }
            if (!$categoryId) {
                $catType = $tx['type'] === 'income' ? 'income' : 'expense';
                $row = $this->db->get_row($this->db->prepare(
                    "SELECT id FROM {$p}categories WHERE user_id=%d AND type=%s AND active=1 LIMIT 1",
                    $uid, $catType
                ));
                $categoryId = $row ? (int)$row->id : 1;
            }

            try {
                $this->db->insert("{$p}transactions", [
                    'user_id'     => $uid,
                    'account_id'  => $accountId,
                    'category_id' => $categoryId,
                    'type'        => $tx['type'],
                    'description' => mb_substr(sanitize_text_field($tx['description']), 0, 255),
                    'amount'      => (float)$tx['amount'],
                    'date'        => sanitize_text_field($tx['date']),
                    'status'      => 'paid',
                    'notes'       => 'Importado: ' . $filename,
                ]);
                $op = $tx['type'] === 'income' ? '+' : '-';
                $this->db->query($this->db->prepare(
                    "UPDATE {$p}accounts SET balance = balance {$op} %f WHERE id=%d",
                    (float)$tx['amount'], $accountId
                ));
                $importado++;
            } catch (\Throwable $e) {
                $erros[] = "Linha {$idx}: " . $e->getMessage();
            }
        }

        // Registra importação
        $tableExists = $this->db->get_var("SHOW TABLES LIKE '{$p}bank_imports'");
        if ($tableExists) {
            $dates = array_column($transacoes, 'date');
            sort($dates);
            $this->db->insert("{$p}bank_imports", [
                'user_id'    => $uid, 'account_id' => $accountId, 'filename' => $filename,
                'format'     => $formato, 'date_from' => $dates[0] ?? null,
                'date_to'    => $dates[count($dates) - 1] ?? null,
                'total_rows' => count($transacoes), 'imported' => $importado, 'skipped' => $pulado,
            ]);
        }

        $this->auditLog('create', 'bank_import', null, null,
            ['file' => $filename, 'imported' => $importado]);

        $this->jsonSuccess(['importado' => $importado, 'pulado' => $pulado, 'erros' => $erros]);
    }
}
