<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class AccountController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid      = $this->userId();
        $p        = $this->tablePrefix;
        $accounts = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE user_id=%d AND active=1 ORDER BY name", $uid
        ), ARRAY_A);
        $this->view('accounts/index', compact('accounts'), __('Contas Bancárias', 'first-financial-box'));
    }

    public function store(): void {
        $this->requireCap(); $this->verifyNonce();
        $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->insert("{$p}accounts", [
            'user_id' => $uid,
            'name'    => $this->sanitize($this->post('name')),
            'bank'    => $this->sanitize($this->post('bank')),
            'type'    => sanitize_key($this->post('type', 'checking')),
            'balance' => (float)str_replace(',', '.', $this->post('balance', '0')),
            'color'   => sanitize_hex_color($this->post('color', '#3B82F6')) ?? '#3B82F6',
        ]);
        $this->jsonSuccess(['id' => $this->db->insert_id], __('Conta criada!', 'first-financial-box'));
    }

    public function update(): void {
        $this->requireCap(); $this->verifyNonce();
        $id  = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $acc = $this->db->get_row($this->db->prepare("SELECT id FROM {$p}accounts WHERE id=%d AND user_id=%d", $id, $uid));
        if (!$acc) { $this->jsonError('Conta não encontrada.', 404); return; }
        $this->db->update("{$p}accounts", [
            'name'  => $this->sanitize($this->post('name')),
            'bank'  => $this->sanitize($this->post('bank')),
            'type'  => sanitize_key($this->post('type')),
            'color' => sanitize_hex_color($this->post('color')) ?? '#3B82F6',
        ], ['id' => $id]);
        $this->jsonSuccess([], __('Conta atualizada!', 'first-financial-box'));
    }

    public function destroy(): void {
        $this->requireCap(); $this->verifyNonce();
        $id = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->update("{$p}accounts", ['active' => 0], ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Conta desativada.', 'first-financial-box'));
    }

    public function fetch(): void {
        $this->requireCap(); $this->verifyNonce();
        $id  = (int)$this->query('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $acc = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$p}accounts WHERE id=%d AND user_id=%d", $id, $uid
        ), ARRAY_A);
        if (!$acc) { $this->jsonError('Não encontrada.', 404); return; }
        $this->jsonSuccess($acc);
    }
}
