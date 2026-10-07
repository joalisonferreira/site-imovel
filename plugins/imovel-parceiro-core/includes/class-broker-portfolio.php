<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carteira de imóveis do corretor responsável (admin).
 *
 * Quando um proprietário cadastra um imóvel, o
 * Imovel_Parceiro_Owner_Workflow atribui o primeiro administrador como
 * corretor responsável (postmeta META_BROKER_ID + coluna broker_user_id da
 * relação). Esta classe cobre o que faltava nesse evento:
 *
 * 1. Notifica o admin atribuído (e-mail + notificação in-app), uma única vez
 *    por imóvel (flag em postmeta — sem mudança de schema).
 * 2. Expõe a aba "Carteira de imóveis" (post_status=carteira) na listagem
 *    Meus imóveis do dashboard, visível só para admin, filtrando apenas os
 *    publicados cujo corretor responsável é o próprio admin.
 */
class Imovel_Parceiro_Broker_Portfolio {
    const QUERY_STATUS = 'carteira';
    const QUERY_MARKER = 'ip_carteira_broker';
    const META_NOTIFIED = '_imovel_parceiro_broker_assigned_notified';

    public function __construct() {
        // Marca a query do dashboard antes do WP_Query (o template pai
        // sobrescreve post_status depois deste filtro, por isso a aplicação
        // real acontece no pre_get_posts via marcador).
        add_filter( 'houzez20_search_filters', array( $this, 'mark_carteira_query' ), 10, 1 );
        add_action( 'pre_get_posts', array( $this, 'apply_carteira_query' ), 20 );
    }

    /**
     * Há pedido da aba carteira na listagem de imóveis do dashboard?
     *
     * @return bool
     */
    public static function is_carteira_request() {
        if ( is_admin() || ! is_user_logged_in() ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['post_status'] ) && self::QUERY_STATUS === sanitize_key( wp_unslash( $_GET['post_status'] ) );
    }

    public static function is_admin_viewer() {
        if ( function_exists( 'houzez_is_admin' ) ) {
            return (bool) houzez_is_admin();
        }

        return current_user_can( 'manage_options' );
    }

    /**
     * URL da aba Carteira de imóveis.
     *
     * @return string
     */
    public static function carteira_url() {
        if ( function_exists( 'houzez_get_template_link_2' ) ) {
            $base = houzez_get_template_link_2( 'template/user_dashboard_properties.php' );
        } else {
            $base = home_url( '/' );
        }

        return add_query_arg( 'post_status', self::QUERY_STATUS, $base );
    }

    /**
     * Conta os publicados cujo corretor responsável é o usuário informado.
     *
     * @param int $broker_id ID do usuário.
     * @return int
     */
    public static function count_broker_properties( $broker_id ) {
        $broker_id = absint( $broker_id );
        if ( ! $broker_id || ! class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ) {
            return 0;
        }

        $query = new WP_Query(
            array(
                'post_type' => 'property',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
                'meta_query' => array(
                    array(
                        'key' => Imovel_Parceiro_Owner_Workflow::META_BROKER_ID,
                        'value' => $broker_id,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ),
                ),
            )
        );

        return (int) $query->found_posts;
    }

    /**
     * Marca os args da query do dashboard quando a aba carteira é pedida.
     *
     * @param array $args Args do WP_Query do template pai.
     * @return array
     */
    public function mark_carteira_query( $args ) {
        if ( ! is_array( $args ) || ! self::is_carteira_request() ) {
            return $args;
        }

        if ( ! self::is_admin_viewer() ) {
            // Não-admin tentando acesso direto via URL: força resultado vazio.
            unset( $args['author'], $args['author__in'], $args['author__not_in'] );
            $args['author'] = -1;
            $args['post__in'] = array( 0 );
            return $args;
        }

        $args[ self::QUERY_MARKER ] = get_current_user_id();

        return $args;
    }

