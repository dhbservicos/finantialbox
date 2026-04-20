<?php
// =============================================
// BankImportService.php
// Importação de extratos bancários OFX e CSV
//
// Formatos suportados (testados com arquivos reais):
//   - Mercado Pago: TAB-separado, header linha 4, data DD-MM-YYYY
//   - Nubank:       CSV aninhado em coluna única, data DD/MM/YYYY
//   - Banco Inter:  TAB-separado, header linha 6, data DD/MM/YY
//   - OFX/SGML:     formato padrão bancário BR
//   - CSV Genérico: auto-detecção de separador e colunas
// =============================================

class BankImportService {

    // =============================================
    // MAPA DE TIPO POR PALAVRA-CHAVE NA DESCRIÇÃO
    //
    // Prioridade MÁXIMA — garante que "Dinheiro retirado",
    // "Pix Enviado", "Compra no débito" etc. nunca sejam
    // classificados como income mesmo se o valor vier positivo.
    //
    // Formato: 'palavra_chave' => 'income' | 'expense'
    // Verificado antes do sinal do valor numérico.
    // =============================================
    private const TIPO_POR_DESCRICAO = [
        // Saídas definitivas — sempre expense
        'dinheiro retirado'      => 'expense',
        'dinheiro reservado'     => 'expense',
        'saque'                  => 'expense',
        'pix enviado'            => 'expense',
        'transferencia enviada'  => 'expense',
        'transferência enviada'  => 'expense',
        'ted enviada'            => 'expense',
        'doc enviado'            => 'expense',
        'compra no debito'       => 'expense',
        'compra no débito'       => 'expense',
        'compra debito'          => 'expense',
        'compra débito'          => 'expense',
        'pagamento efetuado'     => 'expense',
        'pagamento cartao'       => 'expense',
        'pagamento cartão'       => 'expense',
        'boleto pago'            => 'expense',
        'debito automatico'      => 'expense',
        'débito automático'      => 'expense',
        'tarifa'                 => 'expense',
        'aplicacao'              => 'expense',  // aplicação = sai do saldo
        'aplicação'              => 'expense',
        'facebook pay'           => 'expense',
        'google pay'             => 'expense',
        // Entradas definitivas — sempre income
        'pix recebido'           => 'income',
        'transferencia recebida' => 'income',
        'transferência recebida' => 'income',
        'ted recebida'           => 'income',
        'doc recebido'           => 'income',
        'deposito'               => 'income',
        'depósito'               => 'income',
        'estorno'                => 'income',
        'reembolso'              => 'income',
        'salario'                => 'income',
        'salário'                => 'income',
        'rendimento'             => 'income',
        'rendimentos'            => 'income',
        'cashback'               => 'income',
        'pagamento recebido'     => 'income',
    ];

    // =============================================
    // PONTO DE ENTRADA
    // =============================================
    public function parseCsv(string $content, array $options = []): array {
        $content = $this->normalizar($content);
        if (trim($content) === '') return [];

        if ($this->isMercadoPago($content)) return $this->parseMercadoPago($content);
        if ($this->isNubank($content))      return $this->parseNubank($content);
        if ($this->isInter($content))       return $this->parseInter($content);

        return $this->parseGenerico($content, $options);
    }

    // =============================================
    // DETECÇÃO DE BANCO
    // =============================================
    private function isMercadoPago(string $c): bool {
        return str_contains($c, 'INITIAL_BALANCE')
            || str_contains($c, 'RELEASE_DATE')
            || str_contains($c, 'TRANSACTION_NET_AMOUNT');
    }
    private function isNubank(string $c): bool {
        $fl = strtok($c, "\n");
        return str_contains($fl, 'Data,Valor,Identificador');
    }
    private function isInter(string $c): bool {
        return str_contains($c, 'Extrato Conta Corrente')
            || str_contains($c, 'Data Lançamento')
            || (str_contains($c, 'Período') && str_contains($c, 'Saldo'));
    }

