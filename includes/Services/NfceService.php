<?php
// =============================================
// app/Services/NfceService.php
// Consulta NFC-e modelo 65 — SEFAZ-SP
//
// URL correta confirmada em 17/04/2026:
//   ConsultaQRCode.aspx?p={chave44}|3|1
//
// Seletores XPath confirmados no HTML real:
//   Nome:     td.txtTit (sem noWrap)
//   Qtde:     td.Rqtd
//   UN:       td.RUN
//   VlUnit:   td.RvlUnit
//   VlTotal:  td.txtTit.noWrap
//   Total NF: span#vPag
//   Emitente: #nomeEmitente
//   Chave:    span.chave
// =============================================

class NfceService {

    // URL de consulta pública confirmada e funcional
    // Parâmetro p = {chave44}|3|1  (tpAmb=3 é o correto para o portal público)
    private const URL_QRCODE = 'https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx';

    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private int $timeout = 20;

    // =============================================
    // Consulta por Chave de Acesso (44 dígitos)
    // O sufixo |3|1 é obrigatório para o portal público da SEFAZ-SP
    // =============================================
    public function consultarPorChave(string $chave): array {
        $chave = preg_replace('/\D/', '', $chave);

        if (strlen($chave) !== 44) {
            return $this->erro('Chave de acesso deve ter exatamente 44 dígitos numéricos.');
        }

        $modelo = substr($chave, 20, 2);
        if ($modelo !== '65') {
            return $this->erro("Modelo inválido ({$modelo}). Esta consulta aceita apenas NFC-e modelo 65.");
        }

        if (!$this->validarDigitoVerificador($chave)) {
            return $this->erro('Chave de acesso inválida: dígito verificador incorreto.');
        }

        // Parâmetro p = {chave}|3|1 — confirmado como correto para consulta pública SP
        $url  = self::URL_QRCODE . '?p=' . $chave . '|3|1';
        $html = $this->curlGet($url);

        if ($html === null) {
            return $this->erro(
                'Não foi possível conectar ao portal SEFAZ-SP. '
                . 'Verifique sua conexão ou tente novamente em instantes.'
            );
        }

        return $this->parsearHtml($html, $chave);
    }

    // =============================================
    // Consulta por URL completa do QR Code
    // Ex: ...ConsultaQRCode.aspx?p=3526...|3|1
    // =============================================
    public function consultarPorQrCode(string $qrCodeUrl): array {
        // Extrai o parâmetro p=
        $qs = parse_url($qrCodeUrl, PHP_URL_QUERY) ?? '';
        parse_str($qs, $params);
        $p = $params['p'] ?? '';

        // Se não veio como URL, trata como string bruta do QR
        if (!$p) {
            // Remove prefixo ?p= se presente
            $p = ltrim(str_replace('?p=', '', $qrCodeUrl), '?');
        }

        // Extrai os 44 dígitos da chave (antes do primeiro |)
        $partes = explode('|', $p);
        $chave  = preg_replace('/\D/', '', $partes[0] ?? '');

        if (strlen($chave) !== 44) {
            return $this->erro('QR Code inválido: não foi possível extrair chave de 44 dígitos.');
        }

        // Monta a URL com o parâmetro p completo
        // Garante que o sufixo seja sempre |3|1
        $sufixo = isset($partes[1]) ? implode('|', array_slice($partes, 1)) : '3|1';
        $url    = self::URL_QRCODE . '?p=' . urlencode($chave . '|' . $sufixo);
        $html   = $this->curlGet($url);

        if ($html === null) {
            // Fallback: tenta diretamente com sufixo padrão
            return $this->consultarPorChave($chave);
        }

        return $this->parsearHtml($html, $chave);
    }

