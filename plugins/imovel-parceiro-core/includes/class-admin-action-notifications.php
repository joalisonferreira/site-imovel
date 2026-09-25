<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notifications for admin-authorization actions that are handled directly in the
 * Houzez dashboard management screen (and therefore do not fire theme hooks).
 *
 * Item 7 of the plan: every action that depends on admin authorization must
 * notify the affected user by e-mail and in-app notification.
 */
class Imovel_Parceiro_Admin_Action_Notifications {

    /**
     * Notify the property author when an admin approves (publishes) a property.
     *
     * @param int  $property_id Property post ID.
     * @param bool $approved    Whether the property was approved.
     */
    public static function notify_property_owner( $property_id, $approved = true ) {
        $property_id = absint( $property_id );
        $post        = get_post( $property_id );

        if ( ! $post || 'property' !== $post->post_type ) {
            return;
        }

        $owner_id = (int) $post->post_author;
        if ( ! $owner_id ) {
            return;
        }

        $property_title = get_the_title( $property_id );
        if ( '' === $property_title ) {
            $property_title = sprintf( __( 'Imóvel #%d', 'imovel-parceiro-core' ), $property_id );
        }

        if ( $approved ) {
            $title   = __( 'Imóvel aprovado', 'imovel-parceiro-core' );
            $message = sprintf( __( 'Seu imóvel "%s" foi aprovado e já está publicado.', 'imovel-parceiro-core' ), $property_title );
            $type    = 'PROPERTY_APPROVED';
        } else {
            $title   = __( 'Imóvel não aprovado', 'imovel-parceiro-core' );
            $message = sprintf( __( 'Seu imóvel "%s" precisa de ajustes antes de ser publicado.', 'imovel-parceiro-core' ), $property_title );
            $type    = 'PROPERTY_REJECTED';
        }

        $url = get_permalink( $property_id );

        if ( class_exists( 'IPC_Notifications' ) ) {
            IPC_Notifications::send(
                array(
                    'user_id'     => $owner_id,
                    'property_id' => $property_id,
                    'type'        => $type,
                    'category'    => IPC_Notifications::CATEGORY_PROPRIEDADES,
                    'title'       => $title,
                    'message'     => $message,
                    'url'         => $url ? $url : IPC_Notifications::get_dashboard_url(),
                    'priority'    => $approved ? IPC_Notifications::PRIORITY_NORMAL : IPC_Notifications::PRIORITY_IMPORTANT,
                )
            );
        }

        $user = get_userdata( $owner_id );
        if ( $user && is_email( $user->user_email ) ) {
            $mail_args = array(
                'listing_title' => $property_title,
                'listing_url'   => $url ? $url : IPC_Notifications::get_dashboard_url(),
            );

            if ( function_exists( 'houzez_email_type' ) ) {
                // Usa o mesmo template editável (Houzez → E-mails) do caminho
                // nativo do tema: uma única fonte de conteúdo para o evento,
                // em qualquer tela de aprovação. Suspende a notificação in-app
                // duplicada (a nossa, com CTA, já foi enviada acima).
                $hook = 'houzez_send_notification';
                $callback = class_exists( 'IPC_Notifications' )
                    ? array( IPC_Notifications::instance(), 'notify_houzez_message' )
                    : null;

                if ( $callback ) {
                    remove_action( $hook, $callback, 20 );
                }
                houzez_email_type( $user->user_email, $approved ? 'listing_approved' : 'listing_disapproved', $mail_args );
                if ( $callback ) {
                    add_action( $hook, $callback, 20, 1 );
                }
            } else {
                $subject = '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $title;
                $args    = array( 'title' => $title );
                if ( $approved && $url ) {
                    $args['cta_url']  = $url;
                    $args['cta_text'] = __( 'Ver imóvel', 'imovel-parceiro-core' );
                }
                Imovel_Parceiro_Email_Template::send(
                    $user->user_email,
                    $subject,
                    Imovel_Parceiro_Email_Template::text_to_html( $message ),
                    $args
                );
            }
        }
    }
}

new Imovel_Parceiro_Admin_Action_Notifications();
