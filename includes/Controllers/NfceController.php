<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class NfceController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid    = $this->userId();
        $month  = sanitize_text_field($this->query('month', date('Y-m')));
        $search = sanitize_text_field($this->query('search', ''));
        $page   = max(1, (int)$this->query('page', 1));
        $per    = 25;
        $p      = $this->tablePrefix;

        // Verifica se tabela de itens existe
        $temItens = (bool)$this->db->get_var("SHOW TABLES LIKE '{$p}nfce_itens'");

        $itens = []; $total = 0; $resumo = ['cupons' => 0, 'total_itens' => 0, 'total_unidades' => 0, 'valor_total' => 0];
        $topProdutos = [];

        if ($temItens) {
            // Monta WHERE com prepare() para evitar SQL injection
            // DATE_FORMAT com %Y e %m deve usar %%Y e %%m dentro de prepare()
            // para que o wpdb não os confunda com seus próprios placeholders
            $whereArgs = [$uid];
            $whereSql  = "i.user_id = %d";

            if ($month) {
                $whereSql   .= " AND DATE_FORMAT(n.data_emissao, '%%Y-%%m') = %s";
                $whereArgs[] = $month;
            }
            if ($search) {
                $whereSql   .= " AND i.nome LIKE %s";
                $whereArgs[] = '%' . $this->db->esc_like($search) . '%';
            }

            $totalSql = "SELECT COUNT(*)
                         FROM {$p}nfce_itens i
                         JOIN {$p}nfce_imports n ON i.nfce_import_id = n.id
                         WHERE {$whereSql}";
            $total    = (int)$this->db->get_var(
                $this->db->prepare($totalSql, ...$whereArgs)
            );

            $offset = ($page - 1) * $per;
            $itemsSql = "SELECT i.*, n.emitente, n.data_emissao
                         FROM {$p}nfce_itens i
                         JOIN {$p}nfce_imports n ON i.nfce_import_id = n.id
                         WHERE {$whereSql}
                         ORDER BY n.data_emissao DESC, i.nome ASC
                         LIMIT %d OFFSET %d";
            $itens = $this->db->get_results(
                $this->db->prepare($itemsSql, ...array_merge($whereArgs, [$per, $offset])),
                ARRAY_A
            );

            $resumo = $this->db->get_row($this->db->prepare(
                "SELECT COUNT(DISTINCT i.chave) AS cupons, COUNT(*) AS total_itens,
                        SUM(i.quantidade) AS total_unidades, SUM(i.valor_total) AS valor_total
                 FROM {$p}nfce_itens i
                 JOIN {$p}nfce_imports n ON i.nfce_import_id = n.id
                 WHERE i.user_id = %d AND DATE_FORMAT(n.data_emissao, '%%Y-%%m') = %s",
                $uid, $month
            ), ARRAY_A) ?? ['cupons' => 0, 'total_itens' => 0, 'total_unidades' => 0, 'valor_total' => 0];

            $topProdutos = $this->db->get_results($this->db->prepare(
                "SELECT i.nome, COUNT(*) AS vezes, SUM(i.quantidade) AS quantidade_total,
                        SUM(i.valor_total) AS valor_total
                 FROM {$p}nfce_itens i
                 JOIN {$p}nfce_imports n ON i.nfce_import_id = n.id
                 WHERE i.user_id = %d AND DATE_FORMAT(n.data_emissao, '%%Y-%%m') = %s
                 GROUP BY i.nome ORDER BY valor_total DESC LIMIT 10",
                $uid, $month
            ), ARRAY_A);
        }

        $imports = $this->db->get_results($this->db->prepare(
            "SELECT n.*, t.description AS tx_desc FROM {$p}nfce_imports n
             LEFT JOIN {$p}transactions t ON n.transaction_id=t.id
             WHERE n.user_id=%d ORDER BY n.created_at DESC LIMIT 20", $uid
        ), ARRAY_A);

        $categories = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE user_id=%d AND type='expense' AND active=1 ORDER BY name", $uid
        ), ARRAY_A);
        $accounts = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);

        $paginator = new \FFB\Helpers\Paginator($total, $page, $per);

        $this->view('nfce/index', compact(
            'itens', 'paginator', 'total', 'resumo', 'topProdutos',
            'imports', 'categories', 'accounts', 'month', 'search', 'temItens'
        ), __('NFC-e Cupons Fiscais', 'first-financial-box'));
    }

    public function consultar(): void {
        $this->requireCap();
        $this->verifyNonce();
        $input = sanitize_textarea_field($this->post('chave_ou_qrcode'));
        if (!$input) { $this->jsonError('Informe a chave ou URL do QR Code.'); return; }

        $service   = new \NfceService();
        $resultado = str_starts_with($input, 'http')
            ? $service->consultarPorQrCode($input)
            : $service->consultarPorChave($input);

        if (isset($resultado['error'])) { $this->jsonError($resultado['message']); return; }

        $this->jsonSuccess([
            'nfce'         => $resultado,
            'json_preview' => json_decode($service->paraJson($resultado), true),
        ]);
    }

    public function importar(): void {
        $this->requireCap();
        $this->verifyNonce();
        $uid  = $this->userId();
        $p    = $this->tablePrefix;
        $v    = new \FFB\Helpers\Validator($_POST);
        $v->required('chave',       'Chave NFC-e')->string('chave', 'Chave NFC-e', 44)
          ->required('account_id',  'Conta')      ->integer('account_id', 'Conta')
          ->required('category_id', 'Categoria')  ->integer('category_id', 'Categoria')
          ->required('amount',      'Valor')       ->positiveFloat('amount', 'Valor')
          ->required('date',        'Data')        ->date('date', 'Data')
          ->optional('description', '');
        if ($v->fails()) { $this->jsonError($v->firstError()); return; }

        $chave    = preg_replace('/\D/', '', $v->get('chave'));
        $produtos = json_decode($this->post('produtos_json', '[]'), true) ?: [];

        // Garante que os valores numéricos dos produtos estejam em float
        // (podem chegar como string BR "67,90" dependendo do parser)
        $produtos = array_map(function(array $p): array {
            $toFloat = function(mixed $v): float {
                $s = str_replace([' ', '.'], '', (string)$v);
                $s = str_replace(',', '.', $s);
                return (float)$s;
            };
            return [
                'nome'           => trim($p['nome'] ?? ''),
                'quantidade'     => $toFloat($p['quantidade'] ?? 1),
                'unidade'        => strtoupper(trim($p['unidade'] ?? 'UN')),
                'valor_unitario' => $toFloat($p['valor_unitario'] ?? 0),
                'valor_total'    => $toFloat($p['valor_total'] ?? 0),
                'desconto'       => $toFloat($p['desconto'] ?? 0),
            ];
        }, $produtos);

        $dup = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$p}nfce_imports WHERE user_id=%d AND chave=%s", $uid, $chave
        ));
        if ($dup) { $this->jsonError('Esta NFC-e já foi importada anteriormente.'); return; }

        // Cria transação
        $txCtrl = new TransactionController();
        $desc   = $v->get('description') ?: 'NFC-e: ' . sanitize_text_field($this->post('emitente', 'Emitente'));
        $txData = [
            'user_id'     => $uid,
            'account_id'  => $v->get('account_id'),
            'category_id' => $v->get('category_id'),
            'type'        => 'expense',
            'description' => $desc,
            'amount'      => $v->get('amount'),
            'date'        => $v->get('date'),
            'status'      => 'paid',
            'notes'       => 'NFC-e chave: ' . $chave,
            'nfce_chave'  => $chave,
        ];

        global $wpdb;
        $wpdb->insert("{$p}transactions", $txData);
        $txId = (int)$wpdb->insert_id;

        // Atualiza saldo
        $wpdb->query($wpdb->prepare(
            "UPDATE {$p}accounts SET balance = balance - %f WHERE id=%d", $v->get('amount'), $v->get('account_id')
        ));

        $wpdb->insert("{$p}nfce_imports", [
            'user_id'        => $uid,
            'chave'          => $chave,
            'emitente'       => sanitize_text_field($this->post('emitente', '')),
            'total'          => $v->get('amount'),
            'data_emissao'   => $v->get('date'),
            'transaction_id' => $txId,
            'status'         => 'ok',
        ]);
        $importId = (int)$wpdb->insert_id;

        // Salva itens (se tabela existir)
        $temTabelaItens = (bool)$wpdb->get_var("SHOW TABLES LIKE '{$p}nfce_itens'");
        $itensSalvos    = 0;
        if ($temTabelaItens && !empty($produtos)) {
            foreach ($produtos as $prod) {
                if (empty(trim($prod['nome']))) continue; // pula itens sem nome
                $ok = $wpdb->insert("{$p}nfce_itens", [
                    'nfce_import_id' => $importId,
                    'user_id'        => $uid,
                    'chave'          => $chave,
                    'nome'           => mb_substr(sanitize_text_field($prod['nome']), 0, 255),
                    'quantidade'     => $prod['quantidade'],
                    'unidade'        => mb_substr($prod['unidade'], 0, 10),
                    'valor_unitario' => $prod['valor_unitario'],
                    'valor_total'    => $prod['valor_total'],
                ]);
                if ($ok !== false) $itensSalvos++;
            }
        }

        // JSON em upload dir do WP (seguro)
        $upload = wp_upload_dir();
        $dir    = $upload['basedir'] . '/ffb/';
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $jsonFile = $dir . 'nfce_cupons_' . $uid . '.json';
        $dados    = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) ?? [] : [];
        $dados[$chave] = [
            'valor_total'  => $v->get('amount'),
            'emitente'     => sanitize_text_field($this->post('emitente', '')),
            'data_emissao' => $v->get('date'),
            'itens'        => $produtos,
            'salvo_em'     => current_time('mysql'),
        ];
        file_put_contents($jsonFile, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->auditLog('create', 'transaction', $txId);
        $this->jsonSuccess(['transaction_id' => $txId, 'import_id' => $importId, 'itens_salvos' => $itensSalvos]);
    }

    public function chartAnual(): void {
        $this->requireCap();
        $this->verifyNonce();
        $uid  = $this->userId(); $p = $this->tablePrefix;
        $year = (int)$this->query('year', date('Y'));

        $top = $this->db->get_col($this->db->prepare(
            "SELECT nome FROM {$p}nfce_itens WHERE user_id=%d AND YEAR(created_at)=%d
             GROUP BY nome ORDER BY SUM(valor_total) DESC LIMIT 8", $uid, $year
        ));
        if (empty($top)) { $this->jsonSuccess(['labels' => [], 'datasets' => []]); return; }

        $placeholders = implode(',', array_fill(0, count($top), '%s'));
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT nome, DATE_FORMAT(created_at,'%%Y-%%m') AS mes, SUM(valor_total) AS valor_total
             FROM {$p}nfce_itens
             WHERE user_id=%d AND YEAR(created_at)=%d AND nome IN ({$placeholders})
             GROUP BY nome, mes ORDER BY nome, mes",
            array_merge([$uid, $year], $top)
        ), ARRAY_A);

        $meses = []; $produtos = []; $dataMap = [];
        foreach ($rows as $r) {
            $meses[$r['mes']] = true;
            $produtos[$r['nome']] = true;
            $dataMap[$r['nome']][$r['mes']] = (float)$r['valor_total'];
        }
        ksort($meses);

        $labels = array_map(function($m) {
            $p = explode('-', $m);
            $mLabels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            return ($mLabels[(int)$p[1] - 1] ?? $p[1]) . '/' . substr($p[0], 2);
        }, array_keys($meses));

        $cores = ['#6366F1','#10B981','#F59E0B','#EF4444','#3B82F6','#EC4899','#14B8A6','#8B5CF6'];
        $datasets = [];
        $i = 0;
        foreach (array_keys($produtos) as $prod) {
            $valores = [];
            foreach (array_keys($meses) as $mes) $valores[] = $dataMap[$prod][$mes] ?? 0;
            $cor = $cores[$i % count($cores)];
            $datasets[] = [
                'label'           => mb_strimwidth($prod, 0, 30, '…'),
                'data'            => $valores,
                'backgroundColor' => $cor . '33',
                'borderColor'     => $cor,
                'borderWidth'     => 2,
                'tension'         => 0.3,
                'fill'            => false,
            ];
            $i++;
        }
        $this->jsonSuccess(['labels' => $labels, 'datasets' => $datasets]);
    }

    public function downloadJson(): void {
        $this->requireCap();
        $uid    = $this->userId();
        $upload = wp_upload_dir();
        $file   = $upload['basedir'] . '/ffb/nfce_cupons_' . $uid . '.json';
        if (!file_exists($file)) { wp_die('Nenhum cupom exportado ainda.'); }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="nfce_cupons_' . date('Y-m-d') . '.json"');
        readfile($file);
        exit;
    }
}
