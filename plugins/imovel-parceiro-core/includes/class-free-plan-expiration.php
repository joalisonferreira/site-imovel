<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ciclo de vida do plano gratuito (validade de 3 meses).
 *
 * O plano gratuito é vendido como uma assinatura WooCommerce de preço zero.
 * Sem intervenção, o WooCommerce Subscriptions renova esse tipo de assinatura
 * indefinidamente (o total é 0, então `payment_complete()` é chamado a cada
 * ciclo) e o WCS nunca agenda a ação de cancelamento
 * (`woocommerce_scheduled_subscription_end_of_prepaid_term`) — bug conhecido
 * do WCS 2.2.x.
 *
 * Esta classe assume o controle do ciclo de vida:
 *  - na ativação, a assinatura passa para `pending-cancel` (o WCS apaga a data
 *    da próxima cobrança e fixa a data de término = início + validade);
 *  - um cron próprio cancela a assinatura quando a validade termina;
 *  - um filtro bloqueia a reativação automática (evita ciclo infinito);
 *  - o gate de acesso ignora assinaturas gratuitas já vencidas, mesmo que o
 *    WooCommerce ainda marque a assinatura como ativa.
 */
class Imovel_Parceiro_Free_Plan_Expiration {
    const CRON_HOOK     = 'imovel_parceiro_free_plan_expiration_daily';
    const EXPIRES_META  = '_imovel_parceiro_free_plan_expires_at';
    const NOTIFIED_META = '_imovel_parceiro_free_plan_expired_notified';
    const AUDIT_EVENT   = 'free_plan_expired';

    private static $instance = null;

