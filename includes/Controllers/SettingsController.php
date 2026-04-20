<?php
namespace FFB\Controllers;

defined('ABSPATH') || exit;

// =============================================
class SettingsController extends Controller {

    public function index(): void {
        $this->requireCap(FFB_CAP_MANAGE);
        $settings = [
            'currency'        => get_option('ffb_currency', 'BRL'),
            'date_format'     => get_option('ffb_date_format', 'd/m/Y'),
            'nfce_enabled'    => get_option('ffb_nfce_enabled', '1'),
            'webmania_key'    => get_option('ffb_webmania_key', ''),
            'webmania_secret' => get_option('ffb_webmania_secret', ''),
            'allow_roles'     => get_option('ffb_allow_roles', ['administrator']),
        ];
        $roles = wp_roles()->get_names();
        $this->view('settings/index', compact('settings', 'roles'),
            __('Configurações', 'first-financial-box'));
    }

    public function save(): void {
        $this->requireCap(FFB_CAP_MANAGE);
        $this->verifyNonce();

        update_option('ffb_currency',        sanitize_text_field($this->post('currency', 'BRL')));
        update_option('ffb_date_format',     sanitize_text_field($this->post('date_format', 'd/m/Y')));
        update_option('ffb_nfce_enabled',    sanitize_text_field($this->post('nfce_enabled', '1')));
        update_option('ffb_webmania_key',    sanitize_text_field($this->post('webmania_key', '')));
        update_option('ffb_webmania_secret', sanitize_text_field($this->post('webmania_secret', '')));

        // Permissões por role
        $allowRoles = array_map('sanitize_key', (array)$this->post('allow_roles', []));
        update_option('ffb_allow_roles', $allowRoles);

        // Atualiza capabilities
        foreach (wp_roles()->role_objects as $roleKey => $role) {
            if (in_array($roleKey, $allowRoles)) {
                $role->add_cap(FFB_CAP_VIEW);
            } else {
                $role->remove_cap(FFB_CAP_VIEW);
            }
        }

        $this->jsonSuccess([], __('Configurações salvas!', 'first-financial-box'));
    }
}
