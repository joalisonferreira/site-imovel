<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imovel_Parceiro_Contact_Widget
 *
 * Floating "Falar com o corretor" widget on the frontend. Two distinct goals:
 *   1. CONTACT  -> WhatsApp of the broker responsible for the property.
 *   2. BUSINESS -> request / track a partnership for the property.
 *
 * The WhatsApp number is server-side gated by the existing contact-release
 * rule and is never printed in the HTML unless it is released for the viewer.
 * The partnership request reuses the canonical imovel_parceiro_request_partnership
 * AJAX (no parallel system). Nothing here modifies Houzez or theme files.
 */
class Imovel_Parceiro_Contact_Widget {
    const NONCE = 'imovel_parceiro_core_nonce';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
        add_action( 'wp_footer', array( $this, 'render' ), 30 );

        add_action( 'wp_ajax_imovel_parceiro_widget_context', array( $this, 'ajax_context' ) );
        add_action( 'wp_ajax_nopriv_imovel_parceiro_widget_context', array( $this, 'ajax_context' ) );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    /* ---------------------------------------------------------------------
     * Context detection
     * ------------------------------------------------------------------ */

    private function property_base() {
        $sample = get_posts( array( 'post_type' => 'property', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
        if ( ! empty( $sample ) ) {
            $link = get_permalink( $sample[0] );
            $path = $link ? (string) parse_url( $link, PHP_URL_PATH ) : '';
            $segments = array_values( array_filter( explode( '/', $path ) ) );
            if ( ! empty( $segments[0] ) ) {
                return '/' . $segments[0] . '/';
            }
        }
        return '/property/';
    }

    private function is_single_property() {
        return is_singular( 'property' );
    }

    private function is_listing_context() {
        if ( is_post_type_archive( 'property' ) || is_author() ) {
            return true;
        }
        if ( is_tax() ) {
            $tax = get_queried_object();
            if ( $tax && ! empty( $tax->taxonomy ) && false !== strpos( $tax->taxonomy, 'property' ) ) {
                return true;
            }
        }
        if ( is_home() ) {
            // Houzez archive front page for properties; fallback only when single is false.
            return false;
        }
        return false;
    }

    private function should_render() {
        // Never render inside the Houzez dashboard.
        if ( function_exists( 'houzez_is_dashboard' ) && houzez_is_dashboard() ) {
            return false;
        }
        if ( isset( $_GET['imovel_dashboard_area'] ) || isset( $_GET['imovel_admin_area'] ) || isset( $_GET['imovel_admin_section'] ) ) {
            return false;
        }

        return $this->is_single_property() || $this->is_listing_context();
    }

    public function maybe_enqueue() {
        if ( ! $this->should_render() ) {
            return;
        }

        $css_ver = file_exists( IMOVEL_PARCEIRO_CORE_DIR . 'assets/css/contact-widget.css' )
            ? (string) filemtime( IMOVEL_PARCEIRO_CORE_DIR . 'assets/css/contact-widget.css' )
            : IMOVEL_PARCEIRO_CORE_VERSION;
        $js_ver = file_exists( IMOVEL_PARCEIRO_CORE_DIR . 'assets/js/contact-widget.js' )
            ? (string) filemtime( IMOVEL_PARCEIRO_CORE_DIR . 'assets/js/contact-widget.js' )
            : IMOVEL_PARCEIRO_CORE_VERSION;

        wp_enqueue_style( 'imovel-parceiro-contact-widget', IMOVEL_PARCEIRO_CORE_URL . 'assets/css/contact-widget.css', array(), $css_ver );
        wp_enqueue_script( 'imovel-parceiro-contact-widget', IMOVEL_PARCEIRO_CORE_URL . 'assets/js/contact-widget.js', array( 'jquery' ), $js_ver, true );

        $uid = get_current_user_id();
        $is_client_global = false;
        if ( $uid && class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $uid ) ) {
            $is_client_global = true;
        } elseif ( $uid ) {
            $u = get_userdata( $uid );
            if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'houzez_owner', 'administrator' ), (array) $u->roles ) ) {
                // Qualquer papel não-corretor é tratado como cliente para ocultar parcerias
                $is_client_global = true;
            }
        }

        wp_localize_script(
            'imovel-parceiro-contact-widget',
            'ipcwData',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( self::NONCE ),
                'is_single' => $this->is_single_property(),
                'is_listing' => $this->is_listing_context(),
                'is_client' => $is_client_global,
                'login_url' => Imovel_Parceiro_Partnerships::login_url(),
                'login_modal' => '#login-register-form',
                'partnerships_url' => Imovel_Parceiro_Partnerships::dashboard_partnerships_url(),
                'property_base' => $this->property_base(),
                'partnership_message_default' => __( 'Olá, tenho um cliente interessado neste imóvel e gostaria de realizar uma parceria.', 'imovel-parceiro-core' ),
                'strings' => array(
                    'loading' => __( 'Carregando…', 'imovel-parceiro-core' ),
                    'wa_unavailable' => __( 'WhatsApp indisponível', 'imovel-parceiro-core' ),
                    'wa_unavailable_sub' => __( 'O contato do corretor ainda não está liberado.', 'imovel-parceiro-core' ),
                    'wa_login' => __( 'Faça login para ver o contato', 'imovel-parceiro-core' ),
                    'wa_login_sub' => __( 'Entre na sua conta para falar com o corretor.', 'imovel-parceiro-core' ),
                    'wa_owner' => __( 'WhatsApp indisponível', 'imovel-parceiro-core' ),
                    'wa_owner_sub' => __( 'Você é o proprietário deste imóvel.', 'imovel-parceiro-core' ),
                    'login_required' => __( 'Faça login para solicitar uma parceria.', 'imovel-parceiro-core' ),
                    'partnership' => __( 'Parceria', 'imovel-parceiro-core' ),
                    'open_partnership' => __( 'Abrir parceria', 'imovel-parceiro-core' ),
                    'sent' => __( 'Solicitação enviada!', 'imovel-parceiro-core' ),
                    'error' => __( 'Não foi possível concluir. Tente novamente.', 'imovel-parceiro-core' ),
                    'needs_subscription' => __( 'Para solicitar uma parceria, você precisa ter um plano ativo.', 'imovel-parceiro-core' ),
                    'view_plans' => __( 'Ver planos', 'imovel-parceiro-core' ),
                ),
            )
        );
    }

    /* ---------------------------------------------------------------------
     * Broker resolution (single source reused by request_partnership)
     * ------------------------------------------------------------------ */

    public static function resolve_agent_user_id( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return 0;
        }

        $display = get_post_meta( $property_id, 'fave_agent_display_option', true );

        if ( 'agent_info' === $display ) {
            $post_id = absint( get_post_meta( $property_id, 'fave_agents', true ) );
            if ( $post_id ) {
                $uid = absint( get_post_meta( $post_id, 'houzez_user_meta_id', true ) );
                if ( $uid && get_userdata( $uid ) ) {
                    return $uid;
                }
                $p = get_post( $post_id );
                if ( $p && ! empty( $p->post_author ) && get_userdata( $p->post_author ) ) {
                    return (int) $p->post_author;
                }
            }
        } elseif ( 'agency_info' === $display ) {
            $post_id = absint( get_post_meta( $property_id, 'fave_property_agency', true ) );
            if ( $post_id ) {
                $uid = absint( get_post_meta( $post_id, 'houzez_user_meta_id', true ) );
                if ( $uid && get_userdata( $uid ) ) {
                    return $uid;
                }
                $p = get_post( $post_id );
                if ( $p && ! empty( $p->post_author ) && get_userdata( $p->post_author ) ) {
                    return (int) $p->post_author;
                }
            }
        }

        $author = absint( get_post_field( 'post_author', $property_id ) );
        return ( $author && get_userdata( $author ) ) ? $author : 0;
    }

    public static function property_broker_user_id( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id ) {
            return 0;
        }

        $broker = absint( get_post_meta( $property_id, '_imovel_parceiro_corretor_responsavel_id', true ) );
        if ( $broker && get_userdata( $broker ) ) {
            return $broker;
        }

        $broker = self::resolve_agent_user_id( $property_id );
        if ( $broker ) {
            return $broker;
        }

        return absint( get_post_field( 'post_author', $property_id ) );
    }

    /* ---------------------------------------------------------------------
     * Data builders
     * ------------------------------------------------------------------ */

    private function property_price_label( $property_id ) {
        $price = (string) get_post_meta( $property_id, 'fave_property_price', true );
        $prefix = (string) get_post_meta( $property_id, 'fave_property_price_prefix', true );
        $postfix = (string) get_post_meta( $property_id, 'fave_property_price_postfix', true );

        if ( class_exists( 'Imovel_Parceiro_Price_Formatting' ) && Imovel_Parceiro_Price_Formatting::is_rental_property( $property_id ) ) {
            if ( empty( $prefix ) && empty( $postfix ) ) {
                $prefix = __( '/mês', 'imovel-parceiro-core' );
            }
        }

        $formatted = '';
        if ( '' !== trim( $price ) && is_numeric( trim( $price ) ) ) {
            $formatted = number_format_i18n( (float) trim( $price ), 0 );
        } elseif ( '' !== trim( $price ) ) {
            $formatted = trim( $price );
        }

        $label = '';
        if ( '' !== trim( $prefix ) ) {
            $label .= trim( $prefix ) . ' ';
        }
        if ( '' !== $formatted ) {
            $label .= $formatted;
        }
        if ( '' !== trim( $postfix ) ) {
            $label .= ' ' . trim( $postfix );
        }

        return '' !== trim( $label ) ? $label : '';
    }

    private function property_location_label( $property_id ) {
        $parts = array();
        $address = trim( (string) get_post_meta( $property_id, 'fave_property_address', true ) );
        $city = trim( (string) get_post_meta( $property_id, 'fave_property_city', true ) );
        $neighborhood = trim( (string) get_post_meta( $property_id, 'fave_property_neighborhood', true ) );
        $area = trim( (string) get_post_meta( $property_id, 'fave_property_area', true ) );

        if ( '' !== $address ) {
            $parts[] = $address;
        }
        $locality = $neighborhood ? $neighborhood : $area;
        if ( '' !== $locality ) {
            $parts[] = $locality;
        }
        if ( '' !== $city ) {
            $parts[] = $city;
        }

        return implode( ', ', array_filter( $parts ) );
    }

    private function viewer_partnership( $property_id, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_partnerships';
        if ( ! $user_id ) {
            return null;
        }
        if ( ! class_exists( 'Imovel_Parceiro_Partnership_Workflow' ) ) {
            return null;
        }
        $requester_col = Imovel_Parceiro_Partnership_Workflow::requester_col();
        $owner_col = Imovel_Parceiro_Partnership_Workflow::owner_col();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE property_id = %d AND ( {$requester_col} = %d OR {$owner_col} = %d ) ORDER BY id DESC LIMIT 1",
                $property_id,
                $user_id,
                $user_id
            )
        );

        return $row ? $row : null;
    }

    public static function widget_status_label( $canonical ) {
        $map = array(
            ''                   => array( 'label' => __( 'Solicitar parceria', 'imovel-parceiro-core' ), 'icon' => 'handshake' ),
            'pending'            => array( 'label' => __( 'Solicitação pendente', 'imovel-parceiro-core' ), 'icon' => 'clock' ),
            'accepted'           => array( 'label' => __( 'Parceria aceita', 'imovel-parceiro-core' ), 'icon' => 'handshake' ),
            'rejected'           => array( 'label' => __( 'Parceria recusada', 'imovel-parceiro-core' ), 'icon' => 'x' ),
            'negotiating'        => array( 'label' => __( 'Negociação em andamento', 'imovel-parceiro-core' ), 'icon' => 'chat' ),
            'contact_released'   => array( 'label' => __( 'Contato liberado', 'imovel-parceiro-core' ), 'icon' => 'unlock' ),
            'opportunity'        => array( 'label' => __( 'Oportunidade em andamento', 'imovel-parceiro-core' ), 'icon' => 'clipboard' ),
            'visit'              => array( 'label' => __( 'Visita em andamento', 'imovel-parceiro-core' ), 'icon' => 'calendar' ),
            'proposal'           => array( 'label' => __( 'Proposta em andamento', 'imovel-parceiro-core' ), 'icon' => 'money' ),
            'won'                => array( 'label' => __( 'Negócio ganho', 'imovel-parceiro-core' ), 'icon' => 'check' ),
            'lost'               => array( 'label' => __( 'Oportunidade perdida', 'imovel-parceiro-core' ), 'icon' => 'x' ),
            'closed'             => array( 'label' => __( 'Parceria encerrada', 'imovel-parceiro-core' ), 'icon' => 'check' ),
        );
        $key = is_string( $canonical ) ? $canonical : '';
        return isset( $map[ $key ] ) ? $map[ $key ] : array( 'label' => __( 'Parceria', 'imovel-parceiro-core' ), 'icon' => 'handshake' );
    }

    private function is_whatsapp_available( $broker_id, $viewer_id, $row ) {
        if ( ! $broker_id ) {
            return false;
        }
        $contact = Imovel_Parceiro_Partnership_Workflow::contact_for_user( $broker_id );
        $wa_call = $contact ? ( isset( $contact['whatsapp_call'] ) ? $contact['whatsapp_call'] : '' ) : '';
        if ( '' === trim( (string) $wa_call ) ) {
            return false;
        }

        if ( $viewer_id === $broker_id ) {
            return true;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        // Cliente (houzez_buyer e qualquer não-corretor) vê WhatsApp direto quando há número
        if ( $viewer_id && class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $viewer_id ) ) {
            return true;
        }
        if ( $viewer_id ) {
            $u = get_userdata( $viewer_id );
            if ( $u && ! array_intersect( array( 'houzez_agent', 'houzez_agency' ), (array) $u->roles ) ) {
                return true;
            }
        }
        if ( $row && Imovel_Parceiro_Partnership_Workflow::is_contact_released( $row ) ) {
            return true;
        }

        return false;
    }

    private function whatsapp_message( $broker_name, $property_title ) {
        $name = $broker_name ? $broker_name : __( 'corretor', 'imovel-parceiro-core' );
        $title = $property_title ? $property_title : __( 'o imóvel', 'imovel-parceiro-core' );
        $msg = sprintf(
            /* translators: 1: broker name, 2: property title */
            __( 'Olá %1$s! Tenho interesse no imóvel "%2$s", que vi no site Imóvel Parceiro.', 'imovel-parceiro-core' ),
            $name,
            $title
        );
        return trim( $msg );
    }

    private function partnership_detail_url( $partnership_id ) {
        $dashboard = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard.php' ) : home_url( '/dashboard/' );
        return add_query_arg(
            array(
                'imovel-parceiro' => 'dashboard',
                'imovel_parceiro_parceria' => absint( $partnership_id ),
            ),
            $dashboard
        );
    }

    /**
     * Build the full JSON config handed to the JS.
     */
    public function build_config( $property_id ) {
        $property_id = absint( $property_id );
        if ( ! $property_id || 'property' !== get_post_type( $property_id ) ) {
            return null;
        }

        $broker_id = self::property_broker_user_id( $property_id );
        $viewer_id = get_current_user_id();
        $partnership_row = $this->viewer_partnership( $property_id, $viewer_id );

        $viewer_is_property_owner = false;
        if ( $viewer_id ) {
            $property_owner_id = class_exists( 'Imovel_Parceiro_Partnerships' ) ? Imovel_Parceiro_Partnerships::property_owner_id( $property_id ) : 0;
            $viewer_is_property_owner = $property_owner_id && (int) $property_owner_id === (int) $viewer_id;
        }

        $canonical = '';
        if ( $partnership_row ) {
            $raw = isset( $partnership_row->status ) ? $partnership_row->status : '';
            $canonical = Imovel_Parceiro_Partnership_Workflow::funnel_status( $raw );
        }

        $wa_available = false;
        $wa_link = '';
        if ( $broker_id && ! $viewer_is_property_owner ) {
            $wa_available = $this->is_whatsapp_available( $broker_id, $viewer_id, $partnership_row );
            if ( $wa_available ) {
                $contact = Imovel_Parceiro_Partnership_Workflow::contact_for_user( $broker_id );
                $wa_call = $contact ? ( isset( $contact['whatsapp_call'] ) ? $contact['whatsapp_call'] : '' ) : '';
                $broker_name = $contact && ! empty( $contact['name'] ) ? $contact['name'] : '';
                $message = $this->whatsapp_message( $broker_name, get_the_title( $property_id ) );
                $wa_link = 'https://wa.me/' . $wa_call . '?text=' . rawurlencode( $message );
            }
        }

        $broker = array();
        if ( $broker_id ) {
            $contact = Imovel_Parceiro_Partnership_Workflow::contact_for_user( $broker_id );
            $broker = $contact ? $contact : array();
        }

        $can_request = false;
        $needs_subscription = false;
        $viewer_is_client = false;
        if ( $viewer_id ) {
            if ( class_exists( 'Imovel_Parceiro_First_Login_Redirect' ) && Imovel_Parceiro_First_Login_Redirect::is_client( $viewer_id ) ) {
                $viewer_is_client = true;
            } else {
                $vu = get_userdata( $viewer_id );
                if ( $vu && ! array_intersect( array( 'houzez_agent', 'houzez_agency', 'houzez_owner', 'administrator' ), (array) $vu->roles ) ) {
                    $viewer_is_client = true;
                }
            }
        }

        if ( $viewer_id && $broker_id && $viewer_id !== $broker_id && ! $viewer_is_property_owner && ! $viewer_is_client ) {
            $viewer = get_userdata( $viewer_id );
            $is_owner_profile = $viewer && in_array( 'houzez_owner', (array) $viewer->roles, true );
            $has_cap = current_user_can( 'imovel_parceiro_manage_commercial' ) || current_user_can( 'manage_options' );
            $has_subscription = class_exists( 'Imovel_Parceiro_Subscriptions' ) ? Imovel_Parceiro_Subscriptions::has_active_subscription( $viewer_id ) : true;
            if ( ! $is_owner_profile && ! $partnership_row && $has_cap ) {
                if ( $has_subscription ) {
                    $can_request = true;
                } else {
                    $needs_subscription = true;
                }
            }
        }

        $status_meta = self::widget_status_label( $canonical );

        return array(
            'property' => array(
                'id' => $property_id,
                'title' => get_the_title( $property_id ),
                'location' => $this->property_location_label( $property_id ),
                'price' => $this->property_price_label( $property_id ),
                'thumb' => get_the_post_thumbnail_url( $property_id, 'medium' ),
                'permalink' => get_permalink( $property_id ),
            ),
            'broker' => array(
                'id' => $broker_id,
                'name' => ! empty( $broker['name'] ) ? $broker['name'] : '',
                'avatar' => ! empty( $broker['user_id'] ) ? get_avatar_url( $broker['user_id'], array( 'size' => 96 ) ) : '',
                'email' => ! empty( $broker['email'] ) ? $broker['email'] : '',
                'creci' => ! empty( $broker['creci'] ) ? $broker['creci'] : '',
                'company' => ! empty( $broker['company'] ) ? $broker['company'] : '',
            ),
            'partnership' => array(
                'exists' => (bool) $partnership_row,
                'id' => $partnership_row ? absint( $partnership_row->id ) : 0,
                'status' => $canonical,
                'label' => $status_meta['label'],
                'detail_url' => $partnership_row ? $this->partnership_detail_url( $partnership_row->id ) : '',
            ),
            'whatsapp' => array(
                'available' => $wa_available,
                'has_number' => $broker && ! empty( $broker['whatsapp'] ),
                'link' => $wa_link,
                'needs_login' => ! $viewer_id,
            ),
            'request' => array(
                'allowed' => (bool) $can_request,
                'needs_subscription' => (bool) $needs_subscription,
                'is_owner' => (bool) $viewer_is_property_owner,
                'is_client' => (bool) $viewer_is_client,
                'plans_url' => class_exists( 'Imovel_Parceiro_Subscriptions' ) ? Imovel_Parceiro_Subscriptions::plans_url() : '',
                'plans_message' => __( 'Para solicitar uma parceria, você precisa ter um plano ativo.', 'imovel-parceiro-core' ),
                'message' => __( 'Olá, tenho um cliente interessado neste imóvel e gostaria de realizar uma parceria.', 'imovel-parceiro-core' ),
                'max_chars' => 500,
            ),
        );
    }

    /* ---------------------------------------------------------------------
     * AJAX: context for a single property (used by listing injection)
     * ------------------------------------------------------------------ */

    public function ajax_context() {
        $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce inválido.', 'imovel-parceiro-core' ) ), 403 );
        }

        $property_id = isset( $_REQUEST['property_id'] ) ? absint( $_REQUEST['property_id'] ) : 0;
        if ( ! $property_id && ! empty( $_REQUEST['property_permalink'] ) ) {
            $permalink = esc_url_raw( wp_unslash( $_REQUEST['property_permalink'] ) );
            $property_id = $permalink ? (int) url_to_postid( $permalink ) : 0;
        }
        $config = $this->build_config( $property_id );
        if ( null === $config ) {
            wp_send_json_error( array( 'message' => __( 'Imóvel inválido.', 'imovel-parceiro-core' ) ), 400 );
        }

        wp_send_json_success( array( 'config' => $config ) );
    }

    /* ---------------------------------------------------------------------
     * Render (button + panel) on wp_footer
     * ------------------------------------------------------------------ */

    public function render() {
        if ( ! $this->should_render() ) {
            return;
        }

        if ( is_user_logged_in() && class_exists( 'Imovel_Parceiro_Security' ) ) {
            // do nothing; security rules are applied in the AJAX handlers.
        }

        $config = null;
        $is_single = $this->is_single_property();
        if ( $is_single ) {
            $property_id = (int) get_queried_object_id();
            if ( ! $property_id && isset( $GLOBALS['post']->ID ) ) {
                $property_id = (int) $GLOBALS['post']->ID;
            }
            $config = $this->build_config( $property_id );
        }

        // Hide the FAB (and auto-config) when there is no broker at all.
        if ( $is_single && ( null === $config || empty( $config['broker']['id'] ) ) ) {
            $config = null;
        }

        $fab_label = __( 'Falar com o corretor', 'imovel-parceiro-core' );
        ?>
        <div id="ipcw-root" class="ipcw-hidden" data-single="<?php echo $is_single ? '1' : '0'; ?>" data-listing="<?php echo $this->is_listing_context() ? '1' : '0'; ?>">
            <?php if ( $config ) : ?>
                <button type="button" id="ipcw-fab" class="ipcw-fab" aria-label="<?php echo esc_attr( $fab_label ); ?>">
                    <span class="ipcw-fab__label" aria-hidden="true"><?php echo esc_html( $fab_label ); ?></span>
                    <?php echo $this->fab_icon(); ?>
                </button>
            <?php endif; ?>

            <div id="ipcw-overlay" class="ipcw-overlay" aria-hidden="true"></div>

            <div id="ipcw-panel" class="ipcw-panel" role="dialog" aria-modal="true" aria-labelledby="ipcw-panel-title">
                <div class="ipcw-panel__head">
                    <div class="ipcw-panel__titles">
                        <h3 id="ipcw-panel-title"><?php esc_html_e( 'Falar com o corretor', 'imovel-parceiro-core' ); ?></h3>
                        <p><?php esc_html_e( 'Entre em contato ou solicite uma parceria para este imóvel.', 'imovel-parceiro-core' ); ?></p>
                    </div>
                    <button type="button" id="ipcw-close" class="ipcw-close" aria-label="<?php esc_attr_e( 'Fechar', 'imovel-parceiro-core' ); ?>">&times;</button>
                </div>
                <div class="ipcw-panel__body" id="ipcw-body">
                    <div class="ipcw-loading"><?php esc_html_e( 'Carregando…', 'imovel-parceiro-core' ); ?></div>
                </div>
            </div>
        </div>

        <?php if ( $config ) : ?>
            <script type="application/json" id="ipcw-config"><?php echo wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
        <?php endif; ?>
        <?php
    }

    private function fab_icon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.5 2 2 6.5 2 12c0 1.9.6 3.7 1.6 5.2L2 22l5-1.4c1.5.9 3.2 1.4 5 1.4 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.2c-1.6 0-3.1-.5-4.4-1.3l-.3-.2-3 .8.8-2.9-.2-.3A8.1 8.1 0 0 1 4 12c0-4.4 3.6-8 8-8s8 3.6 8 8-3.6 8-8 8zm4.4-6.5c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1-.2.2-.6.8-.8 1-.1.2-.3.2-.5.1-.3-.1-1.1-.4-2.1-1.3-.8-.7-1.3-1.6-1.5-1.9-.1-.2 0-.3.1-.4l.4-.5c.1-.2.2-.3.2-.4 0-.2 0-.4-.1-.6-.2-.1-.6-1.5-.8-2-.1-.4-.3-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.2-.9.9-.9 2.2s.9 2.5 1.1 2.7c.1.2 1.9 2.9 4.6 4.1.7.3 1.2.45 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.1-.2-.2-.5-.3z"/></svg>';
    }
}

Imovel_Parceiro_Contact_Widget::instance();
