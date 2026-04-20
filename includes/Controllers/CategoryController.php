<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class CategoryController extends Controller {

    public function index(): void {
        $this->requireCap();
        $uid = $this->userId(); $p = $this->tablePrefix;
        $categories = $this->db->get_results($this->db->prepare(
            "SELECT c.*, COUNT(t.id) AS tx_count
             FROM {$p}categories c
             LEFT JOIN {$p}transactions t ON t.category_id=c.id AND t.deleted_at IS NULL
             WHERE c.user_id=%d AND c.active=1
             GROUP BY c.id ORDER BY c.type, c.name", $uid
        ), ARRAY_A);
        $this->view('categories/index', compact('categories'), __('Categorias', 'first-financial-box'));
    }

    public function store(): void {
        $this->requireCap(); $this->verifyNonce();
        $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->insert("{$p}categories", [
            'user_id' => $uid,
            'name'    => $this->sanitize($this->post('name')),
            'type'    => sanitize_key($this->post('type')),
            'color'   => sanitize_hex_color($this->post('color', '#6B7280')) ?? '#6B7280',
            'icon'    => $this->sanitize($this->post('icon', 'tag')),
        ]);
        $this->jsonSuccess(['id' => $this->db->insert_id], __('Categoria criada!', 'first-financial-box'));
    }

    public function update(): void {
        $this->requireCap(); $this->verifyNonce();
        $id = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $this->db->update("{$p}categories", [
            'name'  => $this->sanitize($this->post('name')),
            'type'  => sanitize_key($this->post('type')),
            'color' => sanitize_hex_color($this->post('color')) ?? '#6B7280',
            'icon'  => $this->sanitize($this->post('icon', 'tag')),
        ], ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Categoria atualizada!', 'first-financial-box'));
    }

    public function destroy(): void {
        $this->requireCap(); $this->verifyNonce();
        $id  = (int)$this->post('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $uso = (int)$this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$p}transactions WHERE category_id=%d AND deleted_at IS NULL", $id
        ));
        if ($uso > 0) {
            $this->jsonError(sprintf(__('Categoria em uso por %d transação(ões).', 'first-financial-box'), $uso), 400);
            return;
        }
        $this->db->update("{$p}categories", ['active' => 0], ['id' => $id, 'user_id' => $uid]);
        $this->jsonSuccess([], __('Categoria excluída.', 'first-financial-box'));
    }

    public function fetch(): void {
        $this->requireCap(); $this->verifyNonce();
        $id  = (int)$this->query('id'); $uid = $this->userId(); $p = $this->tablePrefix;
        $cat = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$p}categories WHERE id=%d AND user_id=%d", $id, $uid
        ), ARRAY_A);
        if (!$cat) { $this->jsonError('Não encontrada.', 404); return; }
        $this->jsonSuccess($cat);
    }
}