    // =============================================
    // cURL — simula navegador Chrome para não ser bloqueado
    // =============================================
    private function curlGet(string $url): ?string {
        if (!function_exists('curl_init')) {
            return $this->fileGet($url);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_ENCODING       => '',   // aceita gzip automaticamente
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Cache-Control: max-age=0',
            ],
        ]);

        $html     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($html === false || $httpCode !== 200) {
            error_log("[NfceService] cURL falhou. URL={$url} HTTP={$httpCode} Error={$curlErr}");
            return null;
        }

        return $html ?: null;
    }

    private function fileGet(string $url): ?string {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => $this->timeout,
                'follow_location' => true,
                'user_agent'      => $this->userAgent,
                'header'          => implode("\r\n", [
                    'Accept: text/html',
                    'Accept-Language: pt-BR,pt;q=0.9',
                ]),
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $html = @file_get_contents($url, false, $ctx);
        return ($html !== false && $html !== '') ? $html : null;
    }

    // =============================================
    // Parser HTML — seletores XPath confirmados
    // com o HTML real do portal SEFAZ-SP
    // =============================================
    private function parsearHtml(string $html, string $chave): array {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // PHP 8.2+: mb_convert_encoding com HTML-ENTITIES é deprecated.
        // Usa charset meta tag + LIBXML flags para evitar warnings de encoding.
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        $xpath = new \DOMXPath($dom);

        $resultado = [
            'chave'        => $chave,
            'modelo'       => '65',
            'status'       => $this->xpathStatus($xpath),
            'emitente'     => $this->xpathEmitente($xpath),
            'data_emissao' => $this->xpathDataEmissao($xpath),
            'numero'       => $this->xpathNumeroSerie($xpath),
            'produtos'     => $this->xpathProdutos($xpath),
            'totais'       => $this->xpathTotais($xpath),
            'pagamentos'   => $this->xpathPagamentos($xpath),
            'raw_ok'       => false,
        ];

        $resultado['raw_ok'] = !empty($resultado['produtos'])
            || ($resultado['totais']['valor_pagar'] ?? 0) > 0
            || !empty($resultado['emitente']['nome']);

        return $resultado;
    }

    // =============================================
    // Extratores — mapeiam o HTML real da SEFAZ-SP
    // =============================================

    private function xpathStatus(\DOMXPath $x): string {
        // Se a página tem tabela com produtos, a nota está autorizada
        $temProdutos = $x->query('//*[contains(@class,"txtTit") and not(contains(@class,"noWrap"))]');
        if ($temProdutos->length > 0) {
            return 'Autorizado o Uso da NF-e';
        }
        // Tenta encontrar mensagem de erro/status
        foreach ($x->query('//*[contains(@class,"alert") or contains(@class,"msgSit") or contains(@id,"sit")]') as $n) {
            $txt = $this->clean($n->textContent);
            if ($txt) return $txt;
        }
        return 'Não encontrado';
    }

    private function xpathEmitente(\DOMXPath $x): array {
        $nome = '';
        $cnpj = '';

        // Razão social — id=nomeEmitente (confirmado no HTML real)
        $node = $x->query('//*[@id="nomeEmitente"]');
        if ($node->length > 0) {
            $nome = $this->clean($node->item(0)->textContent);
        }

        // Fallback: primeiro <p> ou <strong> com texto longo
        if (!$nome) {
            foreach ($x->query('//p[string-length(normalize-space(.)) > 5] | //strong[string-length(normalize-space(.)) > 5]') as $n) {
                $txt = $this->clean($n->textContent);
                if (strlen($txt) > 10 && !str_contains(strtoupper($txt), 'NFC-E') && !str_contains($txt, 'R$')) {
                    $nome = $txt;
                    break;
                }
            }
        }

        // CNPJ — regex no texto completo
        $full = $x->document->textContent ?? '';
        if (preg_match('/(\d{2}[\.\s]?\d{3}[\.\s]?\d{3}[\/\s]?\d{4}[\-\s]?\d{2})/', $full, $m)) {
            $n = preg_replace('/\D/', '', $m[1]);
            if (strlen($n) === 14) {
                $cnpj = substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2);
            }
        }

        return ['nome' => $nome, 'cnpj' => $cnpj];
    }

    private function xpathDataEmissao(\DOMXPath $x): string {
        // "Emissão: DD/MM/YYYY HH:MM:SS"
        $full = $x->document->textContent ?? '';
        if (preg_match('/Emiss[aã]o[:\s]*(\d{2}\/\d{2}\/\d{4})/u', $full, $m)) {
            $p = explode('/', $m[1]);
            return "{$p[2]}-{$p[1]}-{$p[0]}";
        }
        return '';
    }

    private function xpathNumeroSerie(\DOMXPath $x): array {
        $full = $x->document->textContent ?? '';
        $num = '';
        $ser = '';
        if (preg_match('/N[úu]mero[:\s]*(\d+)/ui', $full, $m)) $num = $m[1];
        if (preg_match('/S[ée]rie[:\s]*(\d+)/ui', $full, $m))   $ser = $m[1];
        return ['numero' => $num, 'serie' => $ser];
    }

    // =============================================
    // Produtos — seletores CONFIRMADOS no HTML real
    //
    // td.txtTit (sem noWrap) = nome do produto
    // td.Rqtd              = quantidade
    // td.RUN               = unidade de medida
    // td.RvlUnit           = valor unitário
    // td.txtTit.noWrap     = valor total do item
    // =============================================
    private function xpathProdutos(\DOMXPath $x): array {
        // Usa //* em vez de //td para funcionar independente da tag (span ou td)
        // conforme confirmado com o HTML real do portal SEFAZ-SP
        $nomes  = $x->query('//*[contains(@class,"txtTit") and not(contains(@class,"noWrap"))]');
        $qtds   = $x->query('//*[contains(@class,"Rqtd")]');
        $uns    = $x->query('//*[contains(@class,"RUN")]');
        $units  = $x->query('//*[contains(@class,"RvlUnit")]');
        $totais = $x->query('//*[contains(@class,"txtTit") and contains(@class,"noWrap")]');

        $count    = $nomes->length;
        $produtos = [];

        // Se count for 0, o XPath não encontrou elementos na estrutura atual
        if ($count === 0) {
            return [];
        }

        for ($i = 0; $i < $count; $i++) {
            $nome = $this->clean($nomes->item($i)->textContent ?? '');

            // Verificação de segurança: pula itens sem nome para evitar índices inexistentes
            if (!$nome) continue;

            $vlTotal   = (float)$this->parseBR($totais->item($i)?->textContent ?? '0');
            $vlUnit    = (float)$this->parseBR($units->item($i)?->textContent  ?? '0');
            $quantidade = (float)$this->parseBR($qtds->item($i)?->textContent  ?? '1');

            // Desconto por item: diferença entre (vlUnit * qtd) e vlTotal, quando positiva
            $totalCalculado = round($vlUnit * $quantidade, 2);
            $desconto = $totalCalculado > $vlTotal && $vlTotal > 0
                ? round($totalCalculado - $vlTotal, 2)
                : 0.0;

            $produtos[] = [
                'nome'           => $nome,
                'quantidade'     => $quantidade,
                'unidade'        => strtoupper($this->clean($uns->item($i)?->textContent ?? 'UN')),
                'valor_unitario' => $vlUnit,
                'valor_total'    => $vlTotal,
                'desconto'       => $desconto,
            ];
        }

        return $produtos;
    }

    private function xpathTotais(\DOMXPath $x): array {
        // span#vPag = valor a pagar (confirmado)
        $vPag = $x->query('//*[@id="vPag"]');
        $vPagVal = $vPag->length > 0 ? (float)$this->parseBR($vPag->item(0)->textContent) : 0.0;

        // span#Qt = quantidade total de itens
        $qt   = $x->query('//*[@id="Qt"]');
        $qtVal = $qt->length > 0 ? (int)trim($qt->item(0)->textContent) : 0;

        // Desconto — regex no texto
        $full = $x->document->textContent ?? '';
        $desc = 0.0;
        if (preg_match('/Desconto[:\s]*R?\$?\s*([\d.,]+)/i', $full, $m)) {
            $desc = (float)$this->parseBR($m[1]);
        }

        return [
            'qtd_itens'   => $qtVal,
            'valor_pagar' => $vPagVal,
            'desconto'    => $desc,
        ];
    }

    private function xpathPagamentos(\DOMXPath $x): array {
        $pagamentos = [];
        $full = $x->document->textContent ?? '';

        $formas = [
            'Dinheiro', 'Cartão de Crédito', 'Cartão de Débito', 'Pix',
            'Cheque', 'Vale Alimentação', 'Vale Refeição', 'Crédito Loja',
            'Boleto', 'Outros',
        ];

        foreach ($formas as $forma) {
            if (preg_match('/' . preg_quote($forma, '/') . '[^\d]*([\d.,]+)/i', $full, $m)) {
                $pagamentos[] = [
                    'forma' => $forma,
                    'valor' => (float)$this->parseBR($m[1]),
                ];
            }
        }

        return $pagamentos;
    }

    // =============================================
    // Helpers
    // =============================================

    private function clean(string $txt): string {
        return trim(preg_replace('/\s+/', ' ', $txt));
    }

    /**
     * Converte valor em formato BR (1.234,56 ou 67,90 ou 67.90) para float string
     */
    private function parseBR(string $txt): string {
        $v = $this->clean($txt);
        $v = preg_replace('/[^\d,.]/', '', $v);
        if ($v === '') return '0';

        // Se tem vírgula: é formato BR (67,90 → 67.90 | 1.234,56 → 1234.56)
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);   // remove separador de milhar
            $v = str_replace(',', '.', $v);  // vírgula decimal → ponto
        }

        return $v ?: '0';
    }

    // =============================================
    // Gera JSON estruturado com a chave como raiz
    //
    // Formato:
    // {
    //   "35260404...": {
    //     "valor_total": 67.90,
    //     "emitente": "KAKAZU...",
    //     "itens": [
    //       { "nome": "DUCHA...", "quantidade": 1, "valor_unitario": 67.90, "valor_total": 67.90 }
    //     ]
    //   }
    // }
    // =============================================
    public function paraJson(array $resultado): string {
        if (isset($resultado['error'])) {
            return json_encode(['error' => $resultado['message']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $payload = [$resultado['chave'] => $this->montarPayload($resultado)];
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Salva/acumula JSON em arquivo (append por chave — sem duplicatas)
     */
    public function salvarJson(array $resultado, string $arquivo): bool {
        $chave = $resultado['chave'] ?? '';
        if (!$chave) return false;

        $dados = [];
        if (file_exists($arquivo)) {
            $raw = file_get_contents($arquivo);
            if ($raw) $dados = json_decode($raw, true) ?? [];
        }

        $dados[$chave] = $this->montarPayload($resultado);

        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($arquivo, $json, LOCK_EX) !== false;
    }

    private function montarPayload(array $r): array {
        return [
            'valor_total'  => $r['totais']['valor_pagar']    ?? 0.0,
            'emitente'     => $r['emitente']['nome']          ?? null,
            'cnpj'         => $r['emitente']['cnpj']          ?? null,
            'data_emissao' => $r['data_emissao']               ?? null,
            'numero'       => $r['numero']['numero']           ?? null,
            'serie'        => $r['numero']['serie']            ?? null,
            'status'       => $r['status']                     ?? '',
            'salvo_em'     => date('Y-m-d H:i:s'),
            'itens'        => array_map(fn($p) => [
                'nome'           => $p['nome'],
                'quantidade'     => $p['quantidade'],
                'unidade'        => $p['unidade'],
                'valor_unitario' => $p['valor_unitario'],
                'valor_total'    => $p['valor_total'],
            ], $r['produtos'] ?? []),
        ];
    }

    // =============================================
    // Converte para array de transação do sistema
    // =============================================
    public function paraTransacao(array $r, int $userId, int $accountId, int $categoryId): array {
        return [
            'user_id'     => $userId,
            'account_id'  => $accountId,
            'category_id' => $categoryId,
            'type'        => 'expense',
            'description' => 'NFC-e: ' . ($r['emitente']['nome'] ?? 'Emitente desconhecido'),
            'amount'      => $r['totais']['valor_pagar'] ?? 0.01,
            'date'        => $r['data_emissao']           ?: date('Y-m-d'),
            'status'      => 'paid',
            'notes'       => 'NFC-e chave: ' . $r['chave'],
        ];
    }

    // =============================================
    // Validação do dígito verificador (módulo 11)
    // =============================================
    public function validarChave(string $chave): bool {
        $chave = preg_replace('/\D/', '', $chave);
        if (strlen($chave) !== 44) return false;
        if (substr($chave, 20, 2) !== '65') return false;
        return $this->validarDigitoVerificador($chave);
    }

    private function validarDigitoVerificador(string $chave): bool {
        $soma = 0;
        $mult = 2;
        for ($i = 42; $i >= 0; $i--) {
            $soma += (int)$chave[$i] * $mult;
            $mult  = $mult >= 9 ? 2 : $mult + 1;
        }
        $resto  = $soma % 11;
        $dvCalc = $resto < 2 ? 0 : 11 - $resto;
        return $dvCalc === (int)$chave[43];
    }

    // =============================================
    // Retorno de erro padronizado
    // =============================================
    private function erro(string $msg): array {
        return ['error' => true, 'message' => $msg, 'raw_ok' => false];
    }
}
