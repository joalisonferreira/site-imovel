<?php
/**
 * Plugin Name: Imóvel Parceiro Core
 * Description: Extensão integrada ao Houzez 4.3.5 para aceites obrigatórios, parcerias, oportunidades, negócios e comissões.
 * Version: 1.1.0
 * Author: Copilot
 * Requires at least: 6.0
 * Tested up to: 6.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'IMOVEL_PARCEIRO_CORE_VERSION' ) ) {
    define( 'IMOVEL_PARCEIRO_CORE_VERSION', '1.1.0' );
}
if ( ! defined( 'IMOVEL_PARCEIRO_CORE_DIR' ) ) {
    define( 'IMOVEL_PARCEIRO_CORE_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'IMOVEL_PARCEIRO_CORE_URL' ) ) {
    define( 'IMOVEL_PARCEIRO_CORE_URL', plugin_dir_url( __FILE__ ) );
}

class Imovel_Parceiro_Core {
    protected static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->includes();
        $this->hooks();
    }

    private function includes() {
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-email-template.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-acceptances.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-notifications.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-partnership-integrity.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-owner-workflow.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-property-duplicates.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-price-formatting.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-partnerships.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-partnership-workflow.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-watermark.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-property-visibility.php';
        $media_organizer = IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-property-media-organizer.php';
        if ( file_exists( $media_organizer ) ) {
            require_once $media_organizer;
        }
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-opportunities.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-deals.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-commissions.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-dashboard.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-security.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-contact-widget.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-subscriptions.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-houzez-woocommerce-subscriptions.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-user-fields.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-package-access.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-package-extras.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-checkout-cleanup.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-verification-notifications.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-verification-fix.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-subscription-links.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-purchase-redirect.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-user-blocking.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-admin-action-notifications.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-profile-guard.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-contact-visibility.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-instant-logout.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-first-login-redirect.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-upload-performance.php';
        require_once IMOVEL_PARCEIRO_CORE_DIR . 'includes/class-mailer.php';
    }

    private function hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_notification_assets' ) );
        add_filter( 'pre_wp_mail', array( $this, 'maybe_mock_mail_for_client' ), 10, 2 );
        add_filter( 'gettext', array( $this, 'fix_search_labels' ), 20, 3 );
    }

    public function fix_search_labels( $translated, $text, $domain ) {
        // Corrige truncamentos do Houzez pt_BR: Claro -> Limpar, Pesquisa -> Pesquisar
        if ( $translated === 'Claro' ) {
            return 'Limpar';
        }
        if ( $translated === 'Pesquisa' ) {
            return 'Pesquisar';
        }
        return $translated;
    }

    public function maybe_mock_mail_for_client( $return, $atts ) {
        // Em ambiente local sem SMTP, evita "Server Error" infinito para cliente (houzez_buyer)
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX && is_user_logged_in() ) {
            $uid = get_current_user_id();
            $is_client = false;
            if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $uid ) ) {
                $is_client = true;
            } else {
                $u = get_userdata( $uid );
                if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'administrator' ), (array) $u->roles ) ) {
                    if ( ! current_user_can( 'imovel_parceiro_manage_commercial' ) ) {
                        $is_client = true;
                    }
                }
            }
            if ( $is_client ) {
                // Simula sucesso para não deixar o form em loading eterno no localhost
                return true;
            }
        }
        return $return;
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'imovel-parceiro-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function enqueue_assets() {
        $is_dashboard = function_exists( 'houzez_is_dashboard' ) && houzez_is_dashboard();
        $is_property_page = is_singular( 'property' );
        $is_imovel_admin_dashboard = is_user_logged_in() && ( isset( $_GET['imovel_admin_area'] ) || isset( $_GET['imovel_admin_section'] ) || isset( $_GET['imovel_dashboard_area'] ) );

        if ( ! $is_dashboard && ! $is_property_page && ! $is_imovel_admin_dashboard ) {
            return;
        }

        $request_exists = false;
        $request_status = '';

        if ( $is_property_page && is_user_logged_in() && class_exists( 'Imovel_Parceiro_Partnerships' ) && Imovel_Parceiro_Partnerships::partnerships_table_exists() ) {
            global $wpdb;

            $property_id = (int) get_queried_object_id();
            $user_id = get_current_user_id();
            $table = Imovel_Parceiro_Partnerships::partnerships_table();
            $schema = Imovel_Parceiro_Partnerships::get_partnership_table_schema();
            $requester_col = $schema['requester_col'];

            $existing_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status FROM {$table} WHERE property_id = %d AND {$requester_col} = %d ORDER BY id DESC LIMIT 1",
                    $property_id,
                    $user_id
                )
            );

            if ( $existing_row ) {
                $request_exists = true;
                $request_status = sanitize_key( $existing_row->status );
            }
        }

        $style_version = file_exists( IMOVEL_PARCEIRO_CORE_DIR . 'assets/css/style.css' ) ? (string) filemtime( IMOVEL_PARCEIRO_CORE_DIR . 'assets/css/style.css' ) : IMOVEL_PARCEIRO_CORE_VERSION;
        $script_version = file_exists( IMOVEL_PARCEIRO_CORE_DIR . 'assets/js/app.js' ) ? (string) filemtime( IMOVEL_PARCEIRO_CORE_DIR . 'assets/js/app.js' ) : IMOVEL_PARCEIRO_CORE_VERSION;

        $is_client = false;
        if ( is_user_logged_in() ) {
            $uid = get_current_user_id();
            if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $uid ) ) {
                $is_client = true;
            } else {
                $u = get_userdata( $uid );
                if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'administrator' ), (array) $u->roles ) && ! current_user_can( 'imovel_parceiro_manage_commercial' ) ) {
                    $is_client = true;
                }
            }
        }

        wp_enqueue_style( 'imovel-parceiro-core', IMOVEL_PARCEIRO_CORE_URL . 'assets/css/style.css', array(), $style_version );
        wp_enqueue_script( 'imovel-parceiro-core', IMOVEL_PARCEIRO_CORE_URL . 'assets/js/app.js', array( 'jquery' ), $script_version, true );
        wp_localize_script( 'imovel-parceiro-core', 'imovelParceiroCore', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'imovel_parceiro_core_nonce' ),
            'terms_version' => get_option( 'imovel_parceiro_terms_version', '1.0' ),
            'request_exists' => $request_exists,
            'request_status' => $request_status,
            'is_rental_property' => false,
            'is_client' => $is_client,
            'partnerships_url' => class_exists( 'Imovel_Parceiro_Partnerships' ) ? Imovel_Parceiro_Partnerships::dashboard_partnerships_url() : '',
            'messages' => array(
                'acceptances_required' => __( 'E obrigatorio aceitar todos os termos para publicar ou editar este imovel.', 'imovel-parceiro-core' ),
                'partnership_terms_required' => __( 'Voce deve aceitar os termos da parceria para enviar a solicitacao.', 'imovel-parceiro-core' ),
                'request_exists' => __( 'Voce ja enviou uma solicitacao para este imovel e nao pode pedir novamente.', 'imovel-parceiro-core' ),
            ),
        ) );

        if ( $is_property_page ) {
            $is_rental_property = Imovel_Parceiro_Price_Formatting::is_rental_property( get_queried_object_id() );
            wp_add_inline_script(
                'imovel-parceiro-core',
                'window.imovelParceiroCore = window.imovelParceiroCore || {}; window.imovelParceiroCore.is_rental_property = ' . ( $is_rental_property ? 'true' : 'false' ) . ';',
                'before'
            );
        }
    }

    public function enqueue_notification_assets() {
        if ( ! is_user_logged_in() || ! class_exists( 'IPC_Notifications' ) ) {
            return;
        }

        wp_enqueue_style( 'imovel-parceiro-notifications', IMOVEL_PARCEIRO_CORE_URL . 'assets/css/notifications.css', array(), IMOVEL_PARCEIRO_CORE_VERSION );
        wp_enqueue_script( 'imovel-parceiro-notifications', IMOVEL_PARCEIRO_CORE_URL . 'assets/js/notifications.js', array( 'jquery' ), IMOVEL_PARCEIRO_CORE_VERSION, true );

        wp_localize_script(
            'imovel-parceiro-notifications',
            'ipcNotifications',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'imovel_parceiro_notifications_nonce' ),
                'dashboard_url' => IPC_Notifications::get_dashboard_url(),
                'notifications_url' => IPC_Notifications::get_dashboard_url( array( 'imovel_dashboard_area' => 'notificacoes' ) ),
                'service_worker_url' => IMOVEL_PARCEIRO_CORE_URL . 'assets/js/ipc-notifications-sw.js',
                'vapid_public_key' => get_option( 'imovel_parceiro_notifications_vapid_public_key', '' ),
                'strings' => array(
                    'notifications' => __( 'Notificações', 'imovel-parceiro-core' ),
                    'mark_all_read' => __( 'Marcar todas como lidas', 'imovel-parceiro-core' ),
                    'view_all' => __( 'Ver todas as notificações', 'imovel-parceiro-core' ),
                    'enable_push' => __( 'Ativar notificações do navegador', 'imovel-parceiro-core' ),
                ),
            )
        );
    }

    public function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            'imovel_parceiro_acceptances' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_acceptances (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                property_id bigint(20) unsigned NOT NULL,
                terms_version varchar(50) NOT NULL,
                accepted_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                ip_address varchar(45) DEFAULT '',
                user_agent text DEFAULT '',
                status varchar(20) NOT NULL DEFAULT 'accepted',
                acceptance_data longtext DEFAULT '',
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY property_id (property_id)
            ) $charset_collate;",
            'imovel_parceiro_partnerships' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_partnerships (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                requester_id bigint(20) unsigned NOT NULL,
                owner_id bigint(20) unsigned NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'pending',
                commission_split varchar(20) NOT NULL DEFAULT '50/50',
                terms_version varchar(50) NOT NULL,
                requested_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                responded_at datetime DEFAULT NULL,
                notes text DEFAULT '',
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY requester_id (requester_id),
                KEY owner_id (owner_id)
            ) $charset_collate;",
            'imovel_parceiro_opportunities' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_opportunities (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
                client_name varchar(255) DEFAULT '',
                owner_id bigint(20) unsigned NOT NULL,
                partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
                funnel_stage varchar(50) NOT NULL DEFAULT 'new_lead',
                origin varchar(30) NOT NULL DEFAULT 'direct',
                status varchar(30) NOT NULL DEFAULT 'open',
                next_action varchar(255) DEFAULT '',
                due_date datetime DEFAULT NULL,
                last_contact_date datetime DEFAULT NULL,
                notes longtext DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY partnership_id (partnership_id),
                KEY lead_id (lead_id)
            ) $charset_collate;",
            'imovel_parceiro_deals' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_deals (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                opportunity_id bigint(20) unsigned NOT NULL,
                partnership_id bigint(20) unsigned NOT NULL,
                property_id bigint(20) unsigned NOT NULL,
                lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
                owner_id bigint(20) unsigned NOT NULL,
                partner_id bigint(20) unsigned NOT NULL DEFAULT 0,
                status varchar(30) NOT NULL DEFAULT 'open',
                origin varchar(30) NOT NULL DEFAULT 'direct',
                total_commission decimal(12,2) NOT NULL DEFAULT 0.00,
                closed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                PRIMARY KEY  (id),
                KEY opportunity_id (opportunity_id),
                KEY partnership_id (partnership_id),
                KEY property_id (property_id)
            ) $charset_collate;",
            'imovel_parceiro_commissions' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_commissions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                deal_id bigint(20) unsigned NOT NULL,
                partnership_id bigint(20) unsigned NOT NULL,
                beneficiary_id bigint(20) unsigned NOT NULL,
                beneficiary_role varchar(30) NOT NULL DEFAULT 'owner',
                percentage decimal(5,2) NOT NULL DEFAULT 0.00,
                amount decimal(12,2) NOT NULL DEFAULT 0.00,
                status varchar(30) NOT NULL DEFAULT 'pending',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00',
                paid_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY deal_id (deal_id),
                KEY partnership_id (partnership_id),
                KEY beneficiary_id (beneficiary_id)
            ) $charset_collate;",
            'imovel_parceiro_owner_relations' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_owner_relations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                owner_user_id bigint(20) unsigned NOT NULL,
                broker_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                workflow_status varchar(40) NOT NULL DEFAULT 'RASCUNHO',
                documentation_status varchar(30) NOT NULL DEFAULT 'pendente',
                approval_status varchar(30) NOT NULL DEFAULT 'pendente',
                approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
                approved_at datetime DEFAULT NULL,
                rejected_reason text DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                UNIQUE KEY property_id (property_id),
                KEY owner_user_id (owner_user_id),
                KEY broker_user_id (broker_user_id),
                KEY workflow_status (workflow_status)
            ) $charset_collate;",
            'imovel_parceiro_owner_documents' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_owner_documents (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                owner_user_id bigint(20) unsigned NOT NULL,
                doc_type varchar(60) NOT NULL,
                attachment_id bigint(20) unsigned NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'enviado',
                submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
                reviewed_at datetime DEFAULT NULL,
                review_note text DEFAULT '',
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY owner_user_id (owner_user_id),
                KEY status (status)
            ) $charset_collate;",
            'imovel_parceiro_broker_change_requests' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_broker_change_requests (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                owner_user_id bigint(20) unsigned NOT NULL,
                current_broker_id bigint(20) unsigned NOT NULL DEFAULT 0,
                requested_broker_id bigint(20) unsigned NOT NULL DEFAULT 0,
                reason text DEFAULT '',
                status varchar(30) NOT NULL DEFAULT 'pendente',
                admin_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                admin_note text DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY owner_user_id (owner_user_id),
                KEY status (status)
            ) $charset_collate;",
            'imovel_parceiro_notifications' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_notifications (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                property_id bigint(20) unsigned NOT NULL DEFAULT 0,
                partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
                type varchar(80) NOT NULL,
                category varchar(30) NOT NULL DEFAULT 'sistema',
                title varchar(255) NOT NULL,
                message text DEFAULT '',
                url text DEFAULT '',
                priority varchar(20) NOT NULL DEFAULT 'normal',
                is_read tinyint(1) NOT NULL DEFAULT 0,
                dashboard_sent tinyint(1) NOT NULL DEFAULT 1,
                browser_sent tinyint(1) NOT NULL DEFAULT 0,
                email_sent tinyint(1) NOT NULL DEFAULT 0,
                meta longtext DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                read_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY is_read (is_read),
                KEY created_at (created_at),
                KEY user_id_is_read (user_id, is_read),
                KEY property_id (property_id),
                KEY partnership_id (partnership_id)
            ) $charset_collate;",
            'imovel_parceiro_notification_subscriptions' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_notification_subscriptions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                endpoint_hash varchar(64) NOT NULL,
                endpoint longtext NOT NULL,
                p256dh text DEFAULT '',
                auth_secret text DEFAULT '',
                user_agent text DEFAULT '',
                device_label varchar(120) DEFAULT '',
                last_seen_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                UNIQUE KEY endpoint_hash (endpoint_hash),
                KEY user_id (user_id)
            ) $charset_collate;",
            'imovel_parceiro_deletion_requests' => "CREATE TABLE {$wpdb->prefix}imovel_parceiro_deletion_requests (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                property_id bigint(20) unsigned NOT NULL,
                owner_user_id bigint(20) unsigned NOT NULL,
                reason text DEFAULT '',
                has_commercial_history tinyint(1) NOT NULL DEFAULT 0,
                status varchar(30) NOT NULL DEFAULT 'pendente',
                admin_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                admin_note text DEFAULT '',
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY property_id (property_id),
                KEY owner_user_id (owner_user_id),
                KEY status (status)
            ) $charset_collate;"
        );

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    public function deactivate() {
        if ( class_exists( 'Imovel_Parceiro_Property_Visibility' ) ) {
            Imovel_Parceiro_Property_Visibility::clear_cron();
        }

        // Keep tables for persistence.
    }
}

Imovel_Parceiro_Core::instance();

