<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imovel_Parceiro_Subscriptions
 *
 * Reuses the existing Houzez membership (packages CPT + per-user package meta)
 * instead of creating a parallel plan system. It adds:
 *   - a single source of truth for "active subscription" (temporal + status aware);
 *   - the "one active plan per broker" consistency rule (supported by Houzez's
 *     single package_id overwrite) plus the free-plan-once guard;
 *   - automatic billing_type derivation and persistence;
 *   - a WP-Cron daily warning (5 days before expiry) with dedupe + audit and
 *     humanized email.
 */
class Imovel_Parceiro_Subscriptions {
    const CRON_HOOK = 'imovel_parceiro_subscription_expiration_daily';
    const META_WARNINGS = 'imovel_parceiro_expiration_warnings';
    const META_BILLING = 'imovel_parceiro_billing_type';

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_schedule' ) );
        add_action( self::CRON_HOOK, array( $this, 'process_expiration_warnings' ) );
        add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 20, 1 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'on_payment_complete' ), 20, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_payment_complete' ), 20, 1 );
        add_action( 'houzez_after_user_membership_cancelled', array( $this, 'on_membership_cancelled' ), 10, 2 );
    }

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }

    public function maybe_schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /* ---------------------------------------------------------------------
     * Active package (single source of truth)
     * ------------------------------------------------------------------ */

    /**
     * Return the active package post ID for a user, or 0 if none. null means the
     * user is exempt (admin). Temporal validity and status are checked.
     */
    public static function active_package( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return 0;
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return 0;
        }
        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return null;
        }

        // Subscription products managed by this plugin are valid only while the
        // corresponding WooCommerce Subscriptions record is active.
        if ( class_exists( 'Imovel_Parceiro_Houzez_WooCommerce_Subscriptions' ) ) {
            $subscription_package = Imovel_Parceiro_Houzez_WooCommerce_Subscriptions::active_package_for_user( $user_id );
            if ( $subscription_package ) {
                return $subscription_package;
            }
        }

        $package_id    = absint( get_user_meta( $user_id, 'package_id', true ) );
        $membership_id = absint( get_user_meta( $user_id, 'houzez_membership_id', true ) );
        $pid = $package_id ? $package_id : $membership_id;
        if ( ! $pid ) {
            return 0;
        }

        // Never fall back to Houzez's date-only validity for packages that are
        // governed by WooCommerce Subscriptions.
        if ( get_post_meta( $pid, '_imovel_parceiro_subscription_product_id', true ) ) {
            return 0;
        }

        // A cancelled/expired subscription detail status means not active.
        $detail = get_user_meta( $user_id, 'houzez_subscription_detail_status', true );
        if ( 'expired' === $detail ) {
            return 0;
        }

        if ( (int) get_post_meta( $pid, 'fave_never_expire', true ) ) {
            return $pid;
        }

        $activation = get_user_meta( $user_id, 'package_activation', true );
        if ( '' === $activation ) {
            return $pid;
        }

        $expiry = self::package_expiry_ts( $user_id, $pid, $activation );
        return ( false !== $expiry && $expiry > time() ) ? $pid : 0;
    }

    public static function has_active_subscription( $user_id ) {
        $pid = self::active_package( $user_id );
        return null === $pid || $pid > 0;
    }

    public static function package_expiry_ts( $user_id, $package_id, $activation ) {
        $package_id = absint( $package_id );
        $unit = absint( get_post_meta( $package_id, 'fave_billing_unit', true ) );
        $period = strtolower( (string) get_post_meta( $package_id, 'fave_billing_time_unit', true ) );
        if ( ! $unit || ! $period ) {
            return false;
        }
        $map = array(
            'day'   => DAY_IN_SECONDS,
            'week'  => WEEK_IN_SECONDS,
            'month' => 30 * DAY_IN_SECONDS,
            'year'  => 365 * DAY_IN_SECONDS,
        );
        $secs = isset( $map[ $period ] ) ? $map[ $period ] : 0;
        if ( ! $secs ) {
            return false;
        }
        $base = strtotime( (string) $activation );
        if ( false === $base ) {
            return false;
        }
        return $base + ( $unit * $secs );
    }

    public static function free_package_used( $user_id ) {
        return 'yes' === get_user_meta( absint( $user_id ), 'user_had_free_package', true );
    }

    public static function billing_type_for_user( $user_id ) {
        $pid = self::active_package( $user_id );
        if ( ! $pid ) {
            return '';
        }
        $period = strtolower( (string) get_post_meta( $pid, 'fave_billing_time_unit', true ) );
        $map = array( 'day' => 'daily', 'week' => 'weekly', 'month' => 'monthly', 'year' => 'yearly' );
        return isset( $map[ $period ] ) ? $map[ $period ] : '';
    }

    public static function persist_billing_type( $user_id ) {
        $user_id = absint( $user_id );
        $type = self::billing_type_for_user( $user_id );
        if ( '' !== $type ) {
            update_user_meta( $user_id, self::META_BILLING, $type );
        }
        return $type;
    }

    public static function plans_url() {
        if ( function_exists( 'houzez_get_template_link' ) ) {
            $link = houzez_get_template_link( 'template/template-packages.php' );
            if ( ! empty( $link ) && untrailingslashit( $link ) !== untrailingslashit( home_url( '/' ) ) ) {
                return $link;
            }
        }

        foreach ( array( 'template/template-packages.php', 'template-packages.php' ) as $template ) {
            $pages = get_posts(
                array(
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'posts_per_page' => 1,
                    'meta_key' => '_wp_page_template',
                    'meta_value' => $template,
                    'fields' => 'ids',
                )
            );
            if ( ! empty( $pages ) ) {
                return get_permalink( $pages[0] );
            }
        }
        if ( function_exists( 'houzez_get_template_link_2' ) ) {
            return houzez_get_template_link_2( 'template/user_dashboard.php' );
        }
        return home_url( '/dashboard/' );
    }

    /* ---------------------------------------------------------------------
     * Expiration warning (5 days)
     * ------------------------------------------------------------------ */

    public function process_expiration_warnings() {
        if ( ! function_exists( 'get_users' ) ) {
            return;
        }

        // Bounded: only users that hold a package.
        $members = get_users(
            array(
                'meta_key' => 'package_id',
                'meta_compare' => 'EXISTS',
                'number' => 500,
                'fields' => array( 'ID' ),
            )
        );
        if ( empty( $members ) ) {
            return;
        }

        $now = time();
        foreach ( $members as $member ) {
            $user_id = absint( $member->ID );
            $pid = self::active_package( $user_id );
            if ( ! $pid ) {
                continue;
            }
            $activation = get_user_meta( $user_id, 'package_activation', true );
            if ( '' === $activation ) {
                continue;
            }
            $expiry = self::package_expiry_ts( $user_id, $pid, $activation );
            if ( false === $expiry ) {
                continue;
            }

            $days = ( $expiry - $now ) / DAY_IN_SECONDS;
            // Window: ~5 days (4 <= d < 5.6) to tolerate late cron runs.
            if ( $days < 4 || $days >= 5.6 ) {
                continue;
            }

            $key = $pid . ':' . $expiry;
            $warnings = get_user_meta( $user_id, self::META_WARNINGS, true );
            $warnings = is_array( $warnings ) ? $warnings : array();
            if ( isset( $warnings[ $key ] ) ) {
                continue;
            }

            $sent = $this->send_expiration_warning( $user_id, $pid, $expiry );
            if ( $sent ) {
                $warnings[ $key ] = time();
                update_user_meta( $user_id, self::META_WARNINGS, $warnings );
                $this->audit( $user_id, $pid, $expiry, 'sent' );
            } else {
                $this->audit( $user_id, $pid, $expiry, 'error' );
                // Not marked as sent -> retried on next cron.
            }
        }
    }

    private function send_expiration_warning( $user_id, $package_id, $expiry_ts ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        $package_name = get_the_title( $package_id );
        $billing = self::billing_type_for_user( $user_id );
        $recurring = (int) get_user_meta( $user_id, 'houzez_is_recurring_membership', true );
        $payment_method = get_user_meta( $user_id, 'houzez_payment_method', true );

        $subject = __( 'Sua assinatura está próxima do vencimento', 'imovel-parceiro-core' );

        $lines = array(
            sprintf( __( 'Olá, %s!', 'imovel-parceiro-core' ), $user->display_name ),
            '',
            sprintf( __( 'Sua assinatura do plano "%s" está próxima do vencimento.', 'imovel-parceiro-core' ), $package_name ),
            '',
            '📅 ' . __( 'Data de vencimento: ', 'imovel-parceiro-core' ) . date_i18n( get_option( 'date_format' ), $expiry_ts ),
            '⏳ ' . __( 'Faltam aproximadamente 5 dias para sua assinatura expirar.', 'imovel-parceiro-core' ),
            '',
        );

        if ( $recurring && 'monthly' === $billing ) {
            $lines[] = __( 'Sua assinatura possui renovação automática e será renovada no vencimento. Nenhuma ação é necessária.', 'imovel-parceiro-core' );
        } else {
            $lines[] = __( 'Para continuar utilizando os recursos disponíveis no seu plano, acesse sua conta e confira as opções de renovação.', 'imovel-parceiro-core' );
            $lines[] = '';
            $lines[] = strtoupper( __( 'Renovar assinatura', 'imovel-parceiro-core' ) ) . ': ' . $this->dashboard_url();
        }

        $lines[] = '';
        $lines[] = __( 'Se você já realizou a renovação, pode desconsiderar esta mensagem.', 'imovel-parceiro-core' );
        $lines[] = '';
        $lines[] = __( 'Atenciosamente,', 'imovel-parceiro-core' );
        $lines[] = __( 'Equipe Imóvel Parceiro', 'imovel-parceiro-core' );

        $body = implode( "\n", $lines );

        if ( function_exists( 'houzez_send_emails' ) ) {
            return (bool) houzez_send_emails( $user->user_email, $subject, $body );
        }
        return (bool) wp_mail( $user->user_email, $subject, $body );
    }

    private function dashboard_url() {
        if ( function_exists( 'houzez_get_template_link_2' ) ) {
            return houzez_get_template_link_2( 'template/user_dashboard.php' );
        }
        return home_url( '/dashboard/' );
    }

    private function audit( $user_id, $package_id, $expiry_ts, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(60) NOT NULL DEFAULT '',
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            other_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            property_id bigint(20) unsigned NOT NULL DEFAULT 0,
            partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY property_id (property_id),
            KEY partnership_id (partnership_id)
        ) {$charset};" );

        $meta = array(
            'user_id' => absint( $user_id ),
            'package_id' => absint( $package_id ),
            'expiry' => $expiry_ts,
            'expiry_local' => date_i18n( get_option( 'date_format' ), $expiry_ts ),
            'send_status' => sanitize_key( $status ),
            'billing_type' => self::billing_type_for_user( $user_id ),
            'payment_method' => get_user_meta( $user_id, 'houzez_payment_method', true ),
        );

        $wpdb->insert(
            $table,
            array(
                'event_type' => 'subscription_expiration_5_days',
                'actor_user_id' => absint( $user_id ),
                'meta' => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );
    }

    /* ---------------------------------------------------------------------
     * WooCommerce payment completion -> billing_type + one-active-plan guard
     * ------------------------------------------------------------------ */

    public function on_payment_complete( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $user_id = absint( $order->get_user_id() );
        if ( ! $user_id ) {
            return;
        }

        $package_id = $this->order_package_id( $order );
        if ( ! $package_id ) {
            return;
        }

        // Set/reconcile billing_type from the purchased package.
        self::persist_billing_type( $user_id );

        // Audit the activation.
        $this->audit_activation( $user_id, $package_id, $order_id );

        // One-active-plan rule: Houzez keeps a single package_id (overwrites), but
        // we double-check there is no divergent active plan before allowing a new
        // one to persist. If the user is trying to activate a plan while another is
        // active, Houzez's own update_membership_package will swap it; we only flag.
        $current = absint( get_user_meta( $user_id, 'package_id', true ) );
        if ( $current && $current !== $package_id && self::active_package( $user_id ) ) {
            $order->add_order_note( __( 'Imóvel Parceiro: o usuário já possui um plano ativo; o novo plano substituirá o atual conforme a regra de plano único.', 'imovel-parceiro-core' ) );
        }
    }

    public function on_membership_cancelled( $user_id, $membership_id ) {
        delete_user_meta( absint( $user_id ), self::META_BILLING );
    }

    private function order_package_id( $order ) {
        $items = $order->get_items();
        foreach ( $items as $item ) {
            $product_id = absint( $item->get_product_id() );
            if ( ! $product_id ) {
                continue;
            }
            $package_id = absint( get_post_meta( $product_id, '_houzez_package_id', true ) );
            if ( ! $package_id ) {
                $package_id = absint( get_post_meta( $product_id, 'houzez_package_id', true ) );
            }
            if ( get_post_meta( $product_id, '_is_houzez_payment_mode', true ) === 'package' ) {
                return $package_id;
            }
        }
        return 0;
    }

    private function audit_activation( $user_id, $package_id, $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'imovel_parceiro_audit_logs';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(60) NOT NULL DEFAULT '',
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            other_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            property_id bigint(20) unsigned NOT NULL DEFAULT 0,
            partnership_id bigint(20) unsigned NOT NULL DEFAULT 0,
            meta longtext NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY actor_user_id (actor_user_id),
            KEY property_id (property_id),
            KEY partnership_id (partnership_id)
        ) {$charset};" );

        $meta = array(
            'package_id' => absint( $package_id ),
            'order_id' => absint( $order_id ),
            'billing_type' => self::billing_type_for_user( $user_id ),
            'payment_method' => get_user_meta( $user_id, 'houzez_payment_method', true ),
        );

        $wpdb->insert(
            $table,
            array(
                'event_type' => 'subscription_activated',
                'actor_user_id' => absint( $user_id ),
                'meta' => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );
    }
}

Imovel_Parceiro_Subscriptions::instance();
