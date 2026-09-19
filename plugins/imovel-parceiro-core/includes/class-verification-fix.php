<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Correção da verificação do corretor: status aprovado deve ser sticky.
 *
 * - Torna o endpoint houzez_submit_verification idempotente para approved.
 * - Garante invalidação de cache após aprovação.
 * - Unifica a checagem de verificação para uso do is_user_verified.
 */
class Imovel_Parceiro_Verification_Fix {

    public function __construct() {
        // Intercepta antes do Houzez processar o pedido de verificação.
        add_action( 'houzez_before_verification_request', array( $this, 'block_resubmit_when_approved' ), 5, 2 );
        // Após aprovação, limpa caches de user_meta.
        add_action( 'houzez_after_approve_verification', array( $this, 'clear_verification_cache' ), 20, 2 );
        add_action( 'houzez_after_add_verification_history', array( $this, 'maybe_clear_on_approved_history' ), 20, 3 );
        // Garante que qualquer update direto em houzez_verification_status invalide o cache (ex.: admin-management.php)
        add_action( 'updated_user_meta', array( $this, 'on_verification_status_updated' ), 20, 4 );
        add_action( 'added_user_meta', array( $this, 'on_verification_status_updated' ), 20, 4 );
    }

    /**
     * Se o corretor já está aprovado, não permite nova submissão.
     * Retorna sucesso idempotente em vez de erro que confunde o front.
     */
    public function block_resubmit_when_approved( $user_id, $post_data ) {
        $user_id = absint( $user_id );
        if ( ! $user_id || ! isset( $GLOBALS['houzez_user_verification'] ) ) {
            return;
        }

        $status = $GLOBALS['houzez_user_verification']->get_verification_status( $user_id );
        if ( 'approved' === $status ) {
            $profile_link = function_exists( 'houzez_get_template_link_2' ) ? houzez_get_template_link_2( 'template/user_dashboard_profile.php' ) : home_url( '/meu-perfil/' );
            $redirect = add_query_arg( 'hpage', 'verification', $profile_link );
            wp_send_json_success( array(
                'message'  => __( 'Sua conta já está verificada.', 'houzez' ),
                'redirect' => $redirect,
            ) );
        }
    }

    /**
     * Após aprovação via painel Houzez, limpa cache para que is_user_verified reflita imediatamente.
     */
    public function clear_verification_cache( $user_id, $verification_data ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return;
        }
        clean_user_cache( $user_id );
        wp_cache_delete( $user_id, 'user_meta' );
        // Força recarregar o status na mesma requisição, se houver Redis.
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'user_meta' );
        }
    }

    public function maybe_clear_on_approved_history( $user_id, $status, $entry ) {
        if ( 'approved' === $status ) {
            $this->clear_verification_cache( $user_id, array() );
        }
    }

    public function on_verification_status_updated( $meta_id, $user_id, $meta_key, $meta_value ) {
        if ( 'houzez_verification_status' !== $meta_key ) {
            return;
        }
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return;
        }
        clean_user_cache( $user_id );
        wp_cache_delete( $user_id, 'user_meta' );
    }
}

new Imovel_Parceiro_Verification_Fix();
