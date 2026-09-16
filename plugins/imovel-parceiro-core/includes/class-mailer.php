<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deferred email delivery.
 *
 * SMTP sending blocks the request for seconds per message. This helper runs
 * the actual sending after the HTTP response whenever possible:
 *  1. fastcgi_finish_request() — flushes the response, keeps running;
 *  2. Action Scheduler async job (WooCommerce) — background request;
 *  3. Inline fallback — current behavior when neither is available.
 */
class Imovel_Parceiro_Mailer {

    const AS_HOOK = 'imovel_parceiro_async_send_emails';
    const AS_GROUP = 'imovel-parceiro';

    /**
     * Wire the async runner. Called on file load.
     */
    public static function init() {
        add_action( self::AS_HOOK, array( __CLASS__, 'run_async_jobs' ), 10, 1 );
    }

    /**
     * Defer a list of email jobs past the HTTP response.
     *
     * Each job is array( 'to' => ..., 'subject' => ..., 'body' => ... ).
     * The $sender receives one job array and must return bool.
     *
     * @param array    $jobs   Email jobs.
     * @param callable $sender Sender callback.
     */
    public static function defer( array $jobs, $sender ) {
        $clean = array();
        foreach ( $jobs as $job ) {
            if ( ! is_array( $job ) || empty( $job['to'] ) ) {
                continue;
            }
            $clean[] = array(
                'to' => (string) $job['to'],
                'subject' => isset( $job['subject'] ) ? (string) $job['subject'] : '',
                'body' => isset( $job['body'] ) ? (string) $job['body'] : '',
            );
        }
        $jobs = $clean;

        if ( empty( $jobs ) || ! is_callable( $sender ) ) {
            return;
        }

        if ( function_exists( 'fastcgi_finish_request' ) ) {
            add_action(
                'shutdown',
                function () use ( $jobs, $sender ) {
                    fastcgi_finish_request();
                    if ( function_exists( 'ignore_user_abort' ) ) {
                        @ignore_user_abort( true );
                    }
                    self::run_jobs( $jobs, $sender );
                },
                999
            );
            return;
        }

        if ( function_exists( 'as_enqueue_async_action' ) ) {
            $sender_id = self::register_sender( $sender );
            if ( $sender_id ) {
                as_enqueue_async_action( self::AS_HOOK, array( 'sender' => $sender_id, 'jobs' => $jobs ), self::AS_GROUP );
                return;
            }
        }

        self::run_jobs( $jobs, $sender );
    }

    /**
     * Default sender: Houzez template email with wp_mail fallback.
     *
     * @param array $job Job with to/subject/body.
     * @return bool
     */
    public static function send_via_houzez( $job ) {
        $to = isset( $job['to'] ) ? sanitize_email( $job['to'] ) : '';
        if ( '' === $to ) {
            return false;
        }
        $subject = isset( $job['subject'] ) ? (string) $job['subject'] : '';
        $body = isset( $job['body'] ) ? (string) $job['body'] : '';

        if ( function_exists( 'houzez_send_emails' ) ) {
            return (bool) houzez_send_emails( $to, $subject, $body );
        }

        return (bool) wp_mail( $to, $subject, $body );
    }

    /**
     * Run jobs inline, never throwing.
     *
     * @param array    $jobs   Email jobs.
     * @param callable $sender Sender callback.
     */
    public static function run_jobs( array $jobs, $sender ) {
        foreach ( $jobs as $job ) {
            try {
                call_user_func( $sender, $job );
            } catch ( \Exception $e ) {
                error_log( 'Imovel Parceiro mailer: ' . $e->getMessage() );
            } catch ( \Throwable $e ) {
                error_log( 'Imovel Parceiro mailer: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Keep a sender callable reachable for the async runner.
     *
     * @param callable $sender Sender callback.
     * @return string|false Sender id or false.
     */
    private static function register_sender( $sender ) {
        static $senders = array();

        if ( is_string( $sender ) && function_exists( $sender ) ) {
            return 'fn:' . $sender;
        }

        if ( is_array( $sender ) && count( $sender ) === 2 ) {
            list( $obj_or_class, $method ) = array_values( $sender );
            if ( is_string( $obj_or_class ) && method_exists( $obj_or_class, $method ) ) {
                return 'static:' . $obj_or_class . '::' . $method;
            }
        }

        return false;
    }

    /**
     * Action Scheduler runner.
     *
     * @param array $args Hook args (sender id + jobs).
     */
    public static function run_async_jobs( $args ) {
        $sender_id = isset( $args['sender'] ) ? (string) $args['sender'] : '';
        $jobs = isset( $args['jobs'] ) && is_array( $args['jobs'] ) ? $args['jobs'] : array();

        $sender = null;
        if ( 0 === strpos( $sender_id, 'fn:' ) && function_exists( substr( $sender_id, 3 ) ) ) {
            $sender = substr( $sender_id, 3 );
        } elseif ( 0 === strpos( $sender_id, 'static:' ) ) {
            $parts = explode( '::', substr( $sender_id, 7 ), 2 );
            if ( 2 === count( $parts ) && method_exists( $parts[0], $parts[1] ) ) {
                $sender = array( $parts[0], $parts[1] );
            }
        }

        if ( ! $sender ) {
            return;
        }

        self::run_jobs( $jobs, $sender );
    }
}

Imovel_Parceiro_Mailer::init();
