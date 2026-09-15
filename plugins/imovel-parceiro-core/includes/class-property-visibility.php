<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Imovel_Parceiro_Property_Visibility {
    const CRON_HOOK = 'imovel_parceiro_check_stale_properties';
    const OPTION_KEY = 'imovel_parceiro_property_visibility_settings';
    const META_LAST_OWNER_UPDATE = '_imovel_parceiro_last_owner_update_ts';
    const META_WARNING_SENT_AT = '_imovel_parceiro_stale_warning_sent_at';
    const META_AUTO_DEACTIVATED_AT = '_imovel_parceiro_auto_deactivated_at';
    const META_STATUS_BEFORE_DEACTIVATION = '_imovel_parceiro_status_before_deactivation';

    private $warning_days = 25;
    private $deactivation_days = 30;

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_schedule_cron' ) );
        add_action( 'init', array( $this, 'handle_dashboard_request' ) );
        add_action( self::CRON_HOOK, array( $this, 'run_daily_check' ) );

        add_action( 'houzez_after_property_submit', array( $this, 'mark_owner_update' ), 10, 2 );
        add_action( 'houzez_after_property_update', array( $this, 'mark_owner_update' ), 10, 2 );
        add_action( 'post_updated', array( $this, 'capture_owner_update_on_post_update' ), 10, 3 );
    }

    public static function get_settings() {
        $defaults = array(
            'enabled' => 1,
            'warning_days' => 25,
            'deactivation_days' => 30,
        );

        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        $settings = wp_parse_args( $settings, $defaults );
        $settings['enabled'] = ! empty( $settings['enabled'] ) ? 1 : 0;
        $settings['warning_days'] = max( 1, min( 365, absint( $settings['warning_days'] ) ) );
        $settings['deactivation_days'] = max( 2, min( 366, absint( $settings['deactivation_days'] ) ) );

        if ( $settings['warning_days'] >= $settings['deactivation_days'] ) {
            $settings['warning_days'] = max( 1, $settings['deactivation_days'] - 1 );
        }

        return $settings;
    }

    public function handle_dashboard_request() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( ! isset( $_POST['imovel_visibility_action'] ) ) {
            return;
        }

        $nonce = isset( $_POST['_imovel_visibility_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_imovel_visibility_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'imovel_visibility_update' ) ) {
            wp_die( esc_html__( 'Operação não autorizada.', 'imovel-parceiro-core' ) );
        }

        $action = sanitize_key( wp_unslash( $_POST['imovel_visibility_action'] ) );
        if ( 'save' !== $action ) {
            return;
        }

        $settings = self::get_settings();
        $settings['enabled'] = ! empty( $_POST['imovel_visibility_enabled'] ) ? 1 : 0;
        $settings['warning_days'] = isset( $_POST['imovel_visibility_warning_days'] ) ? max( 1, min( 365, absint( wp_unslash( $_POST['imovel_visibility_warning_days'] ) ) ) ) : 25;
        $settings['deactivation_days'] = isset( $_POST['imovel_visibility_deactivation_days'] ) ? max( 2, min( 366, absint( wp_unslash( $_POST['imovel_visibility_deactivation_days'] ) ) ) ) : 30;

        if ( $settings['warning_days'] >= $settings['deactivation_days'] ) {
            $settings['warning_days'] = max( 1, $settings['deactivation_days'] - 1 );
        }

        update_option( self::OPTION_KEY, $settings, false );
        $this->redirect_with_notice( 'updated', __( 'Configuração de inatividade salva.', 'imovel-parceiro-core' ) );
    }

    public static function render_dashboard_section() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();
        $notice_type = isset( $_GET['imovel_visibility_notice'] ) ? sanitize_key( wp_unslash( $_GET['imovel_visibility_notice'] ) ) : '';
        $notice_text = isset( $_GET['imovel_visibility_message'] ) ? sanitize_text_field( wp_unslash( $_GET['imovel_visibility_message'] ) ) : '';
        ?>
        <div class="imovel-parceiro-admin-section" style="padding:16px; border:1px solid #e6e6e6; border-radius:10px; background:#fff; margin-top:16px;">
            <h4 style="margin:0 0 10px;"><?php esc_html_e( 'Regra de inatividade de imóvel', 'imovel-parceiro-core' ); ?></h4>
            <p style="margin:0 0 14px; color:#666;"><?php esc_html_e( 'Desativa automaticamente imóveis sem atualização do corretor dono e envia aviso prévio por e-mail.', 'imovel-parceiro-core' ); ?></p>

            <?php if ( $notice_type && $notice_text ) : ?>
                <div style="margin-bottom:12px; padding:10px 12px; border-radius:8px; border:1px solid <?php echo 'updated' === $notice_type ? '#badbcc' : '#f5c2c7'; ?>; background:<?php echo 'updated' === $notice_type ? '#d1e7dd' : '#f8d7da'; ?>; color:<?php echo 'updated' === $notice_type ? '#0f5132' : '#842029'; ?>;">
                    <?php echo esc_html( $notice_text ); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'imovel_visibility_update', '_imovel_visibility_nonce' ); ?>
                <input type="hidden" name="imovel_visibility_action" value="save" />

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Ativar regra', 'imovel-parceiro-core' ); ?></label>
                        <label class="control control--checkbox" style="font-weight:400;">
                            <input type="checkbox" name="imovel_visibility_enabled" value="1" <?php checked( (int) $settings['enabled'], 1 ); ?> />
                            <?php esc_html_e( 'Aplicar monitoramento diário de inatividade', 'imovel-parceiro-core' ); ?>
                            <span class="control__indicator"></span>
                        </label>
                    </div>

                    <div>
                        <label for="imovel_visibility_warning_days" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Aviso prévio (dias)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_visibility_warning_days" name="imovel_visibility_warning_days" min="1" max="365" value="<?php echo esc_attr( $settings['warning_days'] ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>

                    <div>
                        <label for="imovel_visibility_deactivation_days" style="display:block; margin-bottom:6px; font-weight:600;"><?php esc_html_e( 'Desativação automática (dias)', 'imovel-parceiro-core' ); ?></label>
                        <input type="number" id="imovel_visibility_deactivation_days" name="imovel_visibility_deactivation_days" min="2" max="366" value="<?php echo esc_attr( $settings['deactivation_days'] ); ?>" style="width:100%; padding:10px; border:1px solid #d9d9d9; border-radius:6px;" />
                    </div>
                </div>

                <p style="margin:0 0 12px; color:#666; font-size:13px;"><?php esc_html_e( 'O aviso será enviado quando faltarem os dias configurados para a desativação. Exemplo: aviso em 25 e desativação em 30.', 'imovel-parceiro-core' ); ?></p>

                <button type="submit" class="btn btn-primary"><?php esc_html_e( 'Salvar configuração', 'imovel-parceiro-core' ); ?></button>
            </form>
        </div>
        <?php
    }

    public static function clear_cron() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public function maybe_schedule_cron() {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        wp_schedule_event( time(), 'daily', self::CRON_HOOK );
    }

    public function mark_owner_update( $property_id, $user_id = 0 ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return;
        }

        $owner_id = (int) get_post_field( 'post_author', $property_id );
        $broker_id = (int) get_post_meta( $property_id, Imovel_Parceiro_Owner_Workflow::META_BROKER_ID, true );
        if ( $owner_id <= 0 ) {
            return;
        }

        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( $user_id && (int) $user_id !== $owner_id && (int) $user_id !== $broker_id ) {
            return;
        }

        $now = current_time( 'timestamp' );
        update_post_meta( $property_id, self::META_LAST_OWNER_UPDATE, $now );
        delete_post_meta( $property_id, self::META_WARNING_SENT_AT );
    }

    public function capture_owner_update_on_post_update( $post_id, $post_after, $post_before ) {
        if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
            return;
        }

        if ( 'property' !== $post_after->post_type ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            return;
        }

        $broker_id = (int) get_post_meta( $post_id, Imovel_Parceiro_Owner_Workflow::META_BROKER_ID, true );
        if ( (int) $post_after->post_author !== (int) $current_user_id && $broker_id !== (int) $current_user_id ) {
            return;
        }

        $this->mark_owner_update( $post_id, $current_user_id );
        if ( get_post_meta( $post_id, self::META_AUTO_DEACTIVATED_AT, true ) && 'draft' === get_post_status( $post_id ) ) {
            wp_update_post( array( 'ID' => $post_id, 'post_status' => get_post_meta( $post_id, self::META_STATUS_BEFORE_DEACTIVATION, true ) ?: 'publish' ) );
            delete_post_meta( $post_id, self::META_AUTO_DEACTIVATED_AT );
            delete_post_meta( $post_id, self::META_STATUS_BEFORE_DEACTIVATION );
        }
    }

    public function run_daily_check() {
        $settings = self::get_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        $warning_days = (int) $settings['warning_days'];
        $deactivation_days = (int) $settings['deactivation_days'];

        $query_args = array(
            'post_type' => 'property',
            'post_status' => array( 'publish' ),
            'posts_per_page' => 200,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false,
            'paged' => 1,
        );

        do {
            $query = new WP_Query( $query_args );
            $property_ids = $query->posts;

            if ( empty( $property_ids ) ) {
                break;
            }

            foreach ( $property_ids as $property_id ) {
                $this->evaluate_property_staleness( (int) $property_id, $warning_days, $deactivation_days );
            }

            $query_args['paged']++;
            wp_reset_postdata();
        } while ( $query_args['paged'] <= (int) $query->max_num_pages );
    }

    private function evaluate_property_staleness( $property_id, $warning_days, $deactivation_days ) {
        $owner_id = (int) get_post_field( 'post_author', $property_id );
        if ( $owner_id <= 0 ) {
            return;
        }

        $last_update = $this->get_last_owner_update_timestamp( $property_id );
        if ( $last_update <= 0 ) {
            return;
        }

        $now = current_time( 'timestamp' );
        if ( $last_update > $now ) {
            return;
        }

        $days_inactive = (int) floor( ( $now - $last_update ) / DAY_IN_SECONDS );

        if ( $days_inactive >= $deactivation_days ) {
            $this->deactivate_property( $property_id, $owner_id );
            return;
        }

        if ( $days_inactive < $warning_days ) {
            return;
        }

        $warning_sent_at = (int) get_post_meta( $property_id, self::META_WARNING_SENT_AT, true );
        if ( $warning_sent_at > 0 && $warning_sent_at >= $last_update ) {
            return;
        }

        $days_remaining = max( 1, $deactivation_days - $days_inactive );
        if ( $this->send_warning_email( $property_id, $owner_id, $days_remaining, $deactivation_days ) ) {
            update_post_meta( $property_id, self::META_WARNING_SENT_AT, $now );
        }
    }

    private function get_last_owner_update_timestamp( $property_id ) {
        $stored = (int) get_post_meta( $property_id, self::META_LAST_OWNER_UPDATE, true );
        if ( $stored > 0 ) {
            return $stored;
        }

        $modified_gmt = get_post_field( 'post_modified_gmt', $property_id );
        if ( ! empty( $modified_gmt ) && '0000-00-00 00:00:00' !== $modified_gmt ) {
            $modified_ts = strtotime( $modified_gmt . ' GMT' );
            if ( $modified_ts ) {
                return $modified_ts;
            }
        }

        $date_gmt = get_post_field( 'post_date_gmt', $property_id );
        if ( ! empty( $date_gmt ) && '0000-00-00 00:00:00' !== $date_gmt ) {
            $date_ts = strtotime( $date_gmt . ' GMT' );
            if ( $date_ts ) {
                return $date_ts;
            }
        }

        return 0;
    }

    private function deactivate_property( $property_id, $owner_id ) {
        if ( 'publish' !== get_post_status( $property_id ) ) {
            return;
        }

        $updated = wp_update_post(
            array(
                'ID' => $property_id,
                'post_status' => 'draft',
            ),
            true
        );

        if ( is_wp_error( $updated ) ) {
            return;
        }

        update_post_meta( $property_id, self::META_STATUS_BEFORE_DEACTIVATION, 'publish' );
        update_post_meta( $property_id, self::META_AUTO_DEACTIVATED_AT, current_time( 'timestamp' ) );

        $this->send_deactivation_email( $property_id, $owner_id );
    }

    private function send_warning_email( $property_id, $owner_id, $days_remaining, $deactivation_days ) {
        $user = get_userdata( $owner_id );
        if ( ! $user || empty( $user->user_email ) ) {
            return false;
        }

        $listing_title = get_the_title( $property_id );
        if ( empty( $listing_title ) ) {
            $listing_title = 'Imovel #' . $property_id;
        }

        $listing_url = get_permalink( $property_id );
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) : home_url( '/' );

        $subject = __( 'Seu imóvel precisa ser atualizado', 'imovel-parceiro-core' );
        $message = sprintf(
            __( "Olá,\n\nSeu imóvel \"%1$s\" está há %2$d dias sem atualização.\nAtualize o anúncio em até %3$d dias para evitar a desativação automática de visualização.\n\nEditar imóvel: %4$s\nPainel: %5$s", 'imovel-parceiro-core' ),
            $listing_title,
            (int) ( $deactivation_days - $days_remaining ),
            (int) $days_remaining,
            $listing_url,
            $dashboard_url
        );

        $subject = apply_filters( 'imovel_parceiro_stale_warning_subject', $subject, $property_id, $owner_id, $days_remaining );
        $message = apply_filters( 'imovel_parceiro_stale_warning_message', $message, $property_id, $owner_id, $days_remaining );

        return $this->send_email_using_houzez_config( $user->user_email, $subject, $message );
    }

    private function send_deactivation_email( $property_id, $owner_id ) {
        $user = get_userdata( $owner_id );
        if ( ! $user || empty( $user->user_email ) ) {
            return;
        }

        $args = array(
            'listing_title' => get_the_title( $property_id ),
            'listing_url' => get_permalink( $property_id ),
            'listing_id' => $property_id,
            'expired_listing_name' => get_the_title( $property_id ),
            'expired_listing_url' => get_permalink( $property_id ),
        );

        if ( function_exists( 'houzez_email_type' ) ) {
            houzez_email_type( $user->user_email, 'listing_expired', $args );
            return;
        }

        $subject = __( 'Seu imóvel foi desativado automaticamente', 'imovel-parceiro-core' );
        $message = sprintf(
            __( "Olá,\n\nO imóvel \"%1$s\" foi desativado automaticamente após 30 dias sem atualização do corretor responsável.\nAcesse seu painel para atualizar e republicar o anúncio.\n\nPainel: %2$s", 'imovel-parceiro-core' ),
            get_the_title( $property_id ),
            function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard_properties.php' ) : home_url( '/' )
        );

        $this->send_email_using_houzez_config( $user->user_email, $subject, $message );
    }

    private function send_email_using_houzez_config( $to, $subject, $message ) {
        if ( function_exists( 'houzez_send_emails' ) ) {
            houzez_send_emails( $to, $subject, $message );
            return true;
        }

        return (bool) wp_mail( $to, $subject, $message );
    }

    private function redirect_with_notice( $type, $message ) {
        $dashboard_url = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/' );

        $redirect = add_query_arg(
            array(
                'imovel_admin_area' => 'gestao',
                'imovel_admin_section' => 'inatividade',
                'imovel_visibility_notice' => sanitize_key( $type ),
                'imovel_visibility_message' => wp_strip_all_tags( $message ),
            ),
            $dashboard_url
        );

        wp_safe_redirect( $redirect );
        exit;
    }
}

new Imovel_Parceiro_Property_Visibility();
