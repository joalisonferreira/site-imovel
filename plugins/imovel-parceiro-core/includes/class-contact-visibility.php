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

    public function __construct() {
        add_filter( 'elementor/widget/render_content', array( $this, 'filter_widget_content' ), 20, 2 );
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
        $content = (string) $content;
        if ( '' === trim( $content ) || ! class_exists( 'DOMDocument' ) ) {
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
        foreach ( array( 'agent-phone-wrap', 'item-buttons-wrap', 'modal-phone-number' ) as $class ) {
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