    // =============================================
    // PARSER: MERCADO PAGO
    // TAB-separado | header linha 4 | data DD-MM-YYYY
    // Valor assinado: positivo=income, negativo=expense
    // =============================================
    private function parseMercadoPago(string $content): array {
        $linhas = explode("\n", $content);
        $transacoes = [];
        $headerIdx  = -1;
        foreach ($linhas as $i => $linha) {
            if (str_contains(strtoupper($linha), 'RELEASE_DATE')) {
                $headerIdx = $i; break;
            }
        }
        if ($headerIdx < 0) return [];

        for ($i = $headerIdx + 1; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);
            if ($linha === '') continue;
            $cols   = str_getcsv($linha, "\t");
            $dateRaw = trim($cols[0] ?? '', " \t\"");
            $desc    = trim($cols[1] ?? '', " \t\"");
            $valRaw  = trim($cols[3] ?? '', " \t\"");
            if (!$dateRaw || !$valRaw) continue;
            $date = $this->parseDate($dateRaw);
            if (!$date) continue;
            $valorFloat = $this->parseValorComSinal($valRaw);
            if ($valorFloat == 0) continue;

            // 1ª prioridade: palavra-chave na descrição
            // 2ª prioridade: sinal do valor
            $tipo = $this->detectarTipo($desc, $valorFloat);

            $transacoes[] = [
                'fitid'              => md5('mp' . ($cols[2] ?? '') . $date . abs($valorFloat)),
                'date'               => $date,
                'description'        => $desc ?: 'Lançamento Mercado Pago',
                'amount'             => abs($valorFloat),
                'type'               => $tipo,
                'trntype'            => '',
                'categoria_sugerida' => $this->sugerirCategoria($desc, $tipo),
            ];
        }
        return $transacoes;
    }

    // =============================================
    // PARSER: NUBANK
    // CSV aninhado em coluna única | data DD/MM/YYYY
    // Valor assinado: positivo=income, negativo=expense
    // =============================================
    private function parseNubank(string $content): array {
        $linhas = explode("\n", $content);
        $transacoes = [];
        for ($i = 1; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);
            if ($linha === '') continue;
            $inner = trim($linha, '"');
            if (!preg_match('/^(\d{2}\/\d{2}\/\d{4}),(-?[\d.,]+),([^,]+),(.+)$/', $inner, $m)) {
                if (!preg_match('/^(\d{2}\/\d{2}\/\d{4}),(-?[\d.,]+),(.+)$/', $inner, $m2)) continue;
                [$dateRaw, $valRaw, $desc] = [$m2[1], $m2[2], trim($m2[3])];
            } else {
                [$dateRaw, $valRaw, , $desc] = [$m[1], $m[2], $m[3], trim($m[4])];
            }
            $date = $this->parseDate($dateRaw);
            if (!$date) continue;
            $valorFloat = $this->parseValorComSinal($valRaw);
            if ($valorFloat == 0) continue;

            $tipo = $this->detectarTipo($desc, $valorFloat);

            $transacoes[] = [
                'fitid'              => md5('nu' . $dateRaw . $valRaw . $desc),
                'date'               => $date,
                'description'        => mb_substr($desc, 0, 255),
                'amount'             => abs($valorFloat),
                'type'               => $tipo,
                'trntype'            => '',
                'categoria_sugerida' => $this->sugerirCategoria($desc, $tipo),
            ];
        }
        return $transacoes;
    }

    // =============================================
    // PARSER: BANCO INTER
    // TAB-separado | header linha 6 | data DD/MM/YY
    // Descrição com aspas duplas escapadas
    // =============================================
    private function parseInter(string $content): array {
        $linhas = explode("\n", $content);
        $transacoes = [];
        $headerIdx  = -1;
        foreach ($linhas as $i => $linha) {
            $upper = strtoupper($linha);
            if (str_contains($upper, 'DATA LAN') && str_contains($upper, 'DESCRI')) {
                $headerIdx = $i; break;
            }
        }
        if ($headerIdx < 0) return [];

        for ($i = $headerIdx + 1; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);
            if ($linha === '') continue;
            $cols    = str_getcsv($linha, "\t");
            $dateRaw = trim($cols[0] ?? '', " \t\"");
            $desc    = trim($cols[1] ?? '', " \t\"");
            $valRaw  = trim($cols[2] ?? '', " \t\"");
            if (!$dateRaw) continue;

            // Limpa aspas duplas escapadas e pega parte descritiva
            $desc = str_replace('""', '"', $desc);
            $desc = trim($desc, '"');
            if (preg_match('/:\s*"?(.+)"?$/', $desc, $m)) {
                $desc = trim($m[1], '"');
            }

            $date = $this->parseDate($dateRaw);
            if (!$date) continue;
            $valorFloat = $this->parseValorComSinal($valRaw);
            if ($valorFloat == 0) continue;

            $tipo = $this->detectarTipo($desc, $valorFloat);

            $transacoes[] = [
                'fitid'              => md5('inter' . $dateRaw . $valRaw . $desc),
                'date'               => $date,
                'description'        => mb_substr($desc ?: 'Lançamento Inter', 0, 255),
                'amount'             => abs($valorFloat),
                'type'               => $tipo,
                'trntype'            => '',
                'categoria_sugerida' => $this->sugerirCategoria($desc, $tipo),
            ];
        }
        return $transacoes;
    }

    // =============================================
    // PARSER GENÉRICO (fallback)
    // =============================================
    private function parseGenerico(string $content, array $options = []): array {
        $linhas = array_values(array_filter(explode("\n", $content), fn($l) => trim($l) !== ''));
        if (count($linhas) < 2) return [];
        $sep = $options['sep'] ?? $this->detectarSeparador($linhas);
        [$headerIdx, $colMap] = $this->encontrarHeader($linhas, $sep);
        if ($headerIdx < 0 || empty($colMap)) return [];

        $transacoes = [];
        for ($i = $headerIdx + 1; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);
            if ($linha === '') continue;
            $cols = str_getcsv($linha, $sep);
            $tx   = $this->extrairLinhaGenerica($cols, $colMap);
            if ($tx) {
                $tx['categoria_sugerida'] = $this->sugerirCategoria($tx['description'], $tx['type']);
                $transacoes[] = $tx;
            }
        }
        return $transacoes;
    }

    private function encontrarHeader(array $linhas, string $sep): array {
        $keywords = [
            'date'        => ['data', 'date', 'dt', 'release_date', 'data lancamento', 'data lançamento'],
            'description' => ['descricao', 'descrição', 'description', 'historico', 'histórico', 'transaction_type', 'memo', 'lancamento'],
            'amount'      => ['valor', 'amount', 'transaction_net_amount', 'trnamt', 'value'],
            'credit'      => ['credito', 'crédito', 'entrada', 'credit'],
            'debit'       => ['debito', 'débito', 'saida', 'debit'],
        ];
        for ($i = 0; $i < min(10, count($linhas)); $i++) {
            $cols = str_getcsv($linhas[$i], $sep);
            $colMap = []; $hits = 0;
            foreach ($cols as $idx => $col) {
                $clean = preg_replace('/[^a-z0-9 _]/u', '', mb_strtolower(trim($col)));
                foreach ($keywords as $field => $aliases) {
                    foreach ($aliases as $alias) {
                        if ($clean === $alias || str_contains($clean, $alias)) {
                            if (!isset($colMap[$field])) { $colMap[$field] = $idx; $hits++; }
                            break;
                        }
                    }
                }
            }
            if ($hits >= 2 && isset($colMap['date'])) return [$i, $colMap];
        }
        return [-1, []];
    }

    private function extrairLinhaGenerica(array $cols, array $colMap): ?array {
        $get = fn(string $f) => isset($colMap[$f]) ? trim($cols[$colMap[$f]] ?? '') : '';
        $dateRaw = $get('date'); $desc = $get('description');
        $valRaw  = $get('amount'); $creditRaw = $get('credit'); $debitRaw = $get('debit');
        if (!$dateRaw) return null;
        $date = $this->parseDate($dateRaw);
        if (!$date) return null;

        $tipo = 'expense'; $valor = 0.0;
        if ($creditRaw !== '' || $debitRaw !== '') {
            $cred = $this->parseValorAbs($creditRaw); $deb = $this->parseValorAbs($debitRaw);
            if ($cred > 0)    { $valor = $cred; $tipo = $this->detectarTipo($desc, $cred); }
            elseif ($deb > 0) { $valor = $deb;  $tipo = $this->detectarTipo($desc, -$deb); }
        } elseif ($valRaw !== '') {
            $float = $this->parseValorComSinal($valRaw);
            $valor = abs($float);
            $tipo  = $this->detectarTipo($desc, $float);
        }
        if ($valor <= 0 || !$desc) return null;
        return [
            'fitid' => md5($date . $desc . $valor), 'date' => $date,
            'description' => mb_substr(trim($desc), 0, 255),
            'amount' => $valor, 'type' => $tipo, 'trntype' => '',
        ];
    }

    // =============================================
    // OFX
    // =============================================
    public function parseOfx(string $content): array {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/<BANKTRANLIST>(.*?)<\/BANKTRANLIST>/si', $content, $bloco)) {
            $txs = [];
            preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $bloco[1], $stmts);
            foreach ($stmts[1] as $stmt) { $tx = $this->extractOfxFields($stmt); if ($tx) $txs[] = $tx; }
            return $txs;
        }
        return $this->parseOfxSgml($content);
    }

    private function parseOfxSgml(string $content): array {
        $txs = [];
        $bloco = preg_match('/<BANKTRANLIST>(.*?)(<\/BANKTRANLIST>|$)/si', $content, $m) ? $m[1] : $content;
        $partes = preg_split('/<STMTTRN>/i', $bloco);
        array_shift($partes);
        foreach ($partes as $parte) {
            $parte = preg_split('/<\/STMTTRN>/i', $parte)[0];
            $tx = $this->extractOfxFields($parte);
            if ($tx) $txs[] = $tx;
        }
        return $txs;
    }

    private function extractOfxFields(string $bloco): ?array {
        $get = fn(string $tag) => preg_match("/<{$tag}>\s*([^\r\n<]+)/i", $bloco, $m) ? trim($m[1]) : '';
        $trntype = strtoupper($get('TRNTYPE'));
        $dtposted = $get('DTPOSTED'); $amount = $get('TRNAMT');
        $memo  = $get('MEMO') ?: $get('NAME'); $fitid = $get('FITID');
        if (!$dtposted || !$amount) return null;
        $date = preg_replace('/^(\d{4})(\d{2})(\d{2}).*$/', '$1-$2-$3', $dtposted);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;

        $amount = str_replace(' ', '', $amount);
        $valorFloat = str_contains($amount, ',') && !str_contains($amount, '.')
            ? (float)str_replace(',', '.', $amount)
            : (float)str_replace(',', '', $amount);

        // Para OFX: usa campo TRNTYPE para desambiguar quando sinal for positivo
        $tipoOFX = in_array($trntype, ['DEBIT','CHECK','PAYMENT','CASH'], true) ? 'expense'
                 : (in_array($trntype, ['CREDIT','INT','DIV','DIRECTDEP'], true) ? 'income' : null);

        $tipo = $tipoOFX ?? $this->detectarTipo($memo, $valorFloat);
        $valor = abs($valorFloat);
        if ($valor <= 0) return null;

        return [
            'fitid'              => $fitid ?: md5($date . $memo . $valor),
            'date'               => $date,
            'description'        => mb_substr($memo ?: 'Lançamento bancário', 0, 255),
            'amount'             => $valor,
            'type'               => $tipo,
            'trntype'            => $trntype,
            'categoria_sugerida' => $this->sugerirCategoria($memo, $tipo),
        ];
    }

    // =============================================
    // DETECÇÃO DE TIPO (income/expense)
    //
    // Prioridade:
    //   1. Palavra-chave na descrição (TIPO_POR_DESCRICAO)
    //   2. Sinal do valor numérico (positivo=income, negativo=expense)
    // =============================================
    public function detectarTipo(string $desc, float $valorComSinal): string {
        $d = mb_strtolower($desc);
        // Verifica palavras-chave por ordem de especificidade (mais longas primeiro)
        $mapa = self::TIPO_POR_DESCRICAO;
        uasort($mapa, fn($a, $b) => 0); // mantém ordem de inserção
        // Ordena por comprimento decrescente para palavras mais específicas vencerem
        uksort($mapa, fn($a, $b) => strlen($b) - strlen($a));
        foreach ($mapa as $keyword => $tipo) {
            if (str_contains($d, $keyword)) {
                return $tipo;
            }
        }
        // Fallback: sinal do valor
        return $valorComSinal >= 0 ? 'income' : 'expense';
    }

    // =============================================
    // SUGESTÃO AUTOMÁTICA DE CATEGORIA
    // =============================================
    public function sugerirCategoria(string $desc, string $tipo): string {
        $d = mb_strtolower($desc);
        if ($tipo === 'income') {
            if (str_contains($d, 'salario') || str_contains($d, 'salário') || str_contains($d, 'folha')) return 'Salário';
            if (str_contains($d, 'freelance') || str_contains($d, 'servico') || str_contains($d, 'serviço')) return 'Freelance';
            if (str_contains($d, 'rendimento') || str_contains($d, 'rendimentos') || str_contains($d, 'juros') || str_contains($d, 'rdb') || str_contains($d, 'cdb')) return 'Investimentos';
            if (str_contains($d, 'estorno') || str_contains($d, 'reembolso') || str_contains($d, 'cashback')) return 'Outros Rendimentos';
            if (str_contains($d, 'pix') || str_contains($d, 'transferencia') || str_contains($d, 'transferência')) return 'Outros Rendimentos';
            return 'Outros Rendimentos';
        }
        // Despesas
        if (str_contains($d, 'mercado') || str_contains($d, 'supermercado') || str_contains($d, 'hortifrute') || str_contains($d, 'padaria') || str_contains($d, 'ifood') || str_contains($d, 'restaurante')) return 'Alimentação';
        if (str_contains($d, 'uber') || str_contains($d, '99pop') || str_contains($d, 'combustivel') || str_contains($d, 'combustível') || str_contains($d, 'posto')) return 'Transporte';
        if (str_contains($d, 'farmacia') || str_contains($d, 'farmácia') || str_contains($d, 'hospital') || str_contains($d, 'medico') || str_contains($d, 'médico')) return 'Saúde';
        if (str_contains($d, 'aluguel') || str_contains($d, 'condominio') || str_contains($d, 'condomínio') || str_contains($d, 'energia') || str_contains($d, 'agua') || str_contains($d, 'luz') || str_contains($d, 'internet')) return 'Moradia';
        if (str_contains($d, 'cartao') || str_contains($d, 'cartão') || str_contains($d, 'fatura')) return 'Contas e Serviços';
        if (str_contains($d, 'aplicacao') || str_contains($d, 'aplicação') || str_contains($d, 'poupanca') || str_contains($d, 'poupança') || str_contains($d, 'rdb') || str_contains($d, 'cdb') || str_contains($d, 'investimento')) return 'Investimentos';
        if (str_contains($d, 'netflix') || str_contains($d, 'spotify') || str_contains($d, 'steam') || str_contains($d, 'cinema')) return 'Lazer';
        if (str_contains($d, 'escola') || str_contains($d, 'faculdade') || str_contains($d, 'curso') || str_contains($d, 'livro')) return 'Educação';
        if (str_contains($d, 'saque') || str_contains($d, 'retirado') || str_contains($d, 'dinheiro')) return 'Outros';
        if (str_contains($d, 'pix') || str_contains($d, 'transferencia') || str_contains($d, 'transferência')) return 'Outros';
        return 'Outros';
    }

    // =============================================
    // HELPERS
    // =============================================
    private function normalizar(string $content): string {
        $content = ltrim($content, "\xEF\xBB\xBF");
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    private function detectarSeparador(array $linhas): string {
        $sample = implode("\n", array_slice($linhas, 0, 5));
        $scores = ["\t" => substr_count($sample, "\t") * 3, ';' => substr_count($sample, ';'), ',' => substr_count($sample, ',')];
        arsort($scores);
        return array_key_first($scores);
    }

    private function parseDate(string $raw): string {
        $raw = trim($raw, " \t\"");
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $raw, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{2})$/', $raw, $m)) {
            $ano = (int)$m[3] >= 50 ? '19'.$m[3] : '20'.$m[3];
            return "{$ano}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) return $raw;
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) return "{$m[1]}-{$m[2]}-{$m[3]}";
        return '';
    }

    private function parseValorComSinal(string $raw): float {
        $raw = trim($raw, " \t\"");
        if ($raw === '' || $raw === '-') return 0.0;
        $negativo = str_starts_with($raw, '-');
        $v = preg_replace('/[^\d,.]/', '', $raw);
        if ($v === '') return 0.0;
        if (str_contains($v, ',')) { $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v); }
        $float = (float)$v;
        return $negativo ? -$float : $float;
    }

    private function parseValorAbs(string $raw): float {
        return abs($this->parseValorComSinal($raw));
    }

    public function detectFormat(string $content): string {
        $start = strtoupper(substr(ltrim($content, "\xEF\xBB\xBF"), 0, 300));
        return str_contains($start, 'OFXHEADER') || str_contains($start, '<OFX>')
            || str_contains($start, '<STMTTRN>') || str_contains($start, 'BANKMSGSRSV')
            ? 'ofx' : 'csv';
    }
}
