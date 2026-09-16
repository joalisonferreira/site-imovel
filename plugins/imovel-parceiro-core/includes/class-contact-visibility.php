<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Contact info visibility gate.
 *
 * Phone/contact buttons on property pages are only rendered for viewers who
 * are logged in as corretor/imobiliária, with an active plan and a verified
 * Houzez profile. Everyone else gets no contact markup at all (server-side,
 * so phone numbers never leak into the page source).
 */
class Imovel_Parceiro_Contact_Visibility {

    const ALLOWED_ROLES = array( 'houzez_agent', 'houzez_agency' );

    /**
     * Elementor contact widgets fully suppressed when the viewer is unqualified.
     *
     * @var string[]
     */
    private static $blocked_widgets = array(
        'houzez-agent-email-btn',
        'houzez-agent-call-btn',
        'houzez-agent-whatsapp-btn',
        'houzez-agent-telegram-btn',
        'houzez-agent-line-btn',
        'houzez-agency-email-btn',
        'houzez-agency-call-btn',
        'houzez-agency-whatsapp-btn',
        'houzez-agency-telegram-btn',
        'houzez-agency-line-btn',
    );

    /**
     * Contact AJAX endpoints that email the broker directly.
     *
     * @var string[]
     */
    private static $gated_ajax_actions = array(
        'houzez_schedule_send_message',
        'houzez_property_agent_contact',
        'houzez_contact_realtor',
    );

    public function __construct() {
        add_filter( 'elementor/widget/render_content', array( $this, 'filter_widget_content' ), 20, 2 );
        add_filter( 'houzez_property_schema', array( $this, 'strip_schema_contact' ), 20, 2 );

        // Server-side enforcement (priority 1, before the theme handlers):
        // UI locks alone would not stop direct POSTs.
        foreach ( self::$gated_ajax_actions as $action ) {
            add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'block_unqualified_ajax' ), 1 );
            add_action( 'wp_ajax_' . $action, array( $this, 'block_unqualified_ajax' ), 1 );
        }
    }

    /**
     * Block contact/tour submissions from unqualified viewers.
     * Uses the `msg` key so the theme JS displays it in .form_messages.
     */
    public function block_unqualified_ajax() {
        if ( self::viewer_can_see_contact() ) {
            return;
        }

        if ( is_user_logged_in() ) {
            $message = __( 'Contatos disponíveis para corretores e imobiliárias com plano ativo e perfil verificado.', 'imovel-parceiro-core' );
        } else {
            $message = __( 'Faça login como corretor ou imobiliária para entrar em contato.', 'imovel-parceiro-core' );
        }

        wp_send_json_error( array( 'msg' => $message ) );
    }

    /**
     * Remove phone/email from the JSON-LD schema for unqualified viewers.
     *
     * @param array $schema      Complete schema array.
     * @param int   $property_id Property post ID.
     * @return array
     */
    public function strip_schema_contact( $schema, $property_id ) {
        if ( self::viewer_can_see_contact() || ! is_array( $schema ) ) {
            return $schema;
        }

        if ( isset( $schema['offers']['seller']['telephone'] ) ) {
            unset( $schema['offers']['seller']['telephone'] );
        }
        if ( isset( $schema['offers']['seller']['email'] ) ) {
            unset( $schema['offers']['seller']['email'] );
        }

        return $schema;
    }

    /**
     * Whether the viewer may see phone/contact buttons.
     *
     * @param int $user_id Optional user id. Defaults to current user.
     * @return bool
     */
    public static function viewer_can_see_contact( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );
        if ( ! $user || ! array_intersect( self::ALLOWED_ROLES, (array) $user->roles ) ) {
            return false;
        }

        if ( class_exists( 'Imovel_Parceiro_Subscriptions' ) && ! Imovel_Parceiro_Subscriptions::has_active_subscription( $user_id ) ) {
            return false;
        }

        if ( 'approved' !== get_user_meta( $user_id, 'houzez_verification_status', true ) ) {
            return false;
        }

        return true;
    }

    /**
     * Suppress contact widgets (or strip contact parts) for unqualified viewers.
     *
     * @param string $content Rendered widget HTML.
     * @param object $widget  Elementor widget instance.
     * @return string
     */
    public function filter_widget_content( $content, $widget ) {
        if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
            return $content;
        }

        if ( self::viewer_can_see_contact() ) {
            return $content;
        }

        $name = $widget->get_name();
        if ( in_array( $name, self::$blocked_widgets, true ) ) {
            return '';
        }

        if ( 'houzez_elementor_agent_card' === $name ) {
            return self::strip_agent_card_contact( $content );
        }

        return $content;
    }

    /**
     * Remove phone numbers, action buttons and the phone modal from the agent
     * card markup. Photo, name and partnership button are preserved.
     *
     * @param string $content Rendered agent card HTML.
     * @return string
     */
    public static function strip_agent_card_contact( $content ) {
        return self::strip_nodes_by_class(
            $content,
            array( 'agent-phone-wrap', 'item-buttons-wrap', 'modal-phone-number' )
        );
    }

    /**
     * Remove every node carrying one of the given CSS classes.
     *
     * @param string   $content Rendered HTML fragment.
     * @param string[] $classes CSS classes to remove.
     * @return string
     */
    public static function strip_nodes_by_class( $content, array $classes ) {
        $content = (string) $content;
        if ( '' === trim( $content ) || empty( $classes ) || ! class_exists( 'DOMDocument' ) ) {
            return $content;
        }

        $prev = libxml_use_internal_errors( true );
        $doc  = new DOMDocument( '1.0', 'UTF-8' );
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><div id="ipc-contact-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $xpath = new DOMXPath( $doc );
        foreach ( $classes as $class ) {
            $class = trim( (string) $class );
            if ( '' === $class ) {
                continue;
            }
            foreach ( $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]" ) as $node ) {
                if ( $node->parentNode ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }

        $root = $doc->getElementById( 'ipc-contact-root' );
        if ( ! $root ) {
            return $content;
        }

        $out = '';
        foreach ( $root->childNodes as $child ) {
            $out .= $doc->saveHTML( $child );
        }

        return $out;
    }
}

new Imovel_Parceiro_Contact_Visibility();