    /** Assinaturas que entraram em 'active' durante a requisição. */
    private $queued = array();

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'maybe_schedule' ) );
        add_action( self::CRON_HOOK, array( $this, 'run_expiration_sweep' ) );

        // A conversão para 'pending-cancel' roda no fim da requisição para
        // não alterar o status dentro da própria transição de status.
        add_action( 'woocommerce_subscription_status_active', array( $this, 'queue_on_activation' ), 20, 1 );
        add_action( 'shutdown', array( $this, 'flush_queue' ) );

        add_action( 'woocommerce_subscription_status_changed', array( $this, 'on_status_changed' ), 30, 4 );
        add_filter( 'woocommerce_can_subscription_be_updated_to_active', array( $this, 'block_reactivation' ), 10, 2 );
    }

    /* ---------------------------------------------------------------------
     * Agendamento
     * ------------------------------------------------------------------ */

    public function maybe_schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /* ---------------------------------------------------------------------
     * Ativação -> pending-cancel
     * ------------------------------------------------------------------ */

    /**
     * Guarda a assinatura recém-ativada para conversão ao fim da requisição.
     *
     * @param WC_Subscription $subscription
     */
    public function queue_on_activation( $subscription ) {
        if ( ! $this->is_free_subscription( $subscription ) ) {
            return;
        }
        $id = (int) $subscription->get_id();
        if ( $id && ! in_array( $id, $this->queued, true ) ) {
            $this->queued[] = $id;
        }
    }

    /**
     * Converte as assinaturas enfileiradas para 'pending-cancel'.
     */
    public function flush_queue() {
        if ( empty( $this->queued ) ) {
            return;
        }
        $ids          = $this->queued;
        $this->queued = array();

        foreach ( $ids as $id ) {
            $subscription = $this->get_subscription( $id );
            if ( ! $subscription ) {
                continue;
            }
            $this->convert_to_pending_cancel( $subscription );
        }
    }

    /**
     * Desativa a renovação automática da assinatura gratuita.
     *
     * O WCS apaga a data de próxima cobrança e grava a data de término
     * (início + validade) quando a assinatura entra em 'pending-cancel'.
     *
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function convert_to_pending_cancel( $subscription ) {
        if ( ! $this->is_free_subscription( $subscription ) ) {
            return false;
        }
        if ( ! $subscription->has_status( 'active' ) ) {
            return false;
        }
        if ( ! $subscription->can_be_updated_to( 'pending-cancel' ) ) {
            return false;
        }

        $package_id = $this->package_id_for_subscription( $subscription );
        $expiry     = $this->compute_expiry( $subscription, $package_id );

        $note = __( 'Plano gratuito com validade definida: a renovação automática foi desativada.', 'imovel-parceiro-core' );
        try {
            $subscription->update_status( 'pending-cancel', $note );
        } catch ( Exception $e ) {
            return false;
        }

        $this->store_expiry( $subscription, $expiry );
        $this->unschedule_renewal_notices( $subscription );

        return true;
    }

    /* ---------------------------------------------------------------------
     * Cron: cancela as vencidas e normaliza as legadas
     * ------------------------------------------------------------------ */

    /**
     * Varre as assinaturas dos planos gratuitos:
     *  - 'active' -> converte para 'pending-cancel' (impede a renovação);
     *  - vencida  -> cancela de fato (status 'cancelled').
     *
     * @return array
     */
    public function run_expiration_sweep() {
        $summary = array(
            'converted' => 0,
            'cancelled' => 0,
        );

        foreach ( $this->free_package_ids() as $package_id ) {
            $product_id = absint( get_post_meta( $package_id, Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::PACKAGE_PRODUCT_META, true ) );
            if ( ! $product_id || ! function_exists( 'wcs_get_subscriptions' ) ) {
                continue;
            }

            $subscriptions = wcs_get_subscriptions(
                array(
                    'product_id'             => $product_id,
                    'subscription_status'    => array( 'active', 'pending-cancel' ),
                    'subscriptions_per_page' => 200,
                )
            );

            foreach ( $subscriptions as $subscription ) {
                $expiry = $this->compute_expiry( $subscription, $package_id );

                if ( $subscription->has_status( 'active' ) ) {
                    // Já venceu? Cancela direto. Senão, desliga a renovação.
                    if ( $expiry && $expiry <= time() ) {
                        if ( $this->cancel_expired( $subscription, $expiry ) ) {
                            $summary['cancelled']++;
                        }
                        continue;
                    }
                    if ( $this->convert_to_pending_cancel( $subscription ) ) {
                        $summary['converted']++;
                    }
                    continue;
                }

                if ( ! $subscription->has_status( 'pending-cancel' ) ) {
                    continue;
                }

                // Assinaturas antigas podem não ter a data registrada: grava
                // agora para saber quando o período termina.
                if ( ! $expiry ) {
                    continue;
                }
                $this->store_expiry( $subscription, $expiry );

                if ( $expiry > time() ) {
                    continue;
                }

                if ( $this->cancel_expired( $subscription, $expiry ) ) {
                    $summary['cancelled']++;
                }
            }
        }

        return $summary;
    }

    /**
     * Cancela de fato a assinatura gratuita vencida.
     *
     * @param WC_Subscription $subscription
     * @param int              $expiry
     * @return bool
     */
    private function cancel_expired( $subscription, $expiry ) {
        if ( ! $subscription->can_be_updated_to( 'cancelled' ) ) {
            return false;
        }

        $note = __( 'Plano gratuito expirado: o período de acesso terminou.', 'imovel-parceiro-core' );
        try {
            $subscription->update_status( 'cancelled', $note );
        } catch ( Exception $e ) {
            return false;
        }

        $this->store_expiry( $subscription, $expiry );

        return true;
    }

    /* ---------------------------------------------------------------------
     * Reativação bloqueada
     * ------------------------------------------------------------------ */

    /**
     * Impede que uma assinatura gratuita em 'pending-cancel' seja reativada
     * (a reativação recriaria a data de próxima cobrança e reiniciaria o
     * ciclo de 3 meses).
     *
     * @param bool            $can_be_updated
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function block_reactivation( $can_be_updated, $subscription ) {
        if ( ! $can_be_updated || ! $subscription ) {
            return $can_be_updated;
        }
        if ( ! $subscription->has_status( 'pending-cancel' ) ) {
            return $can_be_updated;
        }
        if ( ! $this->is_free_subscription( $subscription ) ) {
            return $can_be_updated;
        }
        return false;
    }

    /* ---------------------------------------------------------------------
     * Aviso de expiração (e-mail + notificação interna + auditoria)
     * ------------------------------------------------------------------ */

    /**
     * Reage à mudança de status de uma assinatura gratuita.
     *
     * @param int             $subscription_id
     * @param string          $old_status
     * @param string          $new_status
     * @param WC_Subscription $subscription
     */
    public function on_status_changed( $subscription_id, $old_status, $new_status, $subscription = null ) {
        if ( ! in_array( $new_status, array( 'cancelled', 'expired' ), true ) ) {
            return;
        }
        if ( ! $subscription ) {
            $subscription = $this->get_subscription( $subscription_id );
        }
        if ( ! $subscription || ! $this->is_free_subscription( $subscription ) ) {
            return;
        }
        if ( '1' === (string) $subscription->get_meta( self::NOTIFIED_META ) ) {
            return;
        }

        $user_id    = (int) $subscription->get_customer_id();
        $package_id = $this->package_id_for_subscription( $subscription );
        $expiry     = $this->compute_expiry( $subscription, $package_id );

        $subscription->update_meta_data( self::NOTIFIED_META, '1' );
        $subscription->save();

        // O plano termina de qualquer forma: o acesso é liberado aqui.
        $this->cleanup_membership( $user_id, $package_id );

        // Cancelamento manual antes do vencimento: o WooCommerce já avisa o
        // usuário, então não enviamos o e-mail de expiração.
        if ( $expiry && $expiry > time() ) {
            return;
        }

        $this->send_expiration_email( $user_id, $package_id, $expiry );
        $this->send_expiration_notification( $user_id, $package_id, $expiry );
        $this->audit( $user_id, $package_id, $expiry );
    }

    /**
     * Remove a referência ao plano gratuito do usuário (sem expirar anúncios).
     *
     * @param int $user_id
     * @param int $package_id
     */
    private function cleanup_membership( $user_id, $package_id ) {
        if ( ! $user_id ) {
            return;
        }

        $current = absint( get_user_meta( $user_id, 'package_id', true ) );
        if ( $current && $package_id && $current !== $package_id ) {
            return;
        }

        if ( class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) && Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::active_package_for_user( $user_id ) ) {
            // O usuário ainda tem outro plano ativo: não mexe no cadastro.
            return;
        }

        delete_user_meta( $user_id, 'package_id' );
        delete_user_meta( $user_id, 'houzez_membership_id' );
        update_user_meta( $user_id, 'houzez_subscription_detail_status', 'expired' );
    }

    /**
     * E-mail avisando que o plano gratuito terminou.
     *
     * @param int    $user_id
     * @param int    $package_id
     * @param int    $expiry
     * @return bool
     */
    private function send_expiration_email( $user_id, $package_id, $expiry ) {
        $user = $user_id ? get_userdata( $user_id ) : null;
        if ( ! $user ) {
            return false;
        }

        $package_name = $package_id ? get_the_title( $package_id ) : __( 'Plano gratuito', 'imovel-parceiro-core' );
        $subject      = __( 'Seu plano gratuito expirou', 'imovel-parceiro-core' );
        $date         = $expiry ? date_i18n( get_option( 'date_format' ), $expiry ) : '';
        $suffix       = $date ? ' (' . $date . ')' : '';

        $lines = array(
            sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $user->display_name ),
            '',
            sprintf( __( 'O período de acesso do plano gratuito "%s" chegou ao fim%s.', 'imovel-parceiro-core' ), $package_name, $suffix ),
            '',
            __( 'Como o plano gratuito tem prazo de uso definido, ele não se renova automaticamente e não houve nenhuma cobrança.', 'imovel-parceiro-core' ),
            '',
            __( 'Com o fim do plano, você continua usando o site, mas os recursos do plano ficam pausados:', 'imovel-parceiro-core' ),
            '- ' . __( 'publicar novos imóveis', 'imovel-parceiro-core' ),
            '- ' . __( 'visualizar os contatos dos clientes dos seus imóveis', 'imovel-parceiro-core' ),
            '- ' . __( 'solicitar parcerias com outras imobiliárias', 'imovel-parceiro-core' ),
            '',
            __( 'Os imóveis que você já publicou continuam no ar.', 'imovel-parceiro-core' ),
            '',
            __( 'Se quiser voltar a anunciar, escolha um plano:', 'imovel-parceiro-core' ),
            Imovel_Parceiro_Subscriptions::plans_url(),
            '',
            __( 'Atenciosamente,', 'imovel-parceiro-core' ),
            __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' ),
        );

        $body = implode( "\n", $lines );

        if ( function_exists( 'houzez_send_emails' ) ) {
            return (bool) houzez_send_emails( $user->user_email, $subject, $body );
        }
        return (bool) wp_mail( $user->user_email, $subject, $body );
    }

    /**
     * Notificação interna (sino do dashboard).
     *
     * @param int $user_id
     * @param int $package_id
     * @param int $expiry
     * @return bool
     */
    private function send_expiration_notification( $user_id, $package_id, $expiry ) {
        if ( ! $user_id || ! class_exists( 'IPC_Notifications' ) ) {
            return false;
        }

        $package_name = $package_id ? get_the_title( $package_id ) : __( 'Plano gratuito', 'imovel-parceiro-core' );
        $date         = $expiry ? date_i18n( get_option( 'date_format' ), $expiry ) : '';
        $suffix       = $date ? ' (' . $date . ')' : '';

        return (bool) IPC_Notifications::send(
            array(
                'user_id'  => $user_id,
                'type'     => 'FREE_PLAN_EXPIRED',
                'category' => IPC_Notifications::CATEGORY_SISTEMA,
                'title'    => __( 'Seu plano gratuito expirou', 'imovel-parceiro-core' ),
                'message'  => sprintf(
                    __( 'O período de acesso do plano "%s" chegou ao fim%s. Escolha um plano para voltar a anunciar.', 'imovel-parceiro-core' ),
                    $package_name,
                    $suffix
                ),
                'url'      => Imovel_Parceiro_Subscriptions::plans_url(),
                'priority' => IPC_Notifications::PRIORITY_IMPORTANT,
            )
        );
    }

    /**
     * Registro de auditoria (mesma tabela usada pelas demais rotinas).
     *
     * @param int $user_id
     * @param int $package_id
     * @param int $expiry
     */
    private function audit( $user_id, $package_id, $expiry ) {
        global $wpdb;

        $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        $this->maybe_create_audit_table( $table );

        $meta = array(
            'user_id'      => $user_id,
            'package_id'   => $package_id,
            'expiry'       => $expiry ? $expiry : 0,
            'expiry_local' => $expiry ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry ) : '',
            'send_status'  => 'sent',
        );

        $wpdb->insert(
            $table,
            array(
                'event_type'     => self::AUDIT_EVENT,
                'actor_user_id'  => $user_id,
                'other_user_id'  => 0,
                'property_id'    => 0,
                'partnership_id' => 0,
                'meta'           => wp_json_encode( $meta ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
        );
    }

    private function maybe_create_audit_table( $table ) {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL auto_increment,
            event_type varchar(60) NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL default 0,
            other_user_id bigint(20) unsigned NOT NULL default 0,
            property_id bigint(20) unsigned NOT NULL default 0,
            partnership_id bigint(20) unsigned NOT NULL default 0,
            meta longtext NULL,
            created_at datetime NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ---------------------------------------------------------------------
     * Utilitários
     * ------------------------------------------------------------------ */

    /**
     * Remove os avisos de "renovação" que o WooCommerce/ENR agendam para a
     * assinatura gratuita (ela não se renova, ela expira).
     *
     * @param WC_Subscription $subscription
     */
    private function unschedule_renewal_notices( $subscription ) {
        $id = (int) $subscription->get_id();
        if ( ! $id ) {
            return;
        }

        // WooCommerce Subscriptions (grupo wcs_customer_notifications).
        // O plano gratuito avisa o usuário em PT-BR pelo próprio plugin
        // (aviso de 5 dias + e-mail de expiração), então os avisos nativos
        // do WooCommerce (renovação e expiração) são desligados.
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            $args = array( 'subscription_id' => $id );
            as_unschedule_all_actions(
                'woocommerce_scheduled_subscription_customer_notification_renewal',
                $args,
                'wcs_customer_notifications'
            );
            as_unschedule_all_actions(
                'woocommerce_scheduled_subscription_customer_notification_expiration',
                $args,
                'wcs_customer_notifications'
            );
        }

        // Plugin Enhancer for WooCommerce Subscriptions (args com day_to_remind).
        if ( class_exists( 'ENR_Action_Scheduler' ) && method_exists( 'ENR_Action_Scheduler', 'unschedule_all_actions_by_hook' ) ) {
            $enr_hooks = array(
                'enr_woocommerce_scheduled_subscription_manual_renewal_reminder',
                'enr_woocommerce_scheduled_subscription_auto_renewal_reminder',
                'enr_woocommerce_scheduled_subscription_expiration_reminder',
            );
            foreach ( $enr_hooks as $hook ) {
                ENR_Action_Scheduler::unschedule_all_actions_by_hook(
                    $hook,
                    array( 'subscription_id' => $id )
                );
            }
        }
    }

    /**
     * IDs dos pacotes marcados como plano gratuito.
     *
     * @return int[]
     */
    public function free_package_ids() {
        $ids = get_posts(
            array(
                'post_type'      => 'houzez_packages',
                'post_status'    => 'any',
                'posts_per_page' => 100,
                'fields'         => 'ids',
            )
        );

        $free = array();
        foreach ( (array) $ids as $id ) {
            if ( Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $id ) ) {
                $free[] = absint( $id );
            }
        }
        return $free;
    }

    /**
     * Pacote (houzez_packages) vinculado à assinatura.
     *
     * @param WC_Subscription $subscription
     * @return int
     */
    public function package_id_for_subscription( $subscription ) {
        if ( ! $subscription || ! is_callable( array( $subscription, 'get_items' ) ) ) {
            return 0;
        }
        foreach ( $subscription->get_items() as $item ) {
            $package_id = absint( get_post_meta( $item->get_product_id(), Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::PRODUCT_PACKAGE_META, true ) );
            if ( $package_id ) {
                return $package_id;
            }
        }
        return 0;
    }

    /**
     * A assinatura pertence a um plano gratuito?
     *
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function is_free_subscription( $subscription ) {
        if ( ! $subscription ) {
            return false;
        }
        $package_id = $this->package_id_for_subscription( $subscription );
        if ( ! $package_id ) {
            return false;
        }
        return (bool) Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::is_free_package( $package_id );
    }

    /**
     * Carrega a assinatura pelo ID (compatível com HPOS).
     *
     * @param int $subscription_id
     * @return WC_Subscription|false
     */
    public function get_subscription( $subscription_id ) {
        $subscription_id = absint( $subscription_id );
        if ( ! $subscription_id ) {
            return false;
        }
        if ( function_exists( 'wcs_get_subscription' ) ) {
            $subscription = wcs_get_subscription( $subscription_id );
            if ( $subscription ) {
                return $subscription;
            }
        }
        if ( function_exists( 'WC' ) && WC()->order_factory ) {
            $subscription = WC()->order_factory->get_order( $subscription_id );
            if ( $subscription && is_callable( array( $subscription, 'get_items' ) ) ) {
                return $subscription;
            }
        }
        return false;
    }

    /**
     * Data de término da assinatura gratuita (timestamp).
     *
     * Ordem de confiança:
     *  1. meta '_imovel_parceiro_free_plan_expires_at' — data de término
     *     gravada pelo próprio plugin (é a validade real do plano);
     *  2. 'end'      — data que o próprio WCS gravou ao entrar em
     *                   'pending-cancel' (já é início + validade). Ignorada
     *                   quando a assinatura está cancelada/expirada, porque
     *                   nesses status o WCS sobrescreve esse campo com o
     *                   momento do cancelamento (o que faria o plano parecer
     *                   vencido antes da hora);
     *  3. 'next_payment' — data da próxima cobrança (produto gratuito roda
     *                   com intervalo de 3 meses = início + validade);
     *  4. 'start' + validade do pacote — recálculo quando não há datas.
     *
     * @param WC_Subscription $subscription
     * @param int              $package_id
     * @return int
     */
    public function compute_expiry( $subscription, $package_id = 0 ) {
        if ( ! $subscription ) {
            return 0;
        }

        $package_id = $package_id ? absint( $package_id ) : $this->package_id_for_subscription( $subscription );

        // 1) Data de término registrada pelo plugin.
        $stored = absint( $subscription->get_meta( self::EXPIRES_META ) );
        if ( $stored ) {
            return $stored;
        }

        $status = $subscription->get_status();

        // 2) Data de término que o WooCommerce gravou (pending-cancel).
        if ( ! in_array( $status, array( 'cancelled', 'expired', 'trash' ), true ) ) {
            $end = $this->date_timestamp( $subscription, 'end' );
            if ( $end ) {
                return $end;
            }
        }

        // 3) Próxima cobrança (ciclo de 3 meses do plano gratuito).
        $next = $this->date_timestamp( $subscription, 'next_payment' );
        if ( $next && $next > time() ) {
            return $next;
        }

        // 4) Início da assinatura + validade definida no pacote.
        $start = $this->date_timestamp( $subscription, 'start' );
        if ( $start ) {
            return $this->add_validity( $start, $package_id );
        }

        return 0;
    }

    /**
     * A assinatura gratuita já venceu?
     *
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function is_expired( $subscription ) {
        $expiry = $this->compute_expiry( $subscription );
        if ( ! $expiry ) {
            return false;
        }
        return $expiry <= time();
    }

    /**
     * Versão estática de is_expired() (usada pelo portão central).
     *
     * @param WC_Subscription $subscription
     * @return bool
     */
    public static function is_subscription_expired( $subscription ) {
        return self::instance()->is_expired( $subscription );
    }

    /**
     * Data de término do plano gratuito de um usuário.
     *
     * @param int    $user_id
     * @param int    $package_id
     * @param string $activation
     * @return int
     */
    public static function expiry_for_user( $user_id, $package_id, $activation = '' ) {
        $instance    = self::instance();
        $package_id  = absint( $package_id );

        if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
            foreach ( wcs_get_users_subscriptions( absint( $user_id ) ) as $subscription ) {
                if ( ! $subscription->has_status( array( 'active', 'pending-cancel' ) ) ) {
                    continue;
                }
                if ( $instance->package_id_for_subscription( $subscription ) !== $package_id ) {
                    continue;
                }
                $expiry = $instance->compute_expiry( $subscription, $package_id );
                if ( $expiry ) {
                    return $expiry;
                }
            }
        }

        $base = $activation ? strtotime( (string) $activation ) : false;
        if ( false === $base ) {
            $base = time();
        }
        return $instance->add_validity( $base, $package_id );
    }

    /**
     * Soma a validade do pacote gratuito a uma data.
     *
     * @param int $timestamp
     * @param int $package_id
     * @return int
     */
    private function add_validity( $timestamp, $package_id ) {
        $validity = Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::free_plan_validity( $package_id );
        $value    = max( 1, absint( $validity['value'] ) );
        $unit     = $validity['unit'];

        switch ( $unit ) {
            case 'month':
                if ( function_exists( 'wcs_add_months' ) ) {
                    return (int) wcs_add_months( $timestamp, $value );
                }
                return $timestamp + ( $value * 30 * DAY_IN_SECONDS );
            case 'year':
                if ( function_exists( 'wcs_add_months' ) ) {
                    return (int) wcs_add_months( $timestamp, $value * 12 );
                }
                return $timestamp + ( $value * 365 * DAY_IN_SECONDS );
            case 'week':
                return $timestamp + ( $value * WEEK_IN_SECONDS );
            default:
                return $timestamp + ( $value * DAY_IN_SECONDS );
        }
    }

    /**
     * Lê a data da assinatura como timestamp UTC.
     *
     * @param WC_Subscription $subscription
     * @param string          $type
     * @return int
     */
    private function date_timestamp( $subscription, $type ) {
        if ( ! $subscription ) {
            return 0;
        }
        if ( is_callable( array( $subscription, 'get_time' ) ) ) {
            $timestamp = absint( $subscription->get_time( $type ) );
            if ( $timestamp ) {
                return $timestamp;
            }
        }
        if ( ! is_callable( array( $subscription, 'get_date' ) ) ) {
            return 0;
        }
        $date = $subscription->get_date( $type );
        if ( ! $date ) {
            return 0;
        }
        $timestamp = strtotime( $date . ' UTC' );
        return $timestamp ? (int) $timestamp : 0;
    }

    /**
     * Guarda a data de término na assinatura (auditoria / consultas futuras).
     *
     * @param WC_Subscription $subscription
     * @param int              $expiry
     */
    private function store_expiry( $subscription, $expiry ) {
        if ( ! $subscription || ! $expiry ) {
            return;
        }
        if ( (int) $subscription->get_meta( self::EXPIRES_META ) === (int) $expiry ) {
            return;
        }
        $subscription->update_meta_data( self::EXPIRES_META, (int) $expiry );
        $subscription->save();
    }
}

Imovel_Parceiro_Free_Plan_Expiration::instance();
