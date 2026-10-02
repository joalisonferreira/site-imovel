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
     * Contato da plataforma: leads de clientes (Agendar visita / Mensagem /
     * contato do corretor) são redirecionados para este e-mail em vez do corretor.
     */
    const CLIENT_LEAD_EMAIL = 'contato@imovelparceiro.com.br';

    /**
     * Flag request-scoped: filtro wp_mail armado só durante o AJAX de contato
     * enviado por cliente/proprietário/visitante (ver arm_lead_redirect()).
     *
     * @var bool
     */
    private static $client_redirect_armed = false;

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
     * Quem pode enviar: corretores/imobiliárias qualificados (plano +
     * verificação), clientes, proprietários e visitantes. Para todos exceto
     * os qualificados, o lead é redirecionado para CLIENT_LEAD_EMAIL.
     * Só corretores/imobiliárias NÃO qualificados são bloqueados.
     * (Regra espelhada em viewer_can_submit_forms() para os templates.)
     */
    public function block_unqualified_ajax() {
        if ( self::viewer_can_see_contact() ) {
            return;
        }

        // Cliente, proprietário, visitante ou outro papel não-corretor:
        // formulários habilitados, mas o lead vai para contato@
        // (filtro wp_mail armado só nesta requisição AJAX).
        if ( self::viewer_can_submit_forms() ) {
            self::arm_lead_redirect();
            return;
        }

        // Corretor/imobiliária sem plano ativo ou perfil verificado: bloqueado.
        $message = __( 'Contatos disponíveis para corretores e imobiliárias com plano ativo e perfil verificado.', 'imovel-parceiro-core' );
        wp_send_json_error( array( 'msg' => $message, 'Message' => $message, 'message' => $message, 'code' => 'broker_verification_required' ) );
    }

    /**
     * Whether the viewer may submit contact/tour forms (Agendar visita,
     * Mensagem). Única fonte da regra — usada pelo gate AJAX acima e pelos
     * templates (botão de envio habilitado/desabilitado).
     *
     * @param int|null $user_id Optional user id. Defaults to current user.
     * @return bool
     */
    public static function viewer_can_submit_forms( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( self::viewer_can_see_contact( $user_id ) ) {
            return true;
        }
        if ( ! $user_id ) {
            return true; // visitante: lead vai para contato@
        }
        if ( self::viewer_is_owner_blocked( $user_id ) ) {
            return true; // proprietário: lead vai para contato@
        }
        if ( self::is_client( $user_id ) ) {
            return true; // cliente: lead vai para contato@
        }
        $user = get_userdata( $user_id );
        if ( $user && ! array_intersect( self::ALLOWED_ROLES, (array) $user->roles ) ) {
            return true; // outros papéis: lead vai para contato@
        }
        return false; // corretor/imobiliária não qualificado
    }

    /**
     * Remove phone/email from the JSON-LD schema for unqualified viewers.
     *
     * @param array $schema      Complete schema array.
     * @param int   $property_id Property post ID.
     * @return array
     */
    public function strip_schema_contact( $schema, $property_id ) {
        if ( self::viewer_can_see_contact( 0, $property_id ) || ! is_array( $schema ) ) {
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
     * @param int $user_id     Optional user id. Defaults to current user.
     * @param int $property_id Optional property context. Owners keep the
     *                         channel of their OWN listing; on third-party
     *                         listings (or without context) they are blocked.
     * @return bool
     */
    public static function viewer_can_see_contact( $user_id = 0, $property_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }

        // Proprietário não contata outros corretores (canal próprio preservado via $property_id).
        if ( self::viewer_is_owner_blocked( $user_id, $property_id ) ) {
            return false;
        }

        // Clientes e outros papéis não-corretor podem ver/contatar - não aplica gate de plano/verificação
        if ( ! array_intersect( self::ALLOWED_ROLES, (array) $user->roles ) ) {
            return true;
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
     * Whether the viewer is a proprietário (houzez_owner) blocked from
     * contacting brokers in this context.
     *
     * Owners keep the channel of their OWN listing (property whose registered
     * owner is the viewer). Everywhere else — third-party listings or no
     * property context — they are blocked.
     *
     * @param int $user_id     Optional user id. Defaults to current user.
     * @param int $property_id Optional property context.
     * @return bool
     */
    public static function viewer_is_owner_blocked( $user_id = 0, $property_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        $user = get_userdata( $user_id );
        if ( ! $user || ! in_array( 'houzez_owner', (array) $user->roles, true ) ) {
            return false;
        }
        $property_id = absint( $property_id );
        if ( $property_id && class_exists( 'Imovel_Parceiro_Partnerships' ) ) {
            $owner_id = (int) Imovel_Parceiro_Partnerships::property_owner_id( $property_id );
            if ( $owner_id && $owner_id === (int) $user_id ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the user is a cliente (qualquer papel que não seja
     * corretor/imobiliária/proprietário/admin). É esse público que tem os
     * formulários habilitados com lead redirecionado para a plataforma.
     *
     * @param int $user_id Optional user id. Defaults to current user.
     * @return bool
     */
    public static function is_client( $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        return empty( array_intersect(
            array( 'houzez_agent', 'houzez_agency', 'houzez_owner', 'administrator' ),
            (array) $user->roles
        ) );
    }

    /**
     * Arma o redirecionamento do lead para CLIENT_LEAD_EMAIL.
     * Escopo restrito: só vale nesta requisição AJAX (o handler nativo morre
     * com wp_die logo após enviar).
     */
    public static function arm_lead_redirect() {
        if ( self::$client_redirect_armed ) {
            return;
        }
        self::$client_redirect_armed = true;
        add_filter( 'wp_mail', array( __CLASS__, 'redirect_lead_mail' ) );
    }

    /**
     * Troca o destinatário do e-mail pelo contato da plataforma.
     * Nota: se a loja configurar cópia CC/BCC nesses formulários, a cópia
     * também é roteada para contato@ nesta requisição (efeito documentado).
     *
     * @param array $args Argumentos do wp_mail (to, subject, message, headers, attachments).
     * @return array
     */
    public static function redirect_lead_mail( $args ) {
        if ( empty( $args['to'] ) ) {
            return $args;
        }
        $args['to'] = self::CLIENT_LEAD_EMAIL;
        return $args;
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

        // Contexto do próprio anúncio (preserva o canal do proprietário com seu corretor).
        $context_property = is_singular( 'property' ) ? get_the_ID() : 0;
        if ( self::viewer_can_see_contact( 0, $context_property ) ) {
            // Cliente logado: só formulários — suprime botões de contato direto.
            if ( self::is_client() ) {
                $client_name = $widget->get_name();
                if ( in_array( $client_name, self::$blocked_widgets, true ) ) {
                    return '';
                }
                if ( 'houzez_elementor_agent_card' === $client_name ) {
                    return self::strip_agent_card_contact( $content );
                }
            }
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
