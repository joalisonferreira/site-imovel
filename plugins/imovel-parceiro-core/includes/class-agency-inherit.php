<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Toggle Gestão → Parcerias → Agência: permite corretores vinculados
 * (fave_agent_agency) herdarem o plano ativo da imobiliária.
 *
 * Desabilitado por padrão (nativamente off).
 */
class Imovel_Parceiro_Agency_Inherit {
    const OPTION_KEY = 'imovel_parceiro_agency_inherit_settings';

    public function __construct() {
        add_action( 'init', array( $this, 'handle_dashboard_request' ) );
    }

    public static function get_settings() {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $settings = wp_parse_args( $settings, array( 'enabled' => 0 ) );
        $settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;
        return $settings;
    }

    public static function is_enabled() {
        return 1 === (int) self::get_settings()['enabled'];
    }

    public function handle_dashboard_request() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }
        if ( ! isset( $_POST['imovel_agency_inherit_action'] ) ) {
            return;
        }
        $nonce = isset( $_POST['_imovel_agency_inherit_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_imovel_agency_inherit_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'imovel_agency_inherit_update' ) ) {
            wp_die( esc_html__( 'Operação não autorizada.', 'imovel-parceiro-core' ) );
        }
        if ( 'save' !== sanitize_key( wp_unslash( $_POST['imovel_agency_inherit_action'] ) ) ) {
            return;
        }
        $settings = self::get_settings();
        $settings['enabled'] = ! empty( $_POST['imovel_agency_inherit_enabled'] ) ? 1 : 0;
        update_option( self::OPTION_KEY, $settings, false );

        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/' );
        $redirect = add_query_arg(
            array(
                'imovel_admin_area'    => 'gestao',
                'imovel_admin_section' => 'pacote-agencia',
                'imovel_agency_inherit_notice'  => 'updated',
                'imovel_agency_inherit_message' => wp_strip_all_tags( __( 'Configuração salva.', 'imovel-parceiro-core' ) ),
            ),
            $dashboard_url
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function render_dashboard_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = self::get_settings();
        $notice_type = isset( $_GET['imovel_agency_inherit_notice'] ) ? sanitize_key( wp_unslash( $_GET['imovel_agency_inherit_notice'] ) ) : '';
        $notice_text = isset( $_GET['imovel_agency_inherit_message'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_agency_inherit_message'] ) ) : '';
        ?>
        <div class="rounded-2xl border border-slate-100 bg-white p-5 sm:p-6 shadow-sm">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                <div>
                    <h5 style="margin:0 0 4px;"><?php esc_html_e( 'Pacote da agência para corretores vinculados', 'imovel-parceiro-core' ); ?></h5>
                    <p style="margin:0; color:#64748b; font-size:14px;"><?php esc_html_e( 'Quando habilitado, corretores com vínculo fave_agent_agency podem solicitar parcerias, visitas, interações e contatos usando o plano ativo da imobiliária — sem precisar de plano próprio. Desabilitado por padrão.', 'imovel-parceiro-core' ); ?></p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold <?php echo self::is_enabled() ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'; ?>">
                    <?php echo self::is_enabled() ? esc_html__( 'Habilitado', 'imovel-parceiro-core' ) : esc_html__( 'Desabilitado', 'imovel-parceiro-core' ); ?>
                </span>
            </div>

            <?php if ( $notice_type && $notice_text ) : ?>
                <div style="margin-bottom:12px; padding:10px 12px; border-radius:8px; border:1px solid <?php echo 'updated' === $notice_type ? '#badbcc' : '#f5c2c7'; ?>; background:<?php echo 'updated' === $notice_type ? '#d1e7dd' : '#f8d7da'; ?>; color:<?php echo 'updated' === $notice_type ? '#0f5132' : '#842029'; ?>;">
                    <?php echo esc_html( $notice_text ); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'imovel_agency_inherit_update', '_imovel_agency_inherit_nonce' ); ?>
                <input type="hidden" name="imovel_agency_inherit_action" value="save" />

                <label style="display:flex; align-items:center; gap:10px; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; cursor:pointer; font-weight:600; color:#334155;">
                    <input type="checkbox" name="imovel_agency_inherit_enabled" value="1" <?php checked( (int) $settings['enabled'], 1 ); ?> style="accent-color:#6366f1; width:18px; height:18px;" />
                    <?php esc_html_e( 'Permitir que corretores vinculados usem o plano ativo da imobiliária', 'imovel-parceiro-core' ); ?>
                </label>
                <p style="margin:8px 0 14px; color:#64748b; font-size:13px;"><?php esc_html_e( 'Desligado: corretores vinculados precisam de plano próprio para solicitar parceria (comportamento nativo). Ligado: o sistema verifica primeiro o plano do corretor e, se não houver, usa o plano da imobiliária vinculada (fave_agent_agency) para parcerias, visitas, mensagens, e-mails e ligações/WhatsApp.', 'imovel-parceiro-core' ); ?></p>

                <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Salvar', 'imovel-parceiro-core' ); ?></button>
            </form>
        </div>
        <?php
    }
}

new Imovel_Parceiro_Agency_Inherit();