    /**
     * Aplica o filtro real da carteira (mesma visão de Publicados + corretor).
     *
     * @param WP_Query $query Query em construção.
     */
    public function apply_carteira_query( $query ) {
        if ( is_admin() || ! $query instanceof WP_Query ) {
            return;
        }

        $broker_id = (int) $query->get( self::QUERY_MARKER );
        if ( ! $broker_id || ! class_exists( 'Imovel_Parceiro_Owner_Workflow' ) ) {
            return;
        }

        if ( (int) get_current_user_id() !== $broker_id || ! self::is_admin_viewer() ) {
            return;
        }

        if ( 'property' !== $query->get( 'post_type' ) ) {
            return;
        }

        // Mesma visão da aba Publicados, restrita à carteira do admin.
        // Mescla (não sobrescreve) para preservar a ordenação do tema.
        unset( $query->query_vars['author'], $query->query_vars['author__in'], $query->query_vars['author__not_in'] );
        $query->set( 'author', '' );
        $query->set( 'post_status', 'publish' );
        $meta_query = (array) $query->get( 'meta_query' );
        $meta_query[] = array(
            'key' => Imovel_Parceiro_Owner_Workflow::META_BROKER_ID,
            'value' => $broker_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Notifica o admin quando ele é atribuído como corretor responsável.
     * Dispara uma única vez por imóvel (flag em postmeta).
     *
     * @param int $property_id ID do imóvel.
     * @param int $broker_id   ID do corretor atribuído.
     * @param int $owner_id    ID do proprietário autor.
     * @return bool
     */
    public static function maybe_notify_broker_assigned( $property_id, $broker_id, $owner_id ) {
        $property_id = absint( $property_id );
        $broker_id = absint( $broker_id );
        $owner_id = absint( $owner_id );

        if ( ! $property_id || ! $broker_id || ! $owner_id || 'property' !== get_post_type( $property_id ) ) {
            return false;
        }

        // O evento coberto é a atribuição do admin como responsável.
        if ( ! user_can( $broker_id, 'administrator' ) ) {
            return false;
        }

        // Rascunho ainda não é cadastro efetivo: notifica no primeiro save
        // não-rascunho (a flag abaixo garante o disparo único).
        $status = get_post_status( $property_id );
        if ( in_array( $status, array( 'draft', 'auto-draft' ), true ) ) {
            return false;
        }

        if ( get_post_meta( $property_id, self::META_NOTIFIED, true ) ) {
            return false;
        }

        $broker = get_userdata( $broker_id );
        $owner = get_userdata( $owner_id );
        if ( ! $broker ) {
            return false;
        }

        $property_title = get_the_title( $property_id );
        if ( '' === $property_title ) {
            $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id );
        }

        $owner_name = $owner ? $owner->display_name : sprintf( __( 'usuário #%d', 'imovel-parceiro-core' ), $owner_id );
        $carteira_url = self::carteira_url();

        $title = __( 'Novo imóvel na sua carteira', 'imovel-parceiro-core' );
        $message = sprintf(
            __( 'O imóvel "%1$s" (ID %2$d), de %3$s, foi atribuído a você como corretor responsável.', 'imovel-parceiro-core' ),
            $property_title,
            $property_id,
            $owner_name
        );

        if ( class_exists( 'IPC_Notifications' ) ) {
            IPC_Notifications::send(
                array(
                    'user_id' => $broker_id,
                    'property_id' => $property_id,
                    'type' => 'BROKER_ASSIGNED',
                    'category' => IPC_Notifications::CATEGORY_PROPRIEDADES,
                    'title' => $title,
                    'message' => $message,
                    'url' => $carteira_url,
                    'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );
        }

        if ( $broker && is_email( $broker->user_email ) && class_exists( 'Imovel_Parceiro_Email_Template' ) ) {
            $subject = '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $title . ': ' . $property_title;
            Imovel_Parceiro_Email_Template::send(
                $broker->user_email,
                $subject,
                Imovel_Parceiro_Email_Template::text_to_html( $message ),
                array(
                    'title' => $title,
                    'cta_url' => $carteira_url,
                    'cta_text' => __( 'Ver carteira', 'imovel-parceiro-core' ),
                )
            );
        }

        update_post_meta( $property_id, self::META_NOTIFIED, 1 );

        return true;
    }
}

new Imovel_Parceiro_Broker_Portfolio();
